import imageCompression from 'browser-image-compression'
import { suggestTags } from './admin.tagsField'
import {
  deleteMedia,
  escapeAttr,
  escapeHtml,
  formatSize,
  openLightbox,
  saveField,
  showFlash,
} from './admin.mediaInlineEdit'

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

async function computeSha1(file) {
  const buffer = await file.arrayBuffer()
  const hashBuffer = await crypto.subtle.digest('SHA-1', buffer)
  return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('')
}

export function initMultiUpload() {
  const container = document.getElementById('pw-multi-upload')
  if (!container) return

  const uploadUrl = container.dataset.uploadUrl
  const editUrlTemplate = container.dataset.editUrl
  const csrfToken = container.dataset.csrfToken
  const allTags = container.dataset.allTags
  const ctx = {
    inlineUpdateUrl: container.dataset.inlineUpdateUrl,
    deleteUrl: container.dataset.deleteUrl,
    csrfToken,
  }
  const dropZone = document.getElementById('pw-drop-zone')
  const fileInput = document.getElementById('pw-file-input')
  const table = document.getElementById('pw-upload-table')
  const tbody = document.getElementById('pw-upload-tbody')
  const tagAllBar = document.getElementById('pw-tag-all-bar')
  const tagAllInput = document.getElementById('pw-tag-all')
  const tagAllBtn = document.getElementById('pw-tag-all-btn')

  tagAllBtn.addEventListener('click', () => {
    tbody.querySelectorAll('tr').forEach((row) => applyTagAllToRow(row))
  })

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
    tagAllBar.style.display = ''
    for (const file of files) {
      await uploadFile(file)
    }
    showFlash(`${files.length} file(s) processed`, 'success')
  }

  async function uploadFile(file) {
    const row = createPlaceholderRow(file.name)

    const originalHash = await computeSha1(file)
    const processedFile = await compressForUpload(file)

    const formData = new FormData()
    formData.append('file', processedFile)
    formData.append('originalHash', originalHash)
    formData.append('_token', csrfToken)

    try {
      const response = await fetch(uploadUrl, { method: 'POST', body: formData })
      const data = await response.json()

      if (!response.ok) {
        setRowError(row, data.error || 'Upload failed')
        return
      }

      if (data.skipped) {
        populateRow(row, data)
        row.querySelector('.pw-row-status').innerHTML =
          `<i class="fas fa-forward" style="color:#6c757d;" title="Already exists"></i>`
        return
      }

      populateRow(row, data)
      applyTagAllToRow(row)
    } catch (err) {
      setRowError(row, 'Network error')
    }
  }

  function applyTagAllToRow(row) {
    const value = tagAllInput.value.trim()
    if (!value) return
    const tagsInput = row.querySelector('[data-field="tags"]')
    if (!tagsInput || tagsInput.value === value) return
    tagsInput.value = value
    saveField(tagsInput, row, ctx)
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
      ? `<img src="${escapeHtml(data.thumbnailUrl)}" class="pw-thumb" alt="" data-full-src="${escapeAttr(data.thumbnailUrl)}">`
      : `<i class="fas fa-file"></i>`
    const thumbHtml = data.thumbnailUrl ? thumbInner : `<a href="${escapeAttr(editUrl)}">${thumbInner}</a>`

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
      input.addEventListener('blur', () => saveField(input, row, ctx))
    })

    row.querySelector('.pw-delete-btn').addEventListener('click', () => deleteMedia(data.id, row, ctx))

    const thumb = row.querySelector('.pw-thumb')
    if (thumb) {
      thumb.addEventListener('click', () => openLightbox(thumb.dataset.fullSrc))
    }

    suggestTags()
  }

  function setRowError(row, message) {
    row.querySelector('td').innerHTML = `<i class="fas fa-times pw-status-err"></i>`
    row.querySelector('td:nth-child(2)').textContent += ` — ${message}`
  }
}
