import type { CreateSaleRequest, CreateSaleResponse } from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function createSale(
  payload: CreateSaleRequest,
): Promise<CreateSaleResponse> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'sales';

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': settings.nonce,
      'Accept': 'application/json',
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

  const data: CreateSaleResponse = await response.json();
  return data;
}
