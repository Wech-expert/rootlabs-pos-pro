import type { CutXResponse, CutZResponse, CutByIdResponse, CutTicketResponse } from '../types';

type CutResponse = CutXResponse | CutZResponse;

function getSettings() {
  const s = window.mxPosProSettings;
  if (!s || !s.root || !s.nonce) return null;
  return s;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function validateCutResponse(value: unknown): CutResponse {
  if (!isRecord(value) || !isRecord(value.cut) || typeof value.ticket_html !== 'string') {
    throw new Error('La respuesta del corte no contiene los datos requeridos');
  }

  const cut = value.cut;
  const requiredSections = [
    'session',
    'opening',
    'cash_flow',
    'sales',
    'discounts',
    'refunds',
  ];

  if (requiredSections.some((section) => !isRecord(cut[section]))) {
    throw new Error('La respuesta del corte está incompleta');
  }

  if (Array.isArray(cut.by_method) && cut.by_method.length === 0) {
    cut.by_method = {};
  }

  if (!isRecord(cut.by_method)) {
    throw new Error('La respuesta del corte está incompleta');
  }

  return value as unknown as CutResponse;
}

export async function generateCutX(sessionId: number): Promise<CutXResponse> {
  const settings = getSettings();
  if (!settings) throw new Error('RootLabs POS settings not available');

  const url = settings.root + 'sessions/' + sessionId + '/cuts/x';
  const response = await fetch(url, {
    method: 'GET',
    headers: { 'X-WP-Nonce': settings.nonce, Accept: 'application/json' },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message = body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return validateCutResponse(await response.json());
}

export async function generateCutZ(sessionId: number): Promise<CutZResponse> {
  const settings = getSettings();
  if (!settings) throw new Error('RootLabs POS settings not available');

  const url = settings.root + 'sessions/' + sessionId + '/cuts/z';
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': settings.nonce,
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message = body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return validateCutResponse(await response.json());
}

export async function getCutById(cutId: number): Promise<CutByIdResponse> {
  const settings = getSettings();
  if (!settings) throw new Error('RootLabs POS settings not available');

  const url = settings.root + 'cuts/' + cutId;
  const response = await fetch(url, {
    method: 'GET',
    headers: { 'X-WP-Nonce': settings.nonce, Accept: 'application/json' },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message = body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return response.json();
}

export async function getCutTicket(cutId: number): Promise<CutTicketResponse> {
  const settings = getSettings();
  if (!settings) throw new Error('RootLabs POS settings not available');

  const url = settings.root + 'cuts/' + cutId + '/ticket';
  const response = await fetch(url, {
    method: 'GET',
    headers: { 'X-WP-Nonce': settings.nonce, Accept: 'application/json' },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const message = body?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return response.json();
}
