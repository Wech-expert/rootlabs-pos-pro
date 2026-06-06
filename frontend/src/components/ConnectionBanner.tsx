import type { ConnectionStatus } from '../hooks/useNetworkStatus';

interface ConnectionBannerProps {
  status: ConnectionStatus;
}

function ConnectionBanner({ status }: ConnectionBannerProps) {
  if (status === 'online') return null;

  let message = '';
  let role: 'status' | 'alert' = 'status';
  let variantClass = '';

  switch (status) {
    case 'offline':
      message = 'Sin conexión. Conservamos el carrito, pero no puedes cobrar hasta reconectar.';
      role = 'alert';
      variantClass = 'mx-connection-banner--offline';
      break;
    case 'checking':
      message = 'Reconectando. Espera a que el sistema confirme conexión antes de cobrar.';
      variantClass = 'mx-connection-banner--checking';
      break;
    case 'degraded':
      message = 'No se puede confirmar conexión con el servidor. El cobro está bloqueado temporalmente.';
      role = 'alert';
      variantClass = 'mx-connection-banner--degraded';
      break;
    default:
      return null;
  }

  return (
    <div
      className={`mx-connection-banner ${variantClass}`}
      role={role}
      aria-live={role === 'alert' ? 'assertive' : 'polite'}
    >
      <p className="mx-connection-banner__text">{message}</p>
    </div>
  );
}

export default ConnectionBanner;
