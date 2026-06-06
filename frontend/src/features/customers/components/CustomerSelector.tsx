import { useState } from 'react';
import { Button } from '../../../components/ui';
import CustomerSearchModal from './CustomerSearchModal';
import EditCustomerModal from './EditCustomerModal';
import PurchaseHistoryModal from './PurchaseHistoryModal';
import type { Customer } from '../types';

interface CustomerSelectorProps {
  customer: Customer | null;
  onSelect: (customer: Customer) => void;
  onClear: () => void;
  onUpdated?: (customer: Customer) => void;
}

function CustomerSelector({ customer, onSelect, onClear, onUpdated }: CustomerSelectorProps) {
  const [showSearch, setShowSearch] = useState(false);
  const [showEdit, setShowEdit] = useState(false);
  const [showHistory, setShowHistory] = useState(false);

  const handleUpdated = (updated: Customer) => {
    if (onUpdated) {
      onUpdated(updated);
    }
  };

  return (
    <div className="mx-customer-selector">
      <span className="mx-customer-selector__label">Cliente</span>
      <div className="mx-customer-selector__value">
        {customer ? (
          <span className="mx-customer-selector__name">
            Cliente: {customer.display_name}
          </span>
        ) : (
          <span className="mx-customer-selector__placeholder">
            Cliente de mostrador
          </span>
        )}
      </div>
      <div className="mx-customer-selector__actions">
        {customer ? (
          <>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setShowEdit(true)}
            >
              Editar
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setShowHistory(true)}
            >
              Historial
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={onClear}
            >
              Quitar cliente
            </Button>
          </>
        ) : (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setShowSearch(true)}
          >
            Buscar cliente
          </Button>
        )}
      </div>

      <CustomerSearchModal
        open={showSearch}
        onClose={() => setShowSearch(false)}
        onSelect={onSelect}
      />

      {customer && onUpdated && (
        <>
          <EditCustomerModal
            open={showEdit}
            customer={customer}
            onClose={() => setShowEdit(false)}
            onUpdated={handleUpdated}
          />
          <PurchaseHistoryModal
            open={showHistory}
            customer={customer}
            onClose={() => setShowHistory(false)}
          />
        </>
      )}
    </div>
  );
}

export default CustomerSelector;
