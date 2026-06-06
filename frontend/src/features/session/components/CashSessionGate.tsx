import { useEffect, useState, useCallback } from 'react';
import { getCurrentSession } from '../services/cashSessionApi';
import OpenSessionForm from './OpenSessionForm';
import CurrentSessionBanner from './CurrentSessionBanner';
import type { CashSession } from '../types';

interface CashSessionGateProps {
  children: React.ReactNode;
}

function CashSessionGate({ children }: CashSessionGateProps) {
  const [loading, setLoading] = useState(true);
  const [session, setSession] = useState<CashSession | null>(null);
  const [fetchError, setFetchError] = useState<string | null>(null);
  const posLogoutUrl = window.mxPosProSettings?.posLogoutUrl ?? '/pos';

  const fetchSession = useCallback(async () => {
    setLoading(true);
    setFetchError(null);
    try {
      const data = await getCurrentSession();
      setSession(data.session);
    } catch (err) {
      setFetchError(
        err instanceof Error ? err.message : 'No se pudo cargar la sesión',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchSession();
  }, [fetchSession]);

  const handleOpened = useCallback(() => {
    fetchSession();
  }, [fetchSession]);

  if (loading) {
    return (
      <div className="mx-session-gate">
        <p className="mx-session-gate__loading">Cargando sesión…</p>
      </div>
    );
  }

  if (session) {
    return (
      <>
        <CurrentSessionBanner session={session} />
        <main className="mx-pos-shell__main">
          {children}
        </main>
      </>
    );
  }

  return (
    <div className="mx-session-gate">
      <div className="mx-session-gate__header">
        <p className="mx-session-gate__eyebrow">RootLabs POS</p>
        <h1 className="mx-session-gate__title">Caja</h1>
      </div>
      <div className="mx-session-gate__body">
        <p className="mx-session-gate__description">
          Abre una sesión de caja para iniciar la operación.
        </p>
        {fetchError && (
          <p className="mx-session-gate__error">{fetchError}</p>
        )}
        <OpenSessionForm onOpened={handleOpened} />
        <a
          className="mx-session-gate__back"
          href={posLogoutUrl}
        >
          Cerrar sesión
        </a>
      </div>
    </div>
  );
}

export default CashSessionGate;
