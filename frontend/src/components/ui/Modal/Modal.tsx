import { useEffect, useCallback, useState, type ReactNode, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import './Modal.css';

interface ModalProps {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  children: ReactNode;
  panelClassName?: string;
  overlayClassName?: string;
}

function Modal({ open, onClose, title, description, children, panelClassName = '', overlayClassName = '' }: ModalProps) {
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const handleKeyDown = useCallback(
    (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    },
    [onClose],
  );

  useEffect(() => {
    if (!open) return;

    const handler = (e: globalThis.KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [open, onClose]);

  if (!open || !mounted) return null;

  const shell = document.querySelector('.mx-pos-shell');
  const portalRoot = shell ?? document.body;

  const titleId = 'mx-ui-modal-title';
  const descId = description ? 'mx-ui-modal-desc' : undefined;
  const overlayClasses = ['mx-ui-modal-overlay', overlayClassName].filter(Boolean).join(' ');
  const panelClasses = ['mx-ui-modal-panel', panelClassName].filter(Boolean).join(' ');

  return createPortal(
    <div
      className={overlayClasses}
      onKeyDown={handleKeyDown}
    >
      <div
        className="mx-ui-modal-backdrop"
        onClick={onClose}
        aria-hidden="true"
      />
      <div
        className={panelClasses}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={descId}
        tabIndex={-1}
      >
        <div className="mx-ui-modal__header">
          <h2 id={titleId} className="mx-ui-modal__title">
            {title}
          </h2>
          <button
            type="button"
            className="mx-ui-modal__close"
            onClick={onClose}
            aria-label="Cerrar"
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
        </div>
        {description && (
          <p id={descId} className="mx-ui-modal__description">
            {description}
          </p>
        )}
        <div className="mx-ui-modal__body">{children}</div>
      </div>
    </div>,
    portalRoot,
  );
}

export default Modal;
