import { useState, useEffect, useCallback, useRef } from 'react';
import { searchCustomers, lookupCustomerByEmail } from '../services/customerApi';
import { Button } from '../../../components/ui';
import CreateCustomerForm from './CreateCustomerForm';
import type { Customer } from '../types';

interface CustomerSearchModalProps {
  open: boolean;
  onClose: () => void;
  onSelect: (customer: Customer) => void;
}

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function CustomerSearchModal({
  open,
  onClose,
  onSelect,
}: CustomerSearchModalProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [searched, setSearched] = useState(false);
  const [emailLookupResult, setEmailLookupResult] = useState<Customer | null | undefined>(undefined);
  const [creating, setCreating] = useState(false);
  const [createEmail, setCreateEmail] = useState('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (open) {
      setQuery('');
      setResults([]);
      setError(null);
      setSearched(false);
      setEmailLookupResult(undefined);
      setCreating(false);
      setCreateEmail('');
      setTimeout(() => inputRef.current?.focus(), 100);
    }
  }, [open]);

  useEffect(() => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    const q = query.trim();

    if (q.length < 2) {
      setResults([]);
      setError(null);
      setSearched(false);
      setEmailLookupResult(undefined);
      return;
    }

    const isEmailFormat = EMAIL_REGEX.test(q);

    setLoading(true);
    setError(null);
    setSearched(true);
    setEmailLookupResult(undefined);

    debounceRef.current = setTimeout(async () => {
      try {
        if (isEmailFormat) {
          const lookup = await lookupCustomerByEmail(q);
          setEmailLookupResult(lookup ?? null);
        }

        const items = await searchCustomers(q);
        setResults(items);
        setSearched(true);
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : 'Error al buscar clientes',
        );
        setResults([]);
      } finally {
        setLoading(false);
      }
    }, 300);

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, [query]);

  const handleSelect = useCallback(
    (customer: Customer) => {
      onSelect(customer);
      onClose();
    },
    [onSelect, onClose],
  );

  const handleCreated = useCallback(
    (customer: Customer) => {
      onSelect(customer);
      onClose();
    },
    [onSelect, onClose],
  );

  const handleStartCreate = useCallback(
    (email?: string) => {
      setCreating(true);
      setCreateEmail(email ?? '');
      setError(null);
    },
    [],
  );

  const handleCancelCreate = useCallback(() => {
    setCreating(false);
    setCreateEmail('');
  }, []);

  if (!open) return null;

  if (creating) {
    return (
      <div className="mx-customer-search-modal">
        <CreateCustomerForm
          initialEmail={createEmail || undefined}
          onCreated={handleCreated}
          onCancel={handleCancelCreate}
        />
      </div>
    );
  }

  const showHint = query.trim().length > 0 && query.trim().length < 2;
  const showLoading = loading;
  const showResults = !loading && results.length > 0 && !error;
  const showEmailResult = !loading && emailLookupResult !== undefined && emailLookupResult !== null;
  const showEmailNotFound = !loading && emailLookupResult === null;
  const showEmpty =
    searched && !loading && results.length === 0 && !showEmailResult && !showEmailNotFound && !error;
  const showCreateOption = showEmailNotFound || (showEmpty && !EMAIL_REGEX.test(query.trim()));
  const currentQuery = query.trim();

  return (
    <div className="mx-customer-search-modal">
      <div className="mx-customer-search-modal__field">
        <label
          className="mx-customer-search-modal__label"
          htmlFor="mx-customer-search-input"
        >
          Buscar cliente
        </label>
        <input
          ref={inputRef}
          id="mx-customer-search-input"
          type="text"
          className="mx-customer-search-modal__input"
          value={query}
          onChange={(e) =>
            setQuery((e.target as HTMLInputElement).value)
          }
          placeholder="Buscar por nombre, correo o teléfono"
          aria-label="Buscar cliente"
        />
        {showHint && (
          <p className="mx-customer-search-modal__hint">
            Escribe al menos 2 caracteres
          </p>
        )}
      </div>

      <div className="mx-customer-search-modal__actions-top">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => handleStartCreate()}
        >
          Crear cliente
        </Button>
      </div>

      {showLoading && (
        <p className="mx-customer-search-modal__loading">Buscando...</p>
      )}

      {error && (
        <p className="mx-customer-search-modal__error">{error}</p>
      )}

      {showEmailResult && emailLookupResult && (
        <div className="mx-customer-search-modal__results">
          <p className="mx-customer-search-modal__section-label">
            Coincidencia exacta
          </p>
          <div className="mx-customer-search-modal__result">
            <div className="mx-customer-search-modal__result-info">
              <span className="mx-customer-search-modal__result-name">
                {emailLookupResult.display_name}
              </span>
              <span className="mx-customer-search-modal__result-email">
                {emailLookupResult.email}
              </span>
              {emailLookupResult.phone && (
                <span className="mx-customer-search-modal__result-phone">
                  {emailLookupResult.phone}
                </span>
              )}
            </div>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => handleSelect(emailLookupResult)}
            >
              Seleccionar
            </Button>
          </div>
        </div>
      )}

      {showEmailNotFound && (
        <div className="mx-customer-search-modal__not-found">
          <p className="mx-customer-search-modal__not-found-text">
            No se encontró un cliente con el email {currentQuery}.
          </p>
          <Button
            variant="primary"
            size="sm"
            onClick={() => handleStartCreate(currentQuery)}
          >
            Crear cliente con este email
          </Button>
        </div>
      )}

      {showResults && (
        <div className="mx-customer-search-modal__results">
          {showEmailResult && (
            <p className="mx-customer-search-modal__section-label">
              Otros resultados
            </p>
          )}
          {results.map((customer) => (
            <div
              key={customer.id}
              className="mx-customer-search-modal__result"
            >
              <div className="mx-customer-search-modal__result-info">
                <span className="mx-customer-search-modal__result-name">
                  {customer.display_name}
                </span>
                <span className="mx-customer-search-modal__result-email">
                  {customer.email}
                </span>
                {customer.phone && (
                  <span className="mx-customer-search-modal__result-phone">
                    {customer.phone}
                  </span>
                )}
              </div>
              <Button
                variant="secondary"
                size="sm"
                onClick={() => handleSelect(customer)}
              >
                Seleccionar
              </Button>
            </div>
          ))}
        </div>
      )}

      {showEmpty && (
        <p className="mx-customer-search-modal__empty">
          No se encontraron clientes
        </p>
      )}

      {showCreateOption && !showEmailNotFound && (
        <div className="mx-customer-search-modal__create-hint">
          <p className="mx-customer-search-modal__create-hint-text">
            ¿No encuentras al cliente?
          </p>
          <Button
            variant="primary"
            size="sm"
            onClick={() => handleStartCreate(currentQuery)}
          >
            Crear cliente
          </Button>
        </div>
      )}
    </div>
  );
}

export default CustomerSearchModal;
