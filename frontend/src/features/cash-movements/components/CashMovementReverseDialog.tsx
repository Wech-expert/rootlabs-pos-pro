import { useEffect, useState, useCallback } from 'react';
import { Button, Modal, MoneyDisplay } from '../../../components/ui';
import type { CashMovement } from '../types';

interface CashMovementReverseDialogProps {
  movement: CashMovement | null;
  open: boolean;
  submitting: boolean;
  error: string | null;
  onClose: () => void;
  onConfirm: (reason: string) => void;
}

function CashMovementReverseDialog({
  movement,
  open,
  submitting,
  error,
  onClose,
  onConfirm,
}: CashMovementReverseDialogProps) {
  const [reason, setReason] = useState('');

  useEffect(() => {
    if (open) {
      setReason('');
    }
  }, [open]);

  const handleSubmit = useCallback(
    (event: React.FormEvent) => {
      event.preventDefault();
      onConfirm(reason.trim());
    },
    [onConfirm, reason],
  );

  if (!movement) {
    return null;
  }

  return (
    <Modal
      open={open}
      onClose={submitting ? () => undefined : onClose}
      title="Anular movimiento"
      description="Se creará un movimiento inverso por el mismo monto. El movimiento original no se borrará."
    >
      <form className="mx-cash-movement-reverse" onSubmit={handleSubmit}>
        <div className="mx-cash-movement-reverse__summary">
          <span>
            {movement.movement_type === 'cash_in' ? 'Entrada' : 'Salida'} #
            {movement.id}
          </span>
          <MoneyDisplay amount={parseFloat(movement.amount)} size="md" emphasized />
        </div>

        <label
          className="mx-cash-movement-form__label"
          htmlFor="mx-cash-movement-reverse-reason"
        >
          Motivo
        </label>
        <textarea
          id="mx-cash-movement-reverse-reason"
          className="mx-cash-movement-form__input mx-cash-movement-reverse__textarea"
          maxLength={180}
          value={reason}
          onChange={(event) => setReason(event.currentTarget.value)}
          disabled={submitting}
          placeholder="Captura incorrecta"
          aria-label="Motivo de anulación"
        />

        {error && <p className="mx-cash-movement-form__error">{error}</p>}

        <div className="mx-cash-movement-reverse__actions">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={submitting}
            onClick={onClose}
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            variant="primary"
            size="sm"
            loading={submitting}
          >
            Anular
          </Button>
        </div>
      </form>
    </Modal>
  );
}

export default CashMovementReverseDialog;
