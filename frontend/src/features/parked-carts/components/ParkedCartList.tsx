import type { ParkedCartSummary } from '../types';
import { MoneyDisplay, Button } from '../../../components/ui';

interface ParkedCartListProps {
  items: ParkedCartSummary[];
  hasQuery: boolean;
  onRestore: (id: number) => void;
  onDelete: (id: number) => void;
  restoringId: number | null;
  deletingId: number | null;
  onClearSearch: () => void;
}

function ParkedCartList({
  items,
  hasQuery,
  onRestore,
  onDelete,
  restoringId,
  deletingId,
  onClearSearch,
}: ParkedCartListProps) {
  if (items.length === 0) {
    return (
      <div className="mx-parked-cart-list__empty">
        <p className="mx-parked-cart-list__empty-title">
          {hasQuery ? 'Sin resultados' : 'No hay carritos guardados'}
        </p>
        <p className="mx-parked-cart-list__empty-text">
          {hasQuery
            ? 'Ajusta la búsqueda para encontrar un carrito guardado.'
            : 'Guarda un carrito desde la venta actual para recuperarlo después.'}
        </p>
        {hasQuery && (
          <Button variant="secondary" size="sm" onClick={onClearSearch}>
            Limpiar búsqueda
          </Button>
        )}
      </div>
    );
  }

  return (
    <div className="mx-parked-cart-list">
      {items.map((item) => {
        const date = new Date(item.created_at.replace(' ', 'T'));
        const dateStr = date.toLocaleDateString('es-MX', {
          month: 'short',
          day: 'numeric',
        });
        const timeStr = date.toLocaleTimeString('es-MX', {
          hour: '2-digit',
          minute: '2-digit',
        });

        const isRestoring = restoringId === item.id;
        const isDeleting = deletingId === item.id;
        const hasAction = restoringId !== null || deletingId !== null;

        return (
          <div key={item.id} className="mx-parked-cart-card">
            <div className="mx-parked-cart-card__header">
              <div className="mx-parked-cart-card__title">
                <span className="mx-parked-cart-card__label">{item.label ?? 'Sin etiqueta'}</span>
                {item.customer_label && (
                  <span className="mx-parked-cart-card__customer">{item.customer_label}</span>
                )}
              </div>
              <button
                className="mx-parked-cart-card__delete"
                disabled={isDeleting || hasAction}
                onClick={() => onDelete(item.id)}
                aria-label={`Quitar carrito ${item.id}`}
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18" />
                  <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
              </button>
            </div>

            <div className="mx-parked-cart-card__body">
              <div className="mx-parked-cart-card__meta">
                <span>{item.item_count} prod.</span>
                <span className="mx-parked-cart-card__dot">•</span>
                <span>{dateStr} {timeStr}</span>
              </div>
              <div className="mx-parked-cart-card__price">
                <MoneyDisplay amount={parseFloat(item.total)} size="sm" />
              </div>
            </div>

            <div className="mx-parked-cart-card__footer">
              <Button
                variant="secondary"
                className="mx-parked-cart-card__restore"
                disabled={isRestoring || hasAction}
                loading={isRestoring}
                onClick={() => onRestore(item.id)}
              >
                Restaurar carrito
              </Button>
            </div>
          </div>
        );
      })}
    </div>
  );
}

export default ParkedCartList;
