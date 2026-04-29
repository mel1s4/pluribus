<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Icon from '../../atoms/Icon.vue'
import FavoriteButton from './FavoriteButton.vue'
import { useAppShell } from '../../composables/useAppShell'
import { t } from '../../i18n/i18n'
import { fetchGlobalSearch } from '../../services/searchApi'

const route = useRoute()
const router = useRouter()
const { sidebarOpen, toggleSidebar, headerActions } = useAppShell()

const searchQuery = ref('')
const searchOpen = ref(false)
const searchLoading = ref(false)
const searchError = ref('')
const searchRoot = ref(null)
const searchRequestId = ref(0)
const searchResults = ref({
  members: [],
  offers: [],
  requirements: [],
  places: [],
})
const sidebarControlsId = 'app-sidebar'
let searchDebounceTimer = null

const titleText = computed(() => {
  const key = route.meta?.headerTitleKey
  if (typeof key !== 'string' || !key.length) return ''
  return t(key)
})

const sidebarKey = computed(() => {
  const key = route.meta?.sidebarKey
  return typeof key === 'string' && key.length > 0 ? key : ''
})

const quickItems = computed(() => [
  { to: '/chats', icon: 'comments', label: t('quickNav.chats') },
  { to: '/map', icon: 'map-location-dot', label: t('quickNav.map') },
  { to: '/notifications', icon: 'bell', label: t('quickNav.notifications') },
  { to: '/profile', icon: 'user', label: t('quickNav.profile') },
])

const resultSections = computed(() => ([
  { key: 'members', label: t('header.searchMembers'), items: searchResults.value.members },
  { key: 'offers', label: t('header.searchOffers'), items: searchResults.value.offers },
  { key: 'requirements', label: t('header.searchRequirements'), items: searchResults.value.requirements },
  { key: 'places', label: t('header.searchPlaces'), items: searchResults.value.places },
]).filter((section) => Array.isArray(section.items) && section.items.length > 0))

const hasAnyResults = computed(() => resultSections.value.length > 0)

function clearSearchResults() {
  searchResults.value = { members: [], offers: [], requirements: [], places: [] }
}

async function runGlobalSearch(rawQuery) {
  const q = String(rawQuery || '').trim()
  if (q.length < 2) {
    searchLoading.value = false
    searchError.value = ''
    searchOpen.value = false
    clearSearchResults()
    return
  }
  const requestId = searchRequestId.value + 1
  searchRequestId.value = requestId
  searchLoading.value = true
  searchError.value = ''
  searchOpen.value = true
  const result = await fetchGlobalSearch(q, 6)
  if (requestId !== searchRequestId.value) return
  searchLoading.value = false
  if (!result.ok) {
    clearSearchResults()
    searchError.value = t('header.searchError')
    return
  }
  const payload = (result.data && typeof result.data === 'object') ? result.data : {}
  searchResults.value = {
    members: Array.isArray(payload.members) ? payload.members : [],
    offers: Array.isArray(payload.offers) ? payload.offers : [],
    requirements: Array.isArray(payload.requirements) ? payload.requirements : [],
    places: Array.isArray(payload.places) ? payload.places : [],
  }
}

function handleSearchFocus() {
  if (String(searchQuery.value || '').trim().length >= 2) {
    searchOpen.value = true
  }
}

function closeSearchPanel() {
  searchOpen.value = false
}

function onOutsideClick(event) {
  const root = searchRoot.value
  if (!root || root.contains(event.target)) return
  closeSearchPanel()
}

async function openResult(item) {
  if (!item || typeof item.to !== 'string' || !item.to.length) return
  closeSearchPanel()
  await router.push(item.to)
}

function onSearchKeydown(event) {
  if (event.key === 'Escape') {
    closeSearchPanel()
  }
}

watch(searchQuery, (value) => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    runGlobalSearch(value)
  }, 300)
})

watch(() => route.fullPath, () => {
  closeSearchPanel()
})

onMounted(() => {
  window.addEventListener('mousedown', onOutsideClick)
})

onBeforeUnmount(() => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  window.removeEventListener('mousedown', onOutsideClick)
})
</script>

<template>
  <header class="app-header">
    <!-- Mobile: menu + title + route-driven actions -->
    <div class="app-header__mobile">
      <button
        type="button"
        class="app-header__iconBtn"
        :aria-expanded="sidebarOpen"
        :aria-controls="sidebarControlsId"
        :aria-label="t('nav.openNavigation')"
        @click="toggleSidebar"
      >
        <Icon class="app-header__iconGlyph" name="bars" aria-hidden="true" />
      </button>

      <h1 v-if="titleText" class="app-header__title">{{ titleText }}</h1>
      <div v-else class="app-header__titleSpacer" />

      <FavoriteButton
        v-if="sidebarKey"
        class="app-header__favorite"
        :route-key="sidebarKey"
        size="sm"
      />

      <div class="app-header__actions">
        <Button
          v-for="action in headerActions"
          :key="action.id"
          type="button"
          :variant="action.variant ?? 'secondary'"
          size="sm"
          @click="action.onClick"
        >
          {{ action.label }}
        </Button>
      </div>
    </div>

    <!-- Desktop: nav + search | quick icon links -->
    <div class="app-header__desktop">
      <div class="app-header__left">
        <button
          type="button"
          class="app-header__iconBtn"
          :aria-expanded="sidebarOpen"
          :aria-controls="sidebarControlsId"
          :aria-label="t('nav.openNavigation')"
          @click="toggleSidebar"
        >
          <Icon class="app-header__iconGlyph" name="bars" aria-hidden="true" />
        </button>

        <div ref="searchRoot" class="app-header__searchWrap">
          <div class="app-header__search">
          <Icon
            class="app-header__searchIcon"
            name="magnifying-glass"
            aria-hidden="true"
          />
          <input
            v-model="searchQuery"
            class="app-header__searchInput"
            type="search"
            name="q"
            :placeholder="t('header.searchPlaceholder')"
            :aria-label="t('header.searchPlaceholder')"
            autocomplete="off"
            @focus="handleSearchFocus"
            @keydown="onSearchKeydown"
          />
          </div>

          <div v-if="searchOpen" class="app-header__searchPanel">
            <p v-if="searchLoading" class="app-header__searchState">{{ t('header.searchLoading') }}</p>
            <p v-else-if="searchError" class="app-header__searchState">{{ searchError }}</p>
            <p v-else-if="!hasAnyResults" class="app-header__searchState">{{ t('header.searchNoResults') }}</p>
            <section
              v-for="section in resultSections"
              :key="section.key"
              class="app-header__searchSection"
              :aria-label="section.label"
            >
              <h2 class="app-header__searchSectionTitle">{{ section.label }}</h2>
              <ul class="app-header__resultList">
                <li v-for="item in section.items" :key="`${section.key}-${item.id}`">
                  <button
                    type="button"
                    class="app-header__resultCard"
                    @click="openResult(item)"
                  >
                    <div class="app-header__resultTop">
                      <img
                        v-if="item.avatar_url || item.image_url"
                        :src="item.avatar_url || item.image_url"
                        alt=""
                        class="app-header__resultAvatar"
                      >
                      <span v-else class="app-header__resultAvatar app-header__resultAvatar--fallback">
                        {{ String(item.name || '?').slice(0, 1).toUpperCase() }}
                      </span>
                      <span class="app-header__resultName">{{ item.name }}</span>
                    </div>
                    <p v-if="item.subtitle" class="app-header__resultSubtitle">{{ item.subtitle }}</p>
                    <ul v-if="Array.isArray(item.tags) && item.tags.length" class="app-header__tagList">
                      <li v-for="tag in item.tags.slice(0, 3)" :key="`${item.id}-${tag}`" class="app-header__tag">
                        {{ tag }}
                      </li>
                    </ul>
                  </button>
                </li>
              </ul>
            </section>
          </div>
        </div>
      </div>

      <nav class="app-header__quick" aria-label="Quick actions">
        <RouterLink
          v-for="item in quickItems"
          :key="item.to"
          :to="item.to"
          class="app-header__quickLink"
          active-class="is-active"
          :title="item.label"
          :aria-label="item.label"
        >
          <Icon class="app-header__iconGlyph" :name="item.icon" aria-hidden="true" />
        </RouterLink>
      </nav>
    </div>
  </header>
</template>

<style lang="scss" scoped>
.app-header {
  border-bottom: 1px solid var(--border);
  background: var(--bg);
}

.app-header__mobile {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 57px;
  padding: 0 0.75rem;
  box-sizing: border-box;

  @media (min-width: 1024px) {
    display: none;
  }
}

.app-header__desktop {
  display: none;

  @media (min-width: 1024px) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-height: 57px;
    padding: 0 1rem;
    box-sizing: border-box;
  }
}

.app-header__left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
  min-width: 0;
}

.app-header__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.2;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-header__titleSpacer {
  flex: 1;
}

.app-header__favorite {
  flex-shrink: 0;
}

.app-header__actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
}

.app-header__iconBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--btn-bg);
  color: var(--btn-text);
  cursor: pointer;
  flex-shrink: 0;

  &:hover {
    background: var(--btn-bg-hover);
  }
}

.app-header__iconGlyph {
  font-size: 1.1rem;
}

.app-header__search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  max-width: 28rem;
  min-width: 0;
  padding: 0 0.65rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--bg);
}

.app-header__searchWrap {
  position: relative;
  width: 100%;
  max-width: 28rem;
}

.app-header__searchIcon {
  color: var(--text);
  opacity: 0.55;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.app-header__searchInput {
  flex: 1;
  min-width: 0;
  border: none;
  padding: 0.5rem 0.25rem 0.5rem 0;
  background: transparent;

  &:focus {
    border-color: transparent;
    outline: none;
  }
}

.app-header__searchPanel {
  position: absolute;
  top: calc(100% + 0.4rem);
  left: 0;
  width: 100%;
  max-height: 70vh;
  overflow: auto;
  border: 1px solid var(--border);
  border-radius: 0.6rem;
  background: var(--bg);
  padding: 0.6rem;
  z-index: 12;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.16);
}

.app-header__searchState {
  margin: 0;
  font-size: 0.92rem;
  color: var(--text);
  opacity: 0.8;
  padding: 0.25rem 0.35rem;
}

.app-header__searchSection + .app-header__searchSection {
  margin-top: 0.6rem;
  padding-top: 0.6rem;
  border-top: 1px solid var(--border);
}

.app-header__searchSectionTitle {
  margin: 0 0 0.4rem;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  opacity: 0.75;
}

.app-header__resultList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.45rem;
}

.app-header__resultCard {
  width: 100%;
  text-align: left;
  border: 1px solid var(--border);
  border-radius: 0.55rem;
  background: transparent;
  color: inherit;
  padding: 0.55rem;
  cursor: pointer;
}

.app-header__resultCard:hover {
  background: var(--btn-bg);
}

.app-header__resultTop {
  display: flex;
  align-items: center;
  gap: 0.45rem;
}

.app-header__resultAvatar {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 999px;
  object-fit: cover;
  flex-shrink: 0;
}

.app-header__resultAvatar--fallback {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--btn-bg);
  font-size: 0.74rem;
  font-weight: 700;
}

.app-header__resultName {
  font-size: 0.93rem;
  font-weight: 600;
}

.app-header__resultSubtitle {
  margin: 0.25rem 0 0;
  font-size: 0.8rem;
  opacity: 0.82;
}

.app-header__tagList {
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin: 0.45rem 0 0;
  padding: 0;
}

.app-header__tag {
  border: 1px solid var(--border);
  border-radius: 999px;
  font-size: 0.72rem;
  padding: 0.08rem 0.45rem;
}

.app-header__quick {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
}

.app-header__quickLink {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.5rem;
  color: var(--text);
  text-decoration: none;
  opacity: 0.75;
  transition: opacity 140ms ease, background-color 140ms ease;

  &:hover {
    opacity: 1;
    background: var(--btn-bg);
  }

  &.is-active {
    opacity: 1;
    color: var(--link);
    background: rgba(37, 99, 235, 0.1);

    html[data-theme='dark'] & {
      background: rgba(96, 165, 250, 0.12);
    }
  }
}
</style>
