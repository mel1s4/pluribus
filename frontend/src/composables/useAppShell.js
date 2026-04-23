import { ref, watch } from 'vue'

const DESKTOP_SIDEBAR_MQ = '(min-width: 1024px)'
const SIDEBAR_STORAGE_KEY = 'pluribus.appShell.sidebarOpen'

function isDesktopSidebarLayout() {
  return typeof window !== 'undefined' && window.matchMedia(DESKTOP_SIDEBAR_MQ).matches
}

function readSidebarStored() {
  if (typeof localStorage === 'undefined') return null
  try {
    const raw = localStorage.getItem(SIDEBAR_STORAGE_KEY)
    if (raw === 'true') return true
    if (raw === 'false') return false
  } catch {
    /* ignore quota / private mode */
  }
  return null
}

function writeSidebarStored(open) {
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.setItem(SIDEBAR_STORAGE_KEY, open ? 'true' : 'false')
  } catch {
    /* ignore */
  }
}

function initialSidebarOpen() {
  if (!isDesktopSidebarLayout()) return false
  return readSidebarStored() === true
}

const sidebarOpen = ref(initialSidebarOpen())
const headerActions = ref([])

if (typeof window !== 'undefined') {
  const mql = window.matchMedia(DESKTOP_SIDEBAR_MQ)
  function onSidebarBreakpointChange() {
    if (mql.matches) {
      const stored = readSidebarStored()
      sidebarOpen.value = stored === true
    } else {
      sidebarOpen.value = false
    }
  }
  mql.addEventListener('change', onSidebarBreakpointChange)
}

watch(sidebarOpen, (open) => {
  if (isDesktopSidebarLayout()) writeSidebarStored(open)
})

export function useAppShell() {
  function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
  }

  function openSidebar() {
    sidebarOpen.value = true
  }

  function closeSidebar() {
    sidebarOpen.value = false
  }

  function setHeaderActions(actions) {
    headerActions.value = Array.isArray(actions) ? actions : []
  }

  function clearHeaderActions() {
    headerActions.value = []
  }

  return {
    sidebarOpen,
    headerActions,
    toggleSidebar,
    openSidebar,
    closeSidebar,
    setHeaderActions,
    clearHeaderActions,
  }
}

