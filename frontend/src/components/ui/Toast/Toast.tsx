import type { ToastType } from '../types';
import './Toast.css';

interface ToastProps {
  type: ToastType;
  message: string;
  onDismiss?: () => void;
  className?: string;
}

function Toast({ type, message, onDismiss, className = '' }: ToastProps) {
  const role = type === 'error' ? 'alert' : 'status';

  return (
    <div
      className={`mx-ui-toast mx-ui-toast--${type} ${className}`.trim()}
      role={role}
      aria-live={type === 'error' ? 'assertive' : 'polite'}
    >
      <span className="mx-ui-toast__message">{message}</span>
      {onDismiss && (
        <button
          type="button"
          className="mx-ui-toast__dismiss"
          onClick={onDismiss}
          aria-label="Dismiss"
        >
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      )}
    </div>
  );
}

export default Toast;
