export type SaleStatus = 'pending' | 'completed' | 'processing' | 'cancelled' | 'refunded';

export type SaleDisplayStatus =
  | 'pending'
  | 'completed'
  | 'processing'
  | 'cancelled'
  | 'refunded'
  | 'partially_refunded';

export interface SaleHistoryItem {
  id: number;
  wc_order_id: number;
  status: SaleStatus;
  display_status: SaleDisplayStatus;
  cashier_id: number;
  cashier_name: string;
  payment_method: string | null;
  payment_method_label: string | null;
  total: string;
  refunded_total: string;
  net_total: string;
  created_at: string;
}

export interface SaleHistoryFiltersState {
  date_from: string;
  date_to: string;
  status: string;
  cashier_id: number | null;
  search: string;
  sessionId?: number;
}

export interface PaginationState {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface SaleHistoryResponse {
  items: SaleHistoryItem[];
  pagination: PaginationState;
}

export interface CashierOption {
  id: number;
  name: string;
}

export interface CashiersResponse {
  cashiers: CashierOption[];
}

export interface SaleLookupItem {
  id: number;
  order_id: number;
  order_number: string;
  status: string;
  total: string;
  refunded_total: string;
  created_at: string;
  can_refund: boolean;
}

export interface SaleDetailItem {
  name: string;
  sku: string;
  quantity: number;
  unit_price: string;
  line_total: string;
}

export interface SaleDetailPayment {
  method: string;
  method_label: string;
  amount_received?: string;
  change?: string;
  card_reference?: string;
}

export interface SaleDetailRefund {
  id: number;
  refund_type: string;
  refund_amount: string;
  refund_method: string | null;
  reason: string | null;
  created_at: string;
}

export interface SaleDetailLog {
  event_type: string;
  message: string | null;
  created_by: number | null;
  created_at: string;
}

export interface SaleDetail {
  sale: {
    id: number;
    wc_order_id: number;
    order_number: string;
    status: SaleStatus;
    display_status: SaleDisplayStatus;
    cashier_id: number;
    cashier_name: string;
    total: string;
    refunded_total: string;
    net_total: string;
    created_at: string;
  };
  order: {
    id: number;
    number: string;
    status: string;
    subtotal: string;
    discount_total: string;
    total: string;
    date_created: string | null;
  } | null;
  items: SaleDetailItem[];
  payment: SaleDetailPayment | null;
  refunds: SaleDetailRefund[];
  logs: SaleDetailLog[];
  actions: {
    can_reprint_ticket: boolean;
  };
}

export const STATUS_OPTIONS: { value: string; label: string }[] = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'completed', label: 'Completado' },
  { value: 'processing', label: 'Procesando' },
  { value: 'partially_refunded', label: 'Reembolso parcial' },
  { value: 'cancelled', label: 'Cancelado' },
  { value: 'refunded', label: 'Reembolsado' },
];

export const STATUS_LABELS: Record<string, string> = {
  pending: 'Pendiente',
  completed: 'Completado',
  processing: 'Procesando',
  partially_refunded: 'Reembolso parcial',
  cancelled: 'Cancelado',
  refunded: 'Reembolsado',
};
