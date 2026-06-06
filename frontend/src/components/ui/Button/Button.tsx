import type { MouseEvent, ReactNode } from 'react';
import type { ButtonVariant, ButtonSize } from '../types';
import './Button.css';

interface ButtonProps {
  children: ReactNode;
  variant?: ButtonVariant;
  size?: ButtonSize;
  disabled?: boolean;
  loading?: boolean;
  type?: 'button' | 'submit' | 'reset';
  onClick?: (e: MouseEvent<HTMLButtonElement>) => void;
  className?: string;
}

function Button({
  children,
  variant = 'primary',
  size = 'md',
  disabled = false,
  loading = false,
  type = 'button',
  onClick,
  className = '',
}: ButtonProps) {
  const classes = [
    'mx-ui-button',
    `mx-ui-button--${variant}`,
    `mx-ui-button--${size}`,
    disabled ? 'mx-ui-button--disabled' : '',
    loading ? 'mx-ui-button--loading' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <button
      type={type}
      className={classes}
      disabled={disabled || loading}
      onClick={onClick}
      aria-disabled={disabled || loading}
      aria-busy={loading}
    >
      {loading && <span className="mx-ui-button__spinner" aria-hidden="true" />}
      <span className={loading ? 'mx-ui-button__label--hidden' : ''}>
        {children}
      </span>
    </button>
  );
}

export default Button;
