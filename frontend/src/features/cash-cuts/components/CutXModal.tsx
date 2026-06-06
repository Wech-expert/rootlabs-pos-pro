import { useState, useCallback, useEffect } from 'react';
import { Modal, Button } from '../../../components/ui';
import { generateCutX } from '../services/cashCutApi';
import { printCutTicket } from '../utils/printCutTicket';
import CutSummaryView from './CutSummaryView';
import CashDenominationCounter from '../../session/components/CashDenominationCounter';
import type { CutSummary } from '../types';

interface CutXModalProps {
  open: boolean;
  sessionId: number;
  onClose: () => void;
}

function CutXModal({ open, sessionId, onClose }: CutXModalProps) {
  const [loading, setLoading] = useState(false);
  const [summary, setSummary] = useState<CutSummary | null>(null);
  const [ticketHtml, setTicketHtml] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const fetchCutX = useCallback(async () => {
    setLoading(true);
    setError(null);
    setSummary(null);
    setTicketHtml(null);

    try {
      const result = await generateCutX(sessionId);
      setSummary(result.cut);
      setTicketHtml(result.ticket_html);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se pudo generar el corte');
    } finally {
      setLoading(false);
    }
  }, [sessionId]);

  useEffect(() => {
    if (open) {
      fetchCutX();
    }
  }, [open, fetchCutX]);

  const handlePrint = useCallback(() => {
    if (ticketHtml) {
      printCutTicket(ticketHtml);
    }
  }, [ticketHtml]);

  const handleClose = useCallback(() => {
    setSummary(null);
    setTicketHtml(null);
    setError(null);
    onClose();
  }, [onClose]);

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title="Pre-corte"
      panelClassName="mx-cut-modal-panel mx-cut-x-modal-panel"
    >
      <div className="mx-cut-modal">
        {loading && (
          <div className="mx-cut-modal__loading">
            <p>Calculando pre-corte...</p>
          </div>
        )}

        {!loading && !summary && !error && (
          <div className="mx-cut-modal__loading">
            <p>Resumen de operaciones al momento. Puede generarse cuantas veces sea necesario. No cierra la sesión.</p>
          </div>
        )}

        {error && !loading && (
          <div className="mx-cut-modal__error" role="alert">
            <p>{error}</p>
            <Button variant="secondary" size="sm" onClick={fetchCutX}>
              Reintentar
            </Button>
          </div>
        )}

        {summary && !loading && (
          <>
            <div className="mx-cut-x-modal__columns">
              <CutSummaryView summary={summary} />

              <div className="mx-cut-x-modal__counter">
                <div className="mx-cut-x-modal__counter-header">
                  <h3 className="mx-cut-x-modal__counter-title">Conteo de apoyo</h3>
                  <p className="mx-cut-x-modal__counter-hint">
                    Este conteo es informativo. No modifica ni guarda el pre-corte.
                  </p>
                </div>

                <CashDenominationCounter
                  expectedAmount={parseFloat(summary.expected_cash) || 0}
                  idPrefix="mx-cut-x-denomination"
                />
              </div>
            </div>

            <div className="mx-cut-modal__actions">
              <Button variant="secondary" size="md" onClick={handlePrint}>
                Imprimir pre-corte
              </Button>
              <Button variant="primary" size="md" onClick={handleClose}>
                Cerrar
              </Button>
            </div>
          </>
        )}
      </div>
    </Modal>
  );
}

export default CutXModal;
