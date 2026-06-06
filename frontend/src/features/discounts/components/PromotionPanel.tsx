import CouponInput from './CouponInput';
import type { AppliedCoupon } from '../types';

interface PromotionPanelProps {
  appliedCoupon: AppliedCoupon | null;
  couponError: string | null;
  onApplyCoupon: (coupon: AppliedCoupon) => void;
  onClearCoupon: () => void;
  children?: React.ReactNode;
}

function PromotionPanel({
  appliedCoupon,
  couponError,
  onApplyCoupon,
  onClearCoupon,
  children,
}: PromotionPanelProps) {
  return (
    <div className="mx-promotion-panel">
      <CouponInput
        appliedCoupon={appliedCoupon}
        couponError={couponError}
        onApply={onApplyCoupon}
        onClear={onClearCoupon}
      />

      {children}
    </div>
  );
}

export default PromotionPanel;
