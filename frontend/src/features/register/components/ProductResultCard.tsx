import { Button, MoneyDisplay } from '../../../components/ui';
import type { CatalogProduct, IndexedProduct } from '../types';
import ProductThumbnail from './ProductThumbnail';

interface ProductResultCardProps {
  product: CatalogProduct;
  onAddToCart: (product: IndexedProduct) => void;
  onSelectVariations: (product: CatalogProduct) => void;
}

function getDisplayPrice(product: CatalogProduct): number {
  const sale = product.sale_price ? parseFloat(product.sale_price) : 0;
  const reg = product.regular_price ? parseFloat(product.regular_price) : 0;
  if (sale > 0) return sale;
  if (reg > 0) return reg;
  return 0;
}

function getVariableStartPrice(product: CatalogProduct): number {
  const min = product.min_price ? parseFloat(product.min_price) : 0;
  if (min > 0) return min;
  return 0;
}

function toIndexedProduct(product: CatalogProduct): IndexedProduct {
  return {
    product_id: product.product_id,
    variation_id: product.variation_id ?? null,
    sku: product.sku,
    name: product.name,
    type: product.type,
    stock_quantity: product.stock_quantity,
    stock_status: product.stock_status,
    regular_price: product.regular_price,
    sale_price: product.sale_price,
    image_url: product.image_url,
    image_alt: product.image_alt,
  };
}

function ProductResultCard({
  product,
  onAddToCart,
  onSelectVariations,
}: ProductResultCardProps) {
  const isOutOfStock = product.stock_status === 'outofstock';
  const isVariableParent = product.variations.length > 0 || product.type === 'variable';
  const canAdd = !isOutOfStock && !isVariableParent;
  const displayPrice = getDisplayPrice(product);
  const variableStartPrice = getVariableStartPrice(product);
  const buttonLabel = isOutOfStock
    ? 'Sin stock'
    : isVariableParent
      ? 'Ver variaciones'
      : 'Agregar';

  return (
    <div className="mx-register-card">
      <div className="mx-register-card__main">
        <ProductThumbnail
          imageUrl={product.image_url}
          imageAlt={product.image_alt}
          name={product.name}
        />
        <div className="mx-register-card__info">
          <p className="mx-register-card__name">{product.name}</p>
          <p className="mx-register-card__sku">{product.sku}</p>
          <div className="mx-register-card__meta">
            <span className="mx-register-card__type">
              {isVariableParent ? 'Con variaciones' : 'Producto'}
            </span>
            {isOutOfStock && (
              <span className="mx-register-card__outofstock">Sin stock</span>
            )}
            {isVariableParent && (
              <span className="mx-register-card__stock">
                {product.variations.length} variaciones
              </span>
            )}
            {!isOutOfStock &&
              !isVariableParent &&
              product.stock_quantity != null && (
                <span className="mx-register-card__stock">
                  Stock: {product.stock_quantity}
                </span>
              )}
          </div>
        </div>
      </div>
      <div className="mx-register-card__bottom">
        {isVariableParent && variableStartPrice > 0 ? (
          <span className="mx-register-card__price-label">
            Desde <MoneyDisplay amount={variableStartPrice} size="sm" />
          </span>
        ) : (
          <MoneyDisplay amount={displayPrice} size="sm" />
        )}
        <Button
          variant="primary"
          size="sm"
          disabled={!canAdd && !isVariableParent}
          onClick={(event) => {
            event.preventDefault();
            event.stopPropagation();

            if (canAdd) {
              onAddToCart(toIndexedProduct(product));
              return;
            }

            if (isVariableParent) {
              onSelectVariations(product);
            }
          }}
        >
          {buttonLabel}
        </Button>
      </div>
    </div>
  );
}

export default ProductResultCard;
