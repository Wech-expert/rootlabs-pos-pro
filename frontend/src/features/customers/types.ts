export interface Customer {
  id: number;
  display_name: string;
  email: string;
  phone: string | null;
  first_name?: string | null;
  last_name?: string | null;
}

export interface CustomerSearchResponse {
  items: Customer[];
}

export interface CreateCustomerRequest {
  name: string;
  email: string;
  phone: string;
}

export interface UpdateCustomerRequest {
  name: string;
  phone: string;
}

export interface PurchaseHistoryItem {
  order_id: number;
  order_number: string;
  date: string | null;
  total: string;
  status: string;
  payment_method: string | null;
  payment_method_label: string | null;
}

export interface PurchaseHistoryResponse {
  items: PurchaseHistoryItem[];
}
