import type { ReactNode } from 'react';
import type { DataTableColumn } from '../types';
import './DataTable.css';

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[];
  rows: T[];
  emptyState?: ReactNode;
}

function DataTable<T>({
  columns,
  rows,
  emptyState = 'No data',
}: DataTableProps<T>) {
  if (rows.length === 0) {
    return (
      <div className="mx-ui-table-empty">
        <p className="mx-ui-table-empty__text">{emptyState}</p>
      </div>
    );
  }

  return (
    <div className="mx-ui-table-wrapper">
      <table className="mx-ui-table">
        <thead>
          <tr>
            {columns.map((col) => (
              <th key={col.key} scope="col" className="mx-ui-table__header">
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, rowIdx) => (
            <tr key={rowIdx} className="mx-ui-table__row">
              {columns.map((col) => (
                <td key={col.key} className="mx-ui-table__cell">
                  {col.render
                    ? col.render(row)
                    : ((row as Record<string, ReactNode>)[col.key] ?? '')}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default DataTable;
