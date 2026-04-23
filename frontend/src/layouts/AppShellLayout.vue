<template>
  <div
    class="app-shell"
    :class="{
      'app-shell--hasHeader': showHeader,
      'app-shell--chromeHidden': showHeader && !chromeVisible,
      'app-shell--mobileTopPad': showMobileTopPadding,
    }"
  >
    <div
      v-if="showHeader"
      class="app-shell__headerWrap"
      :class="{ 'is-chrome-hidden': !chromeVisible }"
    >
      <AppHeader />
    </div>

    <div class="app-shell__content">
      <aside
        v-if="isDesktop"
        class="app-shell__sidebarDesktop"
        :class="{ 'is-open': sidebarOpen }"
      >
        <Sidebar :open="sidebarOpen" @close="closeSidebar" />
      </aside>

      <main class="app-shell__main">
        <slot />
      </main>
    </div>

    <div
      v-if="showHeader"
      class="app-shell__bottomNav"
      :class="{ 'is-chrome-hidden': !chromeVisible }"
    >
      <QuickNav placement="bottom" />
    </div>

    <MobileNav v-if="!isDesktop" :open="sidebarOpen" @close="closeSidebar" />
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '../components/App/Header.vue'
import MobileNav from '../components/App/MobileNav.vue'
import QuickNav from '../components/App/QuickNav.vue'
import Sidebar from '../components/App/Sidebar.vue'
import { useAppShell } from '../composables/useAppShell'
import { useDesktopViewport } from '../composables/useDesktopViewport'
import { useMobileChromeScroll } from '../composables/useMobileChromeScroll'

const route = useRoute()
const { isDesktop } = useDesktopViewport()
const { sidebarOpen, closeSidebar, headerActions } = useAppShell()

const showHeader = computed(() => !route.meta?.hideHeader)
const { chromeVisible } = useMobileChromeScroll(showHeader)

/** Mobile fixed header only when title/actions strip is shown (menu is in bottom bar). */
const showMobileTopPadding = computed(() => {
  if (!showHeader.value) return false
  const key = route.meta?.headerTitleKey
  const hasTitleKey = typeof key === 'string' && key.length > 0
  const hasActions = headerActions.value.length > 0
  if (typeof window === 'undefined') return hasTitleKey || hasActions
  if (!window.matchMedia('(max-width: 1023px)').matches) return false
  return hasTitleKey || hasActions
})

watch(
  () => route.fullPath,
  () => {
    if (window.matchMedia('(max-width: 1023px)').matches) {
      closeSidebar()
    }
  }
)
</script>

<style lang="scss" scoped>
.app-shell {
  min-height: 100dvh;
}

.app-shell__headerWrap {
  @media (max-width: 1023px) {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 35;
    transition: transform 0.22s ease;
    background: var(--bg);

    &.is-chrome-hidden {
      transform: translateY(-100%);
    }
  }
}

.app-shell__bottomNav {
  display: none;

  @media (max-width: 1023px) {
    display: block;
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 35;
    transition: transform 0.22s ease;

    &.is-chrome-hidden {
      transform: translateY(100%);
    }
  }
}

.app-shell__content {
  display: block;
}

.app-shell__main {
  min-width: 0;
}

.app-shell__sidebarDesktop {
  display: none;
}

@media (max-width: 1023px) {
  .app-shell--hasHeader:not(.app-shell--chromeHidden).app-shell--mobileTopPad .app-shell__content {
    padding-top: 57px;
  }

  .app-shell--hasHeader:not(.app-shell--mobileTopPad) .app-shell__content,
  .app-shell--hasHeader.app-shell--chromeHidden .app-shell__content {
    padding-top: 0;
  }

  .app-shell--hasHeader:not(.app-shell--chromeHidden) .app-shell__main {
    padding-bottom: calc(3.25rem + env(safe-area-inset-bottom, 0px));
  }

  .app-shell--hasHeader.app-shell--chromeHidden .app-shell__main {
    padding-bottom: env(safe-area-inset-bottom, 0px);
  }
}

@media (min-width: 1024px) {
  .app-shell__content {
    display: flex;
    min-height: calc(100dvh - 57px);
  }

  .app-shell__sidebarDesktop {
    display: block;
    width: 0;
    overflow: hidden;
    transition: width 220ms ease;
    border-right: 0 solid transparent;
  }

  .app-shell__sidebarDesktop.is-open {
    width: min(30vw, 320px);
    border-right: 1px solid var(--border);
  }

  .app-shell__main {
    flex: 1 1 auto;
  }
}
</style>
