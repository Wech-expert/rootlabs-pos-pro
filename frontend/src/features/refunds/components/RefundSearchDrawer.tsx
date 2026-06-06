import { useState, useEffect, useCallback } from 'react';
import { Button, MoneyDisplay } from '../../../components/ui';
import {
  fetchRecentRefundableSales,
  lookupSales,
} from '../../sales-history/services/saleHistoryApi';
import type { SaleLookupItem } from '../../sales-history/types';

interface RefundSearchDrawerProps {
  onSelectSale: (saleId: number) => void;
  showTitle?: boolean;
}

function getStatusLabel(status: string): string {
  if (status === 'completed') return 'Completado';
  if (status === 'processing') return 'Procesando';
  if (status === 'partially_refunded') return 'Parcial';
  if (status === 'refunded') return 'Reembolsado';
  if (status === 'cancelled') return 'Cancelado';

  return status;
}

function RefundSearchDrawer({ onSelectSale, showTitle = true }: RefundSearchDrawerProps) {
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(false);
  const [recentLoading, setRecentLoading] = useState(false);
  const [recentSales, setRecentSales] = useState<SaleLookupItem[]>([]);
  const [results, setResults] = useState<SaleLookupItem[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [recentError, setRecentError] = useState<string | null>(null);
  const [hasSearched, setHasSearched] = useState(false);

  useEffect(() => {
    let cancelled = false;

    setRecentLoading(true);
    setRecentError(null);

    fetchRecentRefundableSales(5)
      .then((data) => {
        if (!cancelled) {
          setRecentSales(data.items);
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setRecentError(
            err instanceof Error ? err.message : 'No se pudieron cargar las ventas recientes',
          );
          setRecentSales([]);
        }
      })
      .finally(() => {
        if (!cancelled) {
          setRecentLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const handleSearch = useCallback(async () => {
    const trimmed = query.trim();
    if (trimmed === '') return;

    setLoading(true);
    setError(null);
    setHasSearched(true);

    try {
      const data = await lookupSales(trimmed);
      setResults(data.items);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : 'No se pudo realizar la busqueda',
      );
      setResults(null);
    } finally {
      setLoading(false);
    }
  }, [query]);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Enter') {
        handleSearch();
      }
    },
    [handleSearch],
  );

  const displayItems = hasSearched ? results : recentSales;
  const emptyMessage = hasSearched
    ? 'No se encontro una venta con ese dato.'
    : 'No hay ventas recientes disponibles para devolucion.';
  const loadingMessage = hasSearched ? 'Buscando...' : 'Cargando ventas recientes...';

  return (
    <div className="mx-refund-search">
      {showTitle && (
        <div className="mx-refund-search__header">
          <h2 className="mx-refund-search__title">Devoluciones</h2>
        </div>
      )}

      <div className="mx-refund-search__form">
        <input
          type="text"
          className="mx-refund-search__input"
          value={query}
          onChange={(e) => setQuery((e.target as HTMLInputElement).value)}
          onKeyDown={handleKeyDown}
          placeholder="Numero de orden o ID de venta"
          disabled={loading}
          autoFocus
        />
        <Button
          variant="primary"
          size="md"
          onClick={handleSearch}
          disabled={loading || query.trim() === ''}
          loading={loading}
        >
          Buscar
        </Button>
      </div>

      {!hasSearched && !recentLoading && !recentError && recentSales.length > 0 && (
        <div className="mx-refund-search__section-head">
          <span className="mx-refund-search__section-title">Ultimas ventas</span>
          <span className="mx-refund-search__section-count">
            {recentSales.length} disponibles
          </span>
        </div>
      )}

      {(loading || recentLoading) && (
        <div className="mx-refund-search__loading">
          {loadingMessage}
        </div>
      )}

      {error && (
        <div className="mx-refund-search__error" role="alert">
          {error}
        </div>
      )}

      {!hasSearched && recentError && (
        <div className="mx-refund-search__error" role="alert">
          {recentError}
        </div>
      )}

      {!loading &&
        !recentLoading &&
        !error &&
        !recentError &&
        displayItems !== null &&
        displayItems.length === 0 && (
        <div className="mx-refund-search__empty">
          {emptyMessage}
        </div>
      )}

      {displayItems !== null && displayItems.length > 0 && (
        <div className="mx-refund-search__results">
          {displayItems.map((item) => (
            <div key={item.id} className="mx-refund-search__card">
              <div className="mx-refund-search__card-info">
                <div className="mx-refund-search__card-head">
                  <span className="mx-refund-search__card-kicker">Venta #{item.id}</span>
                  <span className="mx-refund-search__status">
                    {getStatusLabel(item.status)}
                  </span>
                </div>
                <div className="mx-refund-search__card-row">
                  <span className="mx-refund-search__card-label">Orden WC</span>
                  <span className="mx-refund-search__card-value">#{item.order_number}</span>
                </div>
                <div className="mx-refund-search__card-row">
                  <span className="mx-refund-search__card-label">Fecha</span>
                  <span className="mx-refund-search__card-value">{item.created_at}</span>
                </div>
                <div className="mx-refund-search__card-row">
                  <span className="mx-refund-search__card-label">Total</span>
                  <MoneyDisplay amount={parseFloat(item.total)} size="md" emphasized />
                </div>
              </div>
              {item.can_refund && (
                <div className="mx-refund-search__card-action">
                  <Button
                    variant="primary"
                    size="sm"
                    onClick={() => onSelectSale(item.id)}
                  >
                    Devolver
                  </Button>
                </div>
              )}
              {!item.can_refund && (
                <div className="mx-refund-search__card-action">
                  <span className="mx-refund-search__card-note">
                    {item.status === 'refunded'
                      ? 'Ya reembolsado'
                      : item.status === 'cancelled'
                        ? 'Cancelado'
                        : 'No disponible'}
                  </span>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default RefundSearchDrawer;
