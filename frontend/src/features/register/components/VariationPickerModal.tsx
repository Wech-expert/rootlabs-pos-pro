import { Button, Modal, MoneyDisplay } from '../../../components/ui';
import type { CatalogProduct, IndexedProduct } from '../types';
import ProductThumbnail from './ProductThumbnail';

interface VariationPickerModalProps {
  product: CatalogProduct | null;
  open: boolean;
  onClose: () => void;
  onAddToCart: (product: IndexedProduct) => void;
}

function getDisplayPrice(product: IndexedProduct): number {
  const sale = product.sale_price ? parseFloat(product.sale_price) : 0;
  const reg = product.regular_price ? parseFloat(product.regular_price) : 0;
  if (sale > 0) return sale;
  if (reg > 0) return reg;
  return 0;
}

function composeVariationCartName(parentName: string, variationName: string): string {
  const parent = parentName.trim();
  const variation = variationName.trim();

  if (!parent) return variation;
  if (!variation) return parent;

  if (variation.toLowerCase().includes(parent.toLowerCase())) {
    return variation;
  }

  return `${parent} - ${variation}`;
}


function VariationPickerModal({
  product,
  open,
  onClose,
  onAddToCart,
}: VariationPickerModalProps) {
  if (!product) {
    return null;
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={product.name}
      description="Selecciona una variación vendible para agregarla al carrito."
    >
      <div className="mx-register-variations">
        {product.variations.length === 0 && (
          <div className="mx-register-variations__empty">
            No hay variaciones disponibles para este producto.
          </div>
        )}

        {product.variations.map((variation) => {
          const isOutOfStock = variation.stock_status === 'outofstock';

          return (
            <div
              className="mx-register-variation"
              key={`${variation.product_id}-${variation.variation_id ?? 0}`}
            >
              <div className="mx-register-variation__main">
                <ProductThumbnail
                  imageUrl={variation.image_url}
                  imageAlt={variation.image_alt}
                  name={variation.name}
                  size="row"
                />
                <div className="mx-register-variation__info">
                  <p className="mx-register-variation__name">
                    {variation.name}
                  </p>
                  <p className="mx-register-variation__sku">
                    {variation.sku}
                  </p>
                  <div className="mx-register-variation__meta">
                    {isOutOfStock ? (
                      <span className="mx-register-card__outofstock">
                        Sin stock
                      </span>
                    ) : (
                      <span className="mx-register-card__stock">
                        Stock:{' '}
                        {variation.stock_quantity === null
                          ? 'N/D'
                          : variation.stock_quantity}
                      </span>
                    )}
                  </div>
                </div>
              </div>
              <div className="mx-register-variation__actions">
                <MoneyDisplay amount={getDisplayPrice(variation)} size="sm" />
                <Button
                  variant="primary"
                  size="sm"
                  disabled={isOutOfStock}
                  onClick={() => {
                    onAddToCart({
                      ...variation,
                      name: composeVariationCartName(product.name, variation.name),
                    });
                    onClose();
                  }}
                >
                  {isOutOfStock ? 'Sin stock' : 'Agregar'}
                </Button>
              </div>
            </div>
          );
        })}
      </div>
    </Modal>
  );
}

export default VariationPickerModal;
