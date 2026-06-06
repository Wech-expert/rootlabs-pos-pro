import { useCallback, useEffect, useRef, useState } from 'react';
import { fetchCatalogProducts } from '../services/productCatalogApi';
import type { CatalogProduct } from '../types';

interface CacheEntry {
  items: CatalogProduct[];
  timestamp: number;
}

interface LiveProductSearchState {
  results: CatalogProduct[];
  loading: boolean;
  refreshing: boolean;
  error: string | null;
  searched: boolean;
}

const CACHE_TTL_MS = 60000;
const DEBOUNCE_MS = 150;
const cache = new Map<string, CacheEntry>();

function cacheKey(query: string, limit: number): string {
  return `${query.trim().toLocaleLowerCase()}::${limit}`;
}

function isAbortError(err: unknown): boolean {
  return err instanceof DOMException && err.name === 'AbortError';
}

export function clearProductSearchCache(): void {
  cache.clear();
}

export function useLiveProductSearch(query: string, limit = 24): LiveProductSearchState {
  const [results, setResults] = useState<CatalogProduct[]>([]);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [searched, setSearched] = useState(false);
  const requestIdRef = useRef(0);
  const abortRef = useRef<AbortController | null>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const queryRef = useRef(query);
  const resultsRef = useRef(results);

  useEffect(() => {
    queryRef.current = query;
  }, [query]);

  useEffect(() => {
    resultsRef.current = results;
  }, [results]);

  const runSearch = useCallback(
    async (rawQuery: string, silent = false) => {
      const trimmed = rawQuery.trim();

      if (trimmed.length === 1) {
        if (!silent) {
          abortRef.current?.abort();
          setResults([]);
          setSearched(true);
          setError(null);
          setLoading(false);
          setRefreshing(false);
        }
        return;
      }

      const key = cacheKey(trimmed, limit);
      const cached = cache.get(key);
      const hasFreshCache =
        cached !== undefined && Date.now() - cached.timestamp < CACHE_TTL_MS;

      if (hasFreshCache && !silent) {
        setResults(cached.items);
        setSearched(trimmed.length >= 2);
        setError(null);
        setLoading(false);
        setRefreshing(true);
      }

      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;
      const requestId = requestIdRef.current + 1;
      requestIdRef.current = requestId;
      const hasVisibleResults = hasFreshCache || resultsRef.current.length > 0;

      if (!silent && !hasVisibleResults) {
        setLoading(true);
      }

      if (!silent && hasVisibleResults) {
        setRefreshing(true);
      }

      if (!silent) {
        setError(null);
      }

      setSearched(trimmed.length >= 2);

      try {
        const items = await fetchCatalogProducts(trimmed, limit, {
          signal: controller.signal,
        });

        if (
          requestId !== requestIdRef.current ||
          controller.signal.aborted ||
          trimmed !== queryRef.current.trim()
        ) {
          return;
        }

        cache.set(key, { items, timestamp: Date.now() });
        setResults(items);
        setError(null);
      } catch (err) {
        if (isAbortError(err) || requestId !== requestIdRef.current || silent) {
          return;
        }

        setError(err instanceof Error ? err.message : 'Error al cargar productos');

        if (!hasVisibleResults) {
          setResults([]);
        }
      } finally {
        if (requestId === requestIdRef.current) {
          setLoading(false);
          setRefreshing(false);
        }
      }
    },
    [limit],
  );

  useEffect(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
    }

    timerRef.current = setTimeout(() => {
      void runSearch(query);
    }, DEBOUNCE_MS);

    return () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current);
      }
    };
  }, [query, runSearch]);

  useEffect(() => {
    const refresh = () => {
      if (document.visibilityState !== 'visible') {
        return;
      }

      void runSearch(queryRef.current, true);
    };

    const invalidateAndRefresh = () => {
      clearProductSearchCache();
      refresh();
    };

    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('mx-pos:catalog-changed', invalidateAndRefresh);

    const intervalId = window.setInterval(refresh, 60000);

    return () => {
      abortRef.current?.abort();
      window.removeEventListener('focus', refresh);
      document.removeEventListener('visibilitychange', refresh);
      window.removeEventListener('mx-pos:catalog-changed', invalidateAndRefresh);
      window.clearInterval(intervalId);
    };
  }, [runSearch]);

  return {
    results,
    loading,
    refreshing,
    error,
    searched,
  };
}
