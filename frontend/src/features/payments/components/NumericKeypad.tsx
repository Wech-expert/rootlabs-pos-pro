interface NumericKeypadProps {
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
}

type KeypadKey =
  | { label: string; value: string; ariaLabel: string }
  | { label: string; action: 'clear' | 'backspace'; ariaLabel: string };

const KEYS: KeypadKey[] = [
  { label: '1', value: '1', ariaLabel: 'Uno' },
  { label: '2', value: '2', ariaLabel: 'Dos' },
  { label: '3', value: '3', ariaLabel: 'Tres' },
  { label: '4', value: '4', ariaLabel: 'Cuatro' },
  { label: '5', value: '5', ariaLabel: 'Cinco' },
  { label: '6', value: '6', ariaLabel: 'Seis' },
  { label: '7', value: '7', ariaLabel: 'Siete' },
  { label: '8', value: '8', ariaLabel: 'Ocho' },
  { label: '9', value: '9', ariaLabel: 'Nueve' },
  { label: '0', value: '0', ariaLabel: 'Cero' },
  { label: '.', value: '.', ariaLabel: 'Punto decimal' },
  { label: '00', value: '00', ariaLabel: 'Doble cero' },
  { label: 'Limpiar', action: 'clear', ariaLabel: 'Limpiar importe' },
  { label: 'Borrar', action: 'backspace', ariaLabel: 'Borrar último dígito' },
];

function normalizeAmount(value: string): string {
  const normalized = value.replace(',', '.').replace(/[^\d.]/g, '');
  const [integerPart = '', ...decimalParts] = normalized.split('.');
  const integer = integerPart.replace(/^0+(?=\d)/, '');

  if (decimalParts.length === 0) {
    return integer;
  }

  return `${integer || '0'}.${decimalParts.join('').slice(0, 2)}`;
}

function appendValue(currentValue: string, nextValue: string): string {
  if (nextValue === '.' && currentValue.includes('.')) {
    return currentValue;
  }

  if (nextValue === '.' && currentValue === '') {
    return '0.';
  }

  if (nextValue === '00' && currentValue === '') {
    return '0';
  }

  return normalizeAmount(`${currentValue}${nextValue}`);
}

function NumericKeypad({ value, onChange, disabled = false }: NumericKeypadProps) {
  const handlePress = (key: KeypadKey) => {
    if ('action' in key) {
      if (key.action === 'clear') {
        onChange('');
        return;
      }

      onChange(value.slice(0, -1));
      return;
    }

    onChange(appendValue(value, key.value));
  };

  return (
    <div className="mx-payment-keypad" aria-label="Teclado numérico">
      {KEYS.map((key) => (
        <button
          key={'action' in key ? key.action : key.value}
          type="button"
          className={[
            'mx-payment-keypad__key',
            'action' in key ? 'mx-payment-keypad__key--utility' : '',
            'action' in key ? `mx-payment-keypad__key--${key.action}` : '',
          ].filter(Boolean).join(' ')}
          onClick={() => handlePress(key)}
          disabled={disabled}
          aria-label={key.ariaLabel}
        >
          {key.label}
        </button>
      ))}
    </div>
  );
}

export default NumericKeypad;
