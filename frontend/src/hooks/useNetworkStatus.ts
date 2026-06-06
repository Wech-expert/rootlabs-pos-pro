import { useState, useEffect, useCallback, useRef } from 'react';

export type ConnectionStatus = 'online' | 'offline' | 'checking' | 'degraded';

export interface NetworkState {
  status: ConnectionStatus;
  isOnline: boolean;
  lastOnlineAt: number | null;
  lastOfflineAt: number | null;
  markDegraded: (message?: string) => void;
  markOnline: () => void;
}

export function useNetworkStatus(): NetworkState {
  const [status, setStatus] = useState<ConnectionStatus>(() =>
    typeof navigator !== 'undefined' && navigator.onLine ? 'online' : 'offline',
  );
  const lastOnlineAtRef = useRef<number | null>(
    status === 'online' ? Date.now() : null,
  );
  const lastOfflineAtRef = useRef<number | null>(
    status === 'offline' ? Date.now() : null,
  );

  const updateStatus = useCallback((newStatus: ConnectionStatus) => {
    setStatus((prev) => {
      if (prev === newStatus) return prev;

      if (newStatus === 'online') {
        window.dispatchEvent(new CustomEvent('mx-pos:connection-restored'));
      }

      if (newStatus === 'offline' && prev === 'online') {
        lastOfflineAtRef.current = Date.now();
      }

      if (newStatus === 'online') {
        lastOnlineAtRef.current = Date.now();
      }

      return newStatus;
    });
  }, []);

  useEffect(() => {
    const handleOffline = () => {
      updateStatus('offline');
    };

    const handleOnline = () => {
      updateStatus('checking');
      const delay = setTimeout(() => {
        updateStatus('online');
      }, 500);
      return () => clearTimeout(delay);
    };

    const handleConnectionDegraded = () => {
      updateStatus('degraded');
    };

    window.addEventListener('offline', handleOffline);
    window.addEventListener('online', handleOnline);
    window.addEventListener('mx-pos:connection-degraded', handleConnectionDegraded);

    return () => {
      window.removeEventListener('offline', handleOffline);
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('mx-pos:connection-degraded', handleConnectionDegraded);
    };
  }, [updateStatus]);

  const markDegraded = useCallback((_message?: string) => {
    updateStatus('degraded');
  }, [updateStatus]);

  const markOnline = useCallback(() => {
    updateStatus('online');
  }, [updateStatus]);

  return {
    status,
    isOnline: status === 'online',
    lastOnlineAt: lastOnlineAtRef.current,
    lastOfflineAt: lastOfflineAtRef.current,
    markDegraded,
    markOnline,
  };
}
