import type {
  RefundOptionsResponse,
  RefundRequest,
  RefundResponse,
  CancelRequest,
  CancelResponse,
} from '../types';

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) {
    return null;
  }
  return s;
}

const REFUND_ERROR_MESSAGE =
  'No se pudo procesar la devolución. Intenta nuevamente o revisa el log.';

async function readJsonResponse<T>(response: Response): Promise<T> {
  const contentType = response.headers.get('content-type') || '';

  if (!contentType.includes('application/json')) {
    const text = await response.text().catch(() => '');
    if (text) {
      console.error('MX POS refund API returned a non-JSON response.', {
        status: response.status,
        body: text.slice(0, 500),
      });
    }
    throw new Error(REFUND_ERROR_MESSAGE);
  }

  return response.json() as Promise<T>;
}

async function assertOk(response: Response): Promise<void> {
  if (response.ok) return;

  let message = REFUND_ERROR_MESSAGE;

  try {
    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      const body = await response.json();
      if (body && body.message) {
        message = body.message;
      } else if (body && body.code) {
        message = body.code + (body.message ? ': ' + body.message : '');
      }
    } else {
      const text = await response.text().catch(() => '');
      if (text) {
        console.error('MX POS refund API non-JSON error response.', {
          status: response.status,
          body: text.slice(0, 1000),
        });
      }
    }
  } catch {
    message = REFUND_ERROR_MESSAGE + ' (HTTP ' + response.status + ')';
  }

  throw new Error(message);
}

export async function fetchRefundOptions(
  saleId: number,
): Promise<RefundOptionsResponse> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'sales/' + saleId + '/refund-options';

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'X-WP-Nonce': settings.nonce,
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
  });

  await assertOk(response);

  const data = await readJsonResponse<RefundOptionsResponse>(response);
  return data;
}

export async function cancelSale(
  saleId: number,
  payload: CancelRequest,
): Promise<CancelResponse> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'sales/' + saleId + '/cancel';

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

  await assertOk(response);

  const data = await readJsonResponse<CancelResponse>(response);
  return data;
}

export async function refundSale(
  saleId: number,
  payload: RefundRequest,
): Promise<RefundResponse> {
  const settings = getSettings();

  if (!settings) {
    throw new Error('RootLabs POS settings not available');
  }

  const url = settings.root + 'sales/' + saleId + '/refund';

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

  await assertOk(response);

  const data = await readJsonResponse<RefundResponse>(response);
  return data;
}
