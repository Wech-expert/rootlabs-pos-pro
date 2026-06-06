import type { ReactNode } from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
export type ButtonSize = 'sm' | 'md' | 'lg';

export type InputType = 'text' | 'number' | 'email' | 'password' | 'search';

export type DrawerPosition = 'left' | 'right';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export type MoneySize = 'sm' | 'md' | 'lg' | 'xl';

export interface DataTableColumn<T> {
  key: string;
  header: string;
  render?: (row: T) => ReactNode;
}
