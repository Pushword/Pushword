import {
  ensureModal,
  openModal as openModalBase,
  closeModal,
  createMessageHandler,
  sendMessageToParent,
  normalizeUrl,
} from './admin.modalUtils.js'

const MEDIA_PICKER_MODAL_ID = 'pw-media-picker-modal'
const MEDIA_PICKER_IFRAME_CLASS = 'pw-admin-popup-iframe'
const MESSAGE_TYPE = 'pw-media-picker-select'
const MESSAGE_TYPE_MULTI = 'pw-media-picker-multi-select'

const debug = (...args) => console.debug('[MediaPicker]', ...args)

function getDatasetValue(select, ...keys) {
  for (const key of keys) {
    const value = select.dataset[key]
    if (value) {
      return value
    }
  }

  return null
}

export function mediaPicker() {
  debug('Booting mediaPicker script')
  initParentPickers()
  initCollectionListeners()
  initPickerChildContext()
}

let isMessageListenerRegistered = false

function initParentPickers() {
  const pickers = document.querySelectorAll('[data-pw-media-picker]')
  debug('initParentPickers: found %s pickers', pickers.length)

  if (!isMessageListenerRegistered) {
    window.addEventListener(
      'message',
      createMessageHandler(MESSAGE_TYPE, handlePickerPayload),
      false,
    )
    window.addEventListener(
      'message',
      createMessageHandler(MESSAGE_TYPE_MULTI, handleMultiPickerPayload),
      false,
    )
    isMessageListenerRegistered = true
    debug('Registered window message listener')
  }

  if (!pickers.length) {
    return
  }

  pickers.forEach((select) => {
    if (select.dataset.pwMediaPickerReady === '1') return
    select.dataset.pwMediaPickerReady = '1'
    debug('Enhancing picker', select.id || select.name)
    enhancePicker(select)
  })
}

function enhancePicker(select) {
  select.classList.add('pw-media-picker__select')

  const wrapper = document.createElement('div')
  wrapper.className = 'pw-media-picker'
  wrapper.innerHTML = buildPickerHtml(select)

  const parent = select.parentElement
  if (parent) {
    parent.insertBefore(wrapper, select)
    wrapper.appendChild(select)
  }

  renderPickerState(select)

  wrapper
    .querySelector('[data-pw-media-picker-action="choose"]')
    ?.addEventListener('click', () => {
      const targetUrl = buildPickerUrl(
        getDatasetValue(select, 'pwMediaPickerModalUrl', 'pwAdminPopupModalUrl'),
        select,
      )
      if (!targetUrl) return
      openPickerModal(select, targetUrl)
    })

  wrapper
    .querySelector('[data-pw-media-picker-action="upload"]')
    ?.addEventListener('click', () => {
      const targetUrl = buildPickerUrl(select.dataset.pwMediaPickerUploadUrl, select)
      if (!targetUrl) return
      openPickerModal(select, targetUrl)
    })

  wrapper
    .querySelector('[data-pw-media-picker-action="remove"]')
    ?.addEventListener('click', () => {
      clearPickerSelection(select)
    })

  select.addEventListener('change', () => renderPickerState(select))

  select.dispatchEvent(new Event('change'))
}

function initCollectionListeners() {
  document.addEventListener('ea.collection.item-added', (event) => {
    const newElement = event.detail?.newElement
    debug('ea.collection.item-added triggered', newElement)
    if (!newElement) {
      return
    }

    newElement.querySelectorAll('[data-pw-media-picker]').forEach((element) => {
      debug('Enhancing picker inside collection', element.id || element.name)
      enhancePicker(element)
    })
  })
}

function buildPickerHtml(select) {
  const emptyLabel = select.dataset.pwMediaPickerEmptyLabel || ''
  const removeLabel = select.dataset.pwMediaPickerRemoveLabel || 'Remove'
  return `
    <div class="pw-media-picker__preview">
      <div class="pw-media-picker__thumb">
        <div class="pw-media-picker__thumb-inner"></div>
      </div>
      <div class="pw-media-picker__details">
        <span class="pw-media-picker__name">${emptyLabel}</span>
        <span class="pw-media-picker__info"></span>
      </div>
    </div>
    <div class="pw-media-picker__actions">
      <button class="btn btn-secondary" type="button" data-pw-media-picker-action="choose">
        ${select.dataset.pwMediaPickerChooseLabel || 'Choose'}
      </button>
      <button class="btn btn-outline-secondary" type="button" data-pw-media-picker-action="upload">
        ${select.dataset.pwMediaPickerUploadLabel || 'Upload'}
      </button>
      <button class="btn btn-link ms-auto" type="button" data-pw-media-picker-action="remove" aria-label="${removeLabel}">
        <span class="fa fa-times" aria-hidden="true"></span>
      </button>
    </div>
  `
}

function renderPickerState(select) {
  const wrapper = select.closest('.pw-media-picker')
  if (!wrapper) return

  const hasSelection = Boolean(select.dataset.pwMediaPickerSelectedId)
  debug('renderPickerState', select.id || select.name, 'hasSelection:', hasSelection)
  const thumb = wrapper.querySelector('.pw-media-picker__thumb-inner')
  const nameEl = wrapper.querySelector('.pw-media-picker__name')
  const infoEl = wrapper.querySelector('.pw-media-picker__info')

  const thumbUrl = hasSelection ? select.dataset.pwMediaPickerSelectedThumb : ''

  if (thumb) {
    thumb.style.backgroundImage = thumbUrl ? `url('${thumbUrl}')` : 'none'
    const ratio = getThumbRatio(select, hasSelection)
    thumb.style.paddingBottom = ratio ? `${ratio * 100}%` : '66.66%'
  }

  if (nameEl) {
    nameEl.textContent = hasSelection
      ? select.dataset.pwMediaPickerSelectedName || ''
      : select.dataset.pwMediaPickerEmptyLabel || ''
  }

  if (infoEl) {
    if (hasSelection) {
      const ratioLabel = select.dataset.pwMediaPickerSelectedRatio || ''
      const dimensions = select.dataset.pwMediaPickerSelectedMeta || ''
      infoEl.textContent =
        ratioLabel || dimensions
          ? [ratioLabel, dimensions].filter(Boolean).join(' · ')
          : ''
      infoEl.title = dimensions || ''
    } else {
      infoEl.textContent = ''
      infoEl.removeAttribute('title')
    }
  }

  wrapper.classList.toggle('pw-media-picker--empty', !hasSelection)

  updateCollectionHeader(
    select,
    hasSelection
      ? select.dataset.pwMediaPickerSelectedFilename ||
          select.dataset.pwMediaPickerSelectedName ||
          ''
      : '',
  )
}

function getThumbRatio(select, hasSelection) {
  const widthAttr = hasSelection ? select.dataset.pwMediaPickerSelectedWidth : null
  const heightAttr = hasSelection ? select.dataset.pwMediaPickerSelectedHeight : null

  const width = widthAttr ? parseFloat(widthAttr) : null
  const height = heightAttr ? parseFloat(heightAttr) : null

  if (!width || !height) {
    return null
  }

  return height / width
}

function clearPickerSelection(select) {
  select.value = ''
  delete select.dataset.pwMediaPickerSelectedId
  delete select.dataset.pwMediaPickerSelectedName
  delete select.dataset.pwMediaPickerSelectedFilename
  delete select.dataset.pwMediaPickerSelectedThumb
  delete select.dataset.pwMediaPickerSelectedMeta
  delete select.dataset.pwMediaPickerSelectedWidth
  delete select.dataset.pwMediaPickerSelectedHeight
  select.dispatchEvent(new Event('change', { bubbles: true }))
  renderPickerState(select)
}

function updateCollectionHeader(select, label) {
  const item = select.closest('.field-collection-item')
  if (!item) {
    return
  }

  const button = item.querySelector('.accordion-button')
  const collapse = item.querySelector('.accordion-collapse')
  if (button) {
    button.classList.remove('collapsed')
    button.setAttribute('aria-expanded', 'true')
    const titleNode = button.querySelector('.accordion-title')
    const content = label || select.dataset.pwMediaPickerEmptyLabel || button.textContent
    if (titleNode) {
      titleNode.textContent = content
    } else {
      button.textContent = content
    }
  }

  if (collapse) {
    collapse.classList.add('show')
  }
}

function buildPickerUrl(baseUrl, select) {
  if (!baseUrl) return null
  return normalizeUrl(baseUrl, { pwMediaPickerFieldId: select.id })
}

function ensureModalElements(select) {
  const title = getDatasetValue(
    select,
    'pwMediaPickerModalTitle',
    'pwAdminPopupModalTitle',
  ) || 'Media picker'

  return ensureModal({
    id: MEDIA_PICKER_MODAL_ID,
    iframeClass: MEDIA_PICKER_IFRAME_CLASS,
    title,
    hasHeader: true,
  })
}

function openPickerModal(select, url) {
  // Ensure modal elements exist with proper title from select element
  ensureModalElements(select)

  openModalBase(
    {
      id: MEDIA_PICKER_MODAL_ID,
      iframeClass: MEDIA_PICKER_IFRAME_CLASS,
      title: getDatasetValue(select, 'pwMediaPickerModalTitle', 'pwAdminPopupModalTitle') || 'Media picker',
      hasHeader: true,
    },
    url,
  )
}

function handlePickerPayload(payload) {
  const { fieldId, media } = payload
  debug('Message received from picker', fieldId, media)
  if (!fieldId || !media) {
    return
  }

  const select = document.getElementById(fieldId)
  if (!select) {
    return
  }

  const mediaLabel = media.fileName ?? media.name ?? media.alt ?? ''

  select.dataset.pwMediaPickerSelectedId = media.id
  select.dataset.pwMediaPickerSelectedName = mediaLabel
  select.dataset.pwMediaPickerSelectedFilename = media.fileName || ''
  select.dataset.pwMediaPickerSelectedThumb = media.thumb
  select.dataset.pwMediaPickerSelectedMeta = media.meta || ''
  select.dataset.pwMediaPickerSelectedRatio = media.ratio || ''
  select.dataset.pwMediaPickerSelectedWidth = media.width || ''
  select.dataset.pwMediaPickerSelectedHeight = media.height || ''

  setSelectValue(select, media.id, mediaLabel)
  renderPickerState(select)

  closeModal(MEDIA_PICKER_MODAL_ID)
}

function setSelectValue(select, value, label) {
  const stringValue = String(value)
  let option = Array.from(select.options).find((opt) => opt.value === stringValue)

  if (!option) {
    option = new Option(label || stringValue, stringValue, true, true)
    select.add(option)
  } else {
    option.selected = true
  }

  select.value = stringValue
  select.dispatchEvent(new Event('change', { bubbles: true }))
}

const PICKER_FIELD_STORAGE_KEY = 'pwMediaPickerFieldId'
const PICKER_ACTIVE_STORAGE_KEY = 'pwMediaPickerActive'

function initPickerChildContext() {
  const params = new URLSearchParams(window.location.search)
  const isPickerContextFromUrl = params.has('pwMediaPicker')
  const isMultiPickerFromUrl = params.has('pwMediaPickerMulti')

  // Store fieldId and active state in sessionStorage so it persists across navigation
  // (pagination, search form submission which loses URL params)
  const fieldIdFromUrl = params.get('pwMediaPickerFieldId')
  if (fieldIdFromUrl && (isPickerContextFromUrl || isMultiPickerFromUrl)) {
    try {
      sessionStorage.setItem(PICKER_FIELD_STORAGE_KEY, fieldIdFromUrl)
      sessionStorage.setItem(PICKER_ACTIVE_STORAGE_KEY, '1')
    } catch {
      // Ignore storage errors
    }
  }

  // Clear stale multi-select state when opening in single-select mode
  if (!isMultiPickerFromUrl) {
    try {
      sessionStorage.removeItem(MULTI_PICKER_STORAGE_KEY)
      sessionStorage.removeItem('pwMediaPickerMultiIds')
      sessionStorage.removeItem('pwMediaPickerMultiItems')
    } catch {}
  }

  // Multi-select mode takes priority (only from URL, not stale sessionStorage)
  if (isMultiPickerFromUrl) {
    initMultiSelectChildContext()
    return
  }

  // Check if we're in picker context from URL or sessionStorage
  // Only use sessionStorage fallback if we're in an iframe (to avoid false positives)
  const isInIframe = window.parent !== window
  const isPickerContextFromStorage =
    isInIframe && getSessionStorageItem(PICKER_ACTIVE_STORAGE_KEY) === '1'
  const isPickerContext = isPickerContextFromUrl || isPickerContextFromStorage

  if (!isPickerContext) {
    return
  }

  document.body.classList.add('pw-admin-popup-modal')
  bindMosaicSelection()
}

function getSessionStorageItem(key) {
  try {
    return sessionStorage.getItem(key)
  } catch {
    return null
  }
}

/**
 * Get the fieldId from URL or fallback to sessionStorage.
 * This ensures we have the fieldId even after navigation (pagination, search).
 */
function getPickerFieldId() {
  const params = new URLSearchParams(window.location.search)
  const fieldIdFromUrl = params.get('pwMediaPickerFieldId')
  if (fieldIdFromUrl) {
    return fieldIdFromUrl
  }

  try {
    return sessionStorage.getItem(PICKER_FIELD_STORAGE_KEY)
  } catch {
    return null
  }
}

let mosaicSelectionBound = false

function bindMosaicSelection() {
  const fieldId = getPickerFieldId()
  debug('bindMosaicSelection', { fieldId })
  if (!fieldId) return

  // Use event delegation on document to handle clicks on dynamically loaded content
  // (pagination, search results, etc.)
  if (!mosaicSelectionBound) {
    document.addEventListener(
      'click',
      (event) => {
        if (!(event.target instanceof Element)) {
          return
        }

        const link = event.target.closest('.media-mosaic__card .mosaic-inner-box')
        if (!link) {
          return
        }

        // Get fieldId (from URL or sessionStorage)
        const currentFieldId = getPickerFieldId()
        if (!currentFieldId) {
          return
        }

        event.preventDefault()
        const card = link.closest('.media-mosaic__card')
        if (!card) {
          return
        }

        sendSelection(currentFieldId, extractMediaFromCard(card))
      },
      true,
    )
    mosaicSelectionBound = true
    debug('Registered document click listener for mosaic selection')
  }

  const params = new URLSearchParams(window.location.search)
  const autoSelectId = params.get('pwMediaPickerSelect')
  if (autoSelectId) {
    const autoCard = document.querySelector(
      `.media-mosaic__card[data-id="${autoSelectId}"]`,
    )
    if (autoCard) {
      sendSelection(fieldId, extractMediaFromCard(autoCard))
    }
    params.delete('pwMediaPickerSelect')
    const newSearch = params.toString()
    const newUrl = newSearch
      ? `${window.location.pathname}?${newSearch}`
      : window.location.pathname
    window.history.replaceState({}, '', newUrl)
  }
}

function extractMediaFromCard(card) {
  const link = card.querySelector('.mosaic-inner-box')
  const previewImg = card.querySelector('.media-mosaic__preview img')
  const alt = (link?.dataset.mediaAlt || '').trim()
  const fileName = (
    link?.dataset.mediaFileName ||
    card.querySelector('.media-mosaic__title')?.textContent ||
    ''
  ).trim()
  const label = alt || fileName

  return {
    id: card.dataset.id,
    alt,
    fileName,
    name: label,
    thumb: previewImg ? previewImg.src : '',
    meta: buildDimensionsFromLink(link),
    ratio: link?.dataset.ratioLabel || '',
    width: link?.dataset.mediaWidth || '',
    height: link?.dataset.mediaHeight || '',
  }
}

function buildDimensionsFromLink(link) {
  if (!link) return ''
  const width = link.dataset.mediaWidth
  const height = link.dataset.mediaHeight
  if (!width || !height) {
    return ''
  }

  return `${width}x${height}`
}

function sendSelection(fieldId, media) {
  if (!media || !media.id) return

  debug('sendSelection', fieldId, media)
  sendMessageToParent(MESSAGE_TYPE, { fieldId, media })
}

function handleMultiPickerPayload(payload) {
  const { fieldId, items } = payload
  debug('Multi-select message received', fieldId, items)
  // This is handled by editorJsHelper via window message listener
  closeModal(MEDIA_PICKER_MODAL_ID)
}

// --- Multi-select child context ---

const MULTI_PICKER_STORAGE_KEY = 'pwMediaPickerMulti'

function isMultiSelectMode() {
  const params = new URLSearchParams(window.location.search)
  if (params.has('pwMediaPickerMulti')) return true
  // Only use sessionStorage fallback if there's no single-select URL param
  if (params.has('pwMediaPicker')) return false
  const isInIframe = window.parent !== window
  if (isInIframe) {
    try { return sessionStorage.getItem(MULTI_PICKER_STORAGE_KEY) === '1' } catch { return false }
  }
  return false
}

let multiSelectedItems = new Map()
let multiSelectConfirmBar = null

function initMultiSelectChildContext() {
  const params = new URLSearchParams(window.location.search)
  if (params.has('pwMediaPickerMulti')) {
    try { sessionStorage.setItem(MULTI_PICKER_STORAGE_KEY, '1') } catch {}
  }

  if (!isMultiSelectMode()) return

  document.body.classList.add('pw-admin-popup-modal', 'pw-media-picker-multi')
  bindMultiMosaicSelection()
  createConfirmBar()
  restoreMultiSelectionState()
}

function bindMultiMosaicSelection() {
  const fieldId = getPickerFieldId()
  debug('bindMultiMosaicSelection', { fieldId })
  if (!fieldId) return

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return

    const card = event.target.closest('.media-mosaic__card')
    if (!card) return

    event.preventDefault()
    toggleCardSelection(card)
  }, true)
}

function toggleCardSelection(card) {
  const mediaId = card.dataset.id
  if (!mediaId) return

  if (multiSelectedItems.has(mediaId)) {
    multiSelectedItems.delete(mediaId)
    card.classList.remove('pw-multi-selected')
  } else {
    multiSelectedItems.set(mediaId, extractMediaFromCard(card))
    card.classList.add('pw-multi-selected')
  }

  persistMultiSelectionState()
  updateConfirmBar()
}

function persistMultiSelectionState() {
  try {
    const ids = Array.from(multiSelectedItems.keys())
    sessionStorage.setItem('pwMediaPickerMultiIds', JSON.stringify(ids))
    const items = Object.fromEntries(multiSelectedItems)
    sessionStorage.setItem('pwMediaPickerMultiItems', JSON.stringify(items))
  } catch {}
}

function restoreMultiSelectionState() {
  try {
    const itemsJson = sessionStorage.getItem('pwMediaPickerMultiItems')
    if (!itemsJson) return
    const items = JSON.parse(itemsJson)
    multiSelectedItems = new Map(Object.entries(items))

    // Re-apply visual state to cards on current page
    for (const [id] of multiSelectedItems) {
      const card = document.querySelector(`.media-mosaic__card[data-id="${id}"]`)
      if (card) card.classList.add('pw-multi-selected')
    }
    updateConfirmBar()
  } catch {}
}

function createConfirmBar() {
  if (multiSelectConfirmBar) return

  multiSelectConfirmBar = document.createElement('div')
  multiSelectConfirmBar.className = 'pw-multi-select-bar'
  multiSelectConfirmBar.style.display = 'none'
  multiSelectConfirmBar.innerHTML = `
    <button type="button" class="pw-multi-select-bar__btn">Add 0 selected</button>
    <button type="button" class="pw-multi-select-bar__cancel">Cancel</button>
  `
  document.body.appendChild(multiSelectConfirmBar)

  multiSelectConfirmBar.querySelector('.pw-multi-select-bar__btn').addEventListener('click', () => {
    const fieldId = getPickerFieldId()
    if (!fieldId || multiSelectedItems.size === 0) return

    const items = Array.from(multiSelectedItems.values())
    debug('sendMultiSelection', fieldId, items)
    sendMessageToParent(MESSAGE_TYPE_MULTI, { fieldId, items })

    // Clean up
    multiSelectedItems.clear()
    try {
      sessionStorage.removeItem('pwMediaPickerMultiIds')
      sessionStorage.removeItem('pwMediaPickerMultiItems')
      sessionStorage.removeItem(MULTI_PICKER_STORAGE_KEY)
    } catch {}
  })

  multiSelectConfirmBar.querySelector('.pw-multi-select-bar__cancel').addEventListener('click', () => {
    multiSelectedItems.clear()
    document.querySelectorAll('.pw-multi-selected').forEach(c => c.classList.remove('pw-multi-selected'))
    try {
      sessionStorage.removeItem('pwMediaPickerMultiIds')
      sessionStorage.removeItem('pwMediaPickerMultiItems')
    } catch {}
    updateConfirmBar()
  })
}

function updateConfirmBar() {
  if (!multiSelectConfirmBar) return
  const count = multiSelectedItems.size
  const btn = multiSelectConfirmBar.querySelector('.pw-multi-select-bar__btn')
  btn.textContent = `Add ${count} selected`
  multiSelectConfirmBar.style.display = count > 0 ? 'flex' : 'none'
}
