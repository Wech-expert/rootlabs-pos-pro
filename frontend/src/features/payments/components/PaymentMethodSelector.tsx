import type { PosPaymentMethod } from '../types';

interface PaymentMethodSelectorProps {
  methods: PosPaymentMethod[];
  selected: string | null;
  onSelect: (slug: string) => void;
  disabled?: boolean;
}

function getMethodMark(method: PosPaymentMethod): string {
  if (method.payment_type === 'cash' || method.affects_cash_register) {
    return '$';
  }

  if (method.payment_type === 'card') {
    return '••';
  }

  return method.name.slice(0, 2).toUpperCase();
}

function PaymentMethodSelector({
  methods,
  selected,
  onSelect,
  disabled = false,
}: PaymentMethodSelectorProps) {
  return (
    <div className="mx-payment-method-selector">
      <label className="mx-payment-method-selector__label">
        Método de pago
      </label>
      <div className="mx-payment-method-selector__grid">
        {methods.map((m) => (
          <button
            key={m.id}
            type="button"
            className={
              'mx-payment-method-selector__option' +
              (selected === m.slug
                ? ' mx-payment-method-selector__option--active'
                : '')
            }
            aria-pressed={selected === m.slug}
            disabled={disabled}
            onClick={() => onSelect(m.slug)}
          >
            <span className="mx-payment-method-selector__icon">
              {getMethodMark(m)}
            </span>
            <span className="mx-payment-method-selector__name">
              {m.name}
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}

export default PaymentMethodSelector;
