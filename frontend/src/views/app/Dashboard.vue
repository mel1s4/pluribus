<template>
  <section class="page page--dashboard">
    <PageToolbarTitle route-key="dashboard">
      <Title tag="h1">{{ t('dashboard.title') }}</Title>
    </PageToolbarTitle>
    <p>{{ t('dashboard.description') }}</p>

    <div class="dashboard-grid">
      <DashboardFinishProfileWidget
        v-if="showFinishProfileWidget"
        :user="sessionUser"
        @open-profile="goProfile"
      />
      <DashboardSetupPlaceWidget
        v-if="showSetupPlaceWidget"
        @open-create-place="goCreatePlace"
      />
      <DashboardInboxWidget :items="lastUnreadChats" />
      <DashboardMyPlacesWidget :items="lastPlaces" />
      <p
        v-if="!showFinishProfileWidget && !showSetupPlaceWidget && !lastUnreadChats.length && !lastPlaces.length"
        class="page__muted"
      >
        {{ t('dashboard.allCaughtUp') }}
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import { useAppShell } from '../../composables/useAppShell'
import DashboardFinishProfileWidget from '../../components/App/DashboardFinishProfileWidget.vue'
import DashboardSetupPlaceWidget from '../../components/App/DashboardSetupPlaceWidget.vue'
import DashboardInboxWidget from '../../components/App/DashboardInboxWidget.vue'
import DashboardMyPlacesWidget from '../../components/App/DashboardMyPlacesWidget.vue'
import { useSession } from '../../composables/useSession.js'
import { fetchChats } from '../../services/chatApi.js'
import { fetchPlaces } from '../../services/placesApi.js'

const router = useRouter()
const { setHeaderActions, clearHeaderActions } = useAppShell()
const { user } = useSession()

const hasAnyPlace = ref(false)
const unreadChats = ref([])
const recentPlaces = ref([])

const sessionUser = computed(() => user.value ?? null)

const profileChecks = computed(() => {
  const currentUser = sessionUser.value || {}
  return {
    hasAvatar: Boolean(currentUser.avatar_url),
    hasPhone: Array.isArray(currentUser.phone_numbers) && currentUser.phone_numbers.length > 0,
    hasContactEmail: Array.isArray(currentUser.contact_emails) && currentUser.contact_emails.length > 0,
  }
})

const showFinishProfileWidget = computed(() => {
  const checks = profileChecks.value
  return !checks.hasAvatar || !checks.hasPhone || !checks.hasContactEmail
})

const showSetupPlaceWidget = computed(() => !hasAnyPlace.value)
const lastUnreadChats = computed(() => unreadChats.value.slice(0, 5))
const lastPlaces = computed(() => recentPlaces.value.slice(0, 5))

function goProfile() {
  router.push('/profile')
}

function goCreatePlace() {
  router.push('/places/create')
}

async function loadPlacesPresence() {
  const { ok, data } = await fetchPlaces()
  if (!ok) {
    hasAnyPlace.value = false
    recentPlaces.value = []
    return
  }
  const places = Array.isArray(data?.data) ? data.data : []
  hasAnyPlace.value = places.length > 0
  recentPlaces.value = places
    .slice()
    .sort(
      (a, b) =>
        new Date(b.updated_at || b.created_at || 0).getTime() -
        new Date(a.updated_at || a.created_at || 0).getTime(),
    )
    .slice(0, 5)
}

async function loadUnreadChats() {
  const { ok, data } = await fetchChats()
  if (!ok) {
    unreadChats.value = []
    return
  }
  const chats = Array.isArray(data?.data) ? data.data : []
  unreadChats.value = chats
    .filter((chat) => Number(chat?.unread_count || 0) > 0)
    .sort(
      (a, b) =>
        new Date(b.last_message_at || b.updated_at || b.created_at || 0).getTime() -
        new Date(a.last_message_at || a.updated_at || a.created_at || 0).getTime(),
    )
    .slice(0, 5)
}

onMounted(() => {
  setHeaderActions([
    {
      id: 'go-settings',
      label: t('nav.settings'),
      variant: 'secondary',
      onClick: () => router.push('/settings'),
    },
  ])
  Promise.all([loadPlacesPresence(), loadUnreadChats()])
})

onUnmounted(() => {
  clearHeaderActions()
})
</script>

<style lang="scss" scoped>
.page--dashboard {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 0.8rem;
}

.page__muted {
  opacity: 0.8;
  margin: 0;
}
</style>
