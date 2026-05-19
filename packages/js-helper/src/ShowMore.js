/**
 * ShowMore - Expand/collapse content blocks
 *
 * Features:
 * - [x] Open/close show-more blocks
 *    1. Page loads → blocks are fully visible (no max-height - best for SEO)
 *    2. On scroll → `addClassForNormalUser` adds `max-h-[250px]` via `data-acinb`, collapsing them
 *       Exception → blocks previously opened by user (in localStorage) stay open
 * - [x] Auto-open when URL contains hash pointing inside block
 * - [x] Auto-open when page loads with scroll position (browser back/refresh)
 * - [x] Auto-open on hash change (SPA navigation)
 * - [x] Ctrl+F: Auto-open when browser finds text in collapsed block
 */

const STORAGE_KEY = 'showmore_opened'
// Must match the Tailwind class on the collapsed wrapper (max-h-64 = 16rem)
// applied via data-acinb in show_more.html.twig. Mismatch causes a visible
// snap at the end of the close animation.
const COLLAPSED_MAX_HEIGHT = '16rem'

const ShowMore = {
  _initialized: false,
  _userClosed: new WeakSet(), // Track blocks manually closed by user
  _openedIds: new Set(), // IDs of blocks user has ever opened

  _loadOpenedIds() {
    try {
      const stored = localStorage.getItem(STORAGE_KEY)
      if (stored) {
        JSON.parse(stored).forEach((id) => this._openedIds.add(id))
      }
    } catch (e) {
      // localStorage not available or corrupted
    }
  },

  _saveOpenedId(id) {
    if (!id) return
    this._openedIds.add(id)
    this._persistOpenedIds()
  },

  _removeOpenedId(id) {
    if (!id) return
    this._openedIds.delete(id)
    this._persistOpenedIds()
  },

  _persistOpenedIds() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify([...this._openedIds]))
    } catch (e) {
      // localStorage not available
    }
  },

  // Detach the transitionend listener and fallback timer left by open() so
  // they can't interfere with a subsequent open or close.
  _cancelPendingOpen(content) {
    if (content._showMoreOnEnd) {
      content.removeEventListener('transitionend', content._showMoreOnEnd)
      delete content._showMoreOnEnd
    }
    if (content._showMoreFallback) {
      clearTimeout(content._showMoreFallback)
      delete content._showMoreFallback
    }
  },

  _hasUserOpened(wrapper) {
    const input = wrapper.querySelector('input.show-hide-input')
    return input && this._openedIds.has(input.id)
  },

  /**
   * @param {HTMLElement} el - Element inside the .show-more wrapper
   * @param {boolean} auto
   */
  open(el, auto = false) {
    const wrapper = el.closest('.show-more')
    if (!wrapper) return

    if (auto && this._userClosed.has(wrapper)) return

    const content = wrapper.children[1]
    if (!content) return

    wrapper.dataset.showMoreScrollPos = String(wrapper.getBoundingClientRect().top + window.scrollY)
    content.classList.remove('overflow-hidden')
    content.style.maxHeight = content.scrollHeight + 'px'
    wrapper.dataset.showMoreOpen = 'true'

    this._cancelPendingOpen(content)

    // Release max-height after the transition so lazy-loaded images (e.g.
    // review galleries) can grow the box without overflowing siblings below.
    // Without this, the wrapper stays pinned to the pre-load scrollHeight and
    // siblings render over the overflowing content.
    const onEnd = (e) => {
      if (e.target !== content || e.propertyName !== 'max-height') return
      content.style.maxHeight = 'none'
      this._cancelPendingOpen(content)
    }
    content._showMoreOnEnd = onEnd
    content.addEventListener('transitionend', onEnd)
    // Fallback for reduced-motion / no-transition: release after 500ms.
    content._showMoreFallback = setTimeout(() => {
      delete content._showMoreFallback
      if (wrapper.dataset.showMoreOpen === 'true' && content.style.maxHeight !== 'none') {
        content.style.maxHeight = 'none'
        content.removeEventListener('transitionend', onEnd)
        delete content._showMoreOnEnd
      }
    }, 500)

    const checkbox = wrapper.querySelector('input.show-hide-input')
    if (checkbox) {
      checkbox.checked = true
      if (!auto) this._saveOpenedId(checkbox.id)
    }
  },

  /** @param {HTMLElement} el */
  close(el) {
    const wrapper = el.closest('.show-more')
    if (!wrapper) return

    const content = wrapper.children[1]
    if (!content) return

    this._userClosed.add(wrapper)

    this._cancelPendingOpen(content)

    // Transitioning from max-height: none is not animatable. Snap to the
    // measured height first, force reflow, then transition to the collapsed
    // value on the next frame so the close animation runs.
    if (content.style.maxHeight === 'none' || content.style.maxHeight === '') {
      content.style.maxHeight = content.scrollHeight + 'px'
      void content.offsetHeight
    }
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        content.classList.add('overflow-hidden')
        content.style.maxHeight = COLLAPSED_MAX_HEIGHT
      })
    })
    delete wrapper.dataset.showMoreOpen

    const checkbox = wrapper.querySelector('input.show-hide-input')
    if (checkbox) {
      checkbox.checked = false
      this._removeOpenedId(checkbox.id)
    }

    const scrollPos = parseFloat(wrapper.dataset.showMoreScrollPos)
    delete wrapper.dataset.showMoreScrollPos
    if (scrollPos) {
      window.scrollTo({ top: Math.max(0, scrollPos - 20), behavior: 'smooth' })
    } else {
      wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  },

  /** @param {HTMLElement} wrapper @returns {boolean} */
  isOpen(wrapper) {
    return wrapper.dataset.showMoreOpen === 'true'
  },

  /**
   * @param {HTMLElement} element
   * @param {boolean} auto
   */
  openContaining(element, auto = true) {
    if (!element) return

    const wrapper = element.closest('.show-more')
    if (!wrapper || this.isOpen(wrapper)) return

    // Use the wrapper itself as fallback if btn not found yet (hidden)
    const btn = wrapper.querySelector('.show-more-btn') || wrapper
    this.open(btn, auto)
  },

  /** @param {string} hash */
  scrollToHash(hash) {
    if (!hash) return

    try {
      const target = document.querySelector(hash)
      if (target) {
        // Hash navigation is explicit user intent (URL load, hashchange, or
        // anchor click), so force-open even if the user previously closed
        // this block — otherwise we'd scroll to invisible content inside a
        // collapsed wrapper.
        const wrapper = target.closest('.show-more')
        if (wrapper) this._userClosed.delete(wrapper)
        this.openContaining(target, false)
        setTimeout(() => {
          target.scrollIntoView({ behavior: 'smooth' })
        }, 100)
      }
    } catch (e) {
      // Invalid selector, ignore
    }
  },

  /** @param {HTMLElement} wrapper @returns {boolean} */
  isCollapsed(wrapper) {
    const content = wrapper.children[1]
    return content && content.classList.contains('overflow-hidden')
  },

  openVisibleBlocks() {
    document.querySelectorAll('.show-more').forEach((wrapper) => {
      if (!this.isCollapsed(wrapper)) return
      if (this._userClosed.has(wrapper)) return
      // Only auto-open if user has previously opened this block
      if (!this._hasUserOpened(wrapper)) return

      const rect = wrapper.getBoundingClientRect()
      // Check if block intersects with viewport (with some margin)
      const isVisible = rect.top < window.innerHeight + 100 && rect.bottom > -100

      if (isVisible) {
        this.openContaining(wrapper, true)
      }
    })
  },

  init() {
    if (this._initialized) return
    this._initialized = true

    this._loadOpenedIds()

    if (location.hash) {
      this.scrollToHash(location.hash)
    }

    let scrollCheckCount = 0
    const maxScrollChecks = 10
    const checkScrollAndOpen = () => {
      if (window.scrollY > 0) {
        this.openVisibleBlocks()
      }
    }

    // Immediate check
    checkScrollAndOpen()

    // Listen for scroll events (catches smooth scroll restoration)
    const onScrollRestore = () => {
      scrollCheckCount++
      checkScrollAndOpen()
      if (scrollCheckCount >= maxScrollChecks) {
        window.removeEventListener('scroll', onScrollRestore)
      }
    }
    window.addEventListener('scroll', onScrollRestore, { passive: true })

    // Also use scrollend event if available (modern browsers)
    if ('onscrollend' in window) {
      window.addEventListener(
        'scrollend',
        () => {
          checkScrollAndOpen()
          window.removeEventListener('scroll', onScrollRestore)
        },
        { once: true },
      )
    }

    // Fallback: remove listener after 2 seconds
    setTimeout(() => {
      window.removeEventListener('scroll', onScrollRestore)
    }, 2000)

    // Handle hash changes (SPA navigation)
    window.addEventListener('hashchange', () => {
      if (location.hash) {
        this.scrollToHash(location.hash)
      }
    })

    // Handle clicks on anchor links pointing to the *current* hash. The
    // hashchange listener above already handles hash transitions; this only
    // covers the same-hash case (clicking #x while location.hash === '#x'),
    // for which the browser does not fire hashchange.
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[href^="#"]')
      if (!link) return
      const hash = link.getAttribute('href')
      if (!hash || hash.length <= 1) return
      if (hash !== location.hash) return
      setTimeout(() => this.scrollToHash(hash), 10)
    })

    // Ctrl+F: open all collapsed blocks so browser can find text
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
        this.openAllCollapsed()
      }
    })
  },

  openAllCollapsed() {
    document.querySelectorAll('.show-more').forEach((wrapper) => {
      if (this.isCollapsed(wrapper)) {
        this.openContaining(wrapper, true)
      }
    })
  },
}

// Auto-init when DOM is ready
export function initShowMore() {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ShowMore.init())
  } else {
    ShowMore.init()
  }
}

// Expose globally for inline onclick handlers in templates
if (typeof window !== 'undefined') {
  window.ShowMore = ShowMore
}

export default ShowMore
