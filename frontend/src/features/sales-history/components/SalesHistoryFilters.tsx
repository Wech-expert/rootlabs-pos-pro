import { useState } from 'react';
import type { SaleHistoryFiltersState, CashierOption } from '../types';
import { STATUS_OPTIONS } from '../types';

interface SalesHistoryFiltersProps {
  filters: SaleHistoryFiltersState;
  onChange: (filters: SaleHistoryFiltersState) => void;
  cashiers: CashierOption[];
  showCashierFilter: boolean;
}

const INITIAL_FILTERS: SaleHistoryFiltersState = {
  date_from: '',
  date_to: '',
  status: '',
  cashier_id: null,
  search: '',
};

function SalesHistoryFilters({
  filters,
  onChange,
  cashiers,
  showCashierFilter,
}: SalesHistoryFiltersProps) {
  const [local, setLocal] = useState<SaleHistoryFiltersState>(filters);

  const handleChange = (field: keyof SaleHistoryFiltersState, value: string | number | null) => {
    setLocal((prev) => ({ ...prev, [field]: value }));
  };

  const handleApply = () => {
    onChange(local);
  };

  const handleClear = () => {
    setLocal(INITIAL_FILTERS);
    onChange(INITIAL_FILTERS);
  };

  return (
    <div className="mx-history-filters">
      <input
        type="date"
        value={local.date_from}
        onChange={(e) => handleChange('date_from', e.target.value)}
        className="mx-history-filters__date"
        title="Desde"
      />
      <input
        type="date"
        value={local.date_to}
        onChange={(e) => handleChange('date_to', e.target.value)}
        className="mx-history-filters__date"
        title="Hasta"
      />
      <select
        value={local.status}
        onChange={(e) => handleChange('status', e.target.value)}
        className="mx-history-filters__select"
      >
        <option value="">Todos los estados</option>
        {STATUS_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
      {showCashierFilter && (
        <select
          value={local.cashier_id ?? ''}
          onChange={(e) =>
            handleChange('cashier_id', e.target.value ? Number(e.target.value) : null)
          }
          className="mx-history-filters__select"
        >
          <option value="">Todos los cajeros</option>
          {cashiers.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
      )}
      <input
        type="text"
        value={local.search}
        onChange={(e) => handleChange('search', e.target.value)}
        placeholder="Buscar por venta u orden WC"
        className="mx-history-filters__search"
      />
      <button
        type="button"
        onClick={handleApply}
        className="mx-history-filters__btn mx-history-filters__btn--apply"
      >
        Buscar
      </button>
      <button
        type="button"
        onClick={handleClear}
        className="mx-history-filters__btn mx-history-filters__btn--clear"
      >
        Limpiar
      </button>
    </div>
  );
}

export default SalesHistoryFilters;
