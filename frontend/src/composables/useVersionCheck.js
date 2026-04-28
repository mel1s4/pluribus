import { ref, onMounted, onUnmounted } from 'vue'

const CHECK_INTERVAL = 5 * 60 * 1000 // Check every 5 minutes
const STORAGE_KEY = 'app_build_timestamp'

let currentVersion = null
let checkTimer = null
let isCheckingVersion = false

/**
 * Check if a new version of the app has been deployed
 * If detected, shows notification and reloads the page
 */
export function useVersionCheck() {
  const hasNewVersion = ref(false)
  const isChecking = ref(false)

  async function fetchVersion() {
    try {
      // Add timestamp to prevent caching
      const response = await fetch(`/version.json?t=${Date.now()}`, {
        cache: 'no-store',
        headers: {
          'Cache-Control': 'no-cache',
          Pragma: 'no-cache',
        },
      })

      if (!response.ok) {
        return null
      }

      const data = await response.json()
      return data.timestamp
    } catch (error) {
      console.warn('[Version Check] Failed to fetch version:', error.message)
      return null
    }
  }

  async function checkVersion(silent = false) {
    if (isCheckingVersion) return
    isCheckingVersion = true
    isChecking.value = true

    try {
      const newVersion = await fetchVersion()

      if (!newVersion) {
        isCheckingVersion = false
        isChecking.value = false
        return
      }

      // Store current version on first check
      if (currentVersion === null) {
        currentVersion = newVersion
        localStorage.setItem(STORAGE_KEY, String(newVersion))
        isCheckingVersion = false
        isChecking.value = false
        return
      }

      // Check if version has changed
      if (newVersion !== currentVersion) {
        console.log('[Version Check] New version detected!', {
          current: currentVersion,
          new: newVersion,
        })
        
        // CRITICAL: Update local storage so we don't infinitely reload!
        localStorage.setItem(STORAGE_KEY, String(newVersion))
        currentVersion = newVersion
        
        hasNewVersion.value = true

        // Auto-reload after short delay unless in silent mode
        if (!silent) {
          setTimeout(() => {
            console.log('[Version Check] Reloading to get new version...')
            window.location.reload()
          }, 2000)
        }
      }
    } catch (error) {
      console.warn('[Version Check] Error during version check:', error)
    } finally {
      isCheckingVersion = false
      isChecking.value = false
    }
  }

  function startVersionChecking() {
    // Initial check after 10 seconds
    setTimeout(() => checkVersion(true), 10000)

    // Check periodically
    checkTimer = setInterval(() => checkVersion(true), CHECK_INTERVAL)

    // Check on visibility change (user returns to tab)
    const handleVisibilityChange = () => {
      if (!document.hidden) {
        checkVersion(true)
      }
    }
    document.addEventListener('visibilitychange', handleVisibilityChange)

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange)
    }
  }

  function stopVersionChecking() {
    if (checkTimer) {
      clearInterval(checkTimer)
      checkTimer = null
    }
  }

  function reloadNow() {
    window.location.reload()
  }

  onMounted(() => {
    // Initialize current version from storage
    const stored = localStorage.getItem(STORAGE_KEY)
    if (stored) {
      currentVersion = parseInt(stored, 10)
    }

    const cleanup = startVersionChecking()

    onUnmounted(() => {
      cleanup()
      stopVersionChecking()
    })
  })

  return {
    hasNewVersion,
    isChecking,
    checkVersion,
    reloadNow,
  }
}
