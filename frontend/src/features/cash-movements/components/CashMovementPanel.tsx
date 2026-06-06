import { useState, useCallback } from 'react';
import { reverseCashMovement } from '../services/cashMovementApi';
import CashMovementSummary from './CashMovementSummary';
import CashMovementForm from './CashMovementForm';
import CashMovementList from './CashMovementList';
import CashMovementReverseDialog from './CashMovementReverseDialog';
import type {
  CashMovement,
  CreateCashMovementResponse,
  CurrentCashMovementsResponse,
  ReverseCashMovementResponse,
} from '../types';

interface CashMovementPanelProps {
  data: CurrentCashMovementsResponse;
  loading: boolean;
  error: string | null;
  onRefresh: (silent?: boolean) => void;
  onMovementChange: (
    response: CreateCashMovementResponse | ReverseCashMovementResponse,
  ) => void;
}

function CashMovementPanel({
  data,
  loading,
  error,
  onRefresh,
  onMovementChange,
}: CashMovementPanelProps) {
  const [movementToReverse, setMovementToReverse] =
    useState<CashMovement | null>(null);
  const [reverseError, setReverseError] = useState<string | null>(null);
  const [reversing, setReversing] = useState(false);
  const currentCash = parseFloat(data.totals.current_cash);

  const filteredItems = data.items.filter((item) => {
    const reason = item.reason || '';
    if (reason.startsWith('Venta POS')) return false;
    if (reason.startsWith('Cambio POS')) return false;
    return true;
  });

  const handleCreated = useCallback(
    (response: CreateCashMovementResponse) => {
      onMovementChange(response);
      onRefresh(true);
    },
    [onMovementChange, onRefresh],
  );

  const closeReverseDialog = useCallback(() => {
    if (reversing) return;

    setMovementToReverse(null);
    setReverseError(null);
  }, [reversing]);

  const handleReverse = useCallback(async (reason: string) => {
    if (!movementToReverse) return;

    setReverseError(null);
    setReversing(true);
    try {
      const response = await reverseCashMovement(movementToReverse.id, {
        reason: reason || undefined,
      });
      onMovementChange(response);
      setMovementToReverse(null);
      onRefresh(true);
    } catch (err) {
      setReverseError(
        err instanceof Error ? err.message : 'No se pudo anular el movimiento',
      );
    } finally {
      setReversing(false);
    }
  }, [movementToReverse, onMovementChange, onRefresh]);

  return (
    <div className="mx-cash-movement-panel">
      <h2 className="mx-cash-movement-panel__title">Movimientos de caja</h2>

      {loading && (
        <p className="mx-cash-movement-panel__loading">Cargando...</p>
      )}

      {error && (
        <div className="mx-cash-movement-panel__error">
          <p>{error}</p>
        </div>
      )}

      {!loading && !error && (
        <>
          <CashMovementSummary totals={data.totals} />
          <CashMovementForm
            currentCash={Number.isNaN(currentCash) ? 0 : currentCash}
            onCreated={handleCreated}
          />
          <CashMovementList
            items={filteredItems}
            onReverse={(movement) => {
              setReverseError(null);
              setMovementToReverse(movement);
            }}
          />
          <CashMovementReverseDialog
            open={movementToReverse !== null}
            movement={movementToReverse}
            submitting={reversing}
            error={reverseError}
            onClose={closeReverseDialog}
            onConfirm={handleReverse}
          />
        </>
      )}
    </div>
  );
}

export default CashMovementPanel;
