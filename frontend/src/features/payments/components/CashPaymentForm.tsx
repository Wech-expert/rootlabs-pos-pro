import { MoneyDisplay } from '../../../components/ui';
import NumericKeypad from './NumericKeypad';
import QuickAmountButtons from './QuickAmountButtons';

interface CashPaymentFormProps {
  total: number;
  amountReceived: string;
  onAmountChange: (value: string) => void;
  disabled?: boolean;
}

function normalizeAmount(value: string): string {
  const normalized = value.replace(',', '.').replace(/[^\d.]/g, '');
  const [integerPart = '', ...decimalParts] = normalized.split('.');
  const integer = integerPart.replace(/^0+(?=\d)/, '');

  if (decimalParts.length === 0) {
    return integer;
  }

  return `${integer || '0'}.${decimalParts.join('').slice(0, 2)}`;
}

function CashPaymentForm({
  total,
  amountReceived,
  onAmountChange,
  disabled = false,
}: CashPaymentFormProps) {
  const received = parseFloat(amountReceived) || 0;
  const pending = Math.max(total - received, 0);

  return (
    <div className="mx-cash-payment-form">
      <div className="mx-cash-payment-form__input-group">
        <label className="mx-cash-payment-form__label" htmlFor="mx-payment-cash-received">
          Efectivo recibido
        </label>
        <div className="mx-cash-payment-form__input-row">
          <span className="mx-cash-payment-form__currency">$</span>
          <input
            id="mx-payment-cash-received"
            type="text"
            inputMode="decimal"
            className="mx-cash-payment-form__field"
            value={amountReceived}
            onChange={(e) => onAmountChange(normalizeAmount(e.target.value))}
            placeholder="0.00"
            disabled={disabled}
            autoFocus
          />
        </div>
      </div>

      <QuickAmountButtons
        total={total}
        selectedAmount={amountReceived}
        onSelect={onAmountChange}
        disabled={disabled}
      />

      <NumericKeypad
        value={amountReceived}
        onChange={onAmountChange}
        disabled={disabled}
      />

      {received > 0 && received < total && (
        <div className="mx-cash-payment-form__insufficient" role="alert">
          Monto insuficiente. Faltan <MoneyDisplay amount={pending} size="sm" />
        </div>
      )}
    </div>
  );
}

export default CashPaymentForm;
