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
      // A rich editor (EasyMDE, EditorJS) renders beside the field it feeds, so
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

/**
 * Initialize unsaved-changes recovery on the edit form.
 *
 * @returns {{save: () => void, forget: () => void}|null}
 */
export function initUnsavedChangesRecovery() {
  const form = document.querySelector(SELECTORS.FORM_AUTOSAVE)
  if (null === form) return null

  const key = form.dataset.pwUnsavedKey
  if (undefined === key || '' === key) return null

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

  const showBanner = (savedAt) => {
    if (null === banner) return

    if (null !== bannerMessage) {
      const time = relativeTime(savedAt)
      bannerMessage.textContent =
        window.pwUnsavedChangesTranslations?.message?.replace('%time%', time) ??
        `You left unsaved changes here ${time}.`
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

    write(key, { values, savedAt: Date.now() })
  }

  const forget = () => {
    clear(key)
    hideBanner()
    baseline = serialize(form)
  }

  // The banner, hence both its buttons, only exists for changes worth offering.
  const snapshot = read(key)
  if (null !== snapshot && !isSameState(snapshot.values, baseline)) {
    showBanner(snapshot.savedAt)

    restoreButton?.addEventListener('click', () => {
      restore(form, snapshot.values)
      hideBanner() // the copy stays stored: it is still unsaved
    })

    discardButton?.addEventListener('click', forget)
  } else if (null !== snapshot) {
    clear(key) // saved since, from this tab or another
  }

  const scheduleSave = () => {
    window.clearTimeout(writeTimeout)
    writeTimeout = window.setTimeout(save, WRITE_DEBOUNCE_MS)
  }

  form.addEventListener('input', scheduleSave)
  form.addEventListener('change', scheduleSave)

  // Ctrl+S save: the form stays on screen, so the copy has to go explicitly.
  form.addEventListener('htmx:after:request', (event) => {
    const status = event?.detail?.ctx?.response?.status ?? 0
    if (status >= 200 && status < 400) forget()
  })

  form.addEventListener('submit', forget)

  return { save, forget }
}
