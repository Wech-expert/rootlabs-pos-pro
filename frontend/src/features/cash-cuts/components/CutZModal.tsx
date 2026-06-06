import { useState, useCallback, useEffect, useRef } from 'react';
import { Modal, Button } from '../../../components/ui';
import { generateCutZ } from '../services/cashCutApi';
import { printCutTicket } from '../utils/printCutTicket';
import CutSummaryView from './CutSummaryView';
import type { CutSummary } from '../types';

interface CutZModalProps {
  open: boolean;
  sessionId: number;
  onClose: () => void;
}

function CutZModal({ open, sessionId, onClose }: CutZModalProps) {
  const [loading, setLoading] = useState(false);
  const [summary, setSummary] = useState<CutSummary | null>(null);
  const [ticketHtml, setTicketHtml] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [generated, setGenerated] = useState(false);
  const requestInFlight = useRef(false);

  const resetState = useCallback(() => {
    requestInFlight.current = false;
    setLoading(false);
    setSummary(null);
    setTicketHtml(null);
    setError(null);
    setGenerated(false);
  }, []);

  const handleClose = useCallback(() => {
    resetState();
    onClose();
  }, [resetState, onClose]);

  const handleGenerate = useCallback(async () => {
    if (generated || requestInFlight.current || !sessionId) return;

    requestInFlight.current = true;
    setLoading(true);
    setError(null);

    try {
      const result = await generateCutZ(sessionId);
      setSummary(result.cut);
      setTicketHtml(result.ticket_html);
      setGenerated(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se pudo generar el comprobante de cierre');
    } finally {
      requestInFlight.current = false;
      setLoading(false);
    }
  }, [sessionId, generated]);

  useEffect(() => {
    if (open && sessionId > 0 && !generated) {
      handleGenerate();
    }
  }, [open, sessionId, generated, handleGenerate]);

  useEffect(() => {
    if (!open) {
      resetState();
    }
  }, [open, resetState]);

  const handlePrint = useCallback(() => {
    if (ticketHtml) {
      printCutTicket(ticketHtml);
    }
  }, [ticketHtml]);

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title="Comprobante de cierre"
      panelClassName="mx-cut-modal-panel"
    >
      <div className="mx-cut-modal">
        {loading && (
          <div className="mx-cut-modal__loading">
            <p>Generando comprobante de cierre...</p>
          </div>
        )}

        {error && !loading && (
          <div className="mx-cut-modal__error" role="alert">
            <p>{error}</p>
            <Button variant="secondary" size="sm" onClick={handleGenerate}>
              Reintentar
            </Button>
          </div>
        )}

        {summary && !loading && (
          <>
            <CutSummaryView summary={summary} />

            <div className="mx-cut-modal__actions">
              <Button variant="secondary" size="md" onClick={handlePrint}>
                Imprimir comprobante de cierre
              </Button>
              <Button variant="primary" size="md" onClick={handleClose}>
                Finalizar
              </Button>
            </div>
          </>
        )}
      </div>
    </Modal>
  );
}

export default CutZModal;
