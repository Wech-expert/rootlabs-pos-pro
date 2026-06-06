import { MoneyDisplay } from '../../../components/ui';
import type { CartValidationResponse } from '../types';

interface ValidationResultPanelProps {
  result: CartValidationResponse | null;
}

function ValidationResultPanel({ result }: ValidationResultPanelProps) {
  if (!result) {
    return null;
  }

  const hasGlobalErrors = result.errors.length > 0;
  const invalidItems = result.items.filter((item) => !item.valid);

  if (!hasGlobalErrors && invalidItems.length === 0) {
    return null;
  }

  return (
    <div className="mx-register-validation">
      <p className="mx-register-validation__status mx-register-validation__status--invalid">
        Errores de validación
      </p>

      {hasGlobalErrors && (
        <div className="mx-register-validation__errors">
          {result.errors.map((err, i) => (
            <p key={i} className="mx-register-validation__error">
              {err}
            </p>
          ))}
        </div>
      )}

      {invalidItems.length > 0 && (
        <div className="mx-register-validation__items">
          {invalidItems.map((item, i) => (
            <div key={i} className="mx-register-validation__item">
              <div className="mx-register-validation__item-header">
                <span className="mx-register-validation__item-name">
                  {item.name}
                </span>
                <span className="mx-register-validation__item-status mx-register-validation__item-status--invalid">
                  Inválido
                </span>
              </div>
              <div className="mx-register-validation__item-meta">
                <span className="mx-register-validation__item-sku">{item.sku}</span>
                <span className="mx-register-validation__item-qty">
                  Cant: {item.quantity}
                </span>
                <MoneyDisplay
                  amount={parseFloat(item.line_total)}
                  size="sm"
                />
              </div>
              {item.errors.length > 0 && (
                <div className="mx-register-validation__item-errors">
                  {item.errors.map((err, j) => (
                    <p key={j} className="mx-register-validation__item-error">
                      {err}
                    </p>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

export default ValidationResultPanel;
