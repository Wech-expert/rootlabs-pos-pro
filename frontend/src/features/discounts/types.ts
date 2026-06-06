export type DiscountType = 'percentage' | 'fixed';

export interface DiscountInput {
  type: DiscountType;
  value: string;
  reason: string;
}

export interface ValidatedDiscount {
  type: DiscountType;
  value: string;
  reason: string;
  amount: string;
}

export interface AppliedCoupon {
  code: string;
  discount_type: string;
  amount: string;
  description?: string;
}

export interface CouponSearchResult {
  id: number;
  code: string;
  discount_type: string;
  amount: string;
  description: string;
  date_expires: string | null;
  usage_limit: number;
  usage_count: number;
  minimum_amount: string;
  maximum_amount: string;
  individual_use: boolean;
  exclude_sale_items: boolean;
}

export interface CouponSearchResponse {
  items: CouponSearchResult[];
}
