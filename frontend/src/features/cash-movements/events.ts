export const CASH_MOVEMENTS_CHANGED_EVENT = 'mx-pos:cash-movements-changed';

export function emitCashMovementsChanged(): void {
  window.dispatchEvent(new CustomEvent(CASH_MOVEMENTS_CHANGED_EVENT));
}
