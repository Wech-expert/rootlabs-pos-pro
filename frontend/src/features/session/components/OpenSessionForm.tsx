import { useState, useCallback } from 'react';
import { openSession } from '../services/cashSessionApi';
import { Button, MoneyDisplay } from '../../../components/ui';

interface OpenSessionFormProps {
  onOpened: () => void;
}

interface Denomination {
  key: string;
  label: string;
  value: number;
}

const BILL_DENOMINATIONS: Denomination[] = [
  { key: 'bill-1000', label: 'Billete $1000', value: 1000 },
  { key: 'bill-500', label: 'Billete $500', value: 500 },
  { key: 'bill-200', label: 'Billete $200', value: 200 },
  { key: 'bill-100', label: 'Billete $100', value: 100 },
  { key: 'bill-50', label: 'Billete $50', value: 50 },
  { key: 'bill-20', label: 'Billete $20', value: 20 },
];

const COIN_DENOMINATIONS: Denomination[] = [
  { key: 'coin-20', label: 'Moneda $20', value: 20 },
  { key: 'coin-10', label: 'Moneda $10', value: 10 },
  { key: 'coin-5', label: 'Moneda $5', value: 5 },
  { key: 'coin-2', label: 'Moneda $2', value: 2 },
  { key: 'coin-1', label: 'Moneda $1', value: 1 },
  { key: 'coin-050', label: 'Moneda $0.50', value: 0.5 },
];

const ALL_DENOMINATIONS = [...BILL_DENOMINATIONS, ...COIN_DENOMINATIONS];

function parseQuantity(value: string | undefined): number {
  if (!value) return 0;

  const parsed = parseInt(value, 10);

  if (Number.isNaN(parsed) || parsed < 0) return 0;

  return parsed;
}

function OpenSessionForm({ onOpened }: OpenSessionFormProps) {
  const [quantities, setQuantities] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const openingTotal = ALL_DENOMINATIONS.reduce(
    (total, denomination) =>
      total + parseQuantity(quantities[denomination.key]) * denomination.value,
    0,
  );

  const updateQuantity = useCallback((key: string, value: string) => {
    const numericValue = value === '' ? '' : String(Math.max(0, parseInt(value, 10) || 0));

    setQuantities((prev) => ({
      ...prev,
      [key]: numericValue,
    }));
  }, []);

  const renderDenominationRow = useCallback(
    (denomination: Denomination) => {
      const quantity = parseQuantity(quantities[denomination.key]);
      const subtotal = quantity * denomination.value;
      const inputId = `mx-session-denomination-${denomination.key}`;

      return (
        <div className="mx-session-denomination-row" key={denomination.key}>
          <label
            className="mx-session-denomination-row__label"
            htmlFor={inputId}
          >
            {denomination.label}
          </label>
          <input
            id={inputId}
            type="number"
            className="mx-session-denomination-row__input"
            min={0}
            step={1}
            inputMode="numeric"
            value={quantities[denomination.key] ?? ''}
            onChange={(e) =>
              updateQuantity(
                denomination.key,
                (e.target as HTMLInputElement).value,
              )
            }
            disabled={isSubmitting}
            aria-label={`Cantidad para ${denomination.label}`}
          />
          <div className="mx-session-denomination-row__subtotal">
            <MoneyDisplay amount={subtotal} size="sm" />
          </div>
        </div>
      );
    },
    [isSubmitting, quantities, updateQuantity],
  );

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setError(null);

      setIsSubmitting(true);
      try {
        await openSession({ opening_amount: openingTotal.toFixed(2) });
        onOpened();
      } catch (err) {
        setError(
          err instanceof Error ? err.message : 'No se pudo abrir la caja',
        );
      } finally {
        setIsSubmitting(false);
      }
    },
    [onOpened, openingTotal],
  );

  return (
    <form className="mx-session-open" onSubmit={handleSubmit}>
      <div className="mx-session-denomination">
        <div className="mx-session-denomination__section">
          <h2 className="mx-session-denomination__title">Billetes</h2>
          <div className="mx-session-denomination__rows">
            {BILL_DENOMINATIONS.map(renderDenominationRow)}
          </div>
        </div>

        <div className="mx-session-denomination__section">
          <h2 className="mx-session-denomination__title">Monedas</h2>
          <div className="mx-session-denomination__rows">
            {COIN_DENOMINATIONS.map(renderDenominationRow)}
          </div>
        </div>

        <div
          className="mx-session-denomination-total"
          aria-live="polite"
          aria-label={`Total apertura ${openingTotal.toFixed(2)} pesos`}
        >
          <span className="mx-session-denomination-total__label">
            Total apertura
          </span>
          <MoneyDisplay
            amount={openingTotal}
            size="lg"
            emphasized
            className="mx-session-denomination-total__amount"
          />
        </div>
      </div>

      {error && <p className="mx-session-open__error">{error}</p>}

      <Button
        type="submit"
        variant="primary"
        size="lg"
        disabled={isSubmitting}
        className="mx-session-open__button"
      >
        {isSubmitting ? 'Abriendo…' : 'Abrir caja'}
      </Button>
    </form>
  );
}

export default OpenSessionForm;
