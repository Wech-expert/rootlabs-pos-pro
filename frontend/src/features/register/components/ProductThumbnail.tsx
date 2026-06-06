interface ProductThumbnailProps {
  imageUrl: string | null;
  imageAlt: string;
  name: string;
  size?: 'card' | 'row';
}

function ProductThumbnail({
  imageUrl,
  imageAlt,
  name,
  size = 'card',
}: ProductThumbnailProps) {
  const className = `mx-register-thumbnail mx-register-thumbnail--${size}`;

  if (!imageUrl) {
    return (
      <div className={className} aria-hidden="true">
        <span>No image</span>
      </div>
    );
  }

  return (
    <div className={className}>
      <img src={imageUrl} alt={imageAlt || name} loading="lazy" />
    </div>
  );
}

export default ProductThumbnail;
