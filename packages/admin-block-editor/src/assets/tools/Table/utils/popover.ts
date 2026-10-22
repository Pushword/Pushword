import * as $ from './dom';

/**
 * @typedef {object} PopoverItem
 * @property {string} label - button text
 * @property {string} icon - button icon
 * @property {boolean} confirmationRequired - if true, a confirmation state will be applied on the first click
 * @property {function} hideIf - if provided, item will be hid, if this method returns true
 * @property {function} onClick - click callback
 */
export interface PopoverItem {
  label: string;
  icon: string;
  confirmationRequired?: boolean;
  hideIf?: () => boolean;
  onClick: () => void;
  isActive?: () => boolean;
  separatorBefore?: boolean;
}

/**
 * This cass provides a popover rendering
 */
export default class Popover {
  items: PopoverItem[];
  wrapper: HTMLElement | undefined;
  itemEls: HTMLElement[];
  confirmationLabel: string;

  /**
   * @param {object} options - constructor options
   * @param {PopoverItem[]} options.items - constructor options
   */
  constructor({items, confirmationLabel}: { items: PopoverItem[]; confirmationLabel: string }) {
    this.items = items;
    this.wrapper = undefined;
    this.itemEls = [];
    this.confirmationLabel = confirmationLabel;
  }

  /**
   * Set of CSS classnames used in popover
   *
   * @returns {object}
   */
  static get CSS() {
    return {
      popover: 'tc-popover',
      popoverOpened: 'tc-popover--opened',
      item: 'tc-popover__item',
      itemHidden: 'tc-popover__item--hidden',
      itemConfirmState: 'tc-popover__item--confirm',
      itemIcon: 'tc-popover__item-icon',
      itemLabel: 'tc-popover__item-label'
    };
  }

  /**
   * Returns the popover element
   *
   * @returns {Element}
   */
  render(): HTMLElement {
    this.wrapper = $.make('div', Popover.CSS.popover);
    this.wrapper.setAttribute('role', 'menu');

    this.items.forEach((item, index) => {
      const itemEl = $.make('button', Popover.CSS.item, { type: 'button', tabIndex: -1 });
      itemEl.setAttribute('role', 'menuitem');
      itemEl.classList.toggle('tc-popover__item--destructive', !!item.confirmationRequired);
      itemEl.classList.toggle('tc-popover__item--separated', !!item.separatorBefore);
      if (item.isActive) itemEl.setAttribute('role', 'menuitemradio');
      const icon = $.make('div', Popover.CSS.itemIcon, {
        innerHTML: item.icon
      });
      const label = $.make('div', Popover.CSS.itemLabel, {
        textContent: item.label
      });

      itemEl.dataset.index = String(index);

      itemEl.appendChild(icon);
      itemEl.appendChild(label);

      this.wrapper!.appendChild(itemEl);
      this.itemEls.push(itemEl);
    });

    /**
     * Delegate click
     */
    this.wrapper.addEventListener('click', (event) => {
      this.popoverClicked(event);
    });
    this.wrapper.addEventListener('keydown', (event) => {
      const visible = this.itemEls.filter(el => !el.classList.contains(Popover.CSS.itemHidden));
      const index = visible.indexOf(document.activeElement as HTMLElement);
      let next: number;
      switch (event.key) {
        case 'ArrowDown': next = (index + 1) % visible.length; break;
        case 'ArrowUp': next = (index - 1 + visible.length) % visible.length; break;
        case 'Home': next = 0; break;
        case 'End': next = visible.length - 1; break;
        default: return;
      }
      event.preventDefault();
      event.stopPropagation();
      visible[next]?.focus();
    });

    return this.wrapper;
  }

  /**
   * Popover wrapper click listener
   * Used to delegate clicks in items
   *
   * @returns {void}
   */
  popoverClicked(event: MouseEvent): void {
    const clickedItem = (event.target as Element).closest(`.${Popover.CSS.item}`) as HTMLElement | null;

    /**
     * Clicks outside or between item
     */
    if (!clickedItem) {
      return;
    }

    const clickedItemIndex = Number(clickedItem.dataset.index);
    const item = this.items[clickedItemIndex];

    if (!item) {
      return;
    }

    if (item.confirmationRequired && !this.hasConfirmationState(clickedItem)) {
      this.setConfirmationState(clickedItem);

      return;
    }

    item.onClick();
  }

  /**
   * Enable the confirmation state on passed item
   *
   * @returns {void}
   */
  setConfirmationState(itemEl: HTMLElement): void {
    itemEl.classList.add(Popover.CSS.itemConfirmState);
    itemEl.querySelector(`.${Popover.CSS.itemLabel}`)!.textContent = this.confirmationLabel;
  }

  /**
   * Disable the confirmation state on passed item
   *
   * @returns {void}
   */
  clearConfirmationState(itemEl: HTMLElement): void {
    itemEl.classList.remove(Popover.CSS.itemConfirmState);
    itemEl.querySelector(`.${Popover.CSS.itemLabel}`)!.textContent = this.items[Number(itemEl.dataset.index)]!.label;
  }

  /**
   * Check if passed item has the confirmation state
   *
   * @returns {boolean}
   */
  hasConfirmationState(itemEl: HTMLElement): boolean {
    return itemEl.classList.contains(Popover.CSS.itemConfirmState);
  }

  /**
   * Return an opening state
   *
   * @returns {boolean}
   */
  get opened(): boolean {
    return this.wrapper!.classList.contains(Popover.CSS.popoverOpened);
  }

  /**
   * Opens the popover
   *
   * @returns {void}
   */
  open(): void {
    /**
     * If item provides 'hideIf()' method that returns true, hide item
     */
    this.items.forEach((item, index) => {
      if (item.isActive) {
        const active = item.isActive();
        this.itemEls[index]!.setAttribute('aria-checked', String(active));
        this.itemEls[index]!.classList.toggle('tc-popover__item--active', active);
      }
      if (typeof item.hideIf === 'function') {
        this.itemEls[index]?.classList.toggle(Popover.CSS.itemHidden, item.hideIf());
      }
    });

    this.wrapper!.classList.add(Popover.CSS.popoverOpened);
    this.itemEls.find(el => !el.classList.contains(Popover.CSS.itemHidden))?.focus();
  }

  /**
   * Closes the popover
   *
   * @returns {void}
   */
  close(): void {
    this.wrapper!.classList.remove(Popover.CSS.popoverOpened);
    this.itemEls.forEach(el => {
      this.clearConfirmationState(el);
    });
  }
}
