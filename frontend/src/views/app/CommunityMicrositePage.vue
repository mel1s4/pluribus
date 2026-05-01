<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'
import { fetchCommunityBranding } from '../../composables/useCommunity'
import { hasCapability } from '../../composables/useCapabilities'
import { sessionStatus, sessionUser } from '../../composables/useSession'
import { fetchCommunityMicrosite } from '../../services/communityApi'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const loadError = ref('')
const payload = ref(null)

const slug = computed(() => {
  const raw = route.params.slug
  return typeof raw === 'string' ? raw.trim() : ''
})

const community = computed(() => {
  const c = payload.value?.community
  return c && typeof c === 'object' ? c : null
})

const isMember = computed(() => Boolean(payload.value?.is_member))
const stewards = computed(() =>
  Array.isArray(payload.value?.stewards) ? payload.value.stewards : [],
)
const publicPlaces = computed(() =>
  Array.isArray(payload.value?.public_places) ? payload.value.public_places : [],
)
const memberPlaces = computed(() =>
  Array.isArray(payload.value?.member_places) ? payload.value.member_places : [],
)
const recentPosts = computed(() =>
  Array.isArray(payload.value?.recent_posts) ? payload.value.recent_posts : [],
)

const creditsTotal = computed(() => {
  const raw = payload.value?.credits_granted_total
  return typeof raw === 'string' || typeof raw === 'number' ? String(raw) : '0'
})

const canManageMemberships = computed(() => {
  if (!hasCapability('community.memberships.manage')) {
    return false
  }
  const s = slug.value
  if (!s) {
    return false
  }
  const u = sessionUser.value
  if (u?.is_root) {
    return true
  }
  const list = Array.isArray(u?.communities) ? u.communities : []
  const row = list.find((c) => c && String(c.slug || '').trim() === s)
  return Boolean(row && row.role === 'admin')
})

async function load() {
  if (!slug.value) return
  loading.value = true
  loadError.value = ''
  const { ok, status, data } = await fetchCommunityMicrosite(slug.value)
  loading.value = false
  if (!ok) {
    loadError.value =
      (data && typeof data === 'object' && typeof data.message === 'string' && data.message) ||
      t('communityMicrosite.loadError').replace('{status}', String(status))
    payload.value = null
    return
  }
  payload.value = data && typeof data === 'object' ? data : null
  await fetchCommunityBranding(slug.value)
}

onMounted(load)
watch(slug, () => {
  void load()
})

function goLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } })
}
</script>

<template>
  <section class="community-microsite">
    <p v-if="loadError" class="community-microsite__error" role="alert">{{ loadError }}</p>
    <p v-else-if="loading" class="community-microsite__muted">{{ t('communityMicrosite.loading') }}</p>
    <template v-else-if="community">
      <header class="community-microsite__hero">
        <img
          v-if="community.logo_url"
          class="community-microsite__logo"
          :src="community.logo_url"
          :alt="community.name || ''"
        />
        <Title tag="h1" class="community-microsite__title">{{ community.name }}</Title>
        <p v-if="community.description" class="community-microsite__desc">{{ community.description }}</p>
        <p v-if="canManageMemberships" class="community-microsite__adminNav">
          <RouterLink class="community-microsite__link" :to="{ name: 'communityMemberships', params: { slug } }">
            {{ t('communityMicrosite.manageMemberships') }}
          </RouterLink>
        </p>
      </header>

      <Card class="community-microsite__card">
        <h2 class="community-microsite__h2">{{ t('communityMicrosite.creditsTitle') }}</h2>
        <p class="community-microsite__stat">{{ creditsTotal }}</p>
        <p class="community-microsite__hint">{{ t('communityMicrosite.creditsHint') }}</p>
        <p class="community-microsite__creditsLink">
          <RouterLink class="community-microsite__link" :to="{ name: 'communityCredits', params: { slug } }">
            {{ t('communityMicrosite.creditsDetailLink') }}
          </RouterLink>
        </p>
      </Card>

      <Card class="community-microsite__card">
        <h2 class="community-microsite__h2">{{ t('communityMicrosite.stewardsTitle') }}</h2>
        <ul v-if="stewards.length" class="community-microsite__list">
          <li v-for="s in stewards" :key="s.id" class="community-microsite__list-item">
            <span class="community-microsite__steward-name">{{ s.name }}</span>
            <span class="community-microsite__muted"> · {{ s.membership_role }}</span>
          </li>
        </ul>
        <p v-else class="community-microsite__muted">{{ t('communityMicrosite.stewardsEmpty') }}</p>
      </Card>

      <Card class="community-microsite__card">
        <h2 class="community-microsite__h2">{{ t('communityMicrosite.publicPlacesTitle') }}</h2>
        <ul v-if="publicPlaces.length" class="community-microsite__list">
          <li v-for="p in publicPlaces" :key="p.id" class="community-microsite__list-item">
            <RouterLink class="community-microsite__link" :to="{ name: 'placePublic', params: { slug: p.slug } }">
              {{ p.name }}
            </RouterLink>
          </li>
        </ul>
        <p v-else class="community-microsite__muted">{{ t('communityMicrosite.publicPlacesEmpty') }}</p>
      </Card>

      <template v-if="isMember">
        <Card class="community-microsite__card">
          <h2 class="community-microsite__h2">{{ t('communityMicrosite.memberPlacesTitle') }}</h2>
          <ul v-if="memberPlaces.length" class="community-microsite__list">
            <li v-for="p in memberPlaces" :key="p.id" class="community-microsite__list-item">
              <RouterLink class="community-microsite__link" :to="{ name: 'placePublic', params: { slug: p.slug } }">
                {{ p.name }}
              </RouterLink>
            </li>
          </ul>
          <p v-else class="community-microsite__muted">{{ t('communityMicrosite.memberPlacesEmpty') }}</p>
        </Card>

        <Card class="community-microsite__card">
          <h2 class="community-microsite__h2">{{ t('communityMicrosite.recentPostsTitle') }}</h2>
          <ul v-if="recentPosts.length" class="community-microsite__post-list">
            <li v-for="post in recentPosts" :key="post.id" class="community-microsite__post-item">
              <span class="community-microsite__post-type">{{ post.type }}</span>
              <strong class="community-microsite__post-title">{{ post.title }}</strong>
            </li>
          </ul>
          <p v-else class="community-microsite__muted">{{ t('communityMicrosite.recentPostsEmpty') }}</p>
        </Card>
      </template>

      <div v-else class="community-microsite__cta">
        <p class="community-microsite__cta-text">{{ t('communityMicrosite.joinHint') }}</p>
        <Button v-if="sessionStatus !== 'authenticated'" type="button" @click="goLogin">
          {{ t('communityMicrosite.logIn') }}
        </Button>
        <RouterLink v-else class="community-microsite__link community-microsite__link--inline" :to="{ name: 'myCommunities' }">
          {{ t('communityMicrosite.myCommunitiesLink') }}
        </RouterLink>
      </div>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.community-microsite {
  max-width: 720px;
  margin: 0 auto;
  padding: 1.5rem 1rem 2.5rem;
}
.community-microsite__hero {
  margin-bottom: 1.5rem;
}
.community-microsite__logo {
  max-width: 120px;
  max-height: 120px;
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
  object-fit: contain;
}
.community-microsite__title {
  margin: 0 0 0.5rem;
}
.community-microsite__desc {
  color: var(--muted, #6b7280);
  margin: 0;
  line-height: 1.5;
}
.community-microsite__adminNav {
  margin: 0.75rem 0 0;
}
.community-microsite__card {
  margin-bottom: 1rem;
  padding: 1rem 1.1rem;
}
.community-microsite__h2 {
  margin: 0 0 0.65rem;
  font-size: 1.05rem;
}
.community-microsite__stat {
  font-size: 1.75rem;
  font-weight: 700;
  margin: 0 0 0.35rem;
}
.community-microsite__hint,
.community-microsite__muted {
  color: var(--muted, #6b7280);
  font-size: 0.9rem;
  margin: 0;
}
.community-microsite__creditsLink {
  margin: 0.65rem 0 0;
}
.community-microsite__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.35rem;
}
.community-microsite__list-item {
  margin: 0;
}
.community-microsite__steward-name {
  font-weight: 600;
}
.community-microsite__link {
  color: var(--link, #2563eb);
  text-decoration: none;
  &:hover {
    text-decoration: underline;
  }
}
.community-microsite__link--inline {
  display: inline-block;
  margin-top: 0.5rem;
}
.community-microsite__post-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.5rem;
}
.community-microsite__post-item {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid var(--border, #e5e7eb);
  &:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }
}
.community-microsite__post-type {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted, #6b7280);
}
.community-microsite__post-title {
  font-weight: 600;
}
.community-microsite__cta {
  margin-top: 1.25rem;
  padding: 1rem;
  border: 1px dashed var(--border, #e5e7eb);
  border-radius: 0.5rem;
}
.community-microsite__cta-text {
  margin: 0 0 0.75rem;
  color: var(--muted, #6b7280);
}
.community-microsite__error {
  color: #b91c1c;
}
</style>
