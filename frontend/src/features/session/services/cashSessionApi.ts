import type {
  CurrentSessionResponse,
  OpenSessionRequest,
  OpenSessionResponse,
  CloseSessionRequest,
  CloseSessionResponse,
} from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function getCurrentSession(): Promise<CurrentSessionResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'sessions/current';

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

  const data: CurrentSessionResponse = await response.json();
  return data;
}

export async function openSession(
  payload: OpenSessionRequest,
): Promise<OpenSessionResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'sessions/open';

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

  const data: OpenSessionResponse = await response.json();
  return data;
}

export async function closeSession(
  sessionId: number,
  payload: CloseSessionRequest,
): Promise<CloseSessionResponse> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'sessions/' + sessionId + '/close';

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

  const data: CloseSessionResponse = await response.json();
  return data;
}
