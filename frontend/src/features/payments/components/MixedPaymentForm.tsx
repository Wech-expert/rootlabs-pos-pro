import { useState, useCallback, useMemo } from 'react';
import { Button, MoneyDisplay } from '../../../components/ui';
import type { PosPaymentMethod, PaymentLine } from '../types';

function resolveMethodBySlug(methods: PosPaymentMethod[], slug: string): PosPaymentMethod | undefined {
  return methods.find((m) => m.slug === slug);
}

function isCashLike(method: PosPaymentMethod): boolean {
  return method.payment_type === 'cash' || method.affects_cash_register;
}

interface MixedPaymentFormProps {
  total: number;
  methods: PosPaymentMethod[];
  onSubmit: (lines: PaymentLine[]) => void;
  disabled?: boolean;
}

function MixedPaymentForm({
  total,
  methods,
  onSubmit,
  disabled = false,
}: MixedPaymentFormProps) {
  const availableMethods = methods.filter((m) => m.slug !== 'mixed');
  const [lines, setLines] = useState<Array<{ method: string; amount: string; reference: string }>>([]);
  const [error, setError] = useState<string | null>(null);

  const addLine = useCallback(() => {
    setLines((prev) => [...prev, { method: availableMethods[0]?.slug ?? '', amount: '', reference: '' }]);
    setError(null);
  }, [availableMethods]);

  const removeLine = useCallback((index: number) => {
    setLines((prev) => prev.filter((_, i) => i !== index));
    setError(null);
  }, []);

  const updateLine = useCallback(
    (index: number, field: string, value: string) => {
      setLines((prev) =>
        prev.map((l, i) => (i === index ? { ...l, [field]: value } : l)),
      );
      setError(null);
    },
    [],
  );

  const lineSums = lines.reduce((s, l) => s + (parseFloat(l.amount) || 0), 0);
  const pending = Math.max(0, total - lineSums);
  const change = lineSums >= total ? Math.max(0, lineSums - total) : 0;

  const validationErrors = useMemo(() => {
    const errs: string[] = [];
    let nonCashTotal = 0;

    lines.forEach((l) => {
      const amt = parseFloat(l.amount) || 0;
      if (amt <= 0 && l.amount !== '') return;

      const method = resolveMethodBySlug(availableMethods, l.method);
      if (!method) return;

      if (!isCashLike(method)) {
        nonCashTotal += amt;
      }
    });

    if (nonCashTotal > total + 0.01) {
      errs.push('El pago con tarjeta no puede exceder el total de la venta.');
    }

    const hasCashLine = lines.some((l) => {
      const m = resolveMethodBySlug(availableMethods, l.method);
      return m && isCashLike(m) && (parseFloat(l.amount) || 0) > 0;
    });

    if (change > 0.01 && !hasCashLine) {
      errs.push('Solo el efectivo puede generar cambio. Agregue una línea de efectivo para el excedente.');
    }

    return errs;
  }, [lines, availableMethods, total, change]);

  const handleSubmit = () => {
    if (lines.length === 0) {
      setError('Agregue al menos una forma de pago.');
      return;
    }

    if (lineSums < total - 0.01) {
      setError(`Faltan $${pending.toFixed(2)} para cubrir el total.`);
      return;
    }

    if (validationErrors.length > 0) {
      setError(validationErrors[0]);
      return;
    }

    const paymentLines: PaymentLine[] = lines.map((l) => ({
      method: l.method,
      amount: parseFloat(l.amount) || 0,
      reference: l.reference.trim() || null,
    }));

    onSubmit(paymentLines);
  };

  return (
    <div className="mx-mixed-payment-form">
      <div className="mx-mixed-payment-form__summary">
        <div className="mx-mixed-payment-form__summary-row">
          <span>Total a pagar</span>
          <MoneyDisplay amount={total} size="md" emphasized />
        </div>
        <div className="mx-mixed-payment-form__summary-row">
          <span>Total capturado</span>
          <MoneyDisplay amount={lineSums} size="md" />
        </div>
        {pending > 0 && (
          <div className="mx-mixed-payment-form__summary-row mx-mixed-payment-form__summary-row--pending">
            <span>Pendiente</span>
            <MoneyDisplay amount={pending} size="md" />
          </div>
        )}
        {change > 0 && (
          <div className="mx-mixed-payment-form__summary-row">
            <span>Cambio</span>
            <MoneyDisplay amount={change} size="md" />
          </div>
        )}
      </div>

      {lines.map((line, index) => (
        <div key={index} className="mx-mixed-payment-form__line">
          <select
            className="mx-mixed-payment-form__method-select"
            value={line.method}
            onChange={(e) => updateLine(index, 'method', e.target.value)}
            disabled={disabled}
          >
            {availableMethods.map((m) => (
              <option key={m.id} value={m.slug}>
                {m.name}
              </option>
            ))}
          </select>
          <input
            type="text"
            inputMode="decimal"
            className="mx-mixed-payment-form__amount"
            value={line.amount}
            onChange={(e) => updateLine(index, 'amount', e.target.value)}
            placeholder="0.00"
            disabled={disabled}
          />
          {availableMethods.find((m) => m.slug === line.method)?.allow_reference && (
            <input
              type="text"
              className="mx-mixed-payment-form__reference"
              value={line.reference}
              onChange={(e) => updateLine(index, 'reference', e.target.value)}
              placeholder="Ref. opcional"
              maxLength={100}
              disabled={disabled}
            />
          )}
          <Button
            variant="ghost"
            size="sm"
            onClick={() => removeLine(index)}
            disabled={disabled || lines.length <= 1}
          >
            ✕
          </Button>
        </div>
      ))}

      {error && <p className="mx-mixed-payment-form__error">{error}</p>}

      <div className="mx-mixed-payment-form__actions">
        <Button
          variant="ghost"
          size="sm"
          onClick={addLine}
          disabled={disabled}
        >
          + Agregar forma de pago
        </Button>
        <Button
          variant="primary"
          size="lg"
          onClick={handleSubmit}
          disabled={disabled || lines.length === 0 || lineSums <= 0}
        >
          {`Cobrar $${lineSums.toFixed(2)}`}
        </Button>
      </div>
    </div>
  );
}

export default MixedPaymentForm;
