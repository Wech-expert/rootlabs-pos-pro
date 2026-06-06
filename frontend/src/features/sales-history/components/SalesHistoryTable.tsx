import type { ReactNode } from 'react';
import { DataTable, MoneyDisplay, Button } from '../../../components/ui';
import type { DataTableColumn } from '../../../components/ui/types';
import type { SaleHistoryItem } from '../types';
import { STATUS_LABELS } from '../types';

interface SalesHistoryTableProps {
  items: SaleHistoryItem[];
  onViewDetail: (saleId: number) => void;
  emptyState?: ReactNode;
}

function SalesHistoryTable({
  items,
  onViewDetail,
  emptyState,
}: SalesHistoryTableProps) {
  const columns: DataTableColumn<SaleHistoryItem>[] = [
    {
      key: 'id',
      header: 'Venta',
      render: (row) => (
        <span className="mx-history-table__id">#{row.id}</span>
      ),
    },
    {
      key: 'wc_order_id',
      header: 'Orden WC',
      render: (row) => (
        <span className="mx-history-table__order">#{row.wc_order_id}</span>
      ),
    },
    {
      key: 'created_at',
      header: 'Fecha',
      render: (row) => (
        <span className="mx-history-table__date">
          {formatDate(row.created_at)}
        </span>
      ),
    },
    {
      key: 'cashier_name',
      header: 'Cajero',
    },
    {
      key: 'display_status',
      header: 'Estado',
      render: (row) => (
        <span className={`mx-history-table__status mx-history-table__status--${row.display_status}`}>
          {STATUS_LABELS[row.display_status] || row.display_status}
        </span>
      ),
    },
    {
      key: 'payment_method',
      header: 'Método',
      render: (row) => (
        <span className="mx-history-table__method">
          {row.payment_method_label || '—'}
        </span>
      ),
    },
    {
      key: 'total',
      header: 'Total',
      render: (row) => (
        <MoneyDisplay amount={parseFloat(row.total)} size="sm" />
      ),
    },
    {
      key: 'net_total',
      header: 'Neto',
      render: (row) => (
        <MoneyDisplay amount={parseFloat(row.net_total)} size="sm" />
      ),
    },
    {
      key: 'actions',
      header: '',
      render: (row) => (
        <div className="mx-history-table__actions">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => onViewDetail(row.id)}
          >
            Ver detalle
          </Button>
        </div>
      ),
    },
  ];

  return (
    <DataTable
      columns={columns}
      rows={items}
      emptyState={emptyState}
    />
  );
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '';
  const d = new Date(dateStr.replace(' ', 'T'));
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('es-MX', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default SalesHistoryTable;
