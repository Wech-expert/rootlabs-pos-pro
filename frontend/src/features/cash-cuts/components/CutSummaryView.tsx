import { MoneyDisplay } from '../../../components/ui';
import type { CutSummary, MethodBreakdown } from '../types';

interface CutSummaryViewProps {
  summary: CutSummary;
}

function getMethodName(key: string, method: MethodBreakdown): string {
  if (method.name) return method.name;
  if (key === 'cash') return 'Efectivo';
  if (key === 'card') return 'Tarjeta';
  if (key === 'mixed') return 'Pago mixto';

  return method.slug || key;
}
void getMethodName;

function getMethodAmount(method: MethodBreakdown): number {
  const amount = method.total ?? method.amount ?? 0;
  const parsed = typeof amount === 'number' ? amount : parseFloat(amount);
  return Number.isNaN(parsed) ? 0 : parsed;
}
void getMethodAmount;

function getMethodCount(method: MethodBreakdown): number {
  return method.count ?? method.sales_count ?? 0;
}
void getMethodCount;

function parseMoney(value: string | number | undefined | null): number {
  if (value === null || value === undefined) return 0;
  const parsed = typeof value === 'number' ? value : parseFloat(value);
  return Number.isNaN(parsed) ? 0 : parsed;
}

function CutSummaryView({ summary }: CutSummaryViewProps) {
  const isZ = summary.cut_type === 'Z';
  const methods = Object.entries(summary.by_method ?? {});
void methods;
  const cashierName = summary.session.cashier_name || 'Sin operador registrado';
  const manualCashIn = parseMoney(summary.cash_flow.manual_cash_in_total);
  const manualCashOut = parseMoney(summary.cash_flow.manual_cash_out_total);
  const salesCashIn = parseMoney(
    summary.cash_flow.sales_cash_in_total ??
      summary.sales.cash_collected_total,
  );
  const salesChangeOut = parseMoney(
    summary.cash_flow.sales_change_out_total ??
      summary.sales.cash_change_total,
  );
void salesChangeOut;
  const cardSales = parseMoney(summary.sales.card_collected_total);

  return (
    <div className="mx-cut-summary">
      <div className="mx-cut-summary__header">
        <p className="mx-cut-summary__type">
          {isZ ? 'Cierre de caja' : 'Pre-corte'}
        </p>
        <p className="mx-cut-summary__meta">
          Sesión #{summary.session.id} | Caja abierta por: {cashierName}
        </p>
      </div>

      <div className="mx-cut-summary__section mx-cut-summary__section--primary">
        <h3 className="mx-cut-summary__section-title">Ventas</h3>

        <div className="mx-cut-summary__row mx-cut-summary__row--hero">
          <span className="mx-cut-summary__label">Ventas cobradas totales</span>
          <MoneyDisplay
            amount={parseFloat(summary.sales.collected_total)}
            size="md"
            emphasized
          />
        </div>

        <div className="mx-cut-summary__split">
          <div className="mx-cut-summary__metric">
            <span className="mx-cut-summary__metric-label">Efectivo</span>
            <MoneyDisplay amount={salesCashIn} size="sm" emphasized />
          </div>
          <div className="mx-cut-summary__metric">
            <span className="mx-cut-summary__metric-label">Tarjeta</span>
            <MoneyDisplay amount={cardSales} size="sm" emphasized />
          </div>
        </div>

        <div className="mx-cut-summary__row">
          <span className="mx-cut-summary__label">Devoluciones</span>
          <MoneyDisplay amount={parseFloat(summary.refunds.total)} size="sm" />
        </div>

        {parseFloat(summary.discounts.total) > 0 && (
          <div className="mx-cut-summary__row">
            <span className="mx-cut-summary__label">Descuentos</span>
            <MoneyDisplay amount={parseFloat(summary.discounts.total)} size="sm" />
          </div>
        )}

        <div className="mx-cut-summary__row mx-cut-summary__row--highlight">
          <span className="mx-cut-summary__label">Neto después de dev.</span>
          <MoneyDisplay
            amount={parseFloat(summary.net_after_refunds)}
            size="md"
            emphasized
          />
        </div>

        <p className="mx-cut-summary__counts">
          Tickets: {summary.sales.count_orders} | Devoluciones: {summary.refunds.count_refunds} | Cancelaciones: {summary.refunds.count_cancellations}
        </p>
      </div>

      <div className="mx-cut-summary__section">
        <h3 className="mx-cut-summary__section-title">Caja</h3>

        <div className="mx-cut-summary__row">
          <span className="mx-cut-summary__label">Apertura</span>
          <MoneyDisplay amount={parseFloat(summary.opening.amount)} size="sm" />
        </div>

        <div className="mx-cut-summary__group">
          <p className="mx-cut-summary__group-title">Desglose de cobros</p>
          <div className="mx-cut-summary__row">
            <span className="mx-cut-summary__label">Ventas en efectivo</span>
            <MoneyDisplay amount={salesCashIn} size="sm" />
          </div>
          <div className="mx-cut-summary__row">
            <span className="mx-cut-summary__label">Ventas con tarjeta</span>
            <MoneyDisplay amount={cardSales} size="sm" />
          </div>
        </div>

        <div className="mx-cut-summary__group">
          <p className="mx-cut-summary__group-title">Movimientos manuales</p>
          <div className="mx-cut-summary__row">
            <span className="mx-cut-summary__label">Ingresos manuales</span>
            <MoneyDisplay amount={manualCashIn} size="sm" />
          </div>
          <div className="mx-cut-summary__row">
            <span className="mx-cut-summary__label">Salidas manuales</span>
            <MoneyDisplay amount={manualCashOut} size="sm" />
          </div>
        </div>

        <div className="mx-cut-summary__row mx-cut-summary__row--muted">
          <span className="mx-cut-summary__label">Entradas de caja totales</span>
          <MoneyDisplay amount={parseFloat(summary.cash_flow.cash_in_total)} size="sm" />
        </div>

        <div className="mx-cut-summary__row mx-cut-summary__row--muted">
          <span className="mx-cut-summary__label">Salidas de caja totales</span>
          <MoneyDisplay amount={parseFloat(summary.cash_flow.cash_out_total)} size="sm" />
        </div>

        <div className="mx-cut-summary__row mx-cut-summary__row--muted">
          <span className="mx-cut-summary__label">Devoluciones</span>
          <MoneyDisplay amount={parseFloat(summary.refunds.total)} size="sm" />
        </div>

        <div className="mx-cut-summary__row mx-cut-summary__row--highlight">
          <span className="mx-cut-summary__label">Efectivo esperado</span>
          <MoneyDisplay
            amount={parseFloat(summary.expected_cash)}
            size="lg"
            emphasized
          />
        </div>

        {isZ && summary.closing && (
          <>
            <div className="mx-cut-summary__divider" />
            <div className="mx-cut-summary__row">
              <span className="mx-cut-summary__label">Efectivo contado</span>
              <MoneyDisplay
                amount={summary.closing.counted_amount ? parseFloat(summary.closing.counted_amount) : 0}
                size="md"
              />
            </div>
            <div className="mx-cut-summary__row">
              <span className="mx-cut-summary__label">Diferencia</span>
              <MoneyDisplay
                amount={summary.closing.difference ? parseFloat(summary.closing.difference) : 0}
                size="md"
              />
            </div>
            {summary.closing.close_note && (
              <div className="mx-cut-summary__row">
                <span className="mx-cut-summary__label">Nota de cierre</span>
                <span className="mx-cut-summary__value">{summary.closing.close_note}</span>
              </div>
            )}
          </>
        )}
      </div>



      <div className="mx-cut-summary__footer">
        <span>
          Generado: {summary.generated_at} por {summary.generated_by}
        </span>
      </div>
    </div>
  );
}

export default CutSummaryView;
