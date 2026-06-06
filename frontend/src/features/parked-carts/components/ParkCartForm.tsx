import { useState, useCallback } from 'react';
import { createParkedCart } from '../services/parkedCartApi';
import { Button } from '../../../components/ui';
import type { CartItem } from '../../register/types';
import type { DiscountInput } from '../../discounts/types';

interface ParkCartFormProps {
  items: CartItem[];
  customerId: number | null;
  discount: DiscountInput | null;
  onCreated: () => void;
  onCancel: () => void;
}

function ParkCartForm({ items, customerId, discount, onCreated, onCancel }: ParkCartFormProps) {
  const [label, setLabel] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setError(null);
      setSubmitting(true);
      try {
        await createParkedCart({
          label: label.trim() || undefined,
          customer_id: customerId ?? undefined,
          discount: discount ?? undefined,
          items: items.map((i) => ({
            product_id: i.product_id,
            variation_id: i.variation_id,
            quantity: i.quantity,
          })),
        });
        onCreated();
      } catch (err) {
        setError(
          err instanceof Error ? err.message : 'No se pudo guardar el carrito',
        );
      } finally {
        setSubmitting(false);
      }
    },
    [items, label, customerId, discount, onCreated],
  );

  return (
    <form className="mx-parked-cart-form" onSubmit={handleSubmit}>
      <div className="mx-parked-cart-form__field">
        <label
          className="mx-parked-cart-form__label"
          htmlFor="mx-parked-cart-label"
        >
          Etiqueta
          <span className="mx-parked-cart-form__label-hint"> (opcional)</span>
        </label>
        <input
          id="mx-parked-cart-label"
          type="text"
          className="mx-parked-cart-form__input"
          maxLength={255}
          value={label}
          onChange={(e) => setLabel((e.target as HTMLInputElement).value)}
          disabled={submitting}
          placeholder="Ej. Cliente mostrador"
          aria-label="Etiqueta"
        />
      </div>

      <p className="mx-parked-cart-form__count">
        {items.length} producto{items.length !== 1 ? 's' : ''} en el carrito
      </p>

      {error && <p className="mx-parked-cart-form__error">{error}</p>}

      <div className="mx-parked-cart-form__actions">
        <Button
          type="button"
          variant="ghost"
          size="md"
          disabled={submitting}
          onClick={onCancel}
        >
          Cancelar
        </Button>
        <Button
          type="submit"
          variant="primary"
          size="md"
          disabled={submitting}
          loading={submitting}
        >
          Guardar carrito
        </Button>
      </div>
    </form>
  );
}

export default ParkCartForm;
