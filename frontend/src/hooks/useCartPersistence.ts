import { useEffect, useRef, useCallback } from 'react';
import type { DiscountInput } from '../features/discounts/types';
import type { CartItem } from '../features/register/types';

const STORAGE_KEY = 'mx_pos_pending_cart';
const MAX_AGE_MS = 30 * 60 * 1000;

interface PersistedCartItem {
  product_id: number;
  variation_id: number | null;
  quantity: number;
}

interface PersistedCart {
  version: number;
  timestamp: number;
  clientRequestId: string | null;
  items: PersistedCartItem[];
  customerId: number | null;
  discount: DiscountInput | null;
  couponCode: string | null;
  parkedCartId: number | null;
}

interface CartSnapshot {
  clientRequestId: string | null;
  items: CartItem[];
  customerId: number | null;
  discount: DiscountInput | null;
  couponCode: string | null;
  parkedCartId: number | null;
}

interface UseCartPersistenceResult {
  restoreCart: () => PersistedCart | null;
  clearPersistedCart: () => void;
  saveCart: (snapshot: CartSnapshot) => void;
  saveCartNow: (snapshot: CartSnapshot) => void;
  hasPendingCart: () => boolean;
}

export function useCartPersistence(): UseCartPersistenceResult {
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    return () => {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
      }
    };
  }, []);

  const writeCart = useCallback((snapshot: CartSnapshot) => {
    if (snapshot.items.length === 0) {
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch {
        // ignore
      }
      return;
    }

    const data: PersistedCart = {
      version: 1,
      timestamp: Date.now(),
      clientRequestId: snapshot.clientRequestId,
      items: snapshot.items.map((i) => ({
        product_id: i.product_id,
        variation_id: i.variation_id ?? null,
        quantity: i.quantity,
      })),
      customerId: snapshot.customerId,
      discount: snapshot.discount,
      couponCode: snapshot.couponCode,
      parkedCartId: snapshot.parkedCartId,
    };

    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch {
      // localStorage might be full or unavailable
    }
  }, []);

  const saveCart = useCallback((snapshot: CartSnapshot) => {
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
    }

    timerRef.current = setTimeout(() => {
      timerRef.current = null;
      writeCart(snapshot);
    }, 1000);
  }, [writeCart]);

  const saveCartNow = useCallback((snapshot: CartSnapshot) => {
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }

    writeCart(snapshot);
  }, [writeCart]);

  const restoreCart = useCallback((): PersistedCart | null => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;

      const data: PersistedCart = JSON.parse(raw);
      if (!data || !data.items || data.version !== 1) return null;

      if (Date.now() - data.timestamp > MAX_AGE_MS) {
        localStorage.removeItem(STORAGE_KEY);
        return null;
      }

      if (data.items.length === 0) return null;

      return data;
    } catch {
      return null;
    }
  }, []);

  const clearPersistedCart = useCallback(() => {
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
      // ignore
    }
  }, []);

  const hasPendingCart = useCallback((): boolean => {
    return restoreCart() !== null;
  }, [restoreCart]);

  return {
    restoreCart,
    clearPersistedCart,
    saveCart,
    saveCartNow,
    hasPendingCart,
  };
}
