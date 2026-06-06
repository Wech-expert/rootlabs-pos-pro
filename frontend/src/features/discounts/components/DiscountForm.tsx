import { useState, useCallback } from 'react';
import { Button } from '../../../components/ui';
import type { DiscountInput, DiscountType } from '../types';

interface DiscountFormProps {
  onApply: (discount: DiscountInput) => void;
  onCancel: () => void;
}

function DiscountForm({ onApply, onCancel }: DiscountFormProps) {
  const [type, setType] = useState<DiscountType>('percentage');
  const [value, setValue] = useState('');
  const [reason, setReason] = useState('');
  const [localError, setLocalError] = useState<string | null>(null);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      setLocalError(null);

      const parsed = parseFloat(value);
      if (Number.isNaN(parsed) || parsed <= 0) {
        setLocalError('El valor debe ser un número positivo.');
        return;
      }

      if (type === 'percentage' && parsed > 100) {
        setLocalError('El porcentaje no puede exceder 100%.');
        return;
      }

      if (reason.trim() === '') {
        setLocalError('El motivo es obligatorio.');
        return;
      }

      onApply({
        type,
        value: parsed.toFixed(2),
        reason: reason.trim(),
      });
    },
    [type, value, reason, onApply],
  );

  return (
    <form className="mx-discount-form" onSubmit={handleSubmit}>
      <div className="mx-discount-form__toggle">
        <button
          type="button"
          className={`mx-discount-form__toggle-btn ${type === 'percentage' ? 'mx-discount-form__toggle-btn--active' : ''}`}
          aria-pressed={type === 'percentage'}
          onClick={() => setType('percentage')}
        >
          Porcentaje
        </button>
        <button
          type="button"
          className={`mx-discount-form__toggle-btn ${type === 'fixed' ? 'mx-discount-form__toggle-btn--active' : ''}`}
          aria-pressed={type === 'fixed'}
          onClick={() => setType('fixed')}
        >
          Monto fijo
        </button>
      </div>

      <div className="mx-discount-form__field">
        <label
          className="mx-discount-form__label"
          htmlFor="mx-discount-value"
        >
          Valor
        </label>
        <input
          id="mx-discount-value"
          type="number"
          className="mx-discount-form__input"
          min="0.01"
          step="0.01"
          inputMode="decimal"
          value={value}
          onChange={(e) => setValue((e.target as HTMLInputElement).value)}
          required
          placeholder={type === 'percentage' ? '10' : '50.00'}
          aria-label="Valor del descuento"
        />
      </div>

      <div className="mx-discount-form__field">
        <label
          className="mx-discount-form__label"
          htmlFor="mx-discount-reason"
        >
          Motivo
        </label>
        <textarea
          id="mx-discount-reason"
          className="mx-discount-form__textarea"
          maxLength={255}
          value={reason}
          onChange={(e) => setReason((e.target as HTMLTextAreaElement).value)}
          required
          placeholder="Ej. Promoción mostrador"
          rows={3}
          aria-label="Motivo del descuento"
        />
      </div>

      {localError && (
        <p className="mx-discount-form__error">{localError}</p>
      )}

      <div className="mx-discount-form__actions">
        <Button type="button" variant="ghost" size="md" onClick={onCancel}>
          Cancelar
        </Button>
        <Button type="submit" variant="primary" size="md">
          Guardar descuento
        </Button>
      </div>
    </form>
  );
}

export default DiscountForm;
