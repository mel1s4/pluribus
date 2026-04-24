<script setup>
import { computed, ref } from 'vue'
import Card from '../../atoms/Card.vue'
import { t } from '../../i18n/i18n'
import { fetchCommunityLeadership } from '../../services/communityApi.js'

const loadError = ref('')
const loading = ref(true)
const leaders = ref([])

function apiErrorMessage(data, status, fallback) {
  if (data && typeof data === 'object') {
    if (typeof data.message === 'string' && data.message.length) {
      return data.message
    }
  }
  return fallback.replace('{status}', String(status))
}

function roleLabel(leader) {
  const key = leader?.role_label_key
  if (key === 'root') {
    return t('communitySettings.leaderRoleRoot')
  }
  if (key === 'admin') {
    return t('users.typeAdmin')
  }
  if (key === 'developer') {
    return t('users.typeDeveloper')
  }
  return key ?? '—'
}

function initials(name) {
  if (typeof name !== 'string' || !name.trim()) {
    return '?'
  }
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase()
  }
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

const empty = computed(() => !loading.value && !loadError.value && leaders.value.length === 0)

async function load() {
  loadError.value = ''
  loading.value = true
  const { ok, status, data } = await fetchCommunityLeadership()
  loading.value = false
  if (!ok) {
    loadError.value = apiErrorMessage(data, status, t('communitySettings.leadershipLoadError'))
    leaders.value = []
    return
  }
  const list = data?.leaders
  leaders.value = Array.isArray(list) ? list : []
}

load()
</script>

<template>
  <div class="community-leadership-tab">
    <p class="community-leadership-tab__intro">{{ t('communitySettings.leadershipIntro') }}</p>

    <p v-if="loadError" class="community-leadership-tab__error" role="alert">
      {{ loadError }}
    </p>
    <p v-else-if="loading" class="community-leadership-tab__muted">{{ t('communitySettings.leadershipLoading') }}</p>
    <p v-else-if="empty" class="community-leadership-tab__muted">{{ t('communitySettings.leadershipEmpty') }}</p>

    <Card v-else class="community-leadership-tab__panel">
      <ul class="community-leadership-tab__list" role="list">
        <li v-for="leader in leaders" :key="leader.id" class="community-leadership-tab__item">
          <div class="community-leadership-tab__avatar-wrap">
            <img
              v-if="leader.avatar_url"
              :src="leader.avatar_url"
              alt=""
              class="community-leadership-tab__avatar-img"
              loading="lazy"
            />
            <span v-else class="community-leadership-tab__avatar-fallback" aria-hidden="true">
              {{ initials(leader.name) }}
            </span>
          </div>
          <div class="community-leadership-tab__meta">
            <span class="community-leadership-tab__name">{{ leader.name }}</span>
            <span class="community-leadership-tab__role">{{ roleLabel(leader) }}</span>
          </div>
        </li>
      </ul>
    </Card>
  </div>
</template>

<style lang="scss" scoped>
.community-leadership-tab__intro {
  margin: 0;
  opacity: 0.85;
  max-width: 40rem;
}

.community-leadership-tab__muted {
  margin: 0;
  opacity: 0.8;
}

.community-leadership-tab__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}

.community-leadership-tab__panel {
  margin-top: 0.25rem;
}

.community-leadership-tab__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
}

.community-leadership-tab__item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.5rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
}

.community-leadership-tab__avatar-wrap {
  width: 3rem;
  height: 3rem;
  border-radius: 999px;
  overflow: hidden;
  flex-shrink: 0;
  background: color-mix(in srgb, var(--border) 40%, transparent);
  display: flex;
  align-items: center;
  justify-content: center;
}

.community-leadership-tab__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.community-leadership-tab__avatar-fallback {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--muted, #4b5563);
}

.community-leadership-tab__meta {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}

.community-leadership-tab__name {
  font-weight: 600;
  font-size: 0.95rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.community-leadership-tab__role {
  font-size: 0.8rem;
  opacity: 0.85;
}
</style>
