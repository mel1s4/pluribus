<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import Icon from '../../atoms/Icon.vue'
import { hasCapability } from '../../composables/useCapabilities'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { fetchMyCommunities } from '../../services/communityApi'

const emit = defineEmits(['navigate'])

const loading = ref(false)
const error = ref('')
const rows = ref([])

async function load() {
  loading.value = true
  error.value = ''
  const { ok, status, data } = await fetchMyCommunities()
  loading.value = false
  if (!ok) {
    error.value = t('myCommunities.loadError').replace('{status}', String(status))
    rows.value = []
    return
  }
  rows.value = Array.isArray(data?.data) ? data.data : []
}

function onNavigate() {
  emit('navigate')
}

/** Matches `communityMembershipsBeforeEnter` in the router. */
function showCommunityMembersLink(row) {
  if (!hasCapability('community.memberships.manage')) {
    return false
  }
  if (sessionUser.value?.is_root) {
    return true
  }
  return row.role === 'admin'
}

onMounted(load)
</script>

<template>
  <div v-if="rows.length > 0" class="sidebar-my-communities">
    <div class="sidebar-my-communities__heading" role="presentation">
      {{ t('nav.myCommunitiesSection') }}
    </div>
    <p v-if="error" class="sidebar-my-communities__error">{{ error }}</p>
    <p v-else-if="loading" class="sidebar-my-communities__muted">{{ t('myCommunities.loading') }}</p>
    <ul v-else class="sidebar-my-communities__list" role="list">
      <li v-for="row in rows" :key="row.id" class="sidebar-my-communities__block">
        <div class="sidebar-my-communities__name">{{ row.name }}</div>
        <div class="sidebar-my-communities__links">
          <RouterLink
            class="sidebar-my-communities__link"
            :to="`/community/${row.slug}`"
            @click="onNavigate"
          >
            <Icon class="sidebar-my-communities__icon" name="people-roof" aria-hidden="true" />
            <span>{{ t('sidebar.myCommunities.microsite') }}</span>
          </RouterLink>
          <RouterLink
            class="sidebar-my-communities__link"
            :to="`/community/${row.slug}/dashboard`"
            @click="onNavigate"
          >
            <Icon class="sidebar-my-communities__icon" name="gauge-high" aria-hidden="true" />
            <span>{{ t('sidebar.myCommunities.dashboard') }}</span>
          </RouterLink>
          <RouterLink
            class="sidebar-my-communities__link"
            :to="{ name: 'communitySettingsBySlug', params: { slug: row.slug } }"
            @click="onNavigate"
          >
            <Icon class="sidebar-my-communities__icon" name="gear" aria-hidden="true" />
            <span>{{ t('sidebar.myCommunities.settings') }}</span>
          </RouterLink>
          <RouterLink
            v-if="showCommunityMembersLink(row)"
            class="sidebar-my-communities__link"
            :to="{ name: 'communityMemberships', params: { slug: row.slug } }"
            @click="onNavigate"
          >
            <Icon class="sidebar-my-communities__icon" name="users" aria-hidden="true" />
            <span>{{ t('sidebar.myCommunities.members') }}</span>
          </RouterLink>
        </div>
      </li>
    </ul>
    <div class="sidebar-my-communities__footer">
      <RouterLink
        class="sidebar-my-communities__manage"
        to="/my-communities"
        @click="onNavigate"
      >
        {{ t('sidebar.myCommunities.manage') }}
      </RouterLink>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.sidebar-my-communities {
  padding: 0.5rem 0.65rem 0.75rem;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.sidebar-my-communities__heading {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted, #6b7280);
  margin-bottom: 0.45rem;
}
.sidebar-my-communities__error {
  color: #b91c1c;
  font-size: 0.85rem;
  margin: 0 0 0.35rem;
}
.sidebar-my-communities__muted {
  color: var(--muted, #6b7280);
  font-size: 0.85rem;
  margin: 0;
}
.sidebar-my-communities__list {
  list-style: none;
  padding: 0;
  margin: 0 0 0.5rem;
  display: grid;
  gap: 0.65rem;
}
.sidebar-my-communities__block {
  margin: 0;
}
.sidebar-my-communities__name {
  font-weight: 600;
  font-size: 0.88rem;
  margin-bottom: 0.25rem;
}
.sidebar-my-communities__links {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}
.sidebar-my-communities__link {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.35rem 0.4rem;
  border-radius: 0.4rem;
  color: inherit;
  text-decoration: none;
  font-size: 0.85rem;
  &:hover {
    background: color-mix(in srgb, var(--border) 45%, transparent);
  }
}
.sidebar-my-communities__icon {
  font-size: 0.85rem;
  flex-shrink: 0;
  opacity: 0.85;
}
.sidebar-my-communities__footer {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.sidebar-my-communities__manage {
  display: block;
  font-size: 0.82rem;
  padding: 0.35rem 0.4rem;
  color: var(--link, #2563eb);
  text-decoration: none;
  border-radius: 0.4rem;
  &:hover {
    background: color-mix(in srgb, var(--border) 35%, transparent);
  }
}
</style>
