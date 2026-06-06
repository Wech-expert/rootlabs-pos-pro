export interface MxPosProSettings {
  nonce: string;
  root: string;
  posUrl: string;
  posLogoutUrl: string;
  context: 'pos';
  beepEnabled: boolean;
  capabilities: {
    canApplyDiscount: boolean;
    canRefund: boolean;
    canCashCut: boolean;
  };
}

declare global {
  interface Window {
    mxPosProSettings?: MxPosProSettings;
    wpApiSettings?: {
      nonce: string;
      root: string;
    };
  }
}

export {};
