import { suggestTags } from './admin.tagsField'

export function escapeAttr(str) {
  return (str ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

export function escapeHtml(str) {
  const div = document.createElement('div')
  div.textContent = str ?? ''
  return div.innerHTML
}

export function formatSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1024 / 1024).toFixed(1) + ' MB'
}

export function showFlash(message, type) {
  const flash = document.createElement('div')
  const alertTypes = { success: 'success', warning: 'warning' }
  flash.className = `alert alert-${alertTypes[type] ?? 'danger'} alert-dismissible fade show`
  flash.setAttribute('role', 'status')
  flash.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;min-width:250px;box-shadow:0 4px 12px rgba(0,0,0,.15);'
  flash.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`
  document.body.appendChild(flash)
  setTimeout(() => flash.remove(), 4000)
}

export function openLightbox(src) {
  const overlay = document.createElement('div')
  overlay.className = 'pw-lightbox'
  overlay.innerHTML = `<img src="${escapeAttr(src)}" alt="">`
  overlay.addEventListener('click', () => overlay.remove())
  document.body.appendChild(overlay)
}

const STATUS_SPIN = '<span class="pw-status-spin"></span>'
const STATUS_OK = '<i class="fas fa-check pw-status-ok"></i>'
const STATUS_ERR = '<i class="fas fa-exclamation-triangle pw-status-err"></i>'

function setRowStatus(row, html) {
  const statusEl = row.querySelector('.pw-row-status')
  if (statusEl) statusEl.innerHTML = html
}

export async function saveField(input, row, ctx) {
  setRowStatus(row, STATUS_SPIN)

  const url = ctx.inlineUpdateUrl.replace('__ID__', input.dataset.id)
  const formData = new FormData()
  formData.append('field', input.dataset.field)
  formData.append('value', input.value)
  formData.append('_token', ctx.csrfToken)

  try {
    const response = await fetch(url, { method: 'POST', body: formData })
    if (!response.ok) {
      setRowStatus(row, STATUS_ERR)
      return
    }
    const data = await response.json()
    if (data.slug && data.slug !== input.value) {
      showFlash(`Renamed to "${data.fileName}" (name was already taken)`, 'warning')
      input.value = data.slug
    }
    setRowStatus(row, STATUS_OK)
  } catch {
    setRowStatus(row, STATUS_ERR)
  }
}

export async function deleteMedia(id, row, ctx) {
  const url = ctx.deleteUrl.replace('__ID__', id)
  const formData = new FormData()
  formData.append('_token', ctx.csrfToken)

  setRowStatus(row, STATUS_SPIN)

  try {
    const response = await fetch(url, { method: 'POST', body: formData })
    if (response.ok) {
      row.style.transition = 'opacity .3s'
      row.style.opacity = '0'
      setTimeout(() => row.remove(), 300)
    } else {
      setRowStatus(row, STATUS_ERR)
      showFlash('Delete failed', 'error')
    }
  } catch {
    setRowStatus(row, STATUS_ERR)
    showFlash('Network error', 'error')
  }
}

/**
 * Wire inline editing (blur-save, delete, lightbox) on the media list table
 * (?view=table). Rows are server-rendered and carry data-field/data-id inputs.
 */
export function initMediaTableEdit() {
  const container = document.getElementById('pw-media-table')
  if (!container) return

  const ctx = {
    inlineUpdateUrl: container.dataset.inlineUpdateUrl,
    deleteUrl: container.dataset.deleteUrl,
    csrfToken: container.dataset.csrfToken,
  }

  container.querySelectorAll('tr[data-id]').forEach((row) => {
    row.querySelectorAll('[data-field][data-id]').forEach((input) => {
      input.addEventListener('blur', () => saveField(input, row, ctx))
    })

    const deleteBtn = row.querySelector('.pw-delete-btn')
    if (deleteBtn) {
      deleteBtn.addEventListener('click', () => {
        if (window.confirm(deleteBtn.dataset.confirm)) deleteMedia(deleteBtn.dataset.id, row, ctx)
      })
    }

    const thumb = row.querySelector('.pw-thumb')
    if (thumb) thumb.addEventListener('click', () => openLightbox(thumb.dataset.fullSrc))
  })

  suggestTags()
}
