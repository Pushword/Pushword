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
    } else if (element.easyMDE) {
      // Writing .value on a textarea EasyMDE owns leaves the CodeMirror view
      // showing the old text; the editor instance is the way in.
      element.easyMDE.value(stored[0])
    } else {
      element.value = stored[0]
    }

    element.dispatchEvent(new Event('change', { bubbles: true }))
  }
}

// Key order comes from form.elements, which is render-stable.
const isSameState = (a, b) => JSON.stringify(a) === JSON.stringify(b)

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

  const hideBanner = () => {
    if (null === banner) return
    banner.style.display = 'none'
    banner.setAttribute('aria-hidden', 'true')
  }

  const showBanner = (savedAt) => {
    if (null === banner) return

    if (null !== bannerMessage) {
      const time = new Date(savedAt).toLocaleString()
      bannerMessage.textContent =
        window.pwUnsavedChangesTranslations?.message?.replace('%time%', time) ??
        `You have unsaved changes from ${time}.`
    }

    banner.style.display = 'flex'
    banner.setAttribute('aria-hidden', 'false')
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
