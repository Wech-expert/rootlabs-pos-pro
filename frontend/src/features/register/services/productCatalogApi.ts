import type { CatalogProduct, CatalogResponse } from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function fetchCatalogProducts(
  q = '',
  limit = 24,
  options: { signal?: AbortSignal } = {},
): Promise<CatalogProduct[]> {
  const settings = getSettings();
  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = new URL(
    settings.root + 'products/catalog',
    window.location.origin,
  );

  const query = q.trim();
  if (query.length > 0) {
    url.searchParams.set('q', query);
  }
  url.searchParams.set('limit', String(limit));

  const controller = new AbortController();
  const abortFromCaller = () => controller.abort();
  if (options.signal) {
    if (options.signal.aborted) {
      controller.abort();
    } else {
      options.signal.addEventListener('abort', abortFromCaller, { once: true });
    }
  }
  const timeoutId = window.setTimeout(() => controller.abort(), 15000);

  try {
    const response = await fetch(url.toString(), {
      method: 'GET',
      headers: {
        'X-WP-Nonce': settings.nonce,
        Accept: 'application/json',
      },
      cache: 'default',
      credentials: 'same-origin',
      signal: controller.signal,
    });

    if (!response.ok) {
      const body = await response.json().catch(() => null);
      const message =
        body?.message || `Request failed with status ${response.status}`;
      throw new Error(message);
    }

    const data: CatalogResponse = await response.json();

    return data.items ?? [];
  } finally {
    window.clearTimeout(timeoutId);
    if (options.signal) {
      options.signal.removeEventListener('abort', abortFromCaller);
    }
  }
}
