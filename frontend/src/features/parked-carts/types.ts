import type { ValidatedCartItem } from '../register/types';
import type { Customer } from '../customers/types';
import type { DiscountInput, ValidatedDiscount } from '../discounts/types';

export type { ValidatedCartItem };
export type { Customer };

export interface ParkedCartSummary {
  id: number;
  label: string | null;
  customer_label?: string | null;
  item_count: number;
  total: string;
  created_at: string;
}

export interface CurrentParkedCartsResponse {
  has_open_session: boolean;
  items: ParkedCartSummary[];
}

export interface ParkedCartItemRequest {
  product_id: number;
  variation_id: number | null;
  quantity: number;
}

export interface CreateParkedCartRequest {
  label?: string;
  customer_id?: number | null;
  discount?: DiscountInput | null;
  coupon_code?: string | null;
  items: ParkedCartItemRequest[];
}

export interface CreateParkedCartResponse {
  cart: ParkedCartSummary;
  parked_carts: ParkedCartSummary[];
}

export interface ParkedCartDetail {
  id: number;
  label: string | null;
  customer?: Customer | null;
  discount?: ValidatedDiscount | null;
  coupon?: { code: string; discount_type?: string; amount?: string; description?: string } | null;
  items: ValidatedCartItem[];
  totals: {
    subtotal: string;
    coupon_total: string;
    discount_total: string;
    total: string;
  };
}

export interface RestoreParkedCartResponse {
  cart: ParkedCartDetail;
}

export interface DeleteParkedCartResponse {
  deleted: boolean;
}
