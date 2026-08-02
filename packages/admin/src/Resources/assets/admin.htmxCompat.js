/**
 * htmx 4 compatibility for the admin fragments.
 */

/**
 * - htmx 4 swaps 4xx/5xx response bodies by default; an error page must never
 *   replace an admin fragment, so restore the htmx 2 no-swap-on-error
 *   behaviour.
 * - htmx 4 seeds the `changed` trigger modifier lazily (the first event always
 *   fires) where htmx 2 seeded it at bind time — and our fragments swap in a
 *   fresh input on every save, so each focus+blur would post a no-op
 *   inline-update. Drop requests whose value still equals the server-rendered
 *   one.
 */
export function installHtmxCompat(htmx) {
  htmx.config.noSwap.push('4xx', '5xx')

  document.addEventListener('htmx:config:request', (e) => {
    const el = e.detail?.ctx?.sourceElement
    if (
      el?.matches?.('input, textarea') &&
      /\bchanged\b/.test(el.getAttribute('hx-trigger') || '') &&
      el.value === el.defaultValue
    ) {
      e.preventDefault()
    }
  })
}
