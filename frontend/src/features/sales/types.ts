import type { DiscountInput } from '../discounts/types';
import type { PaymentInfo } from '../payments/types';

export interface CreateSaleRequest {
  items: Array<{
    product_id: number;
    variation_id: number | null;
    quantity: number;
    manual_discount?: DiscountInput | null;
  }>;
  customer_id?: number | null;
  discount?: DiscountInput | null;
  coupon_code?: string | null;
  parked_cart_id?: number | null;
  client_request_id: string;
}

export interface CreateSaleResponse {
  sale: {
    id: number;
    order_id: number;
    order_number: string;
    status: string;
    session_id: number;
    cashier_id: number;
    customer_id: number | null;
    totals: {
      subtotal: string;
      coupon_total: string;
      discount_total: string;
      total: string;
    };
    payment?: PaymentInfo;
    created_at: string;
    paid_at?: string;
  };
}
