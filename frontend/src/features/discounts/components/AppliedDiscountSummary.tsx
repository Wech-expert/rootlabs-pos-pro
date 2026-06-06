import type { ValidatedDiscount } from '../types';
import { MoneyDisplay } from '../../../components/ui';

interface AppliedDiscountSummaryProps {
  discount: ValidatedDiscount;
}

function AppliedDiscountSummary({ discount }: AppliedDiscountSummaryProps) {
  const typeLabel =
    discount.type === 'percentage'
      ? `${parseFloat(discount.value)}%`
      : `$${parseFloat(discount.value).toFixed(2)} fijo`;

  return (
    <div className="mx-discount-applied">
      <span className="mx-discount-applied__label">Descuento aplicado</span>
      <div className="mx-discount-applied__details">
        <span className="mx-discount-applied__type">{typeLabel}</span>
        <span className="mx-discount-applied__reason">{discount.reason}</span>
        <span className="mx-discount-applied__amount">
          <MoneyDisplay amount={parseFloat(discount.amount)} size="sm" />
        </span>
      </div>
    </div>
  );
}

export default AppliedDiscountSummary;
