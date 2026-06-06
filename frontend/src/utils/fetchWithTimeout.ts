export type FetchErrorType = 'network' | 'timeout' | 'http' | 'parse';

export interface FetchErrorInfo {
  type: FetchErrorType;
  status?: number;
  message: string;
  body?: unknown;
}

export class FetchWithTimeoutError extends Error {
  type: FetchErrorType;
  status?: number;
  body?: unknown;

  constructor(info: FetchErrorInfo) {
    super(info.message);
    this.name = 'FetchWithTimeoutError';
    this.type = info.type;
    this.status = info.status;
    this.body = info.body;
  }
}

interface FetchWithTimeoutOptions {
  timeoutMs?: number;
}

export async function fetchWithTimeout(
  input: RequestInfo,
  init?: RequestInit,
  options: FetchWithTimeoutOptions = {},
): Promise<Response> {
  const { timeoutMs = 15000 } = options;

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

  const requestInit: RequestInit = {
    ...init,
    signal: controller.signal,
  };

  try {
    const response = await fetch(input, requestInit);
    clearTimeout(timeoutId);
    return response;
  } catch (err: unknown) {
    clearTimeout(timeoutId);

    if (err instanceof DOMException && err.name === 'AbortError') {
      throw new FetchWithTimeoutError({
        type: 'timeout',
        message: `La solicitud excedió el tiempo de espera (${timeoutMs / 1000}s).`,
      });
    }

    if (err instanceof TypeError) {
      throw new FetchWithTimeoutError({
        type: 'network',
        message: 'Sin conexión. Verifique su conexión de red.',
      });
    }

    throw err;
  }
}

export async function apiFetch<T = unknown>(
  url: string,
  init?: RequestInit,
  options: FetchWithTimeoutOptions = {},
): Promise<T> {
  const response = await fetchWithTimeout(url, init, options);

  if (!response.ok) {
    let body: unknown = null;
    let message = `Error del servidor (${response.status})`;

    try {
      body = await response.json();
      if (body && typeof body === 'object' && 'message' in (body as Record<string, unknown>)) {
        message = (body as Record<string, string>).message;
      }
    } catch {
      // response body is not JSON
    }

    throw new FetchWithTimeoutError({
      type: 'http',
      status: response.status,
      message,
      body,
    });
  }

  try {
    const data: T = await response.json();
    return data;
  } catch (err: unknown) {
    throw new FetchWithTimeoutError({
      type: 'parse',
      message: 'No se pudo procesar la respuesta del servidor.',
    });
  }
}
