import type { MoneySize } from '../types';
import './MoneyDisplay.css';

interface MoneyDisplayProps {
  amount: number;
  currency?: string;
  size?: MoneySize;
  emphasized?: boolean;
  className?: string;
}

function MoneyDisplay({
  amount,
  currency = 'MXN',
  size = 'md',
  emphasized = false,
  className = '',
}: MoneyDisplayProps) {
  const locale = currency === 'MXN' ? 'es-MX' : 'en-US';

  const formatted = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);

  const classes = [
    'mx-ui-money',
    `mx-ui-money--${size}`,
    emphasized ? 'mx-ui-money--emphasized' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  return <span className={classes}>{formatted}</span>;
}

export default MoneyDisplay;
