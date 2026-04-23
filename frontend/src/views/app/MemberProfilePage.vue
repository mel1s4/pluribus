<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import Card from '../../atoms/Card.vue'
import { t } from '../../i18n/i18n'
import { fetchMemberProfile } from '../../services/membersApi.js'

const route = useRoute()
const router = useRouter()

const member = ref(null)
const places = ref([])
const loadError = ref('')
const loading = ref(true)

const userId = computed(() => {
  const raw = route.params.userId
  return typeof raw === 'string' ? raw : ''
})

const displayEmails = computed(() => {
  const m = member.value
  if (!m) return []
  const primary = typeof m.email === 'string' && m.email.trim() ? [m.email.trim()] : []
  const extra = Array.isArray(m.contact_emails) ? m.contact_emails.filter(Boolean) : []
  const merged = [...primary]
  for (const e of extra) {
    if (!merged.includes(e)) merged.push(e)
  }
  return merged
})

async function load() {
  const id = userId.value
  if (!id) return
  loadError.value = ''
  loading.value = true
  member.value = null
  places.value = []
  const { ok, status, data } = await fetchMemberProfile(id)
  loading.value = false
  if (!ok) {
    loadError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('memberProfile.loadError').replace('{status}', String(status))
    if (status === 403 || status === 404) {
      window.setTimeout(() => router.replace({ name: 'users' }), 1600)
    }
    return
  }
  member.value = data?.member && typeof data.member === 'object' ? data.member : null
  places.value = Array.isArray(data?.places) ? data.places : []
  if (!member.value) {
    loadError.value = t('memberProfile.loadError').replace('{status}', String(status))
  }
}

watch(userId, () => {
  load()
})

function goUsers() {
  router.push({ name: 'users' })
}

load()
</script>

<template>
  <section class="member-profile-page">
    <div class="member-profile-page__toolbar">
      <button type="button" class="member-profile-page__back" @click="goUsers">
        {{ t('memberProfile.backToMembers') }}
      </button>
    </div>

    <p v-if="loadError" class="member-profile-page__error" role="alert">
      {{ loadError }}
    </p>
    <p v-else-if="loading" class="member-profile-page__muted">{{ t('memberProfile.loading') }}</p>

    <template v-else-if="member">
      <div class="member-profile-page__header">
        <div class="member-profile-page__avatarWrap">
          <img
            v-if="member.avatar_url"
            :src="member.avatar_url"
            alt=""
            class="member-profile-page__avatar"
          />
          <div v-else class="member-profile-page__avatar member-profile-page__avatar--placeholder">
            {{ (member.name || '?').slice(0, 1) }}
          </div>
        </div>
        <div class="member-profile-page__headText">
          <h1 class="member-profile-page__title">{{ member.name }}</h1>
          <p v-if="member.username" class="member-profile-page__username">@{{ member.username }}</p>
        </div>
      </div>

      <Card class="member-profile-page__card">
        <h2 class="member-profile-page__cardTitle">{{ t('memberProfile.contactHeading') }}</h2>
        <div v-if="displayEmails.length" class="member-profile-page__block">
          <h3 class="member-profile-page__label">{{ t('memberProfile.emailHeading') }}</h3>
          <ul class="member-profile-page__list">
            <li v-for="(em, i) in displayEmails" :key="'e'+i">
              <a :href="'mailto:' + em">{{ em }}</a>
            </li>
          </ul>
        </div>
        <div v-if="member.phone_numbers?.length" class="member-profile-page__block">
          <h3 class="member-profile-page__label">{{ t('profile.fieldPhones') }}</h3>
          <ul class="member-profile-page__list">
            <li v-for="(p, i) in member.phone_numbers" :key="'p'+i">{{ p }}</li>
          </ul>
        </div>
        <div v-if="member.external_links?.length" class="member-profile-page__block">
          <h3 class="member-profile-page__label">{{ t('profile.fieldExternalLinks') }}</h3>
          <ul class="member-profile-page__list">
            <li v-for="(link, i) in member.external_links" :key="'l'+i">
              <a :href="link.url" rel="noopener noreferrer" target="_blank">{{ link.title || link.url }}</a>
            </li>
          </ul>
        </div>
        <div v-if="member.aliases?.length" class="member-profile-page__block">
          <h3 class="member-profile-page__label">{{ t('profile.fieldAliases') }}</h3>
          <p class="member-profile-page__aliases">{{ member.aliases.join(', ') }}</p>
        </div>
        <p
          v-if="
            !displayEmails.length &&
            !member.phone_numbers?.length &&
            !member.external_links?.length &&
            !member.aliases?.length
          "
          class="member-profile-page__muted"
        >
          {{ t('memberProfile.contactEmpty') }}
        </p>
      </Card>

      <Card class="member-profile-page__card">
        <h2 class="member-profile-page__cardTitle">{{ t('memberProfile.placesHeading') }}</h2>
        <p v-if="!places.length" class="member-profile-page__muted">{{ t('memberProfile.placesEmpty') }}</p>
        <ul v-else class="member-profile-page__places" role="list">
          <li v-for="pl in places" :key="pl.id" class="member-profile-page__placeRow">
            <RouterLink
              class="member-profile-page__placeLink"
              :to="{
                name: 'placeView',
                params: { placeId: String(pl.id) },
                query: { member: userId },
              }"
            >
              <span v-if="pl.logo_url" class="member-profile-page__placeLogoWrap">
                <img :src="pl.logo_url" alt="" class="member-profile-page__placeLogo" />
              </span>
              <span class="member-profile-page__placeText">
                <span class="member-profile-page__placeName">{{ pl.name }}</span>
                <span v-if="pl.description" class="member-profile-page__placeDesc">{{ pl.description }}</span>
              </span>
            </RouterLink>
          </li>
        </ul>
      </Card>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.member-profile-page {
  padding: 2rem;
  max-width: 44rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.member-profile-page__toolbar {
  display: flex;
}

.member-profile-page__back {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
}

.member-profile-page__muted {
  margin: 0;
  opacity: 0.8;
}

.member-profile-page__error {
  margin: 0;
  color: #b91c1c;
}

.member-profile-page__header {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.member-profile-page__avatarWrap {
  flex-shrink: 0;
}

.member-profile-page__avatar {
  width: 5rem;
  height: 5rem;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid var(--border);
}

.member-profile-page__avatar--placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.75rem;
  font-weight: 700;
  background: rgba(37, 99, 235, 0.12);
  color: #1d4ed8;
}

.member-profile-page__title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.2;
}

.member-profile-page__username {
  margin: 0.25rem 0 0;
  opacity: 0.85;
}

.member-profile-page__card {
  margin: 0;
}

.member-profile-page__cardTitle {
  margin: 0 0 0.75rem;
  font-size: 1.05rem;
  font-weight: 600;
}

.member-profile-page__block {
  margin-bottom: 1rem;
}

.member-profile-page__block:last-child {
  margin-bottom: 0;
}

.member-profile-page__label {
  margin: 0 0 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
  opacity: 0.85;
}

.member-profile-page__list {
  margin: 0;
  padding-left: 1.1rem;
}

.member-profile-page__aliases {
  margin: 0;
}

.member-profile-page__places {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.member-profile-page__placeLink {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  padding: 0.65rem 0.5rem;
  border-radius: 0.5rem;
  text-decoration: none;
  color: inherit;
  border: 1px solid transparent;
}

.member-profile-page__placeLink:hover {
  border-color: var(--border);
  background: var(--btn-bg, rgba(0, 0, 0, 0.04));
}

.member-profile-page__placeLogo {
  width: 3rem;
  height: 3rem;
  object-fit: cover;
  border-radius: 0.35rem;
  border: 1px solid var(--border);
}

.member-profile-page__placeText {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}

.member-profile-page__placeName {
  font-weight: 600;
}

.member-profile-page__placeDesc {
  font-size: 0.85rem;
  opacity: 0.85;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
