function findCustomerSelector(target: EventTarget | null): Element | null {
  if (!(target instanceof Element)) {
    return null;
  }

  return target.closest('.mx-customer-selector');
}

function clickSearchCustomerButton(selector: Element | null): void {
  if (!selector) {
    return;
  }

  const buttons = selector.querySelectorAll<HTMLButtonElement>('.mx-customer-selector__actions button');

  for (const button of buttons) {
    const buttonText = (button.textContent || '').trim().toLowerCase();

    if (buttonText.includes('buscar cliente')) {
      button.click();
      return;
    }
  }
}

function makeCustomerValueFocusable(): void {
  const values = document.querySelectorAll<HTMLElement>('.mx-customer-selector__value');

  values.forEach((value) => {
    if (!value.hasAttribute('tabindex')) {
      value.setAttribute('tabindex', '0');
    }

    if (!value.hasAttribute('role')) {
      value.setAttribute('role', 'button');
    }

    if (!value.hasAttribute('aria-label')) {
      value.setAttribute('aria-label', 'Buscar cliente');
    }
  });
}

function installCustomerSelectorClickBehavior(): void {
  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const value = target?.closest('.mx-customer-selector__value') ?? null;

    if (!value) {
      return;
    }

    const selector = findCustomerSelector(value);

    if (!selector) {
      return;
    }

    event.preventDefault();
    clickSearchCustomerButton(selector);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    const target = event.target instanceof Element ? event.target : null;
    const value = target?.closest('.mx-customer-selector__value') ?? null;

    if (!value) {
      return;
    }

    const selector = findCustomerSelector(value);

    if (!selector) {
      return;
    }

    event.preventDefault();
    clickSearchCustomerButton(selector);
  });

  makeCustomerValueFocusable();

  const observer = new MutationObserver(() => {
    makeCustomerValueFocusable();
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
  });
}

function enhanceCustomerSearchModal(modal: Element): void {
  const modalElement = modal as HTMLElement;

  if (modalElement.dataset.rootlabsCustomerCancelEnhanced === 'yes') {
    return;
  }

  const actions = modal.querySelector('.mx-customer-search-modal__actions-top');

  if (!actions) {
    return;
  }

  const cancel = document.createElement('button');
  cancel.type = 'button';
  cancel.className = 'mx-customer-search-modal__cancel';
  cancel.textContent = 'Cancelar';

  cancel.addEventListener('click', () => {
    modalElement.dataset.rootlabsCustomerCancelled = 'yes';

    const input = modal.querySelector<HTMLInputElement>('input');
    input?.blur();
  });

  actions.appendChild(cancel);
  modalElement.dataset.rootlabsCustomerCancelEnhanced = 'yes';
}

function enhanceAllCustomerSearchModals(): void {
  document.querySelectorAll('.mx-customer-search-modal').forEach(enhanceCustomerSearchModal);
}

function installCustomerSearchCancelAction(): void {
  document.addEventListener(
    'click',
    (event) => {
      const target = event.target instanceof Element ? event.target : null;
      const value = target?.closest('.mx-customer-selector__value') ?? null;

      if (!value) {
        return;
      }

      const selector = value.closest('.mx-customer-selector');

      if (!selector) {
        return;
      }

      selector
        .querySelectorAll<HTMLElement>('.mx-customer-search-modal[data-rootlabs-customer-cancelled="yes"]')
        .forEach((modal) => {
          modal.dataset.rootlabsCustomerCancelled = 'no';
        });

      window.setTimeout(enhanceAllCustomerSearchModals, 50);
      window.setTimeout(enhanceAllCustomerSearchModals, 250);
    },
    true,
  );

  enhanceAllCustomerSearchModals();

  const observer = new MutationObserver(() => {
    enhanceAllCustomerSearchModals();
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
  });
}

function promotionHasActiveState(panel: Element): boolean {
  const text = (panel.textContent || '').toLowerCase();

  return (
    text.includes('quitar cupón') ||
    text.includes('descuento aplicado') ||
    text.includes('quitar descuento')
  );
}

function promotionSummary(panel: Element): string {
  return promotionHasActiveState(panel) ? 'Con descuento' : 'Sin descuento';
}

function updatePromotionPanelState(panel: HTMLElement, header: HTMLButtonElement, summary: HTMLElement, icon: HTMLElement): void {
  const hasActiveState = promotionHasActiveState(panel);
  const collapsed = panel.dataset.rootlabsPromoCollapsed === 'yes';

  summary.textContent = promotionSummary(panel);
  panel.dataset.rootlabsPromoHasActiveState = hasActiveState ? 'yes' : 'no';

  header.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  icon.textContent = collapsed ? '⌄' : '⌃';
}

function enhancePromotionPanel(panel: Element): void {
  const panelElement = panel as HTMLElement;

  if (panelElement.dataset.rootlabsPromoEnhanced === 'yes') {
    return;
  }

  const coupon = panel.querySelector(':scope > .mx-coupon-input');
  const discount = panel.querySelector(':scope > .mx-discount-panel');

  if (!coupon && !discount) {
    return;
  }

  const header = document.createElement('button');
  header.type = 'button';
  header.className = 'mx-promotion-panel__rootlabs-header';
  header.setAttribute('aria-expanded', 'false');

  const title = document.createElement('span');
  title.className = 'mx-promotion-panel__rootlabs-title';
  title.textContent = 'Descuentos';

  const summary = document.createElement('span');
  summary.className = 'mx-promotion-panel__rootlabs-summary';
  summary.textContent = promotionSummary(panel);

  const icon = document.createElement('span');
  icon.className = 'mx-promotion-panel__rootlabs-icon';
  icon.setAttribute('aria-hidden', 'true');
  icon.textContent = '⌄';

  header.appendChild(title);
  header.appendChild(summary);
  header.appendChild(icon);

  panel.insertBefore(header, panel.firstChild);

  panelElement.dataset.rootlabsPromoEnhanced = 'yes';
  panelElement.dataset.rootlabsPromoCollapsed = promotionHasActiveState(panel) ? 'no' : 'yes';

  updatePromotionPanelState(panelElement, header, summary, icon);

  header.addEventListener('click', () => {
    const isCollapsed = panelElement.dataset.rootlabsPromoCollapsed === 'yes';
    panelElement.dataset.rootlabsPromoCollapsed = isCollapsed ? 'no' : 'yes';
    updatePromotionPanelState(panelElement, header, summary, icon);
  });
}

function enhanceAllPromotionPanels(): void {
  document.querySelectorAll('.mx-promotion-panel').forEach(enhancePromotionPanel);
}

function installPromotionPanelToggle(): void {
  enhanceAllPromotionPanels();

  const observer = new MutationObserver(() => {
    enhanceAllPromotionPanels();

    document
      .querySelectorAll<HTMLElement>('.mx-promotion-panel[data-rootlabs-promo-enhanced="yes"]')
      .forEach((panel) => {
        const header = panel.querySelector<HTMLButtonElement>('.mx-promotion-panel__rootlabs-header');
        const summary = panel.querySelector<HTMLElement>('.mx-promotion-panel__rootlabs-summary');
        const icon = panel.querySelector<HTMLElement>('.mx-promotion-panel__rootlabs-icon');

        if (header && summary && icon) {
          updatePromotionPanelState(panel, header, summary, icon);
        }
      });
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
    characterData: true,
  });
}

function installPosUiEnhancements(): void {
  if (typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }

  if ((window as Window & { rootlabsPosUiEnhancementsInstalled?: boolean }).rootlabsPosUiEnhancementsInstalled) {
    return;
  }

  (window as Window & { rootlabsPosUiEnhancementsInstalled?: boolean }).rootlabsPosUiEnhancementsInstalled = true;

  installCustomerSelectorClickBehavior();
  installCustomerSearchCancelAction();
  installPromotionPanelToggle();
}

installPosUiEnhancements();
