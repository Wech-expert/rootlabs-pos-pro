import { useCallback, useState } from 'react';
import type { FormEvent } from 'react';
import { MoneyDisplay, Button, Modal } from '../../../components/ui';
import type { CartItem } from '../types';
import type { DiscountInput, DiscountType } from '../../discounts/types';

interface CartItemRowProps {
  item: CartItem;
  onUpdateQuantity: (key: string, qty: number) => void;
  onRemoveItem: (key: string) => void;
  canApplyDiscount: boolean;
  onApplyItemDiscount: (key: string, discount: DiscountInput) => void;
  onClearItemDiscount: (key: string) => void;
}

interface SplitCartItemNameResult {
  productName: string;
  variationLabel: string | null;
}

function splitCartItemName(name: string | null | undefined): SplitCartItemNameResult {
  const cleanName = typeof name === 'string' ? name.trim() : '';

  const match = cleanName.match(
    /^(.*)\s-\s((?:Talla|Color|Medida|Size|Modelo|Variante):\s?.+)$/i,
  );

  if (!match) {
    return {
      productName: cleanName,
      variationLabel: null,
    };
  }

  return {
    productName: match[1].trim(),
    variationLabel: match[2].trim(),
  };
}

function calculateLineDiscountAmount(discount: DiscountInput | null, subtotal: number): number {
  if (!discount || subtotal <= 0) {
    return 0;
  }

  const value = parseFloat(discount.value);

  if (!Number.isFinite(value) || value <= 0) {
    return 0;
  }

  if (discount.type === 'percentage') {
    return Math.min(subtotal, subtotal * (value / 100));
  }

  return Math.min(subtotal, value);
}

function getDiscountLabel(discount: DiscountInput): string {
  const value = parseFloat(discount.value);

  return discount.type === 'percentage'
    ? `${Number.isFinite(value) ? value : 0}%`
    : `$${Number.isFinite(value) ? value.toFixed(2) : '0.00'} fijo`;
}

function CartItemRow({
  item,
  onUpdateQuantity,
  onRemoveItem,
  canApplyDiscount,
  onApplyItemDiscount,
  onClearItemDiscount,
}: CartItemRowProps) {
  const lineSubtotal = item.unit_price * item.quantity;
  const lineDiscount = calculateLineDiscountAmount(item.manual_discount ?? null, lineSubtotal);
  const lineTotal = Math.max(0, lineSubtotal - lineDiscount);
  const cartItemName = splitCartItemName(item.name);

  const [showDiscountForm, setShowDiscountForm] = useState(false);
  const [discountType, setDiscountType] = useState<DiscountType>('percentage');
  const [discountValue, setDiscountValue] = useState('');
  const [discountReason, setDiscountReason] = useState('');
  const [discountError, setDiscountError] = useState<string | null>(null);

  const openDiscountForm = useCallback(() => {
    const currentDiscount = item.manual_discount ?? null;

    setDiscountType(currentDiscount?.type ?? 'percentage');
    setDiscountValue(currentDiscount?.value ?? '');
    setDiscountReason(currentDiscount?.reason ?? '');
    setDiscountError(null);
    setShowDiscountForm(true);
  }, [item.manual_discount]);

  const closeDiscountForm = useCallback(() => {
    setShowDiscountForm(false);
    setDiscountError(null);
  }, []);

  const handleApplyLineDiscount = useCallback((event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setDiscountError(null);

    const parsed = parseFloat(discountValue);

    if (!Number.isFinite(parsed) || parsed <= 0) {
      setDiscountError('El valor debe ser mayor a cero.');
      return;
    }

    if (discountType === 'percentage' && parsed > 100) {
      setDiscountError('El porcentaje no puede exceder 100%.');
      return;
    }

    if (discountType === 'fixed' && parsed > lineSubtotal) {
      setDiscountError('El descuento fijo no puede superar el subtotal de la línea.');
      return;
    }

    if (discountReason.trim() === '') {
      setDiscountError('El motivo es obligatorio.');
      return;
    }

    onApplyItemDiscount(item.key, {
      type: discountType,
      value: parsed.toFixed(2),
      reason: discountReason.trim(),
    });

    setShowDiscountForm(false);
    setDiscountValue('');
    setDiscountReason('');
  }, [
    discountType,
    discountValue,
    discountReason,
    item.key,
    lineSubtotal,
    onApplyItemDiscount,
  ]);

  return (
    <>
      <div className="mx-register-cart-item">
        <input
          type="number"
          className="mx-register-cart-item__qty"
          min={1}
          value={item.quantity}
          onChange={(event) => {
            const value = parseInt((event.target as HTMLInputElement).value, 10);

            if (!Number.isNaN(value)) {
              onUpdateQuantity(item.key, value);
            }
          }}
          aria-label={`Cantidad para ${item.name}`}
        />

        <div className="mx-register-cart-item__details">
          <p className="mx-register-cart-item__name">{cartItemName.productName}</p>

          {cartItemName.variationLabel ? (
            <p className="mx-register-cart-item__variation">{cartItemName.variationLabel}</p>
          ) : null}

          {item.manual_discount && lineDiscount > 0 ? (
            <p className="mx-register-cart-item__line-discount">
              Desc. {getDiscountLabel(item.manual_discount)} · {item.manual_discount.reason}
            </p>
          ) : null}
        </div>

        <div className="mx-register-cart-item__price">
          {lineDiscount > 0 ? (
            <>
              <span className="mx-register-cart-item__price-before">
                <MoneyDisplay amount={lineSubtotal} size="sm" />
              </span>
              <MoneyDisplay amount={lineTotal} size="sm" />
            </>
          ) : (
            <MoneyDisplay amount={lineTotal} size="sm" />
          )}
        </div>

        {canApplyDiscount ? (
          <div className="mx-register-cart-item__discount-quick">
            <Button
              variant="ghost"
              size="sm"
              className="mx-register-cart-item__discount-btn"
              onClick={openDiscountForm}
            >
              {item.manual_discount ? 'Editar desc.' : 'Desc.'}
            </Button>

            {item.manual_discount ? (
              <Button
                variant="ghost"
                size="sm"
                className="mx-register-cart-item__discount-clear"
                onClick={() => onClearItemDiscount(item.key)}
              >
                Quitar
              </Button>
            ) : null}
          </div>
        ) : null}

        <Button
          variant="ghost"
          size="sm"
          className="mx-register-cart-item__remove"
          onClick={() => onRemoveItem(item.key)}
          aria-label={`Quitar ${item.name}`}
        >
          <svg
            width="14"
            height="14"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </Button>
      </div>

      <Modal
        open={showDiscountForm}
        onClose={closeDiscountForm}
        title="Descuento por producto"
        description={item.name}
        panelClassName="mx-line-discount-modal-panel"
      >
        <form className="mx-discount-form mx-line-discount-form" onSubmit={handleApplyLineDiscount}>
          <div className="mx-discount-form__toggle">
            <button
              type="button"
              className={`mx-discount-form__toggle-btn ${discountType === 'percentage' ? 'mx-discount-form__toggle-btn--active' : ''}`}
              aria-pressed={discountType === 'percentage'}
              onClick={() => setDiscountType('percentage')}
            >
              Porcentaje
            </button>

            <button
              type="button"
              className={`mx-discount-form__toggle-btn ${discountType === 'fixed' ? 'mx-discount-form__toggle-btn--active' : ''}`}
              aria-pressed={discountType === 'fixed'}
              onClick={() => setDiscountType('fixed')}
            >
              Monto fijo
            </button>
          </div>

          <div className="mx-discount-form__field">
            <label className="mx-discount-form__label" htmlFor={`mx-line-discount-value-${item.key}`}>
              Valor
            </label>
            <input
              id={`mx-line-discount-value-${item.key}`}
              type="number"
              className="mx-discount-form__input"
              min="0.01"
              step="0.01"
              inputMode="decimal"
              value={discountValue}
              onChange={(event) => setDiscountValue(event.target.value)}
              required
              placeholder={discountType === 'percentage' ? '10' : '50.00'}
              aria-label="Valor del descuento por producto"
            />
          </div>

          <div className="mx-discount-form__field">
            <label className="mx-discount-form__label" htmlFor={`mx-line-discount-reason-${item.key}`}>
              Motivo
            </label>
            <textarea
              id={`mx-line-discount-reason-${item.key}`}
              className="mx-discount-form__textarea"
              maxLength={255}
              value={discountReason}
              onChange={(event) => setDiscountReason(event.target.value)}
              required
              placeholder="Ej. Promoción mostrador"
              rows={3}
              aria-label="Motivo del descuento por producto"
            />
          </div>

          {lineDiscount > 0 ? (
            <div className="mx-line-discount-form__preview">
              <span>
                Subtotal: <MoneyDisplay amount={lineSubtotal} size="sm" />
              </span>
              <span>
                Actual: <MoneyDisplay amount={lineTotal} size="sm" emphasized />
              </span>
            </div>
          ) : null}

          {discountError ? (
            <p className="mx-discount-form__error">{discountError}</p>
          ) : null}

          <div className="mx-discount-form__actions">
            <Button type="button" variant="ghost" size="md" onClick={closeDiscountForm}>
              Cancelar
            </Button>
            <Button type="submit" variant="primary" size="md">
              Guardar descuento
            </Button>
          </div>
        </form>
      </Modal>
    </>
  );
}

export default CartItemRow;
