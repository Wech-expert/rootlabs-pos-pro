import { useState, useEffect } from 'react';
import { Modal, Button } from '../../../components/ui';
import { updateCustomer } from '../services/customerApi';
import type { Customer } from '../types';

interface EditCustomerModalProps {
  open: boolean;
  customer: Customer;
  onClose: () => void;
  onUpdated: (customer: Customer) => void;
}

function EditCustomerModal({
  open,
  customer,
  onClose,
  onUpdated,
}: EditCustomerModalProps) {
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      setName(customer.display_name);
      setPhone(customer.phone ?? '');
      setError(null);
    }
  }, [open, customer.display_name, customer.phone]);

  const handleSave = async () => {
    const nameTrimmed = name.trim();
    const phoneTrimmed = phone.trim();

    if (nameTrimmed.length < 2) {
      setError('El nombre debe tener al menos 2 caracteres.');
      return;
    }

    if (phoneTrimmed.length < 5) {
      setError('El teléfono debe tener al menos 5 caracteres.');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const updated = await updateCustomer(customer.id, {
        name: nameTrimmed,
        phone: phoneTrimmed,
      });
      onUpdated(updated);
      onClose();
    } catch (err) {
      setError(
        err instanceof Error ? err.message : 'Error al guardar cambios',
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Editar cliente"
    >
      <div className="mx-edit-customer-modal">
        <div className="mx-edit-customer-modal__field">
          <label
            className="mx-edit-customer-modal__label"
            htmlFor="mx-edit-customer-name"
          >
            Nombre
          </label>
          <input
            id="mx-edit-customer-name"
            type="text"
            className="mx-edit-customer-modal__input"
            value={name}
            onChange={(e) => setName((e.target as HTMLInputElement).value)}
            disabled={loading}
          />
        </div>

        <div className="mx-edit-customer-modal__field">
          <label
            className="mx-edit-customer-modal__label"
            htmlFor="mx-edit-customer-phone"
          >
            Teléfono
          </label>
          <input
            id="mx-edit-customer-phone"
            type="text"
            className="mx-edit-customer-modal__input"
            value={phone}
            onChange={(e) => setPhone((e.target as HTMLInputElement).value)}
            disabled={loading}
          />
        </div>

        <div className="mx-edit-customer-modal__field">
          <label
            className="mx-edit-customer-modal__label"
            htmlFor="mx-edit-customer-email"
          >
            Email
          </label>
          <input
            id="mx-edit-customer-email"
            type="email"
            className="mx-edit-customer-modal__input mx-edit-customer-modal__input--readonly"
            value={customer.email}
            readOnly
            disabled
          />
          <p className="mx-edit-customer-modal__hint">
            El email no se puede modificar desde el POS.
          </p>
        </div>

        {error && (
          <p className="mx-edit-customer-modal__error">{error}</p>
        )}

        <div className="mx-edit-customer-modal__actions">
          <Button
            variant="primary"
            size="sm"
            onClick={handleSave}
            loading={loading}
          >
            Guardar
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={onClose}
            disabled={loading}
          >
            Cancelar
          </Button>
        </div>
      </div>
    </Modal>
  );
}

export default EditCustomerModal;
