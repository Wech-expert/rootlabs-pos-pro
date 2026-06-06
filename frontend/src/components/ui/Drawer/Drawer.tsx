import { useEffect, type ReactNode } from 'react';
import type { DrawerPosition } from '../types';
import './Drawer.css';

interface DrawerProps {
  open: boolean;
  onClose: () => void;
  position?: DrawerPosition;
  width?: string;
  overlay?: boolean;
  children: ReactNode;
}

function Drawer({
  open,
  onClose,
  position = 'right',
  width = '384px',
  overlay = true,
  children,
}: DrawerProps) {
  useEffect(() => {
    if (!open) return;

    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <>
      {overlay && (
        <div
          className="mx-ui-drawer-overlay"
          onClick={onClose}
          aria-hidden="true"
        />
      )}
      <div
        className={`mx-ui-drawer mx-ui-drawer--${position}`}
        style={{ width }}
        role="dialog"
        aria-modal={overlay ? 'true' : undefined}
      >
        <button
          type="button"
          className="mx-ui-drawer__close"
          onClick={onClose}
          aria-label="Close"
        >
          <svg
            width="20"
            height="20"
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
        <div className="mx-ui-drawer__content">{children}</div>
      </div>
    </>
  );
}

export default Drawer;
