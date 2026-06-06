import { useState, useEffect, useRef, useCallback } from 'react';
import type { CartItem, CartValidationResponse, CartState } from '../types';
import type { DiscountInput } from '../../discounts/types';
import { validateCart } from '../services/cartValidationApi';

interface UseCartValidationOptions {
  debounceMs?: number;
}

interface UseCartValidationReturn {
  cartState: CartState;
  validationResult: CartValidationResponse | null;
  validationError: string | null;
  isValidating: boolean;
  validateNow: () => Promise<CartValidationResponse | null>;
  resetValidation: () => void;
}

export function useCartValidation(
  cartItems: CartItem[],
  discount: DiscountInput | null,
  couponCode: string | null = null,
  options: UseCartValidationOptions = {},
): UseCartValidationReturn {
  const { debounceMs = 300 } = options;

  const [cartState, setCartState] = useState<CartState>('idle');
  const [validationResult, setValidationResult] =
    useState<CartValidationResponse | null>(null);
  const [validationError, setValidationError] = useState<string | null>(null);
  const [isValidating, setIsValidating] = useState(false);

  const seqRef = useRef(0);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const doValidate = useCallback(
    async (
      items: CartItem[],
      disc: DiscountInput | null,
      coupon: string | null,
    ): Promise<CartValidationResponse | null> => {
      const seq = ++seqRef.current;
      setIsValidating(true);
      setCartState('validating');
      setValidationError(null);

      try {
        const result = await validateCart(items, disc, coupon);

        if (seq !== seqRef.current) {
          return null;
        }

        setValidationResult(result);
        setValidationError(null);

        if (result.valid) {
          setCartState('valid');
        } else {
          setCartState('invalid');
        }

        return result;
      } catch (err) {
        if (seq !== seqRef.current) {
          return null;
        }

        const errorMsg =
          err instanceof Error
            ? err.message
            : 'Falló la validación del carrito';
        setValidationError(errorMsg);
        setCartState('error');

        setValidationResult({
          valid: false,
          items: [],
          totals: { subtotal: '0', coupon_total: '0', discount_total: '0', total: '0' },
          errors: [errorMsg],
        });

        return null;
      } finally {
        if (seq === seqRef.current) {
          setIsValidating(false);
        }
      }
    },
    [],
  );

  useEffect(() => {
    if (cartItems.length === 0) {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
        timerRef.current = null;
      }
      setCartState('idle');
      setValidationResult(null);
      setValidationError(null);
      setIsValidating(false);
      return;
    }

    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
    }

    setCartState('dirty');
    setIsValidating(false);

    timerRef.current = setTimeout(() => {
      doValidate(cartItems, discount, couponCode);
    }, debounceMs);

    return () => {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current);
        timerRef.current = null;
      }
    };
  }, [cartItems, discount, couponCode, debounceMs, doValidate]);

  const validateNow = useCallback(async (): Promise<CartValidationResponse | null> => {
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }

    if (cartItems.length === 0) {
      return null;
    }

    return doValidate(cartItems, discount, couponCode);
  }, [cartItems, discount, couponCode, doValidate]);

  const resetValidation = useCallback(() => {
    seqRef.current++;
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
    setCartState('idle');
    setValidationResult(null);
    setValidationError(null);
    setIsValidating(false);
  }, []);

  return {
    cartState,
    validationResult,
    validationError,
    isValidating,
    validateNow,
    resetValidation,
  };
}

export default useCartValidation;
