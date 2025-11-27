const MEDIA_PICKER_MODAL_ID = 'pw-media-picker-modal'
const MEDIA_PICKER_IFRAME_CLASS = 'pw-admin-popup-iframe'
const MESSAGE_TYPE = 'pw-media-picker-select'

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
    window.addEventListener('message', handlePickerMessage, false)
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
  try {
    const url = new URL(baseUrl, window.location.origin)
    url.searchParams.set('pwMediaPickerFieldId', select.id)

    return url.toString()
  } catch (e) {
    console.warn('Invalid media picker URL', baseUrl)
    return null
  }
}

function ensureModalElements(select) {
  let modal = document.getElementById(MEDIA_PICKER_MODAL_ID)
  if (!modal) {
    modal = document.createElement('div')
    modal.id = MEDIA_PICKER_MODAL_ID
    modal.className = 'modal fade pw-media-picker__modal'
    modal.tabIndex = -1
    modal.innerHTML = `
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">${
              getDatasetValue(
                select,
                'pwMediaPickerModalTitle',
                'pwAdminPopupModalTitle',
              ) || ''
            }</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0">
            <iframe class="${MEDIA_PICKER_IFRAME_CLASS}" title="Media picker" loading="lazy"></iframe>
          </div>
        </div>
      </div>
    `
    document.body.appendChild(modal)
  }

  const iframe = modal.querySelector(`.${MEDIA_PICKER_IFRAME_CLASS}`)
  return { modal, iframe }
}

function openPickerModal(select, url) {
  const { modal, iframe } = ensureModalElements(select)
  if (!iframe) {
    window.open(url, '_blank', 'noopener')
    return
  }

  iframe.src = url

  const modalInstance = window.bootstrap
    ? window.bootstrap.Modal.getOrCreateInstance(modal, { backdrop: 'static' })
    : null

  if (modalInstance) {
    modalInstance.show()
    modal.addEventListener(
      'hidden.bs.modal',
      () => {
        iframe.src = ''
      },
      { once: true },
    )
  } else {
    window.open(url, '_blank', 'noopener')
  }
}

function handlePickerMessage(event) {
  if (event.origin !== window.location.origin) return
  const payload = event.data
  if (!payload || payload.type !== MESSAGE_TYPE) {
    return
  }

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

  const modal = document.getElementById(MEDIA_PICKER_MODAL_ID)
  if (modal && window.bootstrap) {
    window.bootstrap.Modal.getInstance(modal)?.hide()
  }
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

function initPickerChildContext() {
  if (!new URLSearchParams(window.location.search).has('pwMediaPicker')) {
    return
  }

  document.body.classList.add('pw-admin-popup-modal')
  bindMosaicSelection()
}

function bindMosaicSelection() {
  const params = new URLSearchParams(window.location.search)
  const fieldId = params.get('pwMediaPickerFieldId')
  debug('bindMosaicSelection', { fieldId, params: params.toString() })
  if (!fieldId) return

  const handleCardClick = (event) => {
    if (!(event.target instanceof Element)) {
      return
    }

    const link = event.target.closest('.media-mosaic__card .mosaic-inner-box')
    if (!link) {
      return
    }

    event.preventDefault()
    const card = link.closest('.media-mosaic__card')
    if (!card) {
      return
    }

    sendSelection(fieldId, extractMediaFromCard(card))
  }

  document
    .querySelectorAll('.media-mosaic__card .mosaic-inner-box')
    .forEach((element) => {
      element.addEventListener('click', handleCardClick)
    })

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

  const embeddedWrapper = document.querySelector('[data-pw-media-picker-embedded]')
  if (embeddedWrapper) {
    embeddedWrapper.addEventListener(
      'click',
      (event) => {
        if (!(event.target instanceof Element)) {
          return
        }

        const link = event.target.closest('.media-mosaic__card .mosaic-inner-box')
        if (!link) {
          return
        }

        event.preventDefault()
        const card = link.closest('.media-mosaic__card')
        if (!card) {
          return
        }

        sendSelection(fieldId, extractMediaFromCard(card))
      },
      true,
    )
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
  window.parent.postMessage(
    {
      type: MESSAGE_TYPE,
      fieldId,
      media,
    },
    window.location.origin,
  )
}
