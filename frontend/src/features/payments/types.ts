export type PaymentMethod = 'cash' | 'card';

export type PaymentMethodCategory = 'cash' | 'card' | 'mixed' | 'woocommerce' | 'other';

export interface PosPaymentMethod {
  id: number;
  name: string;
  slug: string;
  payment_type: PaymentMethodCategory;
  affects_cash_register: boolean;
  allow_reference: boolean;
  card_fee_enabled: boolean;
  card_fee_type: 'percentage' | 'fixed' | null;
  card_fee_value: string | null;
  wc_gateway_id: string | null;
  is_active: boolean;
  sort_order: number;
}

export interface PaymentMethodsResponse {
  items: PosPaymentMethod[];
}

export interface PaymentLine {
  method: string;
  amount: number;
  reference?: string | null;
}

export interface PaymentInfoLine {
  method: string;
  method_name: string;
  amount: string;
  reference: string | null;
  card_fee: string | null;
  affects_cash_register: boolean;
}

export interface PaymentInfo {
  method: PaymentMethod;
  amount_received: string;
  change: string;
  card_reference: string | null;
  transaction_id: string;
  paid_at: string;
  cashier_id: number;
  session_id: number;
}

export interface PaymentInfoExtended {
  method: string;
  method_name?: string;
  total_due: string;
  total_paid: string;
  change: string;
  payment_lines: PaymentInfoLine[];
  card_fee_total: string;
  transaction_id: string;
  paid_at: string | null;
  cashier_id: number;
  session_id: number;
}

export interface ProcessPaymentRequest {
  payment_method: string;
  amount_received?: number | null;
  card_reference?: string | null;
  payment_lines?: PaymentLine[] | null;
  client_request_id: string;
}

export interface ProcessPaymentResponse {
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
    payment: PaymentInfo;
    created_at: string;
    paid_at: string;
  };
}

export interface CheckoutRequest {
  items: Array<{
    product_id: number;
    variation_id: number | null;
    quantity: number;
  }>;
  customer_id?: number | null;
  discount?: {
    type: string;
    value: string;
    reason: string;
  } | null;
  coupon_code?: string | null;
  parked_cart_id?: number | null;
  payment_lines: PaymentLine[];
  client_request_id: string;
}

export interface CheckoutResponse {
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
    items?: Array<{ name: string; quantity: number; total: string }>;
    payment: PaymentInfoExtended;
    created_at: string;
  };
}
