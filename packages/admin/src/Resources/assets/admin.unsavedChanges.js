/**
 * Recovery of unsaved changes on the page edit form.
 *
 * Keeps the in-progress form state in localStorage so a crash, an accidental
 * close or a session timeout does not lose it, and offers it back on reopen.
 *
 * Nothing is sent to the server. Saving a page here is a publication — it
 * rewrites the flat markdown, regenerates the Open Graph image, purges the
 * static cache and turns every intermediate slug into a redirect page — so a
 * timer must never trigger one. The stored copy is dropped as soon as a real
 * save succeeds.
 *
 * Unrelated to the "Draft" toggle (page_draft.html.twig), which is a
 * publication state living in the database.
 */

import { SELECTORS, debugLog } from './admin.constants'

const WRITE_DEBOUNCE_MS = 800
const DISMISS_MS = 150

// Start of every recovery key (page/edit.html.twig builds the rest). The key
// carries the editor's id: localStorage is per browser, and on a shared machine
// an unqualified one would offer one editor's draft to the next.
const KEY_PREFIX = 'pw:unsaved:'

/**
 * Controls whose value is worth keeping. Files cannot be restored, and the CSRF
 * token is reissued on every render, so restoring a stale one breaks the save.
 */
const isTracked = (element) =>
  '' !== element.name &&
  !element.disabled &&
  !['file', 'submit', 'button', 'reset'].includes(element.type) &&
  !element.name.endsWith('[_token]')

const isMultiSelect = (element) => 'SELECT' === element.tagName && element.multiple

/**
 * Form state as {name: [values]}. Unchecked boxes keep an empty entry so
 * restoring can uncheck what the user unchecked.
 *
 * @returns {Record<string, string[]>}
 */
const serialize = (form) => {
  const values = {}

  for (const element of form.elements) {
    if (!isTracked(element)) continue

    if ('checkbox' === element.type || 'radio' === element.type) {
      values[element.name] = values[element.name] ?? []
      if (element.checked) values[element.name].push(element.value)
    } else if (isMultiSelect(element)) {
      values[element.name] = Array.from(element.selectedOptions, (option) => option.value)
    } else {
      values[element.name] = [element.value]
    }
  }

  return values
}

/**
 * Iterates the form rather than the stored keys, so a control the snapshot does
 * not mention keeps its rendered value instead of being cleared.
 */
const restore = (form, values) => {
  for (const element of form.elements) {
    if (!isTracked(element)) continue

    const stored = values[element.name]
    if (undefined === stored) continue

    if ('checkbox' === element.type || 'radio' === element.type) {
      element.checked = stored.includes(element.value)
    } else if (isMultiSelect(element)) {
      for (const option of element.options)
        option.selected = stored.includes(option.value)
    } else if (element.pwEditor) {
      // A rich editor (Monaco, EditorJS) renders beside the field it feeds, so
      // assigning .value would leave the visible editor on the old content.
      element.pwEditor.setValue(stored[0])
    } else {
      element.value = stored[0]
    }

    element.dispatchEvent(new Event('change', { bubbles: true }))
  }
}

// Key order comes from form.elements, which is render-stable.
const isSameState = (a, b) => JSON.stringify(a) === JSON.stringify(b)

/**
 * What the draft actually changed, as {name: [values]}, measured against the
 * form as the server rendered it when the draft was taken. Restoring writes back
 * these and nothing else: a draft is one editor's edits, not a picture of the
 * whole page, and replaying every field would revert what someone saved
 * meanwhile without ever saying so.
 *
 * A copy stored before `was` was recorded counts as changed throughout — which
 * is what those copies used to do anyway.
 */
const changedFields = (values, was) =>
  Object.fromEntries(
    Object.entries(values).filter(([name, value]) => !isSameState(value, was?.[name])),
  )

const MINUTE = 60_000
const UNITS = [
  ['day', 24 * 60 * MINUTE],
  ['hour', 60 * MINUTE],
  ['minute', MINUTE],
]

/**
 * "5 minutes ago" rather than a timestamp: what the editor needs to decide is
 * how stale the copy is, and they cannot answer that from "19:59:47" without
 * doing the arithmetic. The exact date stays on the element's title.
 */
const relativeTime = (savedAt) => {
  const elapsed = Date.now() - savedAt
  const format = new Intl.RelativeTimeFormat(document.documentElement.lang || 'en', {
    numeric: 'auto',
  })

  for (const [unit, ms] of UNITS) {
    if (elapsed >= ms) return format.format(-Math.floor(elapsed / ms), unit)
  }

  return format.format(0, 'minute') // under a minute: "this minute"
}

const read = (key) => {
  try {
    const raw = window.localStorage.getItem(key)

    return null === raw ? null : JSON.parse(raw)
  } catch (error) {
    debugLog('UnsavedChanges', 'unreadable snapshot, ignoring', error)

    return null
  }
}

const write = (key, snapshot) => {
  try {
    window.localStorage.setItem(key, JSON.stringify(snapshot))
  } catch (error) {
    // Storage disabled or quota exhausted: recovery is a bonus, never a blocker.
    debugLog('UnsavedChanges', 'could not store the changes', error)
  }
}

const clear = (key) => {
  try {
    window.localStorage.removeItem(key)
  } catch (error) {
    debugLog('UnsavedChanges', 'could not clear the stored changes', error)
  }
}

/** Watch `form`, storing and offering its unsaved state under `key`. */
const start = (form, key) => {
  const banner = document.getElementById('pw-unsaved-banner')
  const bannerMessage = document.getElementById('pw-unsaved-message')
  const restoreButton = document.getElementById('pw-unsaved-restore')
  const discardButton = document.getElementById('pw-unsaved-discard')

  let baseline = serialize(form)
  let writeTimeout = null

  // Fades out before hiding, so dismissing reads as an answer rather than a
  // repaint. The class is inert under prefers-reduced-motion (no transition),
  // and the timeout still lands, so the banner goes either way.
  const hideBanner = () => {
    if (null === banner || banner.hidden) return

    banner.classList.add('pw-unsaved--dismissing')
    window.setTimeout(() => {
      banner.hidden = true
      banner.classList.remove('pw-unsaved--dismissing')
    }, DISMISS_MS)
  }

  // `conflicted`: the server re-rendered a field the draft also changed, so
  // restoring puts the draft's version back over one that was saved. The offer
  // stands — the draft may well be the wanted one — but it stops being the safe
  // answer, and the banner has to say which it is.
  const showBanner = (savedAt, conflicted) => {
    if (null === banner) return

    banner.classList.toggle('pw-unsaved--conflict', conflicted)

    if (null !== bannerMessage) {
      const time = relativeTime(savedAt)
      const translations = window.pwUnsavedChangesTranslations
      bannerMessage.textContent = conflicted
        ? (translations?.conflict?.replace('%time%', time) ??
          `You left unsaved changes here ${time}. The page has been saved since, on fields you changed.`)
        : (translations?.message?.replace('%time%', time) ??
          `You left unsaved changes here ${time}.`)
      bannerMessage.title = new Date(savedAt).toLocaleString()
    }

    // hidden, not aria-hidden: it drops the banner out of the accessibility
    // tree too, so removing it is what makes role=status announce.
    banner.hidden = false
  }

  const save = () => {
    const values = serialize(form)

    if (isSameState(values, baseline)) {
      // Back to the state on screen — there is nothing left to recover.
      clear(key)
      hideBanner()

      return
    }

    // The baseline travels with the copy: on reopen it is the only way to tell
    // the fields this editor changed from the ones the page simply holds, and
    // the only way to notice the page moved underneath them.
    write(key, { values, baseline, savedAt: Date.now() })
  }

  const forget = () => {
    clear(key)
    hideBanner()
    baseline = serialize(form)
  }

  // The banner, hence both its buttons, only exists for changes worth offering:
  // edits this editor made that the form does not already show.
  const snapshot = read(key)
  const changes =
    null === snapshot ? {} : changedFields(snapshot.values, snapshot.baseline)
  const worthOffering = Object.entries(changes).some(
    ([name, value]) => !isSameState(value, baseline[name]),
  )

  if (worthOffering) {
    // Only a copy that recorded its baseline can tell us the page moved; an
    // older one says nothing, and claiming a conflict from silence would cry
    // wolf on every draft written before this shipped.
    const conflicted =
      undefined !== snapshot.baseline &&
      Object.keys(changes).some(
        (name) => !isSameState(snapshot.baseline[name], baseline[name]),
      )

    showBanner(snapshot.savedAt, conflicted)

    restoreButton?.addEventListener('click', () => {
      restore(form, changes)
      hideBanner() // the copy stays stored: it is still unsaved
    })

    discardButton?.addEventListener('click', forget)
  } else if (null !== snapshot) {
    // Nothing left worth offering: the save landed — here, in another tab, or
    // on the server. A submit clears nothing by itself, since its POST can
    // still be lost (an expired session has the firewall redirect it and drop
    // the body), and only what comes back rendered tells the two apart.
    clear(key)
  }

  const scheduleSave = () => {
    window.clearTimeout(writeTimeout)
    writeTimeout = window.setTimeout(save, WRITE_DEBOUNCE_MS)
  }

  form.addEventListener('input', scheduleSave)
  form.addEventListener('change', scheduleSave)

  // Ctrl+S save: the form stays on screen, so the copy has to go explicitly —
  // and only on a response that says the save landed.
  form.addEventListener('htmx:after:request', (event) => {
    const status = event?.detail?.ctx?.response?.status ?? 0
    if (status >= 200 && status < 400) forget()
  })
}

/**
 * Initialize unsaved-changes recovery on the edit form. Does nothing on a form
 * that carries no recovery key, which is every screen but a saved page's edit.
 */
export function initUnsavedChangesRecovery() {
  const form = document.querySelector(SELECTORS.FORM_AUTOSAVE)
  if (null === form) return

  const key = form.dataset.pwUnsavedKey
  if (undefined === key || '' === key) return

  // A rich editor rewrites the field it feeds with its own normalisation of the
  // content it was handed, and that write lands after this module runs. Until it
  // does, the form is not what the server rendered: baselining now would book
  // the normalisation as an edit — a draft nobody typed, stored over the real
  // one. The editor flags the field and clears the flag when its parse has
  // landed (admin-block-editor's editor.ts), so a parse already settled by the
  // time we run needs no event at all.
  if (null !== form.querySelector('[data-pw-baseline-pending]')) {
    form.addEventListener('pw:baseline-ready', () => start(form, key), { once: true })

    return
  }

  start(form, key)
}

/**
 * Drop every draft this browser holds when the editor signs out. localStorage is
 * per origin and outlives the session, so on a shared machine the next person to
 * sign in would otherwise be offered — and could restore — work that is not
 * theirs. A session that merely expired is not a sign-out: those copies are the
 * ones recovery exists for, and they stay.
 */
export function initUnsavedChangesSignOutClear() {
  const logoutPath = window.pwLogoutPath
  if (undefined === logoutPath) return

  document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null
    if (null === link) return
    if (new URL(link.href, window.location.origin).pathname !== logoutPath) return

    try {
      for (const key of Object.keys(window.localStorage))
        if (key.startsWith(KEY_PREFIX)) window.localStorage.removeItem(key)
    } catch (error) {
      debugLog('UnsavedChanges', 'could not clear the stored changes', error)
    }
  })
}
