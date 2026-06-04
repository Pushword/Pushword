/**
 * Variant links — opt-in progressive enhancement.
 *
 * The server consolidates internal links to a variant page onto its master:
 *   <a href="{master-url}" data-variant="{variant-url}">…</a>
 * Crawlers and visitors without JS follow href = the master (SEO consolidation).
 *
 * When loaded, this helper intercepts the click, fetches the variant page,
 * extracts its content zone and swaps it in place, then pushes the variant URL
 * (shareability / provenance). It finally dispatches a `DOMChanged` event so
 * other components — and the host site — can re-initialise (Alpine, Glightbox,
 * a booking widget…). Without JS it is a no-op.
 *
 * Sites with their own JS (e.g. htmx/Alpine) can ignore this helper and bind the
 * `data-variant` hook themselves.
 */

const DEFAULT_ZONE = '[data-variant-zone], main, #content'

let variantLoaded = false

/**
 * Fetch a variant URL and swap its content zone into the current page.
 *
 * @param {string} url           the variant URL (value of data-variant)
 * @param {string} zoneSelector  selector of the content zone to replace
 * @returns {Promise<void>}
 */
export function loadVariant(url, zoneSelector = DEFAULT_ZONE) {
  const zone = document.querySelector(zoneSelector)
  if (!zone || !url) return Promise.resolve()

  return fetch(url, { credentials: 'same-origin' })
    .then((response) => response.text())
    .then((html) => {
      const fresh = new DOMParser()
        .parseFromString(html, 'text/html')
        .querySelector(zoneSelector)
      if (!fresh) return

      zone.replaceWith(fresh)
      variantLoaded = true
      history.pushState({ pwVariant: url }, '', url)
      document.dispatchEvent(new Event('DOMChanged'))
    })
    .catch(() => {
      /* ignore network errors — the master content stays in place */
    })
}

/**
 * Wire the delegated click handler on [data-variant] links.
 *
 * @param {{ zone?: string }} options
 */
export function initVariantLinks(options = {}) {
  const zoneSelector = options.zone || DEFAULT_ZONE

  document.addEventListener('click', (event) => {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    ) {
      return
    }

    const link = event.target.closest && event.target.closest('a[data-variant]')
    if (!link) return

    const url = link.getAttribute('data-variant')
    if (!url) return

    event.preventDefault()
    loadVariant(url, zoneSelector)
  })

  // After navigating back/forward past a variant swap, restore real content.
  window.addEventListener('popstate', () => {
    if (variantLoaded) window.location.reload()
  })
}
