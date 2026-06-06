import type {
  CurrentParkedCartsResponse,
  CreateParkedCartRequest,
  CreateParkedCartResponse,
  RestoreParkedCartResponse,
  DeleteParkedCartResponse,
} from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function getCurrentParkedCarts(): Promise<CurrentParkedCartsResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'parked-carts/current';

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': settings.nonce,
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  const data: CurrentParkedCartsResponse = await response.json();
  return data;
}

export async function createParkedCart(
  payload: CreateParkedCartRequest,
): Promise<CreateParkedCartResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'parked-carts';

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': settings.nonce,
      Accept: 'application/json',
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

  const data: CreateParkedCartResponse = await response.json();
  return data;
}

export async function getParkedCart(
  id: number,
): Promise<RestoreParkedCartResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'parked-carts/' + id;

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': settings.nonce,
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  const data: RestoreParkedCartResponse = await response.json();
  return data;
}

export async function deleteParkedCart(
  id: number,
): Promise<DeleteParkedCartResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'parked-carts/' + id;

  const response = await fetch(url, {
    method: 'DELETE',
    headers: {
      'X-WP-Nonce': settings.nonce,
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message =
      body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  const data: DeleteParkedCartResponse = await response.json();
  return data;
}
