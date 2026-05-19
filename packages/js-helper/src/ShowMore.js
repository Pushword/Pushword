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

  /**
   * Load opened block IDs from localStorage
   */
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

  /**
   * Save opened block ID to localStorage
   */
  _saveOpenedId(id) {
    if (!id) return
    this._openedIds.add(id)
    this._persistOpenedIds()
  },

  /**
   * Remove closed block ID from localStorage
   */
  _removeOpenedId(id) {
    if (!id) return
    this._openedIds.delete(id)
    this._persistOpenedIds()
  },

  /**
   * Persist opened IDs to localStorage
   */
  _persistOpenedIds() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify([...this._openedIds]))
    } catch (e) {
      // localStorage not available
    }
  },

  /**
   * Check if user has ever opened this block
   */
  _hasUserOpened(wrapper) {
    const input = wrapper.querySelector('input.show-hide-input')
    return input && this._openedIds.has(input.id)
  },

  /**
   * Open a show-more block
   * @param {HTMLElement} el - Element inside the .show-more wrapper (typically .show-more-btn)
   * @param {boolean} auto - Whether this is an automatic open (not user-initiated)
   */
  open(el, auto = false) {
    const wrapper = el.closest('.show-more')
    if (!wrapper) return

    // Don't auto-open if user manually closed this block
    if (auto && this._userClosed.has(wrapper)) return

    const content = wrapper.children[1]
    if (!content) return

    wrapper.dataset.showMoreScrollPos = String(wrapper.getBoundingClientRect().top + window.scrollY)
    content.classList.remove('overflow-hidden')
    content.style.maxHeight = content.scrollHeight + 'px'
    wrapper.dataset.showMoreOpen = 'true'

    // Cancel any pending listener / fallback from a previous open() so they
    // can't fire on top of this new transition.
    if (content._showMoreOnEnd) {
      content.removeEventListener('transitionend', content._showMoreOnEnd)
      delete content._showMoreOnEnd
    }
    if (content._showMoreFallback) {
      clearTimeout(content._showMoreFallback)
      delete content._showMoreFallback
    }

    // Release max-height after the transition so lazy-loaded images (e.g.
    // review galleries) can grow the box without overflowing siblings below.
    // Without this, the wrapper stays pinned to the pre-load scrollHeight and
    // siblings render over the overflowing content.
    const onEnd = (e) => {
      if (e.target !== content || e.propertyName !== 'max-height') return
      content.style.maxHeight = 'none'
      content.removeEventListener('transitionend', onEnd)
      delete content._showMoreOnEnd
      if (content._showMoreFallback) {
        clearTimeout(content._showMoreFallback)
        delete content._showMoreFallback
      }
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

    // Toggle checkbox to update arrow icon state
    const checkbox = wrapper.querySelector('input.show-hide-input')
    if (checkbox) {
      checkbox.checked = true
      // Save to localStorage when user manually opens (not auto)
      if (!auto) {
        this._saveOpenedId(checkbox.id)
      }
    }
  },

  /**
   * Close a show-more block
   * @param {HTMLElement} el - Element inside the .show-more wrapper
   */
  close(el) {
    const wrapper = el.closest('.show-more')
    if (!wrapper) return

    const content = wrapper.children[1]
    if (!content) return

    // Mark as user-closed to prevent auto-reopening
    this._userClosed.add(wrapper)

    // Detach any pending open() transitionend listener and fallback timer so
    // they can't fire mid-close and reset max-height to 'none'.
    if (content._showMoreOnEnd) {
      content.removeEventListener('transitionend', content._showMoreOnEnd)
      delete content._showMoreOnEnd
    }
    if (content._showMoreFallback) {
      clearTimeout(content._showMoreFallback)
      delete content._showMoreFallback
    }

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

    // Toggle checkbox to update arrow icon state
    const checkbox = wrapper.querySelector('input.show-hide-input')
    if (checkbox) {
      checkbox.checked = false
      // Remove from localStorage when user closes
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

  /**
   * Check if block is open
   * @param {HTMLElement} wrapper
   * @returns {boolean}
   */
  isOpen(wrapper) {
    return wrapper.dataset.showMoreOpen === 'true'
  },

  /**
   * Open the show-more block containing the given element
   * @param {HTMLElement} element - Element inside a show-more block
   * @param {boolean} auto - Whether this is an automatic open
   */
  openContaining(element, auto = true) {
    if (!element) return

    const wrapper = element.closest('.show-more')
    if (!wrapper || this.isOpen(wrapper)) return

    // Use the wrapper itself as fallback if btn not found yet (hidden)
    const btn = wrapper.querySelector('.show-more-btn') || wrapper
    this.open(btn, auto)
  },

  /**
   * Open show-more block containing the hash target and scroll to it
   * @param {string} hash - The hash (with #) to navigate to
   */
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

  /**
   * Check if a show-more block is currently collapsed
   * @param {HTMLElement} wrapper - The .show-more wrapper element
   * @returns {boolean}
   */
  isCollapsed(wrapper) {
    const content = wrapper.children[1]
    return content && content.classList.contains('overflow-hidden')
  },

  /**
   * Open all visible collapsed show-more blocks
   * Useful when page loads with scroll position
   */
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

  /**
   * Initialize ShowMore functionality
   * Safe to call multiple times - will only initialize once
   */
  init() {
    if (this._initialized) return
    this._initialized = true

    // Load previously opened block IDs from localStorage
    this._loadOpenedIds()

    // Open if URL has hash pointing inside a show-more block
    if (location.hash) {
      this.scrollToHash(location.hash)
    }

    // Open if page loaded with scroll (e.g., browser back/refresh)
    // Listen for scroll events to catch smooth scroll restoration
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

    // Handle clicks on anchor links pointing to content inside show-more
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[href^="#"]')
      if (link) {
        const hash = link.getAttribute('href')
        if (hash && hash.length > 1) {
          // Delay to let default navigation happen first
          setTimeout(() => this.scrollToHash(hash), 10)
        }
      }
    })

    // Ctrl+F: open all collapsed blocks so browser can find text
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        this.openAllCollapsed()
      }
    })
  },

  /**
   * Open all collapsed show-more blocks
   * Useful for Ctrl+F to make all content searchable
   */
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
