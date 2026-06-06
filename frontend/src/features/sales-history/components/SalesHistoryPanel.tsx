import { useState, useEffect, useCallback } from 'react';
import { Drawer } from '../../../components/ui';
import SalesHistoryFilters from './SalesHistoryFilters';
import SalesHistoryTable from './SalesHistoryTable';
import SaleDetailDrawer from './SaleDetailDrawer';
import { fetchSalesHistory, fetchCashiers } from '../services/saleHistoryApi';
import type {
  SaleHistoryItem,
  SaleHistoryFiltersState,
  PaginationState,
  CashierOption,
} from '../types';

interface SalesHistoryPanelProps {
  onClose: () => void;
  onRefund?: (saleId: number) => void;
}

const INITIAL_FILTERS: SaleHistoryFiltersState = {
  date_from: '',
  date_to: '',
  status: '',
  cashier_id: null,
  search: '',
};

function SalesHistoryPanel({ onClose, onRefund }: SalesHistoryPanelProps) {
  const [filters, setFilters] = useState<SaleHistoryFiltersState>(INITIAL_FILTERS);
  const [page, setPage] = useState(1);
  const [items, setItems] = useState<SaleHistoryItem[]>([]);
  const [pagination, setPagination] = useState<PaginationState>({
    page: 1,
    per_page: 20,
    total: 0,
    total_pages: 0,
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [cashiers, setCashiers] = useState<CashierOption[]>([]);
  const [selectedSaleId, setSelectedSaleId] = useState<number | null>(null);
  const [showCashierFilter, setShowCashierFilter] = useState(false);

  const canRefund =
    (window.mxPosProSettings?.capabilities?.canRefund ?? false) ||
    window.mxPosProSettings?.capabilities?.canApplyDiscount
      ? true
      : false;

  useEffect(() => {
    if (canRefund) {
      fetchCashiers()
        .then((data) => {
          setCashiers(data.cashiers);
          setShowCashierFilter(true);
        })
        .catch(() => {
          setShowCashierFilter(false);
        });
    }
  }, [canRefund]);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const data = await fetchSalesHistory(filters, page, 20);
      setItems(data.items);
      setPagination(data.pagination);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : 'No se pudo cargar el historial',
      );
    } finally {
      setLoading(false);
    }
  }, [filters, page]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleFiltersChange = useCallback(
    (newFilters: SaleHistoryFiltersState) => {
      setFilters(newFilters);
      setPage(1);
    },
    [],
  );

  const handlePageChange = useCallback((newPage: number) => {
    setPage(newPage);
  }, []);

  const handleViewDetail = useCallback((saleId: number) => {
    setSelectedSaleId(saleId);
  }, []);

  const handleCloseDetail = useCallback(() => {
    setSelectedSaleId(null);
  }, []);

  return (
    <div className="mx-history-panel">
      <div className="mx-history-panel__header">
        <h2 className="mx-history-panel__title">Historial de ventas</h2>
        <button
          type="button"
          onClick={onClose}
          className="mx-history-panel__close"
          aria-label="Cerrar"
        >
          ×
        </button>
      </div>

      <SalesHistoryFilters
        filters={filters}
        onChange={handleFiltersChange}
        cashiers={cashiers}
        showCashierFilter={showCashierFilter}
      />

      {loading && (
        <p className="mx-history-panel__loading">Cargando historial…</p>
      )}

      {error && !loading && (
        <div className="mx-history-panel__error" role="alert">
          <p>{error}</p>
          <button
            type="button"
            onClick={loadData}
            className="mx-history-panel__retry"
          >
            Reintentar
          </button>
        </div>
      )}

      {!loading && !error && (
        <>
          <SalesHistoryTable
            items={items}
            onViewDetail={handleViewDetail}
            emptyState="No hay ventas con estos filtros"
          />

          {pagination.total_pages > 1 && (
            <div className="mx-history-panel__pagination">
              <button
                type="button"
                disabled={pagination.page <= 1}
                onClick={() => handlePageChange(pagination.page - 1)}
                className="mx-history-panel__page-btn"
              >
                Anterior
              </button>
              <span className="mx-history-panel__page-info">
                {pagination.page} de {pagination.total_pages}
              </span>
              <button
                type="button"
                disabled={pagination.page >= pagination.total_pages}
                onClick={() => handlePageChange(pagination.page + 1)}
                className="mx-history-panel__page-btn"
              >
                Siguiente
              </button>
            </div>
          )}
        </>
      )}

      {selectedSaleId !== null && (
        <Drawer
          open
          onClose={handleCloseDetail}
          position="right"
          width="520px"
        >
          <SaleDetailDrawer
            saleId={selectedSaleId}
            onClose={handleCloseDetail}
            onRefund={onRefund}
          />
        </Drawer>
      )}
    </div>
  );
}

export default SalesHistoryPanel;
