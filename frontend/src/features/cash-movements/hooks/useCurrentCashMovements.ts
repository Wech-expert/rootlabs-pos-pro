import { useCallback, useEffect, useState } from 'react';
import { getCurrentCashMovements } from '../services/cashMovementApi';
import type {
  CashMovement,
  CashMovementTotals,
  CurrentCashMovementsResponse,
} from '../types';

const EMPTY_TOTALS: CashMovementTotals = {
  cash_in: '0.0000',
  cash_out: '0.0000',
  net: '0.0000',
  current_cash: '0.0000',
};

const EMPTY_RESPONSE: CurrentCashMovementsResponse = {
  has_open_session: false,
  session_id: null,
  opening_amount: null,
  items: [],
  totals: EMPTY_TOTALS,
};

function useCurrentCashMovements() {
  const [data, setData] = useState<CurrentCashMovementsResponse>(EMPTY_RESPONSE);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async (silent = false) => {
    setError(null);
    if (!silent) {
      setLoading(true);
    }
    try {
      const response = await getCurrentCashMovements();
      setData(response);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'No se pudieron cargar los movimientos de caja',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  const setItems = useCallback((items: CashMovement[]) => {
    setData((current) => ({ ...current, items }));
  }, []);

  const setTotals = useCallback((totals: CashMovementTotals) => {
    setData((current) => ({ ...current, totals }));
  }, []);

  const applyMovement = useCallback(
    (movement: CashMovement, totals: CashMovementTotals) => {
      setData((current) => {
        const exists = current.items.some((item) => item.id === movement.id);
        const items = exists
          ? current.items.map((item) =>
              item.id === movement.id ? movement : item,
            )
          : [movement, ...current.items];

        return {
          ...current,
          items,
          totals,
        };
      });
    },
    [],
  );

  return {
    data,
    loading,
    error,
    refresh,
    setItems,
    setTotals,
    applyMovement,
  };
}

export default useCurrentCashMovements;
