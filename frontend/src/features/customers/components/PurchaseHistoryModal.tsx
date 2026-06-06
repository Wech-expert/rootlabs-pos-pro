import { useState, useEffect } from 'react';
import { Modal } from '../../../components/ui';
import { MoneyDisplay } from '../../../components/ui';
import { getCustomerPurchases } from '../services/customerApi';
import type { Customer, PurchaseHistoryItem } from '../types';

interface PurchaseHistoryModalProps {
  open: boolean;
  customer: Customer;
  onClose: () => void;
}

const STATUS_LABELS: Record<string, string> = {
  pending: 'Pendiente',
  processing: 'Procesando',
  on_hold: 'En espera',
  completed: 'Completado',
  cancelled: 'Cancelado',
  refunded: 'Reembolsado',
  failed: 'Fallido',
};

function statusLabel(status: string): string {
  return STATUS_LABELS[status] ?? status;
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-MX', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return dateStr;
  }
}

function PurchaseHistoryModal({
  open,
  customer,
  onClose,
}: PurchaseHistoryModalProps) {
  const [items, setItems] = useState<PurchaseHistoryItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;

    let cancelled = false;

    setLoading(true);
    setError(null);
    setItems([]);

    getCustomerPurchases(customer.id, 10)
      .then((data) => {
        if (!cancelled) {
          setItems(data.items);
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(
            err instanceof Error
              ? err.message
              : 'Error al cargar historial',
          );
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [open, customer.id]);

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={`Historial — ${customer.display_name}`}
    >
      <div className="mx-purchase-history-modal">
        {loading && (
          <p className="mx-purchase-history-modal__loading">Cargando...</p>
        )}

        {error && (
          <p className="mx-purchase-history-modal__error">{error}</p>
        )}

        {!loading && !error && items.length === 0 && (
          <p className="mx-purchase-history-modal__empty">
            Sin compras registradas.
          </p>
        )}

        {!loading && !error && items.length > 0 && (
          <div className="mx-purchase-history-modal__table-wrapper">
            <table className="mx-purchase-history-modal__table">
              <thead>
                <tr>
                  <th># Orden</th>
                  <th>Fecha</th>
                  <th>Total</th>
                  <th>Estado</th>
                  <th>Pago</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.order_id}>
                    <td>{item.order_number}</td>
                    <td>{formatDate(item.date)}</td>
                    <td>
                      <MoneyDisplay
                        amount={parseFloat(item.total)}
                        size="sm"
                      />
                    </td>
                    <td>{statusLabel(item.status)}</td>
                    <td>{item.payment_method_label ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </Modal>
  );
}

export default PurchaseHistoryModal;
