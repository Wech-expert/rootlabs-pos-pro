import type { PosPaymentMethod, PaymentMethodsResponse } from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function getActivePaymentMethods(): Promise<PosPaymentMethod[]> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'payments/methods/active';

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

  const data: PaymentMethodsResponse = await response.json();
  return data.items;
}
