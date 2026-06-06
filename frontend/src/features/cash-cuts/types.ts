export type CutType = 'X' | 'Z';

export interface CutSessionInfo {
  id: number;
  status: string;
  opened_at: string;
  opened_by: number;
  opened_by_type?: 'pos_employee' | 'wp_user' | string;
  cashier_name: string;
}

export interface CashFlowTotals {
  cash_in_total: string;
  cash_out_total: string;
  net_cash: string;
  manual_cash_in_total?: string;
  manual_cash_in_count?: number;
  manual_cash_out_total?: string;
  manual_cash_out_count?: number;
  manual_net_cash?: string;
  sales_cash_in_total?: string;
  sales_cash_in_count?: number;
  sales_change_out_total?: string;
  sales_change_out_count?: number;
}

export interface MethodBreakdown {
  slug?: string;
  name?: string;
  total?: string | number;
  count?: number;
  amount?: string | number;
  sales_count?: number;
}

export interface SalesInfo {
  collected_total: string;
  count_orders: number;
  cash_collected_total?: string;
  cash_change_total?: string;
  card_collected_total?: string;
  card_sales_count?: number;
}

export interface DiscountsInfo {
  total: string;
}

export interface RefundsInfo {
  total: string;
  count_refunds: number;
  count_cancellations: number;
  cash_refunds: string;
  card_refunds: string;
}

export interface ClosingInfo {
  expected_amount: string | null;
  counted_amount: string | null;
  difference: string | null;
  closed_at: string | null;
  closed_by: string | null;
  close_note: string | null;
}

export interface CutSummary {
  cut_type: CutType;
  cut_id?: number;
  session: CutSessionInfo;
  opening: { amount: string };
  cash_flow: CashFlowTotals;
  expected_cash: string;
  by_method: Record<string, MethodBreakdown>;
  sales: SalesInfo;
  discounts: DiscountsInfo;
  refunds: RefundsInfo;
  net_after_refunds: string;
  closing?: ClosingInfo;
  generated_at: string;
  generated_by: string;
  ticket_html?: string;
}

export interface CutXResponse {
  cut: CutSummary;
  ticket_html: string;
}

export interface CutZResponse {
  cut: CutSummary;
  ticket_html: string;
}

export interface CutByIdResponse {
  cut: CutSummary;
}

export interface CutTicketResponse {
  html: string;
}
