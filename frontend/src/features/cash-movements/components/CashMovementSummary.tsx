import type { CashMovementTotals } from '../types';
import { MoneyDisplay } from '../../../components/ui';

interface CashMovementSummaryProps {
  totals: CashMovementTotals;
}

function CashMovementSummary({ totals }: CashMovementSummaryProps) {
  return (
    <div className="mx-cash-movement-summary">
      <div className="mx-cash-movement-summary__row mx-cash-movement-summary__row--current">
        <span className="mx-cash-movement-summary__label">Saldo actual</span>
        <MoneyDisplay
          amount={parseFloat(totals.current_cash)}
          size="md"
          emphasized
        />
      </div>
      <div className="mx-cash-movement-summary__row">
        <span className="mx-cash-movement-summary__label">Entradas</span>
        <MoneyDisplay amount={parseFloat(totals.manual_cash_in_total || '0')} size="sm" />
      </div>
      <div className="mx-cash-movement-summary__row">
        <span className="mx-cash-movement-summary__label">Salidas</span>
        <MoneyDisplay amount={parseFloat(totals.manual_cash_out_total || '0')} size="sm" />
      </div>
      <div className="mx-cash-movement-summary__row mx-cash-movement-summary__row--net">
        <span className="mx-cash-movement-summary__label">Movimientos netos</span>
        <MoneyDisplay
          amount={parseFloat(totals.manual_net_cash || '0')}
          size="md"
          emphasized
        />
      </div>
    </div>
  );
}

export default CashMovementSummary;
