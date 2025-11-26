/**
 * Gestion de la mémorisation des panels ouverts/fermés
 * Supporte à la fois les panels Bootstrap Collapse et les panels personnalisés pw-settings
 */

// Configuration
const STORAGE_KEY = 'panels'
const DEBUG_MODE = false // Mettre à true pour activer les logs

const ERROR_SELECTORS = [
  '.ea-form-error',
  '.form-error',
  '.form-error-message',
  '.form-message-error',
  '.invalid-feedback',
  '.text-danger',
  '[data-ea-error]',
]

const PW_SETTINGS_SELECTOR = '.pw-settings-accordion'

const log = (...args) => {
  if (DEBUG_MODE) console.debug('[memorizeOpenPanel]', ...args)
}

const readPanelStates = () => {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (!stored || stored === 'undefined') {
      log('No stored panel states found')
      return {}
    }

    const parsed = JSON.parse(stored)
    if (Array.isArray(parsed)) {
      log('Converting legacy array format to object')
      return parsed.reduce((acc, id) => {
        if (typeof id === 'string') acc[id] = true
        return acc
      }, {})
    }

    log('Loaded panel states:', parsed)
    return parsed && typeof parsed === 'object' ? { ...parsed } : {}
  } catch (error) {
    console.warn('Unable to read stored panels', error)
    return {}
  }
}

const persistPanelStates = (states) => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(states))
    log('Persisted panel states:', states)
  } catch (error) {
    console.warn('[memorizeOpenPanel] Unable to persist panels state', error)
  }
}

const escapeId = (id) => {
  if (typeof id !== 'string') return ''
  if (window.CSS && typeof window.CSS.escape === 'function') {
    return window.CSS.escape(id)
  }

  return id.replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1')
}

const showCollapse = (element) => {
  if (!element) return

  const collapseConstructor =
    window.bootstrap && typeof window.bootstrap.Collapse !== 'undefined'
      ? window.bootstrap.Collapse
      : null

  if (collapseConstructor) {
    collapseConstructor.getOrCreateInstance(element, { toggle: false }).show()
    return
  }

  element.classList.add('show')
}

const setupBootstrapCollapsePersistence = (panelStates) => {
  const collapseElements = Array.from(document.querySelectorAll('.collapse'))
  if (!collapseElements.length) return

  const updateIcon = (panelId, isOpen) => {
    if (!panelId) return

    const escapedId = escapeId(panelId)
    const icon = document.querySelector(
      `[href="#${escapedId}"] .fa-plus, [href="#${escapedId}"] .fa-minus`,
    )

    if (!icon) return

    icon.classList.toggle('fa-plus', !isOpen)
    icon.classList.toggle('fa-minus', isOpen)
  }

  const persist = () => persistPanelStates(panelStates)

  collapseElements.forEach((collapseElement) => {
    collapseElement.addEventListener('shown.bs.collapse', (event) => {
      const target = event.currentTarget
      if (!(target instanceof HTMLElement) || !target.id) return

      panelStates[target.id] = true
      persist()
      updateIcon(target.id, true)
    })

    collapseElement.addEventListener('hidden.bs.collapse', (event) => {
      const target = event.currentTarget
      if (!(target instanceof HTMLElement) || !target.id) return

      panelStates[target.id] = false
      persist()
      updateIcon(target.id, false)
    })
  })

  const initializeFromStorage = () => {
    Object.entries(panelStates).forEach(([panelId, isOpen]) => {
      if (!isOpen) return
      const element = document.getElementById(panelId)
      if (element && element.classList.contains('collapse')) {
        showCollapse(element)
        updateIcon(panelId, true)
      }
    })
  }

  const revealPanelsWithErrors = () => {
    document.querySelectorAll('.sonata-ba-field-error-messages').forEach((element) => {
      const panel = element.closest('.collapse')
      if (panel) {
        showCollapse(panel)
        if (panel.id) {
          panelStates[panel.id] = true
          persist()
        }
      }
    })
  }

  initializeFromStorage()
  revealPanelsWithErrors()
}

const isPanelOpen = (panel) => {
  if (!panel.body) return false
  if (panel.body.hasAttribute('hidden')) return false

  const ariaHidden = panel.body.getAttribute('aria-hidden')
  if (ariaHidden === 'true') return false

  const style = window.getComputedStyle(panel.body)
  if (style.display === 'none' || style.visibility === 'hidden') {
    return false
  }

  return panel.root.classList.contains('pw-settings-open') || style.height !== '0px'
}

const syncPanelClasses = (panel, isOpen) => {
  panel.root.classList.toggle('pw-settings-open', isOpen)
  panel.root.classList.toggle('pw-settings-collapsed', !isOpen)

  const chevron = panel.button?.querySelector('.pw-settings-chevron')
  if (chevron) {
    chevron.classList.toggle('is-open', isOpen)
  }

  if (panel.button) {
    panel.button.setAttribute('aria-expanded', isOpen.toString())
  }

  if (panel.body) {
    panel.body.setAttribute('aria-hidden', (!isOpen).toString())
  }
}

const setPanelVisibility = (panel, shouldBeOpen) => {
  if (panel.body) {
    panel.body.style.display = shouldBeOpen ? '' : 'none'
    panel.body.toggleAttribute('hidden', !shouldBeOpen)
  }

  syncPanelClasses(panel, shouldBeOpen)
}

const togglePanelVisibility = (panel) => {
  const nextState = !isPanelOpen(panel)
  setPanelVisibility(panel, nextState)
  return nextState
}

const enforcePanelState = (panel, shouldBeOpen) => {
  setPanelVisibility(panel, shouldBeOpen)
}

const openPanelsWithErrors = (panelStates, panelMap) => {
  let hasChanges = false
  const errorElements = document.querySelectorAll(ERROR_SELECTORS.join(','))
  errorElements.forEach((errorElement) => {
    const root = errorElement.closest(PW_SETTINGS_SELECTOR)
    if (!root) return

    const panel = panelMap.get(root)
    if (!panel) return

    enforcePanelState(panel, true)
    if (panel.key && panelStates[panel.key] !== true) {
      panelStates[panel.key] = true
      hasChanges = true
    }
  })

  if (hasChanges) {
    persistPanelStates(panelStates)
  }
}

const setupPwSettingsPersistence = (panelStates) => {
  const panels = Array.from(document.querySelectorAll(PW_SETTINGS_SELECTOR)).map(
    (root) => {
      const button = root.querySelector('.pw-settings-toggle')
      const body = root.querySelector('.form-fieldset-body')
      const explicitKey =
        root.getAttribute('data-pw-panel-key') ||
        body?.getAttribute('data-pw-panel-key') ||
        button?.getAttribute('aria-controls') ||
        root.id ||
        null

      return {
        root,
        button,
        body,
        key: explicitKey,
      }
    },
  )

  if (!panels.length) return false

  const panelMap = new Map()
  panels.forEach((panel) => panelMap.set(panel.root, panel))

  const persist = () => persistPanelStates(panelStates)

  panels.forEach((panel) => {
    const storedState =
      panel.key && panelStates[panel.key] !== undefined
        ? Boolean(panelStates[panel.key])
        : undefined
    const defaultOpen = !panel.root.classList.contains('pw-settings-collapsed')
    const shouldBeOpen = storedState ?? defaultOpen

    enforcePanelState(panel, shouldBeOpen)

    if (panel.button) {
      panel.button.addEventListener('click', (event) => {
        event.preventDefault()
        const panelIsOpen = togglePanelVisibility(panel)

        if (panel.key) {
          panelStates[panel.key] = panelIsOpen
          persist()
        }
      })
    }
  })

  openPanelsWithErrors(panelStates, panelMap)

  return true
}

export function memorizeOpenPanel() {
  const panelStates = readPanelStates()
  const handled = setupPwSettingsPersistence(panelStates)

  if (!handled) {
    setupBootstrapCollapsePersistence(panelStates)
  }
}
