import type { UploadResponse } from '../Abstract/AbstractMediaTool'

/**
 * A media reference: either a bare name/URL string, or an object holding one
 * under a `media`, `fileName` or `url` key, or nesting it under the `file` or
 * `image` key older blocks used (legacy and current block shapes).
 */
export type MediaData =
  | string
  | {
      media?: string
      fileName?: string
      url?: string
      file?: MediaData
      image?: MediaData
    }

/** The pick currently waiting for the picker's message, if any. */
let pendingMediaPick: AbortController | null = null

/**
 * Opens a media pick, dropping whatever the previous one left listening.
 *
 * The admin holds a single picker modal, and every block reaches it through the
 * same hidden <select>, so only one pick can be in flight — and the message it
 * answers with carries that shared select's id, not the block's. An opener
 * registers its `message` listener against the returned signal and aborts the
 * controller once the pick lands; a pick the editor abandons (modal closed
 * without choosing) sends nothing, so without this its listener would stay bound
 * and the next selection would fill the abandoned block too.
 */
function beginMediaPick(): AbortController {
  pendingMediaPick?.abort()
  pendingMediaPick = new AbortController()

  return pendingMediaPick
}

/** A media as the picker posts it (see admin.mediaPicker.js). */
export interface PickedMedia {
  id?: string | number
  fileName?: string
  alt?: string
  name?: string
  thumb?: string
  width?: string
  height?: string
}

/** The name a block stores for a picked media. */
export function pickedMediaName(media: PickedMedia): string {
  return media.fileName || String(media.id)
}

interface MediaPickerOptions {
  /** Selects the hidden <select> the picker answers for. */
  field?: string
}

/**
 * Opens the admin media picker through the hidden <select> matching `field`,
 * and hands the media the editor picks to `onPick`. `action` presses the
 * picker's upload button instead of its choose one; `multi` opens it in
 * multi-select mode. Returns the pick, for a block that goes away to abort, or
 * null when the page has no such picker.
 */
export function openMediaPicker(
  options: MediaPickerOptions & {
    action?: 'choose' | 'upload'
    multi?: false
    onPick: (media: PickedMedia) => void
  },
): AbortController | null
export function openMediaPicker(
  options: MediaPickerOptions & { multi: true; onPick: (items: PickedMedia[]) => void },
): AbortController | null
export function openMediaPicker({
  field = '[id*="inline_image"]',
  action = 'choose',
  multi = false,
  onPick,
}: MediaPickerOptions & {
  action?: 'choose' | 'upload'
  multi?: boolean
  onPick: (picked: any) => void
}): AbortController | null {
  const select = document.querySelector<HTMLSelectElement>('select' + field)
  const button = select
    ?.closest('.pw-media-picker')
    ?.querySelector<HTMLButtonElement>(`[data-pw-media-picker-action="${action}"]`)

  if (!select || !button) {
    console.error('media picker not found for selector:', 'select' + field)
    return null
  }

  const pick = beginMediaPick()
  const type = multi ? 'pw-media-picker-multi-select' : 'pw-media-picker-select'

  // Registered before the modal opens, so no answer can slip past
  const messageHandler = (event: MessageEvent): void => {
    if (event.origin !== window.location.origin) return
    const payload = event.data
    if (payload?.type !== type || payload.fieldId !== select.id) return

    // The picker never posts an empty pick: such a message is not this pick's answer
    const picked = multi ? payload.items : payload.media
    if (!picked) return

    pick.abort()
    onPick(picked)
  }
  window.addEventListener('message', messageHandler, { signal: pick.signal })

  if (multi) {
    clickInMultiMode(select, button)
  } else {
    button.click()
  }

  return pick
}

/**
 * Clicks the picker's choose button with pwMediaPickerMulti=1 temporarily
 * injected into the select's base URL, so the modal opens in multi-select mode.
 */
function clickInMultiMode(select: HTMLSelectElement, button: HTMLButtonElement): void {
  const urlKey = select.dataset.pwMediaPickerModalUrl
    ? 'pwMediaPickerModalUrl'
    : 'pwAdminPopupModalUrl'
  const originalUrl = select.dataset[urlKey] || ''

  try {
    const url = new URL(originalUrl, window.location.origin)
    url.searchParams.set('pwMediaPickerMulti', '1')
    select.dataset[urlKey] = url.toString()
  } catch {
    // fallback: append as query string
    select.dataset[urlKey] =
      originalUrl + (originalUrl.includes('?') ? '&' : '?') + 'pwMediaPickerMulti=1'
  }

  button.click()

  select.dataset[urlKey] = originalUrl
}

/**
 * Opens the device's file dialog and hands the chosen file to `onFile`.
 *
 * The input is left out of the document on purpose: a dialog the editor cancels
 * fires no event, so an attached input would pile up one dead node per cancel.
 */
export function pickFile(accept: string, onFile: (file: File) => void): void {
  const input = document.createElement('input')
  input.type = 'file'
  if (accept) input.accept = accept

  input.addEventListener('change', () => {
    const file = input.files?.[0]
    if (file) onFile(file)
  })

  input.click()
}

/** Posts a file to the media endpoint; rejects with the server's reason when it refuses it. */
export async function uploadMedia(file: File): Promise<UploadResponse> {
  const formData = new FormData()
  formData.append('image', file)

  const response = await fetch('/admin/media/block', { method: 'POST', body: formData })
  if (!response.ok) throw new Error(await MediaUtils.uploadErrorMessage(response))

  return response.json()
}

/**
 * Utilitaires pour la gestion des médias
 */
export class MediaUtils {
  /**
   * Extrait le nom du fichier média depuis une URL
   * @param url - URL complète du média
   * @returns Le nom du fichier (dernière partie de l'URL après /)
   */
  static extractMediaName(url?: string): string {
    if (!url) return ''
    const urlParts = url.split('/')
    const name = urlParts[urlParts.length - 1] || ''
    try {
      return decodeURIComponent(name)
    } catch {
      return name
    }
  }

  /**
   * Détermine si une donnée est une URL complète ou juste un nom de média
   * @param data - Donnée à vérifier
   * @returns true si c'est une URL complète
   */
  static isFullUrl(data: unknown): boolean {
    if (!data || typeof data !== 'string') return false

    return (
      data.startsWith('http://') ||
      data.startsWith('https://') ||
      data.startsWith('/') ||
      data.includes('/')
    )
  }

  /**
   * Construit l'URL complète à partir du nom du média ou retourne l'URL si déjà complète
   * @param mediaNameOrUrl - Nom du média ou URL complète
   * @param basePath - Chemin de base pour les médias (par défaut: /media/md/)
   * @returns URL complète
   */
  static buildFullUrl(mediaNameOrUrl: string, basePath: string = '/media/md/'): string {
    if (this.isFullUrl(mediaNameOrUrl)) {
      // C'est déjà une URL complète (rétrocompatibilité)
      return mediaNameOrUrl
    }
    // C'est un nom de média, construire l'URL
    return `${basePath}${mediaNameOrUrl}`
  }

  /**
   * The media name a block's reference holds, whichever shape saved it: a bare
   * name or a `media` field (both kept as they are, even a URL), else the name
   * a `url` ends with, else the `file` or `image` object older blocks nested it in.
   */
  static getMediaNameFromData(dataItem: MediaData | null | undefined): string {
    if (!dataItem) return ''
    if (typeof dataItem === 'string') return dataItem

    return (
      dataItem.media ||
      this.extractMediaName(dataItem.url) ||
      this.getMediaNameFromData(dataItem.file) ||
      this.getMediaNameFromData(dataItem.image)
    )
  }

  /**
   * Resolves a media name via the server-side fileNameHistory fallback.
   * Returns the current fileName if found, or null.
   */
  static async resolveMediaName(mediaName: string): Promise<string | null> {
    try {
      const response = await fetch(
        `/admin/media/resolve/${encodeURIComponent(mediaName)}`,
      )
      if (!response.ok) return null
      const data = await response.json()
      return data.fileName || null
    } catch {
      return null
    }
  }

  /**
   * An <img> for a media that, when the file is missing, asks the server for the
   * name the media was renamed to and loads that instead. `onRenamed` lets the
   * block keep the current name; `src` defaults to the media's preview URL.
   */
  static createImage(
    mediaName: string,
    onRenamed?: (renamed: string, url: string) => void,
    src: string = this.buildFullUrl(mediaName),
  ): HTMLImageElement {
    const img = document.createElement('img')
    let current = mediaName

    img.addEventListener('error', async () => {
      const resolved = await this.resolveMediaName(current)
      if (!resolved || resolved === current) return

      current = resolved
      const url = this.buildFullUrl(resolved)
      img.src = url
      onRenamed?.(resolved, url)
    })
    img.src = src

    return img
  }

  /**
   * Builds a human-readable message from a failed media upload response.
   * The endpoint answers `{ success: 0, error }` on failure; fall back to the
   * bare HTTP status when the body isn't that JSON (e.g. an HTML error page).
   */
  static async uploadErrorMessage(response: Response): Promise<string> {
    try {
      const data = await response.json()
      if (data && typeof data.error === 'string' && data.error) return data.error
    } catch {
      // body wasn't the expected JSON — fall back to the status below
    }
    return `HTTP ${response.status}`
  }

  static buildFullUrlFromData(dataItem: MediaData, basePath: string = '/media/md/'): string {
    if (typeof dataItem === 'string') {
      return this.buildFullUrl(dataItem, basePath)
    } else if (dataItem && typeof dataItem === 'object' && dataItem.url) {
      return dataItem.url
    } else if (dataItem && typeof dataItem === 'object' && dataItem.fileName) {
      const mediaName = dataItem.fileName
      return this.buildFullUrl(mediaName, basePath)
    } else if (dataItem && typeof dataItem === 'object' && dataItem.media) {
      const mediaName = dataItem.media
      return this.buildFullUrl(mediaName, basePath)
    }
    return ''
  }
}
