import { apiFetch, FetchWithTimeoutError } from '../../../utils/fetchWithTimeout';
import type { ProcessPaymentRequest, ProcessPaymentResponse, CheckoutRequest, CheckoutResponse } from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function processPayment(
  saleId: number,
  payload: ProcessPaymentRequest,
): Promise<ProcessPaymentResponse> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'sales/' + saleId + '/pay';

  return apiFetch<ProcessPaymentResponse>(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': settings.nonce,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  });
}

export async function completeCheckout(
  payload: CheckoutRequest,
): Promise<CheckoutResponse> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'checkout/complete';

  return apiFetch<CheckoutResponse>(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': settings.nonce,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  }, {
    timeoutMs: 15000,
  });
}

export { FetchWithTimeoutError };
