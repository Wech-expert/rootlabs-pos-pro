import { getTicketWindowFeatures, writeTicketAndPrint } from '../../sales/utils/printTicket';

export function printCutTicket(ticketHtml: string): void {
  const win = window.open('', '_blank', getTicketWindowFeatures(ticketHtml));
  if (!win) return;
  writeTicketAndPrint(win, ticketHtml);
}
