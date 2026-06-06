import { useState, useEffect, useCallback } from 'react';
import { Modal, Button, Input, MoneyDisplay } from '../../../components/ui';
import { fetchRefundOptions, refundSale } from '../services/refundApi';
import { emitCashMovementsChanged } from '../../cash-movements/events';
import type {
  RefundOptionsResponse,
  RefundableItem,
  RefundResponse,
} from '../types';

interface RefundModalProps {
  open: boolean;
  saleId: number;
  onComplete: (result: RefundResponse) => void;
  onClose: () => void;
}

const METHOD_LABELS: Record<string, string> = {
  cash: 'Efectivo',
  card: 'Tarjeta',
};

function RefundModal({ open, saleId, onComplete, onClose }: RefundModalProps) {
  const [options, setOptions] = useState<RefundOptionsResponse | null>(null);
  const [loadingOptions, setLoadingOptions] = useState(false);
  const [optionsError, setOptionsError] = useState<string | null>(null);

  const [quantities, setQuantities] = useState<Record<number, string>>({});
  const [refundMethod, setRefundMethod] = useState<'cash' | 'card' | null>(null);
  const [reason, setReason] = useState('');
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [completedResult, setCompletedResult] = useState<RefundResponse | null>(null);

  useEffect(() => {
    if (!open || saleId <= 0) return;

    setOptions(null);
    setOptionsError(null);
    setQuantities({});
    setRefundMethod(null);
    setReason('');
    setError(null);
    setCompletedResult(null);
    setLoadingOptions(true);

    fetchRefundOptions(saleId)
      .then((data) => {
        setOptions(data);
        setRefundMethod(
          data.sale.payment_method === 'cash' || data.sale.payment_method === 'card'
            ? data.sale.payment_method
            : null,
        );
      })
      .catch((err) => {
        setOptionsError(
          err instanceof Error ? err.message : 'No se pudo cargar la informacion de devolucion',
        );
      })
      .finally(() => {
        setLoadingOptions(false);
      });
  }, [open, saleId]);

  const handleQuantityChange = useCallback(
    (orderItemId: number, value: string) => {
      setQuantities((prev) => ({
        ...prev,
        [orderItemId]: value,
      }));
      setError(null);
    },
    [],
  );

  const handleClose = useCallback(() => {
    if (!processing) {
      onClose();
    }
  }, [processing, onClose]);

  const items = options?.items ?? [];
  const remainingTotal = options
    ? parseFloat(options.sale.remaining_refund_total)
    : 0;

  const selectedItems = items
    .map((item: RefundableItem) => {
      const qtyStr = quantities[item.order_item_id] ?? '0';
      const qty = parseInt(qtyStr, 10) || 0;
      if (qty <= 0) return null;

      const clamped = Math.min(qty, item.refundable_quantity);
      const unitTotal = parseFloat(item.unit_total);
      const lineTotal = unitTotal * clamped;

      return {
        order_item_id: item.order_item_id,
        quantity: clamped,
        line_total: lineTotal,
        name: item.name,
      };
    })
    .filter(Boolean) as Array<{
    order_item_id: number;
    quantity: number;
    line_total: number;
    name: string;
  }>;

  const computedTotal = selectedItems.reduce((sum, si) => sum + si.line_total, 0);
  const allZero = items.length > 0 && selectedItems.length === 0 && !optionsError;

  const hasExcess = selectedItems.some((si) => {
    const item = items.find((i) => i.order_item_id === si.order_item_id);
    if (!item) return false;
    return si.quantity > item.refundable_quantity;
  });

  const canSubmit =
    !processing &&
    !hasExcess &&
    !allZero &&
    refundMethod !== null &&
    selectedItems.length > 0;

  const handleSubmit = useCallback(async () => {
    if (!canSubmit || !options) return;

    setProcessing(true);
    setError(null);

    try {
      const result = await refundSale(saleId, {
        items: selectedItems.map((si) => ({
          order_item_id: si.order_item_id,
          quantity: si.quantity,
        })),
        refund_method: refundMethod!,
        reason,
        client_request_id: crypto.randomUUID(),
      });

      setCompletedResult(result);

      if (refundMethod === 'cash') {
        emitCashMovementsChanged();
      }

      onComplete(result);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : 'No se pudo procesar la devolucion',
      );
    } finally {
      setProcessing(false);
    }
  }, [canSubmit, options, saleId, selectedItems, refundMethod, reason, onComplete]);

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title="Devolucion"
      panelClassName="mx-refund-modal-panel"
    >
      <div className="mx-refund-modal">
        {loadingOptions && (
          <div className="mx-refund-modal__loading">
            Cargando informacion de devolucion...
          </div>
        )}

        {optionsError && (
          <div className="mx-refund-modal__error" role="alert">
            {optionsError}
          </div>
        )}

        {options && !loadingOptions && (
          <>
            {completedResult && (
              <div className="mx-refund-modal__success">
                <div className="mx-refund-modal__success-mark" aria-hidden="true">
                  ✓
                </div>
                <div className="mx-refund-modal__success-copy">
                  <span className="mx-refund-modal__label">Devolucion registrada</span>
                  <MoneyDisplay
                    amount={parseFloat(completedResult.refund.refund_amount)}
                    size="lg"
                    emphasized
                  />
                  <p>
                    Pedido #{options.sale.order_number} ·{' '}
                    {METHOD_LABELS[completedResult.refund.refund_method || ''] ||
                      completedResult.refund.refund_method ||
                      'Metodo no especificado'}
                  </p>
                </div>

                {completedResult.refund.refund_method === 'cash' && (
                  <div className="mx-refund-modal__cash-result">
                    Devolución registrada. Si entregaste efectivo al cliente,
                    registra una salida manual en Movimientos de caja con el
                    comentario &ldquo;Devolución pedido #
                    {options.sale.order_number}&rdquo;.
                  </div>
                )}

                <div className="mx-refund-modal__summary">
                  <span className="mx-refund-modal__summary-label">
                    Disponible restante
                  </span>
                  <MoneyDisplay
                    amount={parseFloat(completedResult.sale.remaining_refund_total)}
                    size="md"
                    emphasized
                  />
                </div>

                <div className="mx-refund-modal__actions">
                  <Button
                    variant="primary"
                    size="md"
                    onClick={handleClose}
                    disabled={processing}
                  >
                    Cerrar
                  </Button>
                </div>
              </div>
            )}

            {!completedResult && (
              <>
            <div className="mx-refund-modal__sale-info">
              <div className="mx-refund-modal__sale-detail">
                <span className="mx-refund-modal__label">Pedido</span>
                <span className="mx-refund-modal__value">
                  #{options.sale.order_number}
                </span>
              </div>
              <div className="mx-refund-modal__sale-detail">
                <span className="mx-refund-modal__label">Pago original</span>
                <span className="mx-refund-modal__value">
                  {METHOD_LABELS[options.sale.payment_method] || options.sale.payment_method || '—'}
                </span>
              </div>
              <div className="mx-refund-modal__sale-detail">
                <span className="mx-refund-modal__label">Total pagado</span>
                <MoneyDisplay
                  amount={parseFloat(options.sale.total)}
                  size="md"
                  emphasized
                />
              </div>
              <div className="mx-refund-modal__sale-detail">
                <span className="mx-refund-modal__label">Disponible para devolver</span>
                <MoneyDisplay
                  amount={remainingTotal}
                  size="md"
                  emphasized
                />
              </div>
            </div>

            {items.length === 0 && (
              <div className="mx-refund-modal__empty">
                No hay productos disponibles para devolver.
              </div>
            )}

            {items.length > 0 && (
              <>
                <div className="mx-refund-modal__items-header">
                  <span className="mx-refund-modal__col-name">Producto</span>
                  <span className="mx-refund-modal__col-qty">Disp.</span>
                  <span className="mx-refund-modal__col-refund">Devolver</span>
                </div>

                <div className="mx-refund-modal__items">
                  {items.map((item) => {
                    const qtyStr = quantities[item.order_item_id] ?? '0';
                    const qtyNum = parseInt(qtyStr, 10) || 0;
                    const exceeds =
                      qtyNum > item.refundable_quantity;

                    return (
                      <div
                        key={item.order_item_id}
                        className="mx-refund-modal__item"
                      >
                        <div className="mx-refund-modal__item-name">
                          <span className="mx-refund-modal__item-title">
                            {item.name}
                          </span>
                          <span className="mx-refund-modal__item-price">
                            <MoneyDisplay
                              amount={parseFloat(item.unit_total)}
                              size="sm"
                            />{' '}
                            c/u
                          </span>
                        </div>
                        <div className="mx-refund-modal__item-available">
                          {item.refundable_quantity}
                        </div>
                        <div className="mx-refund-modal__item-input">
                          <Input
                            id={`refund-qty-${item.order_item_id}`}
                            type="number"
                            value={qtyStr}
                            placeholder="0"
                            disabled={processing}
                            errorText={
                              exceeds
                                ? `Maximo ${item.refundable_quantity}`
                                : undefined
                            }
                            onChange={(e) =>
                              handleQuantityChange(
                                item.order_item_id,
                                e.target.value,
                              )
                            }
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>

                <div className="mx-refund-modal__method">
                  <span className="mx-refund-modal__method-label">
                    Metodo de devolucion
                  </span>
                  <div className="mx-refund-modal__method-buttons">
                    <button
                      type="button"
                      className={
                        'mx-refund-modal__method-btn' +
                        (refundMethod === 'cash'
                          ? ' mx-refund-modal__method-btn--active'
                          : '')
                      }
                      disabled={processing}
                      onClick={() => setRefundMethod('cash')}
                    >
                      Efectivo
                    </button>
                    <button
                      type="button"
                      className={
                        'mx-refund-modal__method-btn' +
                        (refundMethod === 'card'
                          ? ' mx-refund-modal__method-btn--active'
                          : '')
                      }
                      disabled={processing}
                      onClick={() => setRefundMethod('card')}
                    >
                      Tarjeta
                    </button>
                  </div>
                </div>

                <div className="mx-refund-modal__reason">
                  <Input
                    id="refund-reason"
                    label="Motivo"
                    value={reason}
                    placeholder="Opcional"
                    disabled={processing}
                    onChange={(e) => setReason(e.target.value)}
                  />
                </div>

                <div className="mx-refund-modal__summary">
                  <span className="mx-refund-modal__summary-label">
                    Total a devolver
                  </span>
                  <MoneyDisplay
                    amount={computedTotal}
                    size="lg"
                    emphasized
                  />
                </div>

                {allZero && (
                  <div className="mx-refund-modal__hint">
                    Selecciona al menos un producto para continuar.
                  </div>
                )}

                {hasExcess && (
                  <div className="mx-refund-modal__error" role="alert">
                    Algunas cantidades exceden el maximo disponible.
                  </div>
                )}

                {error && (
                  <div className="mx-refund-modal__error" role="alert">
                    {error}
                  </div>
                )}

                <div className="mx-refund-modal__actions">
                  <Button
                    variant="secondary"
                    size="md"
                    onClick={handleClose}
                    disabled={processing}
                  >
                    Volver
                  </Button>
                  <Button
                    variant="primary"
                    size="md"
                    onClick={handleSubmit}
                    disabled={!canSubmit}
                    loading={processing}
                  >
                    {processing ? 'Procesando...' : 'Procesar devolucion'}
                  </Button>
                </div>
              </>
            )}
              </>
            )}
          </>
        )}

        {!loadingOptions && !options && !optionsError && (
          <div className="mx-refund-modal__loading">
            Sin datos disponibles.
          </div>
        )}
      </div>
    </Modal>
  );
}

export default RefundModal;
