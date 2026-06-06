function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

export async function fetchTicket(saleId: number): Promise<string> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = `${settings.root}sales/${saleId}/ticket`;

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

  const data = await response.json();

  if (!data || typeof data.html !== 'string') {
    throw new Error('Invalid ticket response');
  }

  return data.html;
}

export async function fetchGiftTicket(saleId: number): Promise<string> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = `${settings.root}sales/${saleId}/gift-ticket`;

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

  const data = await response.json();

  if (!data || typeof data.html !== 'string') {
    throw new Error('Invalid gift ticket response');
  }

  return data.html;
}
