import { useEffect, useState, useCallback } from 'react';
import {
  getCurrentParkedCarts,
  getParkedCart,
  deleteParkedCart,
} from '../services/parkedCartApi';
import ParkedCartList from './ParkedCartList';
import type { ParkedCartSummary, ValidatedCartItem, Customer } from '../types';
import type { DiscountInput, AppliedCoupon } from '../../discounts/types';
import type { CartItem, CartValidationResponse } from '../../register/types';

export interface RestoreParams {
  parkedCartId: number;
  items: CartItem[];
  customer: Customer | null;
  discount: DiscountInput | null;
  coupon: AppliedCoupon | null;
  validationResult: CartValidationResponse;
}

interface ParkedCartDrawerProps {
  onRestore: (params: RestoreParams) => void;
  onClose: () => void;
}

function ParkedCartDrawer({ onRestore, onClose }: ParkedCartDrawerProps) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [items, setItems] = useState<ParkedCartSummary[]>([]);
  const [query, setQuery] = useState('');
  const [restoringId, setRestoringId] = useState<number | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const fetchList = useCallback(async () => {
    setError(null);
    setLoading(true);
    try {
      const data = await getCurrentParkedCarts();
      setItems(data.items);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'Failed to load parked carts',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchList();
  }, [fetchList]);

  const handleRestore = useCallback(
    async (id: number) => {
      setError(null);
      setRestoringId(id);
      try {
        const data = await getParkedCart(id);
        const mapped: CartItem[] = data.cart.items.map((vi: ValidatedCartItem) => ({
          key: `${vi.product_id}-${vi.variation_id ?? 0}`,
          product_id: vi.product_id,
          variation_id: vi.variation_id,
          sku: vi.sku,
          name: vi.name,
          type: vi.variation_id ? 'variation' : 'simple',
          quantity: vi.quantity,
          unit_price: parseFloat(vi.unit_price),
          stock_status: vi.stock_status,
        }));
        const customer: Customer | null = data.cart.customer
          ? { ...data.cart.customer, phone: data.cart.customer.phone ?? null }
          : null;

        const discountRestored: DiscountInput | null = data.cart.discount
          ? {
              type: data.cart.discount.type,
              value: data.cart.discount.value,
              reason: data.cart.discount.reason,
            }
          : null;

        const validationResult: CartValidationResponse = {
          valid: data.cart.items.every((item) => item.valid),
          items: data.cart.items,
          discount: data.cart.discount ?? null,
          totals: {
            subtotal: data.cart.totals.subtotal,
            coupon_total: data.cart.totals.coupon_total ?? '0',
            discount_total: data.cart.totals.discount_total,
            total: data.cart.totals.total,
          },
          errors: data.cart.items.flatMap((item) => item.errors),
        };

        const restoredCoupon: AppliedCoupon | null = data.cart.coupon
          ? {
              code: data.cart.coupon.code,
              discount_type: data.cart.coupon.discount_type ?? '',
              amount: data.cart.coupon.amount ?? '',
              description: data.cart.coupon.description ?? '',
            }
          : null;

        onRestore({
          parkedCartId: id,
          items: mapped,
          customer,
          discount: discountRestored,
          coupon: restoredCoupon,
          validationResult,
        });
        onClose();
      } catch (err) {
        setRestoringId(null);
        setError(
          err instanceof Error
            ? err.message
            : 'Failed to restore parked cart',
        );
      }
    },
    [onRestore, onClose],
  );

  const handleDelete = useCallback(
    async (id: number) => {
      setError(null);
      setDeletingId(id);
      try {
        await deleteParkedCart(id);
        setItems((prev) => prev.filter((i) => i.id !== id));
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : 'Failed to delete parked cart',
        );
      } finally {
        setDeletingId(null);
      }
    },
    [],
  );

  const normalizedQuery = query.trim().toLowerCase();
  const filteredItems =
    normalizedQuery === ''
      ? items
      : items.filter((item) => {
          const haystack = [
            item.label ?? '',
            item.customer_label ?? '',
            item.created_at,
            item.total,
            String(item.item_count),
          ]
            .join(' ')
            .toLowerCase();

          return haystack.includes(normalizedQuery);
        });

  return (
    <div className="mx-parked-cart-drawer">
      <div className="mx-parked-cart-drawer__header">
        <div>
          <h2 className="mx-parked-cart-drawer__title">Carritos guardados</h2>
          {!loading && !error && (
            <p className="mx-parked-cart-drawer__count">
              {items.length} carrito{items.length !== 1 ? 's' : ''} disponible{items.length !== 1 ? 's' : ''}
            </p>
          )}
        </div>
      </div>

      {loading && (
        <p className="mx-parked-cart-drawer__loading">Cargando...</p>
      )}

      {error && (
        <div className="mx-parked-cart-drawer__error">
          <p>{error}</p>
        </div>
      )}

      {!loading && !error && (
        <>
          <div className="mx-parked-cart-drawer__search">
            <label
              className="mx-parked-cart-drawer__search-label"
              htmlFor="mx-parked-cart-search"
            >
              Buscar carrito
            </label>
            <input
              id="mx-parked-cart-search"
              className="mx-parked-cart-drawer__search-input"
              type="search"
              value={query}
              onChange={(e) => setQuery((e.target as HTMLInputElement).value)}
              placeholder="Etiqueta, cliente o fecha"
            />
          </div>
          <ParkedCartList
            items={filteredItems}
            hasQuery={normalizedQuery !== ''}
            onRestore={handleRestore}
            onDelete={handleDelete}
            restoringId={restoringId}
            deletingId={deletingId}
            onClearSearch={() => setQuery('')}
          />
        </>
      )}
    </div>
  );
}

export default ParkedCartDrawer;
