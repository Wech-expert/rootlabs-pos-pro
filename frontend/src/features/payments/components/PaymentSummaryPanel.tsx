import { MoneyDisplay } from '../../../components/ui';
import type { PaymentMethod } from '../types';

interface PaymentSummaryPanelProps {
  total: number;
  method: PaymentMethod | null;
  amountReceived: string;
}

function parseAmount(value: string): number {
  const parsed = parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function PaymentSummaryPanel({ total, method, amountReceived }: PaymentSummaryPanelProps) {
  const received = parseAmount(amountReceived);
  const pending = method === 'cash' ? Math.max(total - received, 0) : method === 'card' ? 0 : total;
  const change = method === 'cash' ? Math.max(received - total, 0) : 0;

  return (
    <aside className="mx-payment-summary-panel" aria-label="Resumen del cobro">
      <div className="mx-payment-summary-panel__item mx-payment-summary-panel__item--total">
        <span className="mx-payment-summary-panel__label">Total a pagar</span>
        <MoneyDisplay amount={total} size="lg" emphasized />
      </div>

      <div className="mx-payment-summary-panel__item">
        <span className="mx-payment-summary-panel__label">Pendiente</span>
        <MoneyDisplay amount={pending} size="md" emphasized />
      </div>

      <div className="mx-payment-summary-panel__item">
        <span className="mx-payment-summary-panel__label">Cambio</span>
        <MoneyDisplay amount={change} size="md" emphasized />
      </div>
    </aside>
  );
}

export default PaymentSummaryPanel;
