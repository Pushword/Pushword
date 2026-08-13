/**
 * Restore <a> tags for authenticated editors.
 *
 * The server replaces links to unpublished pages with
 *   <span data-status="unpublished" data-href="..." title="...">label</span>
 * so anonymous visitors can't click through. Editors still need to follow those
 * links to preview drafts, so we restore the <a> client-side.
 *
 * Auth state is read from the `pw_auth=1` cookie — the same editor-only hint that
 * gates the admin fragments. Core sets it on editor login (PwAuthCookieListener),
 * re-affirms it on any authenticated request (PwAuthCookieHealListener) and clears
 * it on logout or when an admin fragment answers 401/403, so it stays fresh without
 * a probe of its own and without a sessionStorage cache (which used to keep an
 * editor who logged in mid-session on the anonymous answer until the tab closed).
 *
 * It replaces a fetch of /_pushword/auth-check, which answered 401 to anonymous
 * visitors: correct, but the browser logs every 4xx resource to the console and
 * Lighthouse counts that against best-practices, and no JS can silence it because
 * the message comes from the network stack. Reading a cookie costs no request, so
 * an anonymous visitor now makes none — and the restore also works on a
 * prerendered page, where the probe would have been deferred.
 *
 * Narrower than the probe on purpose: /_pushword/auth-check answers 204 to any
 * fully authenticated user of the firewall covering it, while pw_auth is
 * ROLE_EDITOR only. A site whose non-editor accounts sit on that same firewall
 * stops handing them draft links — which is the intent.
 */

const COOKIE = 'pw_auth=1'

// Same matching as helpers.js' `data-live-if="cookie:…"` gate: an exact name=value
// entry, so a cookie merely ending in `pw_auth` can never pass for it.
function isEditor() {
  return document.cookie.split('; ').indexOf(COOKIE) !== -1
}

export function restoreUnpublishedLinks() {
  const spans = document.querySelectorAll('span[data-status="unpublished"][data-href]')
  if (spans.length === 0) return
  if (!isEditor()) return

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
