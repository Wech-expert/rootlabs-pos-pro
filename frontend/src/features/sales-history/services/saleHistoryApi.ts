import type {
  SaleHistoryResponse,
  SaleDetail,
  CashiersResponse,
  SaleHistoryFiltersState,
  SaleLookupItem,
} from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function fetchSalesHistory(
  filters: SaleHistoryFiltersState,
  page: number,
  perPage: number,
): Promise<SaleHistoryResponse> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const params = new URLSearchParams();
  params.set('page', String(page));
  params.set('per_page', String(perPage));

  if (filters.date_from) params.set('date_from', filters.date_from);
  if (filters.date_to) params.set('date_to', filters.date_to);
  if (filters.status) params.set('status', filters.status);
  if (filters.cashier_id !== null) params.set('cashier_id', String(filters.cashier_id));
  if (filters.search) params.set('search', filters.search);
  if (filters.sessionId) params.set('session_id', String(filters.sessionId));

  const url = `${settings.root}sales/history?${params.toString()}`;

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': settings.nonce,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  const data: SaleHistoryResponse = await response.json();
  return data;
}

export async function fetchSaleDetail(saleId: number): Promise<SaleDetail> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = `${settings.root}sales/${saleId}/detail`;

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': settings.nonce,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  const data: SaleDetail = await response.json();
  return data;
}

export async function fetchCashiers(): Promise<CashiersResponse> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = `${settings.root}sales/history/cashiers`;

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': settings.nonce,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  const data: CashiersResponse = await response.json();
  return data;
}

export interface LookupSalesResponse {
  items: SaleLookupItem[];
}

export async function lookupSales(query: string): Promise<LookupSalesResponse> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const params = new URLSearchParams({ query });
  const url = `${settings.root}sales/lookup?${params.toString()}`;

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': settings.nonce,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  const data: LookupSalesResponse = await response.json();
  return data;
}

export async function fetchRecentRefundableSales(limit = 5): Promise<LookupSalesResponse> {
  const data = await fetchSalesHistory(
    {
      date_from: '',
      date_to: '',
      status: '',
      cashier_id: null,
      search: '',
    },
    1,
    Math.max(limit, 20),
  );

  return {
    items: data.items
      .filter((item) => (
        item.display_status === 'completed' ||
        item.display_status === 'processing' ||
        item.display_status === 'partially_refunded'
      ) && parseFloat(item.net_total) > 0)
      .slice(0, limit)
      .map((item) => ({
        id: item.id,
        order_id: item.wc_order_id,
        order_number: String(item.wc_order_id),
        status: item.display_status,
        total: item.total,
        refunded_total: item.refunded_total,
        created_at: item.created_at,
        can_refund: true,
      })),
  };
}
