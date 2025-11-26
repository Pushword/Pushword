/**
 * Constantes globales pour l'administration
 * Centralise les sélecteurs et configurations partagées
 */

// Sélecteurs DOM
export const SELECTORS = {
  // Formulaires
  FORM_AUTOSAVE: 'form[data-pw-ctrl-s-form="1"]',
  MEDIA_PICKER: '[data-pw-media-picker]',

  // Champs
  HOST_SELECT: 'select[name$="[host]"]',
  LOCALE_INPUT: 'input[id$="_locale"]',
  PARENT_PAGE_SELECT: 'select[name$="[parentPage]"]',

  // Panels
  PW_SETTINGS_ACCORDION: '.pw-settings-accordion',
  PW_SETTINGS_TOGGLE: '.pw-settings-toggle',
  COLLAPSE: '.collapse',

  // Erreurs
  FORM_ERRORS: [
    '.ea-form-error',
    '.form-error',
    '.form-error-message',
    '.form-message-error',
    '.invalid-feedback',
    '.text-danger',
    '[data-ea-error]',
  ],
}

// Configuration
export const CONFIG = {
  // LocalStorage keys
  STORAGE_KEYS: {
    PANELS: 'panels',
  },

  // Timeouts et délais (en millisecondes)
  TIMEOUTS: {
    AUTOSAVE_SUCCESS: 200,
    AUTOSAVE_ERROR: 4000,
    PANEL_ANIMATION: 500,
  },

  // Mode debug
  DEBUG: false,
}

// Types MIME pour les images
export const IMAGE_MIME_TYPES = [
  'image/jpeg',
  'image/gif',
  'image/png',
  'image/webp',
  'image/svg+xml',
]

/**
 * Fonction helper pour le logging en mode debug
 * @param {string} module - Nom du module
 * @param  {...any} args - Arguments à logger
 */
export function debugLog(module, ...args) {
  if (CONFIG.DEBUG) {
    console.debug(`[${module}]`, ...args)
  }
}
