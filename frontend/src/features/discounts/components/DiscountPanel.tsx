import { useState } from 'react';
import { Button } from '../../../components/ui';
import DiscountForm from './DiscountForm';
import AppliedDiscountSummary from './AppliedDiscountSummary';
import type { DiscountInput, ValidatedDiscount } from '../types';

interface DiscountPanelProps {
  discountInput: DiscountInput | null;
  validatedDiscount: ValidatedDiscount | null;
  canApplyDiscount: boolean;
  onApply: (discount: DiscountInput) => void;
  onClear: () => void;
}

function DiscountPanel({
  discountInput,
  validatedDiscount,
  canApplyDiscount,
  onApply,
  onClear,
}: DiscountPanelProps) {
  const [showForm, setShowForm] = useState(false);

  if (!canApplyDiscount) {
    return null;
  }

  return (
    <div className="mx-discount-panel">
      <span className="mx-discount-panel__label">Descuento</span>

      {validatedDiscount ? (
        <>
          <AppliedDiscountSummary discount={validatedDiscount} />
          <div className="mx-discount-panel__actions">
            <Button
              variant="ghost"
              size="sm"
              onClick={onClear}
            >
              Quitar descuento
            </Button>
          </div>
        </>
      ) : discountInput ? (
        <>
          <div className="mx-discount-pending">
            <span className="mx-discount-pending__label">
              Descuento pendiente de validar
            </span>
            <div className="mx-discount-pending__details">
              <span className="mx-discount-pending__type">
                {discountInput.type === 'percentage'
                  ? `${parseFloat(discountInput.value)}%`
                  : `$${parseFloat(discountInput.value).toFixed(2)} fijo`}
              </span>
              <span className="mx-discount-pending__reason">
                {discountInput.reason}
              </span>
            </div>
          </div>
          <div className="mx-discount-panel__actions">
            <Button
              variant="ghost"
              size="sm"
              onClick={onClear}
            >
              Quitar descuento
            </Button>
          </div>
        </>
      ) : showForm ? (
        <DiscountForm
          onApply={(d) => {
            onApply(d);
            setShowForm(false);
          }}
          onCancel={() => setShowForm(false)}
        />
      ) : (
        <div className="mx-discount-panel__empty">
          <span className="mx-discount-panel__placeholder">
            Sin descuento
          </span>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setShowForm(true)}
          >
            Aplicar descuento
          </Button>
        </div>
      )}
    </div>
  );
}

export default DiscountPanel;
