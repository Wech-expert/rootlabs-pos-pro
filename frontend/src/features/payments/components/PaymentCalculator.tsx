import { useEffect, useCallback, useRef } from 'react';
import { Button, MoneyDisplay } from '../../../components/ui';
import NumericKeypad from './NumericKeypad';
import QuickAmountButtons from './QuickAmountButtons';

interface PaymentCalculatorProps {
  label: string;
  value: string;
  quickAmountBase: number;
  onChange: (value: string) => void;
  onApplyAmount: () => void;
  onQuickApply?: (value: string) => void;
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

function parseAmount(value: string): number {
  const parsed = parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function appendValue(currentValue: string, nextValue: string): string {
  if (nextValue === '.' && currentValue.includes('.')) {
    return currentValue;
  }

  if (nextValue === '.' && currentValue === '') {
    return '0.';
  }

  if (nextValue === '00' && currentValue === '') {
    return '0';
  }

  return normalizeAmount(`${currentValue}${nextValue}`);
}

function PaymentCalculator({
  label,
  value,
  quickAmountBase,
  onChange,
  onApplyAmount,
  onQuickApply,
  disabled = false,
}: PaymentCalculatorProps) {
  const amount = parseAmount(value);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!disabled) {
      const timer = setTimeout(() => {
        inputRef.current?.focus();
      }, 50);
      return () => clearTimeout(timer);
    }
  }, [disabled]);

  const handleKeyDown = useCallback(
    (event: globalThis.KeyboardEvent) => {
      if (disabled) return;

      const target = event.target as HTMLElement | null;
      if (!target) return;

      const tag = target.tagName.toLowerCase();

      if (
        tag === 'input' &&
        target.getAttribute('id') !== 'mx-payment-calculator-amount'
      ) {
        return;
      }

      if (tag === 'textarea' || tag === 'select') {
        return;
      }

      if (tag === 'button' || target.closest('button')) {
        return;
      }

      if (event.key >= '0' && event.key <= '9') {
        event.preventDefault();
        inputRef.current?.focus();
        onChange(appendValue(value, event.key));
        return;
      }

      if (event.key === '.' || event.key === ',') {
        event.preventDefault();
        inputRef.current?.focus();
        onChange(appendValue(value, '.'));
        return;
      }

      if (event.key === 'Backspace') {
        event.preventDefault();
        inputRef.current?.focus();
        onChange(value.slice(0, -1));
        return;
      }

      if (event.key === 'Delete') {
        event.preventDefault();
        inputRef.current?.focus();
        onChange('');
        return;
      }

      if (event.key === 'Enter') {
        event.preventDefault();
        onApplyAmount();
      }
    },
    [disabled, value, onChange, onApplyAmount],
  );

  useEffect(() => {
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [handleKeyDown]);

  return (
    <div className="mx-payment-calculator">
      <div className="mx-payment-calculator__display">
        <label className="mx-payment-calculator__label" htmlFor="mx-payment-calculator-amount">
          {label}
        </label>
        <div className="mx-payment-calculator__input-row">
          <span className="mx-payment-calculator__currency">$</span>
          <input
            ref={inputRef}
            id="mx-payment-calculator-amount"
            type="text"
            inputMode="decimal"
            className="mx-payment-calculator__field"
            value={value}
            onChange={(event) => onChange(normalizeAmount(event.target.value))}
            placeholder="0.00"
            disabled={disabled}
            autoFocus
          />
        </div>
        <div className="mx-payment-calculator__current">
          <span>Importe actual</span>
          <MoneyDisplay amount={amount} size="md" emphasized={amount > 0} />
        </div>
      </div>

      <QuickAmountButtons
        total={quickAmountBase}
        selectedAmount={value}
        onSelect={(amount) => {
          onChange(amount);
          if (onQuickApply) {
            onQuickApply(amount);
          }
        }}
        disabled={disabled}
      />

      <NumericKeypad value={value} onChange={onChange} disabled={disabled} />

      <Button
        variant="primary"
        size="lg"
        onClick={onApplyAmount}
        disabled={disabled}
        className="mx-payment-calculator__apply"
      >
        Aplicar importe
      </Button>
    </div>
  );
}

export default PaymentCalculator;
