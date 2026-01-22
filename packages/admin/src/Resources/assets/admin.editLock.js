/**
 * Page Edit Lock System
 * Manages real-time lock pinging and warning display for concurrent editing prevention.
 */

const PING_INTERVAL_MS = 3000 // 3 seconds

const generateTabId = () =>
  crypto?.randomUUID?.() ?? Date.now().toString(36) + Math.random().toString(36).substring(2)

/**
 * Initialize the edit lock system for a page
 * @param {number} pageId - The ID of the page being edited
 */
export function initEditLock(pageId) {
  if (!pageId) return

  // Generate unique ID for this tab/browser instance
  const tabId = generateTabId()

  const state = {
    isOwner: false,
    lastKnownSavedAt: null,
    pingIntervalId: null,
  }

  const banner = document.getElementById('pw-edit-lock-banner')
  const bannerMessage = document.getElementById('pw-edit-lock-message')
  const bannerRefresh = document.getElementById('pw-edit-lock-refresh')

  /**
   * Update the warning banner visibility and content
   */
  const updateBanner = (lockInfo, isOwner, isSameUser) => {
    if (!banner) return

    if (isOwner || !lockInfo) {
      banner.style.display = 'none'
      banner.classList.add('hidden')
      banner.setAttribute('aria-hidden', 'true')
      return
    }

    if (bannerMessage) {
      if (isSameUser) {
        // Same user, different tab/browser
        bannerMessage.textContent =
          window.pwEditLockTranslations?.warningSameUser ??
          'You are editing this page in another tab or browser.'
      } else {
        // Different user
        const username = lockInfo.username || lockInfo.userEmail || 'Another user'
        bannerMessage.textContent =
          window.pwEditLockTranslations?.warning?.replace('%user%', username) ??
          `${username} is currently editing this page. Your changes may be overwritten.`
      }
    }

    banner.classList.remove('hidden')
    banner.style.display = 'flex'
    banner.setAttribute('aria-hidden', 'false')
  }

  const showSaveNotification = () => {
    if (bannerRefresh) bannerRefresh.style.display = 'flex'
  }

  /**
   * Ping the server to maintain lock or check status
   */
  const ping = async () => {
    try {
      const response = await fetch(`/admin/page/${pageId}/lock/ping`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ tabId }),
      })

      if (!response.ok) {
        console.warn('[EditLock] Ping failed:', response.status)
        return
      }

      const data = await response.json()
      state.isOwner = data.isOwner

      updateBanner(data.lockInfo, data.isOwner, data.isSameUser)

      // Check if main editor has saved (for warned users)
      if (!state.isOwner && data.lockInfo?.lastSavedAt) {
        if (state.lastKnownSavedAt !== null && data.lockInfo.lastSavedAt > state.lastKnownSavedAt) {
          showSaveNotification()
        }
        state.lastKnownSavedAt = data.lockInfo.lastSavedAt
      }
    } catch (error) {
      console.error('[EditLock] Ping error:', error)
    }
  }

  /**
   * Start the lock system
   */
  const start = () => {
    ping() // Initial ping
    state.pingIntervalId = window.setInterval(ping, PING_INTERVAL_MS)

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) ping()
    })
  }

  const stop = () => {
    if (state.pingIntervalId) window.clearInterval(state.pingIntervalId)
  }

  // Auto-start
  start()

  // Return control interface
  return { start, stop, ping }
}

/**
 * Auto-initialize from DOM attributes
 */
export function autoInitEditLock() {
  const form = document.querySelector('[data-pw-edit-lock-page-id]')

  if (!form) return null

  const pageId = parseInt(form.dataset.pwEditLockPageId, 10)

  if (!pageId || isNaN(pageId)) return null

  return initEditLock(pageId)
}
