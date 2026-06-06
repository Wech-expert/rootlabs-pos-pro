import { useState, useEffect, useCallback } from 'react';
import { Button, MoneyDisplay } from '../../../components/ui';
import { fetchSaleDetail } from '../services/saleHistoryApi';
import { fetchTicket, fetchGiftTicket } from '../../sales/services/ticketApi';
import { writeTicketAndPrint } from '../../sales/utils/printTicket';
import type { SaleDetail } from '../types';
import { STATUS_LABELS } from '../types';

interface SaleDetailDrawerProps {
  saleId: number;
  onClose: () => void;
  onRefund?: (saleId: number) => void;
}

function SaleDetailDrawer({ saleId, onRefund }: SaleDetailDrawerProps) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [detail, setDetail] = useState<SaleDetail | null>(null);
  const [isPrinting, setIsPrinting] = useState(false);
  const [printError, setPrintError] = useState<string | null>(null);
  const [isGiftPrinting, setIsGiftPrinting] = useState(false);
  const [giftPrintError, setGiftPrintError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const data = await fetchSaleDetail(saleId);
        if (!cancelled) {
          setDetail(data);
        }
      } catch (err) {
        if (!cancelled) {
          setError(
            err instanceof Error ? err.message : 'No se pudo cargar el detalle',
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    load();

    return () => {
      cancelled = true;
    };
  }, [saleId]);

  const doPrint = useCallback(async (useGift: boolean) => {
    const win = window.open('', '_blank', 'width=420,height=760');

    if (!win) {
      const msg = 'No se pudo abrir la ventana de impresión. Permite ventanas emergentes.';
      if (useGift) {
        setGiftPrintError(msg);
      } else {
        setPrintError(msg);
      }
      return;
    }

    if (useGift) {
      setIsGiftPrinting(true);
      setGiftPrintError(null);
    } else {
      setIsPrinting(true);
      setPrintError(null);
    }

    try {
      const html = useGift
        ? await fetchGiftTicket(saleId)
        : await fetchTicket(saleId);
      writeTicketAndPrint(win, html);
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'No se pudo generar el ticket';
      if (useGift) {
        setGiftPrintError(msg);
      } else {
        setPrintError(msg);
      }
      win.close();
    } finally {
      if (useGift) {
        setIsGiftPrinting(false);
      } else {
        setIsPrinting(false);
      }
    }
  }, [saleId]);

  const handlePrintTicket = useCallback(() => doPrint(false), [doPrint]);
  const handleGiftPrint = useCallback(() => doPrint(true), [doPrint]);

  if (loading) {
    return (
      <div className="mx-history-detail">
        <p className="mx-history-detail__loading">Cargando detalle…</p>
      </div>
    );
  }

  if (error || !detail) {
    return (
      <div className="mx-history-detail">
        <p className="mx-history-detail__error" role="alert">
          {error || 'No se pudo cargar el detalle'}
        </p>
      </div>
    );
  }

  const s = detail.sale;
  const o = detail.order;

  const canRefund =
    onRefund &&
    (s.status === 'completed' || s.status === 'processing') &&
    parseFloat(s.refunded_total) < parseFloat(s.total);

  return (
    <div className="mx-history-detail">
      <div className="mx-history-detail__header">
        <h2 className="mx-history-detail__title">
          Venta #{s.id}
        </h2>
        {o && (
          <p className="mx-history-detail__order-number">
            Orden WC #{o.number}
          </p>
        )}
      </div>

      <div className="mx-history-detail__meta">
        <div className="mx-history-detail__meta-row">
          <span className="mx-history-detail__meta-label">Cajero</span>
          <span className="mx-history-detail__meta-value">{s.cashier_name}</span>
        </div>
        <div className="mx-history-detail__meta-row">
          <span className="mx-history-detail__meta-label">Estado</span>
          <span className="mx-history-detail__meta-value">
            {STATUS_LABELS[s.display_status] || s.display_status}
          </span>
        </div>
        <div className="mx-history-detail__meta-row">
          <span className="mx-history-detail__meta-label">Fecha</span>
          <span className="mx-history-detail__meta-value">{s.created_at}</span>
        </div>
      </div>

      {detail.payment && (
        <>
          <h3 className="mx-history-detail__section-title">Pago</h3>
          <div className="mx-history-detail__meta">
            <div className="mx-history-detail__meta-row">
              <span className="mx-history-detail__meta-label">Método</span>
              <span className="mx-history-detail__meta-value">
                {detail.payment.method_label}
              </span>
            </div>
            {detail.payment.method === 'cash' && (
              <>
                <div className="mx-history-detail__meta-row">
                  <span className="mx-history-detail__meta-label">Recibido</span>
                  <MoneyDisplay amount={parseFloat(detail.payment.amount_received || '0')} size="sm" />
                </div>
                <div className="mx-history-detail__meta-row">
                  <span className="mx-history-detail__meta-label">Cambio</span>
                  <MoneyDisplay amount={parseFloat(detail.payment.change || '0')} size="sm" />
                </div>
              </>
            )}
            {detail.payment.method === 'card' && detail.payment.card_reference && (
              <div className="mx-history-detail__meta-row">
                <span className="mx-history-detail__meta-label">Referencia</span>
                <span className="mx-history-detail__meta-value">
                  {detail.payment.card_reference}
                </span>
              </div>
            )}
          </div>
        </>
      )}

      <h3 className="mx-history-detail__section-title">Productos</h3>
      {detail.items.length === 0 ? (
        <p className="mx-history-detail__empty">Sin productos</p>
      ) : (
        <table className="mx-history-detail__items-table">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Cant</th>
              <th>Precio unit.</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            {detail.items.map((item, idx) => (
              <tr key={idx}>
                <td>{item.name}</td>
                <td>{item.quantity}</td>
                <td>
                  <MoneyDisplay amount={parseFloat(item.unit_price)} size="sm" />
                </td>
                <td>
                  <MoneyDisplay amount={parseFloat(item.line_total)} size="sm" />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      <div className="mx-history-detail__totals">
        <div className="mx-history-detail__total-row">
          <span>Total</span>
          <MoneyDisplay amount={parseFloat(s.total)} size="md" emphasized />
        </div>
        {parseFloat(s.refunded_total) > 0 && (
          <div className="mx-history-detail__total-row">
            <span>Devuelto</span>
            <MoneyDisplay amount={parseFloat(s.refunded_total)} size="md" />
          </div>
        )}
        <div className="mx-history-detail__total-row mx-history-detail__total-row--net">
          <span>Neto</span>
          <MoneyDisplay amount={parseFloat(s.net_total)} size="md" emphasized />
        </div>
      </div>

      {detail.refunds.length > 0 && (
        <>
          <h3 className="mx-history-detail__section-title">Devoluciones</h3>
          {detail.refunds.map((r) => (
            <div key={r.id} className="mx-history-detail__refund-item">
              <p>
                {r.refund_type === 'total' ? 'Devolución total' : 'Devolución parcial'}
                {' — '}
                <MoneyDisplay amount={parseFloat(r.refund_amount)} size="sm" />
                {r.refund_method && ` (${r.refund_method === 'cash' ? 'Efectivo' : 'Tarjeta'})`}
              </p>
              {r.reason && <p className="mx-history-detail__refund-reason">{r.reason}</p>}
              <p className="mx-history-detail__refund-date">{r.created_at}</p>
            </div>
          ))}
        </>
      )}

      {detail.logs.length > 0 && (
        <>
          <h3 className="mx-history-detail__section-title">Eventos</h3>
          {detail.logs.map((log, idx) => (
            <div key={idx} className="mx-history-detail__log-item">
              <p>{log.message || log.event_type}</p>
              <p className="mx-history-detail__log-date">{log.created_at}</p>
            </div>
          ))}
        </>
      )}

      {detail.actions.can_reprint_ticket && (
        <div className="mx-history-detail__actions">
          {canRefund && (
            <Button
              variant="primary"
              size="md"
              onClick={() => onRefund!(s.id)}
            >
              Devolver
            </Button>
          )}
          {printError && (
            <p className="mx-history-detail__print-error" role="alert">
              {printError}
            </p>
          )}
          {giftPrintError && (
            <p className="mx-history-detail__print-error" role="alert">
              {giftPrintError}
            </p>
          )}
          <Button
            variant="secondary"
            size="md"
            onClick={handlePrintTicket}
            loading={isPrinting}
            disabled={isPrinting}
          >
            {isPrinting ? 'Preparando ticket…' : 'Reimprimir ticket'}
          </Button>
          <Button
            variant="secondary"
            size="md"
            onClick={handleGiftPrint}
            loading={isGiftPrinting}
            disabled={isGiftPrinting}
          >
            {isGiftPrinting ? 'Preparando ticket…' : 'Ticket de regalo'}
          </Button>
        </div>
      )}
    </div>
  );
}

export default SaleDetailDrawer;
