import { useEffect, useState, useCallback } from 'react';
import type { CashSession } from '../types';
import { MoneyDisplay, Button, CartOverlay } from '../../../components/ui';
import CashMovementPanel from '../../cash-movements/components/CashMovementPanel';
import CloseSessionModal from './CloseSessionModal';
import CutXModal from '../../cash-cuts/components/CutXModal';
import CutZModal from '../../cash-cuts/components/CutZModal';
import ShiftKpiModal from './ShiftKpiModal';
import useCurrentCashMovements from '../../cash-movements/hooks/useCurrentCashMovements';
import { CASH_MOVEMENTS_CHANGED_EVENT } from '../../cash-movements/events';
import type { CloseSessionResponse } from '../types';

interface CurrentSessionBannerProps {
  session: CashSession;
}

function CurrentSessionBanner({ session }: CurrentSessionBannerProps) {
  const [showMovements, setShowMovements] = useState(false);
  const [showCloseModal, setShowCloseModal] = useState(false);
  const [showPreCorte, setShowPreCorte] = useState(false);
  const [showKpiModal, setShowKpiModal] = useState(false);
  const [cutZSessionId, setCutZSessionId] = useState<number | null>(null);
  const {
    data: movementData,
    loading: movementsLoading,
    error: movementsError,
    refresh: refreshMovements,
    applyMovement,
  } = useCurrentCashMovements();
  const openingAmount = parseFloat(session.opening_amount);
  const currentCash = parseFloat(movementData.totals.current_cash);
  const cashIn = parseFloat(movementData.totals.manual_cash_in_total || '0');
  const cashOut = parseFloat(movementData.totals.manual_cash_out_total || '0');
  const movementTotals = movementData.totals as typeof movementData.totals & {
    gross_sales?: string | number;
    sales_income?: string | number;
  };

  const grossSales = parseFloat(
    String(movementTotals.gross_sales ?? movementTotals.sales_income ?? '0')
  );

  const openMovements = useCallback(() => setShowMovements(true), []);
  const closeMovements = useCallback(() => setShowMovements(false), []);
  const openCloseModal = useCallback(() => setShowCloseModal(true), []);
  const closeCloseModal = useCallback(() => setShowCloseModal(false), []);
  const openPreCorte = useCallback(() => setShowPreCorte(true), []);
  const closePreCorte = useCallback(() => setShowPreCorte(false), []);
  const openKpiModal = useCallback(() => setShowKpiModal(true), []);
  const closeKpiModal = useCallback(() => setShowKpiModal(false), []);

  const handleClosed = useCallback(
    (result: CloseSessionResponse) => {
      setShowCloseModal(false);
      setCutZSessionId(result.session.id);
    },
    [],
  );

  const handleCutZClose = useCallback(() => {
    setCutZSessionId(null);
    const posUrl = window.mxPosProSettings?.posUrl || '/pos';
    window.location.replace(posUrl);
  }, []);

  useEffect(() => {
    const handleCashMovementsChanged = () => {
      refreshMovements(true);
    };

    window.addEventListener(
      CASH_MOVEMENTS_CHANGED_EVENT,
      handleCashMovementsChanged,
    );

    return () => {
      window.removeEventListener(
        CASH_MOVEMENTS_CHANGED_EVENT,
        handleCashMovementsChanged,
      );
    };
  }, [refreshMovements]);

  return (
    <div className="mx-session-banner">
      <span className="mx-session-banner__indicator">{session.register_name || 'Caja abierta'}</span>
      <span className="mx-session-banner__info">
        {session.employee_name || 'Administrador'}
      </span>
      <span className="mx-session-banner__amount mx-session-banner__amount--current">
        Saldo actual:{' '}
        {movementsLoading ? (
          <span className="mx-session-banner__loading">Calculando...</span>
        ) : (
          <MoneyDisplay
            amount={Number.isNaN(currentCash) ? 0 : currentCash}
            size="sm"
            emphasized
          />
        )}
      </span>
      <span 
        className="mx-session-banner__amount mx-session-banner__amount--current mx-session-banner__amount--clickable"
        onClick={openKpiModal}
        title="Ver KPIs y Ventas del Turno"
      >
        Ventas del turno:{' '}
        {movementsLoading ? (
          <span className="mx-session-banner__loading">Calculando...</span>
        ) : (
          <MoneyDisplay
            amount={Number.isNaN(grossSales) ? 0 : grossSales}
            size="sm"
            emphasized
          />
        )}
      </span>
      {/* Apertura hidden per request */}
      <span className="mx-session-banner__amount">
        Entradas:{' '}
        <MoneyDisplay amount={Number.isNaN(cashIn) ? 0 : cashIn} size="sm" />
      </span>
      <span className="mx-session-banner__amount">
        Salidas:{' '}
        <MoneyDisplay amount={Number.isNaN(cashOut) ? 0 : cashOut} size="sm" />
      </span>
      <Button
        variant="secondary"
        size="sm"
        onClick={openMovements}
        className="mx-session-banner__movements-btn"
      >
        Movimientos de caja
      </Button>

      <Button
        variant="secondary"
        size="sm"
        onClick={() => window.dispatchEvent(new CustomEvent('mx-pos:show-parked-carts'))}
        className="mx-session-banner__parked-btn"
      >
        Carritos guardados
      </Button>

      <Button
        variant="secondary"
        size="sm"
        onClick={() => window.dispatchEvent(new CustomEvent('mx-pos:show-refunds'))}
        className="mx-session-banner__refunds-btn"
      >
        Devoluciones
      </Button>

      <Button
        variant="secondary"
        size="sm"
        onClick={openPreCorte}
        className="mx-session-banner__precorte-btn"
      >
        Pre-corte
      </Button>

      <Button
        variant="primary"
        size="sm"
        onClick={openCloseModal}
        className="mx-session-banner__close-btn"
      >
        Realizar cierre
      </Button>

      {showMovements && (
        <CartOverlay onClose={closeMovements}>
          <CashMovementPanel
            data={movementData}
            loading={movementsLoading}
            error={movementsError}
            onRefresh={refreshMovements}
            onMovementChange={(response) => {
              applyMovement(response.movement, response.totals);
            }}
          />
        </CartOverlay>
      )}

      <CloseSessionModal
        open={showCloseModal}
        sessionId={session.id}
        openingAmount={openingAmount}
        movementTotals={movementData.totals}
        onClosed={handleClosed}
        onCancel={closeCloseModal}
      />

      <CutXModal
        open={showPreCorte}
        sessionId={session.id}
        onClose={closePreCorte}
      />

      <CutZModal
        open={cutZSessionId !== null}
        sessionId={cutZSessionId ?? 0}
        onClose={handleCutZClose}
      />

      <ShiftKpiModal 
        open={showKpiModal}
        sessionId={session.id}
        onClose={closeKpiModal}
      />
    </div>
  );
}

export default CurrentSessionBanner;
