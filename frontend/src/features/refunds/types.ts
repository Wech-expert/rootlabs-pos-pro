export interface RefundOptionsResponse {
  sale: {
    id: number;
    order_id: number;
    order_number: string;
    status: string;
    payment_method: string;
    total: string;
    refunded_total: string;
    remaining_refund_total: string;
    created_at: string;
  };
  items: RefundableItem[];
}

export interface RefundableItem {
  order_item_id: number;
  product_id: number;
  variation_id: number | null;
  name: string;
  sku: string;
  quantity: number;
  refunded_quantity: number;
  refundable_quantity: number;
  unit_total: string;
  line_total: string;
}

export interface RefundItem {
  order_item_id: number;
  quantity: number;
}

export interface RefundRequest {
  items: RefundItem[];
  refund_method: 'cash' | 'card';
  reason: string;
  client_request_id: string;
}

export interface CancelRequest {
  reason: string;
  client_request_id: string;
}

export interface CancelResponse {
  refund: {
    id: number;
    sale_id: number;
    wc_refund_id: number;
    refund_type: string;
    refund_amount: string;
    refund_method: string | null;
    reason: string | null;
    created_at: string;
  };
  sale: {
    id: number;
    status: string;
  };
}

export interface RefundResponse {
  refund: {
    id: number;
    sale_id: number;
    wc_refund_id: number;
    refund_type: string;
    refund_amount: string;
    refund_method: string | null;
    reason: string | null;
    created_at: string;
  };
  sale: {
    id: number;
    order_id: number;
    status: string;
    total: string;
    refunded_total: string;
    remaining_refund_total: string;
  };
}
