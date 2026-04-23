import { ref, watch, onBeforeUnmount } from 'vue'
import { fetchPhotonSuggestions } from './usePhotonGeocode.js'
import { language, t } from '../i18n/i18n'

const DEBOUNCE_MS = 350

/**
 * Debounced Photon suggestions for the place location search field.
 */
export function usePlaceSearchSuggestions() {
  const searchQuery = ref('')
  const suggestions = ref([])
  const listOpen = ref(false)
  const searchLoading = ref(false)
  const searchError = ref('')

  let debounceTimer = null
  let abortCtrl = null

  function clearDebounce() {
    if (debounceTimer != null) {
      clearTimeout(debounceTimer)
      debounceTimer = null
    }
  }

  function abortPending() {
    abortCtrl?.abort()
    abortCtrl = null
  }

  async function runSearch(q) {
    const trimmed = q.trim()
    if (trimmed.length < 3) {
      suggestions.value = []
      listOpen.value = false
      searchLoading.value = false
      return
    }
    abortPending()
    abortCtrl = new AbortController()
    searchLoading.value = true
    searchError.value = ''
    try {
      const rows = await fetchPhotonSuggestions(trimmed, {
        lang: language.value === 'es' ? 'es' : 'en',
        signal: abortCtrl.signal,
        limit: 8,
      })
      suggestions.value = rows
      listOpen.value = rows.length > 0
    } catch (e) {
      if (e?.name === 'AbortError') return
      suggestions.value = []
      listOpen.value = false
      searchError.value = t('myPlaces.searchAddressError')
    } finally {
      searchLoading.value = false
    }
  }

  watch(searchQuery, (q) => {
    clearDebounce()
    abortPending()
    if (q.trim().length < 3) {
      suggestions.value = []
      listOpen.value = false
      searchError.value = ''
      return
    }
    debounceTimer = window.setTimeout(() => {
      debounceTimer = null
      runSearch(q)
    }, DEBOUNCE_MS)
  })

  onBeforeUnmount(() => {
    clearDebounce()
    abortPending()
  })

  function closeListAndClearSuggestions() {
    listOpen.value = false
    suggestions.value = []
  }

  return {
    searchQuery,
    suggestions,
    listOpen,
    searchLoading,
    searchError,
    closeListAndClearSuggestions,
  }
}
