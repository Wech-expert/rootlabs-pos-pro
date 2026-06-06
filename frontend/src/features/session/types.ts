export interface CashSession {
  id: number;
  opened_at: string;
  opened_by: number;
  opening_amount: string;
  status: 'open' | string;
  register_name?: string | null;
  employee_name?: string | null;
}

export interface CurrentSessionResponse {
  has_open_session: boolean;
  session: CashSession | null;
}

export interface OpenSessionRequest {
  opening_amount: string;
}

export interface OpenSessionResponse {
  session: CashSession;
}

export interface CloseSessionDenominations {
  [key: string]: number;
}

export interface CloseSessionRequest {
  denominations: CloseSessionDenominations;
  close_note?: string | null;
}

export interface CloseSessionTotals {
  cash_in: string;
  cash_out: string;
  net: string;
  manual_cash_in_total?: string;
  manual_cash_in_count?: number;
  manual_cash_out_total?: string;
  manual_cash_out_count?: number;
  manual_net_cash?: string;
  sales_cash_in_total?: string;
  sales_cash_in_count?: number;
  sales_change_out_total?: string;
  sales_change_out_count?: number;
  card_sales?: string;
  refund_total?: string;
}

export interface ClosedCashSession {
  id: number;
  opened_at: string;
  closed_at: string | null;
  opening_amount: string;
  expected_amount: string | null;
  counted_amount: string | null;
  difference: string | null;
  close_note: string | null;
  closed_by: number | null;
  status: string;
}

export interface CloseSessionResponse {
  session: ClosedCashSession;
  totals: CloseSessionTotals;
}
