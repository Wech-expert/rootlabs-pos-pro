import type { CashMovement } from '../types';
import { MoneyDisplay } from '../../../components/ui';

interface CashMovementListProps {
  items: CashMovement[];
  onReverse: (movement: CashMovement) => void;
}

function CashMovementList({ items, onReverse }: CashMovementListProps) {
  if (items.length === 0) {
    return (
      <div className="mx-cash-movement-list__empty">
        <p className="mx-cash-movement-list__empty-text">
          Aún no hay movimientos de caja
        </p>
      </div>
    );
  }

  return (
    <div className="mx-cash-movement-list">
      {items.map((item) => {
        const date = new Date(item.created_at.replace(' ', 'T'));
        const dateStr = date.toLocaleDateString([], {
          month: 'short',
          day: 'numeric',
        });
        const timeStr = date.toLocaleTimeString([], {
          hour: '2-digit',
          minute: '2-digit',
        });

        return (
          <div key={item.id} className="mx-cash-movement-list__item">
            <div className="mx-cash-movement-list__item-main">
              <span className="mx-cash-movement-list__item-date">
                {dateStr} {timeStr}
              </span>
              <span
                className={`mx-cash-movement-list__item-type mx-cash-movement-list__item-type--${item.movement_type}`}
              >
                {item.movement_type === 'cash_in' ? 'Entrada' : 'Salida'}
              </span>
            </div>
            <div className="mx-cash-movement-list__item-actions">
              <div className="mx-cash-movement-list__item-amount">
                <MoneyDisplay amount={parseFloat(item.amount)} size="sm" />
              </div>
              <button
                type="button"
                className="mx-cash-movement-list__reverse"
                onClick={() => onReverse(item)}
              >
                Anular
              </button>
            </div>
            {item.reason && (
              <p className="mx-cash-movement-list__item-reason">
                {item.reason}
              </p>
            )}
          </div>
        );
      })}
    </div>
  );
}

export default CashMovementList;
