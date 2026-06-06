interface CardPaymentFormProps {
  reference: string;
  onReferenceChange: (value: string) => void;
  disabled?: boolean;
}

function CardPaymentForm({ reference, onReferenceChange, disabled = false }: CardPaymentFormProps) {
  return (
    <div className="mx-card-payment-form">
      <div className="mx-card-payment-form__input-group">
        <label className="mx-card-payment-form__label" htmlFor="mx-payment-card-ref">
          Referencia (opcional)
        </label>
        <div className="mx-card-payment-form__input-row">
          <input
            id="mx-payment-card-ref"
            type="text"
            className="mx-card-payment-form__field"
            value={reference}
            onChange={(e) => onReferenceChange(e.target.value)}
            placeholder="Referencia"
            maxLength={100}
            disabled={disabled}
            autoFocus
          />
        </div>
        <p className="mx-card-payment-form__help">
          Solo para referencia interna. No se almacenan datos de tarjeta.
        </p>
      </div>
    </div>
  );
}

export default CardPaymentForm;
