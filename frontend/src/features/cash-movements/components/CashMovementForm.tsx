import { useState, useCallback } from 'react';
import { createCashMovement } from '../services/cashMovementApi';
import { Button } from '../../../components/ui';
import type { CreateCashMovementResponse } from '../types';

function generateClientRequestId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `cash-movement-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

interface CashMovementFormProps {
  currentCash: number;
  onCreated: (response: CreateCashMovementResponse) => void;
}

function CashMovementForm({ currentCash, onCreated }: CashMovementFormProps) {
  const [type, setType] = useState<'cash_in' | 'cash_out'>('cash_in');
  const [amount, setAmount] = useState('');
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const parsedAmount = parseFloat(amount);
  const hasAmount = amount.trim() !== '' && !Number.isNaN(parsedAmount);
  const insufficientCash =
    type === 'cash_out' && hasAmount && parsedAmount > currentCash;
  const canSubmit =
    !submitting && !insufficientCash && reason.trim().length >= 5;

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setError(null);

      const parsed = parseFloat(amount);
      if (Number.isNaN(parsed) || parsed <= 0) {
        setError('El monto debe ser mayor a cero.');
        return;
      }

      if (reason.trim().length < 5) {
        setError('El motivo es obligatorio (mínimo 5 caracteres).');
        return;
      }

      if (type === 'cash_out' && parsed > currentCash) {
        setError('La salida no puede superar el saldo actual de caja.');
        return;
      }

      setSubmitting(true);
      try {
        const response = await createCashMovement({
          movement_type: type,
          amount: parsed.toFixed(2),
          reason: reason.trim(),
          client_request_id: generateClientRequestId(),
        });
        setAmount('');
        setReason('');
        onCreated(response);
      } catch (err) {
        setError(
          err instanceof Error ? err.message : 'No se pudo crear el movimiento',
        );
      } finally {
        setSubmitting(false);
      }
    },
    [type, amount, reason, currentCash, onCreated],
  );

  return (
    <form className="mx-cash-movement-form" onSubmit={handleSubmit}>
      <div className="mx-cash-movement-form__toggle">
        <Button
          type="button"
          variant={type === 'cash_in' ? 'primary' : 'secondary'}
          size="sm"
          onClick={() => setType('cash_in')}
          disabled={submitting}
          className="mx-cash-movement-form__type-btn"
        >
          Entrada
        </Button>
        <Button
          type="button"
          variant={type === 'cash_out' ? 'primary' : 'secondary'}
          size="sm"
          onClick={() => setType('cash_out')}
          disabled={submitting}
          className="mx-cash-movement-form__type-btn"
        >
          Salida
        </Button>
      </div>

      {type === 'cash_out' && (
        <p className="mx-cash-movement-form__available">
          Saldo disponible: ${currentCash.toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })}
        </p>
      )}

      <div className="mx-cash-movement-form__field">
        <label
          className="mx-cash-movement-form__label"
          htmlFor="mx-cash-movement-amount"
        >
          Monto
        </label>
        <input
          id="mx-cash-movement-amount"
          type="number"
          className="mx-cash-movement-form__input"
          min="0.01"
          step="0.01"
          inputMode="decimal"
          value={amount}
          onChange={(e) => setAmount((e.target as HTMLInputElement).value)}
          disabled={submitting}
          required
          aria-label="Monto"
        />
      </div>

      <div className="mx-cash-movement-form__field">
        <label
          className="mx-cash-movement-form__label"
          htmlFor="mx-cash-movement-reason"
        >
          Motivo
          <span className="mx-cash-movement-form__label-hint"> obligatorio (mín. 5 caracteres)</span>
        </label>
        <input
          id="mx-cash-movement-reason"
          type="text"
          className="mx-cash-movement-form__input"
          maxLength={255}
          value={reason}
          onChange={(e) => setReason((e.target as HTMLInputElement).value)}
          disabled={submitting}
          placeholder="Ej. Devolución pedido #123"
          aria-label="Motivo"
          required
        />
      </div>

      {insufficientCash && (
        <p className="mx-cash-movement-form__error">
          La salida no puede superar el saldo actual de caja.
        </p>
      )}
      {error && <p className="mx-cash-movement-form__error">{error}</p>}

      <Button
        type="submit"
        variant="primary"
        size="md"
        disabled={!canSubmit}
        loading={submitting}
        className="mx-cash-movement-form__button"
      >
        Agregar movimiento
      </Button>
    </form>
  );
}

export default CashMovementForm;
