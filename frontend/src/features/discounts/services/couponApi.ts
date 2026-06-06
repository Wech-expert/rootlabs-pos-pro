import type { CouponSearchResult, CouponSearchResponse } from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function searchCoupons(
  q: string,
  limit = 10,
): Promise<CouponSearchResult[]> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const trimmed = q.trim();
  if (trimmed.length < 2) {
    return [];
  }

  const params = new URLSearchParams({ q: trimmed, limit: String(limit) });
  const url = settings.root + 'coupons/search?' + params.toString();

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

  const data: CouponSearchResponse = await response.json();
  return data.items;
}
