import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

interface CartOverlayProps {
  children: React.ReactNode;
  onClose: () => void;
}

export default function CartOverlay({ children, onClose }: CartOverlayProps) {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [onClose]);

  const handleBackdropClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  if (!mounted) return null;

  const overlayRoot = document.getElementById('mx-cart-overlay-root');
  if (!overlayRoot) return null;

  const shell = document.querySelector('.mx-pos-shell');

  return (
    <>
      {shell && createPortal(
        <div className="mx-cart-overlay-backdrop" onClick={onClose} />,
        shell
      )}
      {createPortal(
        <div className="mx-cart-overlay" onClick={handleBackdropClick}>
          {/* We add a custom close button here so we don't have to change CashMovementPanel */}
          <button className="mx-ui-drawer__close" onClick={onClose} aria-label="Cerrar">
            <svg
              width="24"
              height="24"
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
          {children}
        </div>,
        overlayRoot,
      )}
    </>
  );
}
