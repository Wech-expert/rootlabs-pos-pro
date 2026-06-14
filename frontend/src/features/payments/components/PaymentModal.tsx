import { useState, useCallback, useEffect } from 'react';
import { Modal, Button, MoneyDisplay } from '../../../components/ui';
import PaymentMethodSelector from './PaymentMethodSelector';
import PaymentCalculator from './PaymentCalculator';
import { completeCheckout, FetchWithTimeoutError } from '../services/paymentApi';
import { getActivePaymentMethods } from '../services/paymentMethodsApi';
import { emitCashMovementsChanged } from '../../cash-movements/events';
import type { Customer } from '../../customers/types';
import type { DiscountInput, AppliedCoupon } from '../../discounts/types';
import type { CartItem } from '../../register/types';
import type { PosPaymentMethod, PaymentLine, CheckoutResponse } from '../types';

interface PaymentModalProps {
  open: boolean;
  cartItems: CartItem[];
  selectedCustomer: Customer | null;
  discount: DiscountInput | null;
  appliedCoupon: AppliedCoupon | null;
  parkedCartId: number | null;
  clientRequestId: string;
  subtotal: number;
  couponTotal: number;
  discountTotal: number;
  total: number;
  checkoutLoading: boolean;
  onSetCheckoutLoading: (loading: boolean) => void;
  onCheckoutComplete: (result: CheckoutResponse) => void;
  onClose: () => void;
}

interface DraftLine {
  method: string;
  amount: string;
  reference: string;
}

function parseAmount(value: string): number {
  const normalized = value.replace(',', '.').replace(/[^\d.]/g, '');
  const parsed = parseFloat(normalized);

  return Number.isFinite(parsed) ? parsed : 0;
}

function formatAmount(amount: number): string {
  return Number.isInteger(amount) ? String(amount) : amount.toFixed(2);
}

function isCashLikeMethod(method: PosPaymentMethod | undefined): boolean {
  return Boolean(method && (method.payment_type === 'cash' || method.affects_cash_register));
}

function getMethodName(method: PosPaymentMethod | undefined): string {
  return method?.name || 'El método';
}

function sumDraftLines(draftLines: DraftLine[], excludeIndex: number | null = null): number {
  return draftLines.reduce((sum, line, index) => {
    if (excludeIndex !== null && index === excludeIndex) {
      return sum;
    }

    return sum + parseAmount(line.amount);
  }, 0);
}

function getValidationError(
  draftLines: DraftLine[],
  methods: PosPaymentMethod[],
  total: number,
): string | null {
  if (draftLines.length === 0) {
    return 'Agrega al menos una forma de pago.';
  }

  let totalPaid = 0;
  let hasCashLike = false;

  for (const line of draftLines) {
    const amount = parseAmount(line.amount);
    if (amount <= 0) {
      return 'Todos los montos deben ser mayores a 0.';
    }

    const method = methods.find((m) => m.slug === line.method);
    if (!method) {
      return `Método "${line.method}" no disponible.`;
    }

    const isCashLike = isCashLikeMethod(method);

    if (isCashLike) {
      hasCashLike = true;
    } else {
      const pendingBeforeLine = Math.max(0, total - totalPaid);
      if (amount > pendingBeforeLine + 0.005) {
        return `${getMethodName(method)} no puede exceder el saldo pendiente.`;
      }
    }

    totalPaid += amount;
  }

  if (totalPaid < total - 0.005) {
    return `Faltan $${(total - totalPaid).toFixed(2)} para cubrir el total.`;
  }

  if (!hasCashLike && totalPaid > total + 0.005) {
    return `Solo efectivo puede generar cambio. Retira el excedente de $${(totalPaid - total).toFixed(2)}.`;
  }

  return null;
}

function PaymentModal({
  open,
  cartItems,
  selectedCustomer,
  discount,
  appliedCoupon,
  parkedCartId,
  clientRequestId,
  total,
  checkoutLoading,
  onSetCheckoutLoading,
  onCheckoutComplete,
  onClose,
}: PaymentModalProps) {
  const [methods, setMethods] = useState<PosPaymentMethod[]>([]);
  const [draftLines, setDraftLines] = useState<DraftLine[]>([]);
  const [selectedMethodSlug, setSelectedMethodSlug] = useState<string | null>(null);
  const [calculatorAmount, setCalculatorAmount] = useState('');
  const [calculatorReference, setCalculatorReference] = useState('');
  const [editingLineIndex, setEditingLineIndex] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const availableMethods = methods.filter((m) => m.slug !== 'mixed');
  const selectedMethod = availableMethods.find((m) => m.slug === selectedMethodSlug);

  useEffect(() => {
    if (open) {
      getActivePaymentMethods()
        .then((items) => {
          const filteredItems = items.filter((m) => m.slug !== 'mixed');
          setMethods(items);
          setSelectedMethodSlug(filteredItems.find((m) => m.slug === 'cash')?.slug ?? filteredItems[0]?.slug ?? null);
        })
        .catch(() => {
          const fallbackMethods: PosPaymentMethod[] = [
            { id: 1, name: 'Efectivo', slug: 'cash', payment_type: 'cash', affects_cash_register: true, allow_reference: false, card_fee_enabled: false, card_fee_type: null, card_fee_value: null, wc_gateway_id: null, is_active: true, sort_order: 1 },
            { id: 2, name: 'Tarjeta', slug: 'card', payment_type: 'card', affects_cash_register: false, allow_reference: true, card_fee_enabled: false, card_fee_type: null, card_fee_value: null, wc_gateway_id: null, is_active: true, sort_order: 2 },
          ];
          setMethods(fallbackMethods);
          setSelectedMethodSlug('cash');
        });
      setDraftLines([]);
      setCalculatorAmount('');
      setCalculatorReference('');
      setEditingLineIndex(null);
      setError(null);
    }
  }, [open]);

  const resetForm = useCallback(() => {
    setDraftLines([]);
    setCalculatorAmount('');
    setCalculatorReference('');
    setEditingLineIndex(null);
    setError(null);
  }, []);

  const handleClose = useCallback(() => {
    if (checkoutLoading) return;

    const hasCapturedPayments = draftLines.some((line) => parseAmount(line.amount) > 0);

    if (hasCapturedPayments) {
      const confirmed = window.confirm(
        'Tienes pagos capturados sin confirmar. ¿Cerrar de todas formas?',
      );
      if (!confirmed) return;
    }

    resetForm();
    onClose();
  }, [checkoutLoading, draftLines, resetForm, onClose]);

  const handleMethodSelect = useCallback((slug: string) => {
    setSelectedMethodSlug(slug);
    setCalculatorAmount('');
    setCalculatorReference('');
    setEditingLineIndex(null);
    setError(null);
  }, []);

  const handleStartNewLine = useCallback(() => {
    setCalculatorAmount('');
    setCalculatorReference('');
    setEditingLineIndex(null);
    setError(null);
  }, []);
void handleStartNewLine;

  const handleRemoveLine = useCallback((index: number) => {
    setDraftLines((prev) => prev.filter((_, i) => i !== index));
    if (editingLineIndex === index) {
      setCalculatorAmount('');
      setCalculatorReference('');
      setEditingLineIndex(null);
    } else if (editingLineIndex !== null && editingLineIndex > index) {
      setEditingLineIndex(editingLineIndex - 1);
    }
    setError(null);
  }, [editingLineIndex]);

  const handleEditLine = useCallback((index: number) => {
    const line = draftLines[index];
    if (!line) return;

    setSelectedMethodSlug(line.method);
    setCalculatorAmount(line.amount);
    setCalculatorReference(line.reference);
    setEditingLineIndex(index);
    setError(null);
  }, [draftLines]);

  const applyLine = useCallback((methodSlug: string, amountValue: string, referenceValue: string, editIndex: number | null) => {
    const amount = parseAmount(amountValue);
    const method = availableMethods.find((m) => m.slug === methodSlug);

    if (!amountValue.trim()) {
      setError('Captura un importe antes de agregarlo.');
      return;
    }

    if (amount <= 0) {
      setError('El monto debe ser mayor a 0.');
      return;
    }

    if (!method) {
      setError('Selecciona un método de pago disponible.');
      return;
    }

    if (method.slug === 'mixed') {
      setError('Mixto no puede usarse como línea de pago.');
      return;
    }

    const paidWithoutCurrentLine = sumDraftLines(draftLines, editIndex);
    const pendingWithoutCurrentLine = Math.max(0, total - paidWithoutCurrentLine);

    if (!isCashLikeMethod(method) && amount > pendingWithoutCurrentLine + 0.005) {
      setError(`${getMethodName(method)} no puede exceder el saldo pendiente.`);
      return;
    }

    const nextLine = {
      method: method.slug,
      amount: formatAmount(amount),
      reference: method.allow_reference ? referenceValue.trim() : '',
    };

    setDraftLines((prev) => {
      if (editIndex !== null && prev[editIndex]) {
        return prev.map((line, index) => (index === editIndex ? nextLine : line));
      }

      if (prev.length >= 20) {
        return prev;
      }

      return [...prev, nextLine];
    });

    setCalculatorAmount('');
    setCalculatorReference('');
    setEditingLineIndex(null);
    setError(null);
  }, [availableMethods, draftLines, total]);

  const handleApplyCalculatorAmount = useCallback(() => {
    if (!selectedMethodSlug) {
      setError('Selecciona un método de pago.');
      return;
    }

    applyLine(selectedMethodSlug, calculatorAmount, calculatorReference, editingLineIndex);
  }, [applyLine, calculatorAmount, calculatorReference, editingLineIndex, selectedMethodSlug]);

  const handleQuickCash = useCallback(() => {
    const cashMethod = availableMethods.find((m) => m.slug === 'cash' || m.payment_type === 'cash');
    if (!cashMethod) {
      setError('Efectivo no está disponible.');
      return;
    }

    const pendingAmount = Math.max(total - sumDraftLines(draftLines), 0);
    applyLine(cashMethod.slug, formatAmount(pendingAmount || total), '', null);
    setSelectedMethodSlug(cashMethod.slug);
  }, [applyLine, availableMethods, draftLines, total]);

  const handleQuickCard = useCallback(() => {
    const cardMethod = availableMethods.find((m) => m.slug === 'card' || m.payment_type === 'card');
    if (!cardMethod) {
      setError('Tarjeta no está disponible.');
      return;
    }

    const pendingAmount = Math.max(total - sumDraftLines(draftLines), 0);
    applyLine(cardMethod.slug, formatAmount(pendingAmount || total), '', null);
    setSelectedMethodSlug(cardMethod.slug);
  }, [applyLine, availableMethods, draftLines, total]);

  const lineSums = sumDraftLines(draftLines);
  const pending = Math.max(0, total - lineSums);
  const change = draftLines.some((line) => isCashLikeMethod(methods.find((m) => m.slug === line.method))) && lineSums > total
    ? Math.max(0, lineSums - total)
    : 0;
  const validationError = getValidationError(draftLines, methods, total);
  const canPay = !checkoutLoading && draftLines.length > 0 && validationError === null;
  const quickAmountBase = editingLineIndex !== null
    ? Math.max(total - sumDraftLines(draftLines, editingLineIndex), 0) || total
    : pending || total;

  const handleConfirmCheckout = useCallback(async () => {
    if (!canPay) return;

    const paymentLines: PaymentLine[] = draftLines.map((l) => ({
      method: l.method,
      amount: parseAmount(l.amount),
      reference: l.reference.trim() || null,
    }));

    onSetCheckoutLoading(true);
    setError(null);

    try {
      const payload = {
        items: cartItems.map((i) => ({
          product_id: i.product_id,
          variation_id: i.variation_id,
          quantity: i.quantity,
          manual_discount: i.manual_discount ?? null,
        })),
        customer_id: selectedCustomer?.id ?? null,
        discount: discount ?? null,
        coupon_code: appliedCoupon?.code ?? null,
        parked_cart_id: parkedCartId,
        payment_lines: paymentLines,
        client_request_id: clientRequestId,
      };

      const result = await completeCheckout(payload);

      emitCashMovementsChanged();
      resetForm();
      onCheckoutComplete(result);
    } catch (err) {
      let errorMessage: string;

      if (err instanceof FetchWithTimeoutError) {
        if (err.type === 'timeout') {
          errorMessage = 'El servidor no respondió a tiempo. El carrito se conserva. Intente de nuevo.';
          window.dispatchEvent(new CustomEvent('mx-pos:connection-degraded'));
        } else if (err.type === 'network') {
          errorMessage = 'Sin conexión. El carrito se conserva. Intente de nuevo cuando se restaure la conexión.';
          window.dispatchEvent(new CustomEvent('mx-pos:connection-degraded'));
        } else if (err.type === 'parse') {
          errorMessage = 'No se pudo procesar la respuesta del servidor. El carrito se conserva para reintentar.';
        } else {
          errorMessage = err.message;
        }
      } else {
        errorMessage = err instanceof Error ? err.message : 'No se pudo completar el cobro';
      }

      setError(errorMessage);
      onSetCheckoutLoading(false);
    }
  }, [
    canPay,
    draftLines,
    cartItems,
    selectedCustomer,
    discount,
    appliedCoupon,
    parkedCartId,
    clientRequestId,
    onSetCheckoutLoading,
    resetForm,
    onCheckoutComplete,
  ]);

  if (!open) return null;

  return (
    <Modal open={open} onClose={handleClose} title="Cobro" panelClassName="mx-payment-modal-panel">
      <div className="mx-payment-modal">
        <aside className="mx-payment-summary-panel" aria-label="Resumen del cobro">
          <div className="mx-payment-summary-panel__item mx-payment-summary-panel__item--total">
            <span className="mx-payment-summary-panel__label">Total a pagar</span>
            <MoneyDisplay amount={total} size="lg" emphasized />
          </div>
          <div className="mx-payment-summary-panel__item">
            <span className="mx-payment-summary-panel__label">Capturado</span>
            <MoneyDisplay amount={lineSums} size="md" />
          </div>
          <div className="mx-payment-summary-panel__item">
            <span className="mx-payment-summary-panel__label">Pendiente</span>
            <MoneyDisplay amount={pending} size="md" emphasized={pending > 0} />
          </div>
          <div className="mx-payment-summary-panel__item">
            <span className="mx-payment-summary-panel__label">Cambio</span>
            <MoneyDisplay amount={change} size="md" emphasized={change > 0} />
          </div>
        </aside>

        <div className="mx-payment-main">
          <div className="mx-payment-main__quick-actions">
            <Button variant="secondary" size="sm" onClick={handleQuickCash} disabled={checkoutLoading}>
              Efectivo exacto
            </Button>
            <Button variant="secondary" size="sm" onClick={handleQuickCard} disabled={checkoutLoading}>
              Tarjeta exacta
            </Button>
          </div>

          <div className="mx-payment-main__left">
            <PaymentMethodSelector
              methods={availableMethods}
              selected={selectedMethodSlug}
              onSelect={handleMethodSelect}
              disabled={checkoutLoading}
            />

            <div className="mx-payment-lines" aria-label="Líneas de pago">
              <div className="mx-payment-lines__header">
                <span>Líneas de pago</span>
                <span>{draftLines.length}</span>
              </div>

              <div className="mx-payment-lines__list">
                {draftLines.length === 0 && (
                  <div className="mx-payment-main__empty">
                    Selecciona un método rápido o captura un importe.
                  </div>
                )}

                {draftLines.map((line, index) => {
                  const lineMethod = availableMethods.find((m) => m.slug === line.method);

                  return (
                    <div
                      key={`${line.method}-${index}`}
                      className={`mx-payment-lines__line${editingLineIndex === index ? ' mx-payment-lines__line--editing' : ''}`}
                    >
                      <div className="mx-payment-lines__line-main">
                        <span className="mx-payment-lines__method">{getMethodName(lineMethod)}</span>
                        {line.reference && (
                          <span className="mx-payment-lines__reference">{line.reference}</span>
                        )}
                      </div>
                      <MoneyDisplay amount={parseAmount(line.amount)} size="sm" emphasized />
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleEditLine(index)}
                        disabled={checkoutLoading}
                      >
                        Editar
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleRemoveLine(index)}
                        disabled={checkoutLoading}
                      >
                        Eliminar
                      </Button>
                    </div>
                  );
                })}

                {editingLineIndex !== null && (
                  <div className="mx-payment-lines__editing-note">
                    Editando línea {editingLineIndex + 1}. Aplica el importe para guardar el cambio.
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        setCalculatorAmount('');
                        setCalculatorReference('');
                        setEditingLineIndex(null);
                      }}
                      disabled={checkoutLoading}
                    >
                      Cancelar edición
                    </Button>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="mx-payment-main__capture">
            <PaymentCalculator
              label={selectedMethod ? `${getMethodName(selectedMethod)} recibido` : 'Importe actual'}
              value={calculatorAmount}
              quickAmountBase={quickAmountBase}
              onChange={(value) => {
                setCalculatorAmount(value);
                setError(null);
              }}
              onApplyAmount={handleApplyCalculatorAmount}
              onQuickApply={(value) => {
                setCalculatorAmount(value);
                setError(null);
                applyLine(selectedMethodSlug!, value, calculatorReference, editingLineIndex);
              }}
              disabled={checkoutLoading || availableMethods.length === 0}
            />

            {selectedMethod?.allow_reference && (
              <div className="mx-payment-reference">
                <label className="mx-payment-reference__label" htmlFor="mx-payment-reference">
                  Referencia opcional
                </label>
                <input
                  id="mx-payment-reference"
                  type="text"
                  className="mx-payment-reference__field"
                  value={calculatorReference}
                  onChange={(event) => {
                    setCalculatorReference(event.target.value);
                    setError(null);
                  }}
                  placeholder="Referencia"
                  maxLength={100}
                  disabled={checkoutLoading}
                />
              </div>
            )}
          </div>

          {validationError && !error && (
            <p className="mx-mixed-payment-form__error">{validationError}</p>
          )}

          {error && (
            <div className="mx-payment-modal__error" role="alert">
              {error}
            </div>
          )}

          <div className="mx-payment-modal__actions">
            <Button
              variant="secondary"
              size="lg"
              onClick={handleClose}
              disabled={checkoutLoading}
            >
              Volver
            </Button>
            <Button
              variant="primary"
              size="lg"
              onClick={handleConfirmCheckout}
              disabled={!canPay}
              loading={checkoutLoading}
              className="mx-payment-modal__submit"
            >
              {checkoutLoading ? 'Creando orden y procesando pago…' : `Cobrar $${lineSums > 0 ? lineSums.toFixed(2) : total.toFixed(2)}`}
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  );
}

export default PaymentModal;
