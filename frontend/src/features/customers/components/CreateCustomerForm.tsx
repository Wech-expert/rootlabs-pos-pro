import { useState } from 'react';
import { Button } from '../../../components/ui';
import { createCustomer } from '../services/customerApi';
import type { Customer } from '../types';

interface CreateCustomerFormProps {
  initialEmail?: string;
  onCreated: (customer: Customer) => void;
  onCancel: () => void;
}

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function CreateCustomerForm({
  initialEmail,
  onCreated,
  onCancel,
}: CreateCustomerFormProps) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState(initialEmail ?? '');
  const [phone, setPhone] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async () => {
    const nameTrimmed = name.trim();
    const emailTrimmed = email.trim();
    const phoneTrimmed = phone.trim();

    if (nameTrimmed.length < 2) {
      setError('El nombre debe tener al menos 2 caracteres.');
      return;
    }

    if (!EMAIL_REGEX.test(emailTrimmed)) {
      setError('Ingresa un correo electrónico válido.');
      return;
    }

    if (phoneTrimmed.length < 5) {
      setError('El teléfono debe tener al menos 5 caracteres.');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const customer = await createCustomer({
        name: nameTrimmed,
        email: emailTrimmed,
        phone: phoneTrimmed,
      });
      onCreated(customer);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : 'Error al crear cliente',
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="mx-create-customer-form">
      <h4 className="mx-create-customer-form__title">Crear cliente</h4>

      <div className="mx-create-customer-form__field">
        <label
          className="mx-create-customer-form__label"
          htmlFor="mx-create-customer-name"
        >
          Nombre
        </label>
        <input
          id="mx-create-customer-name"
          type="text"
          className="mx-create-customer-form__input"
          value={name}
          onChange={(e) => setName((e.target as HTMLInputElement).value)}
          placeholder="Nombre completo"
          disabled={loading}
        />
      </div>

      <div className="mx-create-customer-form__field">
        <label
          className="mx-create-customer-form__label"
          htmlFor="mx-create-customer-phone"
        >
          Teléfono
        </label>
        <input
          id="mx-create-customer-phone"
          type="text"
          className="mx-create-customer-form__input"
          value={phone}
          onChange={(e) => setPhone((e.target as HTMLInputElement).value)}
          placeholder="Teléfono"
          disabled={loading}
        />
      </div>

      <div className="mx-create-customer-form__field">
        <label
          className="mx-create-customer-form__label"
          htmlFor="mx-create-customer-email"
        >
          Email
        </label>
        <input
          id="mx-create-customer-email"
          type="email"
          className="mx-create-customer-form__input"
          value={email}
          onChange={(e) => setEmail((e.target as HTMLInputElement).value)}
          placeholder="Correo electrónico"
          disabled={loading}
        />
      </div>

      {error && (
        <p className="mx-create-customer-form__error">{error}</p>
      )}

      <div className="mx-create-customer-form__actions">
        <Button
          variant="primary"
          size="sm"
          onClick={handleSubmit}
          loading={loading}
        >
          Crear cliente
        </Button>
        <Button
          variant="ghost"
          size="sm"
          onClick={onCancel}
          disabled={loading}
        >
          Cancelar
        </Button>
      </div>
    </div>
  );
}

export default CreateCustomerForm;
