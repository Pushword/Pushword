import imageCompression from 'browser-image-compression'
import { suggestTags } from './admin.tagsField'

const COMPRESSIBLE_TYPES = ['image/jpeg', 'image/png', 'image/webp']

const UPLOAD_COMPRESSION_OPTIONS = {
  maxSizeMB: 1.8,
  maxWidthOrHeight: 1980,
  initialQuality: 0.85,
  useWebWorker: true,
  preserveExif: false,
}

async function compressForUpload(file) {
  if (!COMPRESSIBLE_TYPES.includes(file.type)) {
    return file
  }

  try {
    const compressed = await imageCompression(file, {
      ...UPLOAD_COMPRESSION_OPTIONS,
      fileType: file.type,
    })
    return new File([compressed], file.name, {
      type: compressed.type,
      lastModified: file.lastModified,
    })
  } catch {
    return file
  }
}

export function initMultiUpload() {
  const container = document.getElementById('pw-multi-upload')
  if (!container) return

  const uploadUrl = container.dataset.uploadUrl
  const inlineUpdateUrlTemplate = container.dataset.inlineUpdateUrl
  const deleteUrlTemplate = container.dataset.deleteUrl
  const editUrlTemplate = container.dataset.editUrl
  const csrfToken = container.dataset.csrfToken
  const allTags = container.dataset.allTags
  const dropZone = document.getElementById('pw-drop-zone')
  const fileInput = document.getElementById('pw-file-input')
  const table = document.getElementById('pw-upload-table')
  const tbody = document.getElementById('pw-upload-tbody')

  // Drop zone events
  dropZone.addEventListener('click', () => fileInput.click())
  dropZone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault()
      fileInput.click()
    }
  })

  dropZone.addEventListener('dragover', (e) => {
    e.preventDefault()
    dropZone.classList.add('dragover')
  })
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'))
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault()
    dropZone.classList.remove('dragover')
    handleFiles(e.dataTransfer.files)
  })

  fileInput.addEventListener('change', () => {
    handleFiles(fileInput.files)
    fileInput.value = ''
  })

  async function handleFiles(fileList) {
    if (!fileList || fileList.length === 0) return
    const files = Array.from(fileList)
    table.style.display = ''
    for (const file of files) {
      await uploadFile(file)
    }
    showFlash(`${files.length} file(s) processed`, 'success')
  }

  async function uploadFile(file) {
    const row = createPlaceholderRow(file.name)

    const processedFile = await compressForUpload(file)

    const formData = new FormData()
    formData.append('file', processedFile)
    formData.append('_token', csrfToken)

    try {
      const response = await fetch(uploadUrl, { method: 'POST', body: formData })
      const data = await response.json()

      if (!response.ok) {
        setRowError(row, data.error || 'Upload failed')
        return
      }

      if (data.skipped) {
        setRowSkipped(row, data.fileName)
        return
      }

      populateRow(row, data)
    } catch (err) {
      setRowError(row, 'Network error')
    }
  }

  function createPlaceholderRow(fileName) {
    const row = document.createElement('tr')
    row.innerHTML = `
      <td><span class="pw-status-spin"></span></td>
      <td colspan="6" class="text-muted">${escapeHtml(fileName)}</td>
      <td></td>
    `
    tbody.appendChild(row)
    return row
  }

  function splitFileName(fileName) {
    const dot = fileName.lastIndexOf('.')
    if (dot <= 0) return { slug: fileName, ext: '' }
    return { slug: fileName.substring(0, dot), ext: fileName.substring(dot) }
  }

  function populateRow(row, data) {
    const editUrl = editUrlTemplate.replace('__ID__', data.id)
    const thumbInner = data.thumbnailUrl
      ? `<img src="${escapeHtml(data.thumbnailUrl)}" class="pw-thumb" alt="">`
      : `<i class="fas fa-file"></i>`
    const thumbHtml = `<a href="${escapeAttr(editUrl)}">${thumbInner}</a>`

    const { slug, ext } = splitFileName(data.fileName)
    const dims = data.width && data.height ? `${data.width}×${data.height}` : '—'
    const size = formatSize(data.size)

    row.innerHTML = `
      <td>${thumbHtml}</td>
      <td class="pw-filename-cell"><input type="text" value="${escapeAttr(slug)}" data-field="slug" data-id="${data.id}"><span class="pw-ext text-muted">${escapeHtml(ext)}</span></td>
      <td><input type="text" value="${escapeAttr(data.alt)}" data-field="alt" data-id="${data.id}"></td>
      <td><input type="text" value="${escapeAttr(data.tags)}" data-field="tags" data-id="${data.id}" data-tags='${escapeAttr(allTags)}' data-delimiter=" "><div class="textSuggester" style="display:none;"></div></td>
      <td><textarea data-field="alts" data-id="${data.id}">${escapeHtml(data.alts)}</textarea></td>
      <td><small>${escapeHtml(size)}</small></td>
      <td><small>${escapeHtml(dims)}</small></td>
      <td class="pw-row-actions">
        <span class="pw-row-status d-inline"><i class="fas fa-check pw-status-ok"></i></span>
        <a href="${escapeAttr(editUrl)}" class="btn btn-outline-secondary btn-sm" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
        <button type="button" class="btn btn-outline-danger btn-sm pw-delete-btn" data-id="${data.id}" title="Delete">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    `

    row.querySelectorAll('input, textarea').forEach((input) => {
      input.addEventListener('blur', () => saveField(input, row))
    })

    row.querySelector('.pw-delete-btn').addEventListener('click', () => deleteMedia(data.id, row))

    suggestTags()
  }

  function setRowError(row, message) {
    row.querySelector('td').innerHTML = `<i class="fas fa-times pw-status-err"></i>`
    row.querySelector('td:nth-child(2)').setAttribute('colspan', '')
    row.querySelector('td:nth-child(2)').textContent += ` — ${message}`
  }

  function setRowSkipped(row, existingFileName) {
    row.querySelector('td').innerHTML = `<i class="fas fa-forward" style="color:#6c757d;"></i>`
    row.querySelector('td:nth-child(2)').setAttribute('colspan', '')
    row.querySelector('td:nth-child(2)').innerHTML =
      `<span class="text-muted">Skipped (already exists as <em>${escapeHtml(existingFileName)}</em>)</span>`
  }

  async function saveField(input, row) {
    const id = input.dataset.id
    const field = input.dataset.field
    const value = input.value
    const statusEl = row.querySelector('.pw-row-status')

    statusEl.innerHTML = '<span class="pw-status-spin"></span>'

    const url = inlineUpdateUrlTemplate.replace('__ID__', id)
    const formData = new FormData()
    formData.append('field', field)
    formData.append('value', value)
    formData.append('_token', csrfToken)

    try {
      const response = await fetch(url, { method: 'POST', body: formData })
      if (response.ok) {
        const data = await response.json()
        if (data.slug && data.slug !== input.value) {
          showFlash(`Renamed to "${data.fileName}" (name was already taken)`, 'warning')
          input.value = data.slug
        }
        statusEl.innerHTML = '<i class="fas fa-check pw-status-ok"></i>'
      } else {
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle pw-status-err"></i>'
      }
    } catch {
      statusEl.innerHTML = '<i class="fas fa-exclamation-triangle pw-status-err"></i>'
    }
  }

  async function deleteMedia(id, row) {
    const url = deleteUrlTemplate.replace('__ID__', id)
    const formData = new FormData()
    formData.append('_token', csrfToken)

    const statusEl = row.querySelector('.pw-row-status')
    statusEl.innerHTML = '<span class="pw-status-spin"></span>'

    try {
      const response = await fetch(url, { method: 'POST', body: formData })
      if (response.ok) {
        row.style.transition = 'opacity .3s'
        row.style.opacity = '0'
        setTimeout(() => row.remove(), 300)
      } else {
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle pw-status-err"></i>'
        showFlash('Delete failed', 'error')
      }
    } catch {
      statusEl.innerHTML = '<i class="fas fa-exclamation-triangle pw-status-err"></i>'
      showFlash('Network error', 'error')
    }
  }

  function showFlash(message, type) {
    const flash = document.createElement('div')
    const alertTypes = { success: 'success', warning: 'warning' }
    flash.className = `alert alert-${alertTypes[type] ?? 'danger'} alert-dismissible fade show`
    flash.setAttribute('role', 'status')
    flash.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;min-width:250px;box-shadow:0 4px 12px rgba(0,0,0,.15);'
    flash.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`
    document.body.appendChild(flash)
    setTimeout(() => flash.remove(), 4000)
  }

  function formatSize(bytes) {
    if (!bytes) return '—'
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1024 / 1024).toFixed(1) + ' MB'
  }

  function escapeHtml(str) {
    const div = document.createElement('div')
    div.textContent = str ?? ''
    return div.innerHTML
  }

  function escapeAttr(str) {
    return (str ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  }
}
