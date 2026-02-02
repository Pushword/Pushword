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
  static isFullUrl(data: any): boolean {
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
   * Extrait le nom du média depuis un objet de données
   * @param dataItem - Objet de données qui peut contenir media, url, ou être une string
   * @returns Le nom du média
   */
  static getMediaNameFromData(dataItem: any): string {
    if (typeof dataItem === 'string') {
      return this.isFullUrl(dataItem) ? this.extractMediaName(dataItem) : dataItem
    } else if (dataItem && typeof dataItem === 'object' && dataItem.media) {
      return dataItem.media
    } else if (dataItem && typeof dataItem === 'object' && dataItem.fileName) {
      return dataItem.fileName
    }
    return ''
  }

  /**
   * Construit l'URL complète depuis un objet de données
   * @param dataItem - Objet de données qui peut contenir media, fileName, url, ou être une string
   * @param basePath - Chemin de base pour les médias
   * @returns URL complète
   */
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

  static buildFullUrlFromData(dataItem: any, basePath: string = '/media/md/'): string {
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
