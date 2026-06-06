import { useState, useEffect, useRef, useCallback } from 'react';
import { Button } from '../../../components/ui';
import { searchCoupons } from '../services/couponApi';
import type { CouponSearchResult, AppliedCoupon } from '../types';

interface CouponInputProps {
  appliedCoupon: AppliedCoupon | null;
  couponError: string | null;
  onApply: (coupon: AppliedCoupon) => void;
  onClear: () => void;
}

function CouponInput({
  appliedCoupon,
  couponError,
  onApply,
  onClear,
}: CouponInputProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<CouponSearchResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showDropdown, setShowDropdown] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const trimmed = query.trim();

  useEffect(() => {
    if (appliedCoupon) {
      setQuery('');
      setResults([]);
      setShowDropdown(false);
    }
  }, [appliedCoupon]);

  useEffect(() => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    if (trimmed.length < 2) {
      setResults([]);
      setShowDropdown(false);
      setSearching(false);
      return;
    }

    setSearching(true);
    setError(null);

    debounceRef.current = setTimeout(async () => {
      try {
        const items = await searchCoupons(trimmed);
        setResults(items);
        setShowDropdown(true);
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : 'Error al buscar cupones',
        );
        setResults([]);
      } finally {
        setSearching(false);
      }
    }, 300);

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, [query]);

  const handleSelect = useCallback(
    (result: CouponSearchResult) => {
      onApply({
        code: result.code,
        discount_type: result.discount_type,
        amount: result.amount,
        description: result.description,
      });
      setQuery('');
      setResults([]);
      setShowDropdown(false);
    },
    [onApply],
  );

  if (appliedCoupon) {
    return (
      <div className="mx-coupon-input mx-coupon-input--applied">
        <span className="mx-coupon-input__label">Cupón</span>
        <div className="mx-coupon-input__badge">
          <span className="mx-coupon-input__code">{appliedCoupon.code}</span>
          {appliedCoupon.description && (
            <span className="mx-coupon-input__desc">
              {appliedCoupon.description}
            </span>
          )}
        </div>
        <Button variant="ghost" size="sm" onClick={onClear}>
          Quitar cupón
        </Button>
      </div>
    );
  }

  return (
    <div className="mx-coupon-input">
      <label className="mx-coupon-input__label" htmlFor="mx-coupon-search">
        Cupón
      </label>
      <div className="mx-coupon-input__field">
        <input
          ref={inputRef}
          id="mx-coupon-search"
          type="text"
          className="mx-coupon-input__input"
          value={query}
          onChange={(e) => setQuery((e.target as HTMLInputElement).value)}
          onFocus={() => {
            if (results.length > 0) {
              setShowDropdown(true);
            }
          }}
          onBlur={() => {
            setTimeout(() => setShowDropdown(false), 200);
          }}
          placeholder="Buscar cupón por código"
          aria-label="Buscar cupón"
        />
        {searching && (
          <span className="mx-coupon-input__spinner" aria-busy="true" />
        )}
      </div>

      {couponError && (
        <p className="mx-coupon-input__error">{couponError}</p>
      )}

      {error && <p className="mx-coupon-input__error">{error}</p>}

      {showDropdown && results.length === 0 && !searching && (
        <p className="mx-coupon-input__empty">
          No encontramos cupones vigentes
        </p>
      )}

      {showDropdown && results.length > 0 && (
        <div className="mx-coupon-input__dropdown">
          {results.map((r) => (
            <button
              key={r.id}
              type="button"
              className="mx-coupon-input__option"
              onMouseDown={(e) => e.preventDefault()}
              onClick={() => handleSelect(r)}
            >
              <span className="mx-coupon-input__option-code">
                {r.code}
              </span>
              <span className="mx-coupon-input__option-detail">
                {r.discount_type === 'percent'
                  ? `${r.amount}%`
                  : `$${r.amount}`}
                {r.description ? ` — ${r.description}` : ''}
              </span>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

export default CouponInput;
