export interface CashMovement {
  id: number;
  session_id: number;
  movement_type: 'cash_in' | 'cash_out';
  amount: string;
  reason: string | null;
  created_at: string;
  created_by: number;
}

export interface CashMovementTotals {
  cash_in: string;
  cash_out: string;
  net: string;
  current_cash: string;
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

export interface CurrentCashMovementsResponse {
  has_open_session: boolean;
  session_id: number | null;
  opening_amount: string | null;
  items: CashMovement[];
  totals: CashMovementTotals;
}

export interface CreateCashMovementRequest {
  movement_type: 'cash_in' | 'cash_out';
  amount: string;
  reason: string;
  client_request_id: string;
}

export interface CreateCashMovementResponse {
  movement: CashMovement;
  totals: CashMovementTotals;
}

export interface ReverseCashMovementRequest {
  reason?: string;
}

export interface ReverseCashMovementResponse {
  movement: CashMovement;
  totals: CashMovementTotals;
}
