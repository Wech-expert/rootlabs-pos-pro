import type { ChangeEvent } from 'react';
import type { InputType } from '../types';
import './Input.css';

interface InputProps {
  id: string;
  type?: InputType;
  label?: string;
  value?: string;
  placeholder?: string;
  disabled?: boolean;
  helpText?: string;
  errorText?: string;
  onChange?: (e: ChangeEvent<HTMLInputElement>) => void;
  className?: string;
}

function Input({
  id,
  type = 'text',
  label,
  value,
  placeholder,
  disabled = false,
  helpText,
  errorText,
  onChange,
  className = '',
}: InputProps) {
  const hasError = Boolean(errorText);
  const helpId = helpText ? `${id}-help` : undefined;
  const errorId = hasError ? `${id}-error` : undefined;
  const describedBy = [helpId, errorId].filter(Boolean).join(' ') || undefined;

  return (
    <div className={`mx-ui-input-group ${className}`.trim()}>
      {label && (
        <label className="mx-ui-input__label" htmlFor={id}>
          {label}
        </label>
      )}
      <input
        id={id}
        type={type}
        value={value}
        placeholder={placeholder}
        disabled={disabled}
        onChange={onChange}
        aria-describedby={describedBy}
        aria-invalid={hasError || undefined}
        className={`mx-ui-input__field ${hasError ? 'mx-ui-input__field--error' : ''} ${disabled ? 'mx-ui-input__field--disabled' : ''}`.trim()}
      />
      {helpText && !hasError && (
        <p id={helpId} className="mx-ui-input__help">
          {helpText}
        </p>
      )}
      {errorText && (
        <p id={errorId} className="mx-ui-input__error" role="alert">
          {errorText}
        </p>
      )}
    </div>
  );
}

export default Input;
