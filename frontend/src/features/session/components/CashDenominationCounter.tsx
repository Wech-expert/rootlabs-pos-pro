import { useCallback, useMemo, useState } from 'react';
import { MoneyDisplay } from '../../../components/ui';

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

interface CashDenominationCounterProps {
  expectedAmount: number;
  idPrefix: string;
}

function CashDenominationCounter({
  expectedAmount,
  idPrefix,
}: CashDenominationCounterProps) {
  const [quantities, setQuantities] = useState<Record<string, string>>({});

  const countedAmount = useMemo(
    () =>
      ALL_DENOMINATIONS.reduce(
        (total, denomination) =>
          total + parseQuantity(quantities[denomination.key]) * denomination.value,
        0,
      ),
    [quantities],
  );

  const difference = useMemo(
    () => countedAmount - expectedAmount,
    [countedAmount, expectedAmount],
  );

  const matchesExpected = Math.abs(difference) < 0.001;

  const updateQuantity = useCallback((key: string, value: string) => {
    const numericValue = value === '' ? '' : String(Math.max(0, parseInt(value, 10) || 0));

    setQuantities((previous) => ({
      ...previous,
      [key]: numericValue,
    }));
  }, []);

  const renderDenominationRow = useCallback(
    (denomination: Denomination) => {
      const quantity = parseQuantity(quantities[denomination.key]);
      const subtotal = quantity * denomination.value;
      const inputId = `${idPrefix}-${denomination.key}`;

      return (
        <div className="mx-session-denomination-row" key={denomination.key}>
          <label className="mx-session-denomination-row__label" htmlFor={inputId}>
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
            onChange={(event) =>
              updateQuantity(
                denomination.key,
                (event.target as HTMLInputElement).value,
              )
            }
            aria-label={`Cantidad para ${denomination.label}`}
          />
          <div className="mx-session-denomination-row__subtotal">
            <MoneyDisplay amount={subtotal} size="sm" />
          </div>
        </div>
      );
    },
    [idPrefix, quantities, updateQuantity],
  );

  return (
    <div className="mx-cash-denomination-counter">
      <div className="mx-session-denomination">
        <div className="mx-session-denomination__section">
          <h3 className="mx-session-denomination__title">Billetes</h3>
          <div className="mx-session-denomination__rows">
            {BILL_DENOMINATIONS.map(renderDenominationRow)}
          </div>
        </div>

        <div className="mx-session-denomination__section">
          <h3 className="mx-session-denomination__title">Monedas</h3>
          <div className="mx-session-denomination__rows">
            {COIN_DENOMINATIONS.map(renderDenominationRow)}
          </div>
        </div>

        <div
          className="mx-session-denomination-total"
          aria-live="polite"
          aria-label={`Total contado ${countedAmount.toFixed(2)} pesos`}
        >
          <span className="mx-session-denomination-total__label">Total contado</span>
          <MoneyDisplay
            amount={countedAmount}
            size="lg"
            emphasized
            className="mx-session-denomination-total__amount"
          />
        </div>
      </div>

      <div className="mx-cash-denomination-counter__comparison" aria-live="polite">
        <div className="mx-cash-denomination-counter__comparison-row">
          <span>Efectivo esperado</span>
          <MoneyDisplay amount={expectedAmount} size="md" />
        </div>
        <div className="mx-cash-denomination-counter__comparison-row">
          <span>Diferencia</span>
          <MoneyDisplay amount={difference} size="md" emphasized />
        </div>
        <p className="mx-cash-denomination-counter__status">
          {matchesExpected
            ? 'El conteo coincide con el efectivo esperado.'
            : 'El conteo aún no coincide con el efectivo esperado.'}
        </p>
      </div>
    </div>
  );
}

export default CashDenominationCounter;
