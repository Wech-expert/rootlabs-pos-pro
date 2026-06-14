import type { DiscountInput } from '../discounts/types';

export interface IndexedProduct {
  product_id: number;
  variation_id: number | null;
  sku: string;
  name: string;
  type: string;
  stock_quantity: number | null;
  stock_status: string;
  regular_price: string | null;
  sale_price: string | null;
  image_url: string | null;
  image_alt: string;
}

export interface CatalogProduct {
  product_id: number;
  variation_id?: number | null;
  sku: string;
  name: string;
  type: string;
  stock_quantity: number | null;
  stock_status: string;
  regular_price: string | null;
  sale_price: string | null;
  min_price?: string | null;
  max_price?: string | null;
  image_url: string | null;
  image_alt: string;
  variations: IndexedProduct[];
}

export interface CartItem {
  key: string;
  product_id: number;
  variation_id: number | null;
  sku: string;
  name: string;
  type: IndexedProduct['type'];
  quantity: number;
  unit_price: number;
  stock_status: string;
  manual_discount?: DiscountInput | null;
}

export interface SearchResponse {
  items: IndexedProduct[];
}

export interface CatalogResponse {
  items: CatalogProduct[];
}

export interface CartValidationRequestItem {
  product_id: number;
  variation_id: number | null;
  quantity: number;
  manual_discount?: DiscountInput | null;
}

export interface ValidatedCartItem {
  product_id: number;
  variation_id: number | null;
  sku: string;
  name: string;
  quantity: number;
  unit_price: string;
  line_subtotal?: string;
  line_total: string;
  line_discount_total?: string;
  manual_discount?: ValidatedDiscount | null;
  stock_status: string;
  stock_quantity: number | null;
  valid: boolean;
  errors: string[];
}

export interface CartValidationResponse {
  valid: boolean;
  items: ValidatedCartItem[];
  discount?: ValidatedDiscount | null;
  coupon?: AppliedCouponDetail | null;
  coupon_error?: string | null;
  totals: {
    subtotal: string;
    coupon_total: string;
    discount_total: string;
    total: string;
  };
  errors: string[];
}

export interface AppliedCouponDetail {
  code: string;
  discount_type: string;
  amount: string;
  description?: string;
  discount_total: string;
}

export interface ValidatedDiscount {
  type: 'percentage' | 'fixed';
  value: string;
  reason: string;
  amount: string;
}

export type CartState =
  | 'idle'
  | 'dirty'
  | 'validating'
  | 'valid'
  | 'invalid'
  | 'sale_creating'
  | 'completed'
  | 'error';
