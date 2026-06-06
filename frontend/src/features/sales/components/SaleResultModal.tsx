import { useState, useCallback } from 'react';
import { Modal, Button, MoneyDisplay } from '../../../components/ui';
import type { CheckoutResponse } from '../../payments/types';
import { fetchTicket, fetchGiftTicket } from '../services/ticketApi';
import { writeTicketAndPrint } from '../utils/printTicket';

interface SaleResultModalProps {
  open: boolean;
  sale: CheckoutResponse | { sale: { id: number; order_id: number; order_number: string; status: string; totals: { subtotal: string; coupon_total: string; discount_total: string; total: string }; payment?: Record<string, unknown>; created_at: string } } | null;
  canRefund: boolean;
  onRefund: () => void;
  onClose: () => void;
}

function SaleResultModal({ open, sale, canRefund, onRefund, onClose }: SaleResultModalProps) {
  const [isPrinting, setIsPrinting] = useState(false);
  const [printError, setPrintError] = useState<string | null>(null);
  const [isGiftPrinting, setIsGiftPrinting] = useState(false);
  const [giftPrintError, setGiftPrintError] = useState<string | null>(null);

  const doPrint = useCallback(async (useGift: boolean) => {
    if (!sale) return;

    const win = window.open('', '_blank', 'width=420,height=760');

    if (!win) {
      const msg = 'No se pudo abrir la ventana de impresión. Permite ventanas emergentes para este sitio.';
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
        ? await fetchGiftTicket(sale.sale.id)
        : await fetchTicket(sale.sale.id);
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
  }, [sale]);

  const handlePrint = useCallback(() => doPrint(false), [doPrint]);
  const handleGiftPrint = useCallback(() => doPrint(true), [doPrint]);

  if (!sale) {
    return null;
  }

  const total = parseFloat(sale.sale.totals.total);
  const payment = sale.sale.payment as Record<string, unknown> | undefined;
  const paymentMethod = payment ? String(payment.method ?? '') : '';

  const statusLabel =
    sale.sale.status === 'pending' ? 'Pendiente' : sale.sale.status;
  const statusMap: Record<string, string> = {
    pending: 'Pendiente',
    processing: 'Procesando',
    completed: 'Completado',
    'on-hold': 'En espera',
    cancelled: 'Cancelado',
    refunded: 'Reembolsado',
    failed: 'Fallido',
  };
  const displayStatus = statusMap[sale.sale.status] || statusLabel;
void displayStatus;
  const saleItems =
    'items' in sale.sale && Array.isArray(sale.sale.items) ? sale.sale.items : [];


  const paymentLines = payment?.['payment_lines'] as Array<Record<string, unknown>> | undefined;
  const hasPaymentLines = Array.isArray(paymentLines) && paymentLines.length > 0;

  return (
    <Modal open={open} onClose={onClose} title="Venta realizada">
      <div className="mx-sale-result-modal">
        <div className="mx-sale-result-modal__detail">
          <span className="mx-sale-result-modal__label">Pedido</span>
          <span className="mx-sale-result-modal__value">
            #{sale.sale.order_number}
          </span>
        </div>

        {hasPaymentLines ? (
          <>
            {paymentLines.map((line: Record<string, unknown>, idx: number) => {
              const lineMethod = String(line.method_name ?? line.method ?? '');
              const lineAmount = parseFloat(String(line.amount ?? '0'));
              const lineRef = line.reference ? String(line.reference) : null;
              return (
                <div key={idx} className="mx-sale-result-modal__detail">
                  <span className="mx-sale-result-modal__label">{lineMethod}</span>
                  <MoneyDisplay amount={lineAmount} size="md" />
                  {lineRef && (
                    <span className="mx-sale-result-modal__value" style={{ marginLeft: 8, fontSize: '0.85em' }}>
                      Ref: {lineRef}
                    </span>
                  )}
                </div>
              );
            })}
            {payment?.['change'] && parseFloat(String(payment['change'])) > 0 && (
              <div className="mx-sale-result-modal__detail">
                <span className="mx-sale-result-modal__label">Cambio</span>
                <MoneyDisplay amount={parseFloat(String(payment['change']))} size="md" />
              </div>
            )}
          </>
        ) : payment && (
          <>
            <div className="mx-sale-result-modal__detail">
              <span className="mx-sale-result-modal__label">Método de pago</span>
              <span className="mx-sale-result-modal__value">
                {paymentMethod === 'cash' ? 'Efectivo' : paymentMethod === 'card' ? 'Tarjeta' : paymentMethod}
              </span>
            </div>

            {paymentMethod === 'cash' && (
              <>
                <div className="mx-sale-result-modal__detail">
                  <span className="mx-sale-result-modal__label">Recibido</span>
                  <MoneyDisplay amount={parseFloat(String(payment.amount_received ?? '0'))} size="md" />
                </div>
                <div className="mx-sale-result-modal__detail">
                  <span className="mx-sale-result-modal__label">Cambio</span>
                  <MoneyDisplay amount={parseFloat(String(payment.change ?? '0'))} size="md" />
                </div>
              </>
            )}

            {paymentMethod === 'card' && payment.card_reference && (
              <div className="mx-sale-result-modal__detail">
                <span className="mx-sale-result-modal__label">Referencia</span>
                <span className="mx-sale-result-modal__value">
                  {String(payment.card_reference)}
                </span>
              </div>
            )}
          </>
        )}

        {saleItems.length > 0 && (
          <div className="mx-sale-result-modal__items">
            <table className="mx-sale-result-modal__items-table">
              <thead>
                <tr>
                  <th>Cant.</th>
                  <th>Producto</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                {saleItems.map((item: any, i: number) => (
                  <tr key={i}>
                    <td>{item.quantity}</td>
                    <td className="mx-sale-result-modal__items-name">{item.name}</td>
                    <td>${item.total}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {canRefund &&
          (sale.sale.status === 'completed' ||
            sale.sale.status === 'processing') && (
            <Button
              variant="secondary"
              size="md"
              onClick={onRefund}
              className="mx-sale-result-modal__refund"
            >
              Devolver
            </Button>
          )}

        <div className="mx-sale-result-modal__detail mx-sale-result-modal__detail--total">
          <span className="mx-sale-result-modal__label">Total</span>
          <MoneyDisplay amount={total} size="lg" emphasized />
        </div>

        {printError && (
          <div className="mx-sale-result-modal__error" role="alert">
            {printError}
          </div>
        )}

        {giftPrintError && (
          <div className="mx-sale-result-modal__error" role="alert">
            {giftPrintError}
          </div>
        )}

        <Button
          variant="secondary"
          size="md"
          onClick={handlePrint}
          loading={isPrinting}
          disabled={isPrinting}
          className="mx-sale-result-modal__print"
        >
          {isPrinting ? 'Preparando ticket…' : 'Imprimir ticket'}
        </Button>

        <Button
          variant="secondary"
          size="md"
          onClick={handleGiftPrint}
          loading={isGiftPrinting}
          disabled={isGiftPrinting}
          className="mx-sale-result-modal__gift"
        >
          {isGiftPrinting ? 'Preparando ticket…' : 'Ticket de regalo'}
        </Button>

        <Button
          variant="primary"
          size="md"
          onClick={onClose}
          disabled={isPrinting || isGiftPrinting}
          className="mx-sale-result-modal__close"
        >
          Cerrar
        </Button>
      </div>
    </Modal>
  );
}

export default SaleResultModal;
