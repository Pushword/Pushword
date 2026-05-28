/**
 * Restore <a> tags for authenticated editors.
 *
 * The server replaces links to unpublished pages with
 *   <span data-status="unpublished" data-href="..." title="...">label</span>
 * so anonymous visitors can't click through. Logged-in users still need to
 * follow those links to preview drafts, so we restore the <a> client-side.
 *
 * Auth state is probed via /_pushword/auth-check (returns 204 / 401) and
 * cached in sessionStorage to avoid one round-trip per page.
 */

const STORAGE_KEY = 'pw_authed'
const ENDPOINT = '/_pushword/auth-check'

function restoreLinks(spans) {
  spans.forEach((span) => {
    const a = document.createElement('a')
    a.href = span.dataset.href
    a.innerHTML = span.innerHTML
    a.dataset.unpublished = '1'
    a.title = span.title
    a.style.opacity = '0.6'
    span.replaceWith(a)
  })
}

export function restoreUnpublishedLinks() {
  const spans = document.querySelectorAll(
    'span[data-status="unpublished"][data-href]',
  )
  if (spans.length === 0) return

  const cached = sessionStorage.getItem(STORAGE_KEY)
  if (cached === '1') {
    restoreLinks(spans)
    return
  }
  if (cached === '0') return

  fetch(ENDPOINT, { credentials: 'same-origin' })
    .then((r) => {
      sessionStorage.setItem(STORAGE_KEY, r.ok ? '1' : '0')
      if (r.ok) restoreLinks(spans)
    })
    .catch(() => {
      /* ignore network errors */
    })
}
