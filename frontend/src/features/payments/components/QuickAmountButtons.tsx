import { MoneyDisplay } from '../../../components/ui';

interface QuickAmountButtonsProps {
  total: number;
  selectedAmount: string;
  onSelect: (value: string) => void;
  disabled?: boolean;
}

function roundUp(total: number, multiple: number): number {
  return Math.ceil(total / multiple) * multiple;
}

function buildQuickAmounts(total: number): number[] {
  const candidates = [
    total,
    roundUp(total, 50),
    roundUp(total, 100),
    roundUp(total, 200),
    500,
    1000,
  ];

  return Array.from(new Set(candidates.filter((amount) => amount >= total)))
    .sort((a, b) => a - b);
}

function amountToValue(amount: number): string {
  return Number.isInteger(amount) ? String(amount) : amount.toFixed(2);
}

function QuickAmountButtons({
  total,
  selectedAmount,
  onSelect,
  disabled = false,
}: QuickAmountButtonsProps) {
  const selected = parseFloat(selectedAmount);
  const amounts = buildQuickAmounts(total);

  return (
    <div className="mx-payment-quick-amounts" aria-label="Montos rápidos">
      {amounts.map((amount) => {
        const active = Number.isFinite(selected) && Math.abs(selected - amount) < 0.01;

        return (
          <button
            key={amountToValue(amount)}
            type="button"
            className={`mx-payment-quick-amounts__button${active ? ' mx-payment-quick-amounts__button--active' : ''}`}
            onClick={() => onSelect(amountToValue(amount))}
            disabled={disabled}
          >
            <MoneyDisplay amount={amount} size="sm" emphasized={active} />
          </button>
        );
      })}
    </div>
  );
}

export default QuickAmountButtons;
