import { MoneyDisplay, Button } from '../../../components/ui';
import type { CartItem } from '../types';

interface CartItemRowProps {
  item: CartItem;
  onUpdateQuantity: (key: string, qty: number) => void;
  onRemoveItem: (key: string) => void;
}

function CartItemRow({ item, onUpdateQuantity, onRemoveItem }: CartItemRowProps) {
  const lineTotal = item.unit_price * item.quantity;

  return (
    <div className="mx-register-cart-item">
      <input
        type="number"
        className="mx-register-cart-item__qty"
        min={1}
        value={item.quantity}
        onChange={(e) => {
          const v = parseInt((e.target as HTMLInputElement).value, 10);
          if (!isNaN(v)) {
            onUpdateQuantity(item.key, v);
          }
        }}
        aria-label={`Cantidad para ${item.name}`}
      />
      <p className="mx-register-cart-item__name">{item.name}</p>
      <div className="mx-register-cart-item__price">
        <MoneyDisplay amount={lineTotal} size="sm" />
      </div>
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
  );
}

export default CartItemRow;
