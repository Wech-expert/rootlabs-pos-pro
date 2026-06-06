import { useNetworkStatus } from './hooks/useNetworkStatus';
import ConnectionBanner from './components/ConnectionBanner';
import Register from './routes/Register';
import CashSessionGate from './features/session/components/CashSessionGate';
import './styles/app.css';

function PosApp() {
  const { status: connectionStatus } = useNetworkStatus();

  return (
    <div className="mx-pos-shell">
      <ConnectionBanner status={connectionStatus} />
      <CashSessionGate>
        <Register connectionStatus={connectionStatus} />
      </CashSessionGate>
    </div>
  );
}

export default PosApp;
