import type { CartItem, CartValidationResponse } from '../types';
import type { DiscountInput } from '../../discounts/types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function validateCart(
  items: CartItem[],
  discount: DiscountInput | null = null,
  couponCode: string | null = null,
): Promise<CartValidationResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const payload: Record<string, unknown> = {
    items: items.map((item) => ({
      product_id: item.product_id,
      variation_id: item.variation_id,
      quantity: item.quantity,
    })),
  };

  if (discount) {
    payload.discount = discount;
  }

  if (couponCode) {
    payload.coupon_code = couponCode;
  }

  const url = settings.root + 'cart/validate';

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': settings.nonce,
      Accept: 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  const data: CartValidationResponse = await response.json();
  return data;
}
