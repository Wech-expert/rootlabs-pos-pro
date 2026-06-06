import type {
  CurrentCashMovementsResponse,
  CreateCashMovementRequest,
  CreateCashMovementResponse,
  ReverseCashMovementRequest,
  ReverseCashMovementResponse,
} from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function getCurrentCashMovements(): Promise<CurrentCashMovementsResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'cash-movements/current';

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

  const data: CurrentCashMovementsResponse = await response.json();
  return data;
}

export async function createCashMovement(
  payload: CreateCashMovementRequest,
): Promise<CreateCashMovementResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'cash-movements';

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

  const data: CreateCashMovementResponse = await response.json();
  return data;
}

export async function reverseCashMovement(
  movementId: number,
  payload: ReverseCashMovementRequest,
): Promise<ReverseCashMovementResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = `${settings.root}cash-movements/${movementId}/reverse`;

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

  const data: ReverseCashMovementResponse = await response.json();
  return data;
}
