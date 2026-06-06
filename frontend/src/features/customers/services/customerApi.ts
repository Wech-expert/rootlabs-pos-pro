import type {
  Customer,
  CustomerSearchResponse,
  CreateCustomerRequest,
  UpdateCustomerRequest,
  PurchaseHistoryResponse,
} from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

function getHeaders(requireAuth = true): HeadersInit {
  const settings = getSettings();
  const headers: HeadersInit = {
    Accept: 'application/json',
  };
  if (requireAuth && settings) {
    headers['X-WP-Nonce'] = settings.nonce;
  }
  return headers;
}

function getRoot(): string {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }
  return settings.root;
}

export async function searchCustomers(
  q: string,
  limit = 20,
): Promise<Customer[]> {
  const root = getRoot();
  const params = new URLSearchParams({ q, limit: String(limit) });
  const url = root + 'customers/search?' + params.toString();

  const response = await fetch(url, {
    method: 'GET',
    headers: getHeaders(),
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  const data: CustomerSearchResponse = await response.json();
  return data.items;
}

export async function lookupCustomerByEmail(
  email: string,
): Promise<Customer | null> {
  const root = getRoot();
  const url = root + 'customers/lookup?email=' + encodeURIComponent(email);

  const response = await fetch(url, {
    method: 'GET',
    headers: getHeaders(),
    credentials: 'same-origin',
  });

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return response.json();
}

export async function createCustomer(
  payload: CreateCustomerRequest,
): Promise<Customer> {
  const root = getRoot();
  const url = root + 'customers';

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      ...getHeaders(),
      'Content-Type': 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return response.json();
}

export async function updateCustomer(
  customerId: number,
  payload: UpdateCustomerRequest,
): Promise<Customer> {
  const root = getRoot();
  const url = root + 'customers/' + customerId;

  const response = await fetch(url, {
    method: 'PUT',
    headers: {
      ...getHeaders(),
      'Content-Type': 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return response.json();
}

export async function getCustomerPurchases(
  customerId: number,
  limit = 10,
): Promise<PurchaseHistoryResponse> {
  const root = getRoot();
  const url =
    root + 'customers/' + customerId + '/purchases?limit=' + String(limit);

  const response = await fetch(url, {
    method: 'GET',
    headers: getHeaders(),
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return response.json();
}
