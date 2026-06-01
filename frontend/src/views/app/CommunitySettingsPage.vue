<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import CommunityLeadershipTab from '../../components/App/CommunityLeadershipTab.vue'
import CommunityLegalDocumentTab from '../../components/App/CommunityLegalDocumentTab.vue'
import CommunityDomainsTab from '../../components/App/CommunityDomainsTab.vue'
import CommunitySettingsFormTab from '../../components/App/CommunitySettingsFormTab.vue'
import { t } from '../../i18n/i18n'

const route = useRoute()
const router = useRouter()

const scopedCommunitySlug = computed(() => {
  if (String(route.name || '') === 'communitySettingsBySlug') {
    const sl = route.params.slug
    return typeof sl === 'string' && sl.trim() !== '' ? sl.trim() : ''
  }
  const raw = route.params.communitySlug
  return typeof raw === 'string' && raw.trim() !== '' ? raw.trim() : ''
})

const settingsRouteName = computed(() => {
  const n = String(route.name || '')
  if (n === 'communitySettingsBySlug' || n === 'communitySettingsScoped' || n === 'communitySettings') {
    return n
  }
  return scopedCommunitySlug.value !== '' ? 'communitySettingsScoped' : 'communitySettings'
})

const activeTab = computed(() => {
  const raw = route.params.tab
  if (raw === 'settings') return 'settings'
  if (raw === 'domains') return 'domains'
  if (raw === 'terms') return 'terms'
  if (raw === 'privacy') return 'privacy'
  if (raw === 'leadership' || raw === undefined) return 'leadership'
  return 'leadership'
})

function setTab(id) {
  const name = settingsRouteName.value
  const slug = scopedCommunitySlug.value
  if (id === 'leadership') {
    if (name === 'communitySettingsScoped') {
      router.push({ name, params: { communitySlug: slug } })
    } else if (name === 'communitySettingsBySlug') {
      router.push({ name, params: { slug } })
    } else {
      router.push({ name: 'communitySettings' })
    }
  } else if (name === 'communitySettingsScoped') {
    router.push({ name, params: { communitySlug: slug, tab: id } })
  } else if (name === 'communitySettingsBySlug') {
    router.push({ name, params: { slug, tab: id } })
  } else {
    router.push({ name: 'communitySettings', params: { tab: id } })
  }
}

watch(
  () => route.params.tab,
  (t) => {
    if (t == null || t === 'leadership' || t === 'settings' || t === 'domains' || t === 'terms' || t === 'privacy') {
      return
    }
    const n = String(route.name || '')
    const slugByPath = typeof route.params.slug === 'string' ? route.params.slug.trim() : ''
    const slugScoped = typeof route.params.communitySlug === 'string' ? route.params.communitySlug.trim() : ''
    if (n === 'communitySettingsBySlug' && slugByPath !== '') {
      router.replace({ name: 'communitySettingsBySlug', params: { slug: slugByPath } })
    } else if (slugScoped !== '') {
      router.replace({ name: 'communitySettingsScoped', params: { communitySlug: slugScoped } })
    } else {
      router.replace({ name: 'communitySettings' })
    }
  },
  { immediate: true },
)
</script>

<template>
  <section class="community-settings-page">
    <PageToolbarTitle route-key="community-settings">
      <Title tag="h1">{{ t('communitySettings.title') }}</Title>
    </PageToolbarTitle>
    <p class="community-settings-page__intro">{{ t('communitySettings.pageIntro') }}</p>

    <div class="community-settings-page__tabs" role="tablist" :aria-label="t('communitySettings.tabsAria')">
      <button
        type="button"
        role="tab"
        class="community-settings-page__tab"
        :class="{ 'community-settings-page__tab--active': activeTab === 'leadership' }"
        :aria-selected="activeTab === 'leadership'"
        @click="setTab('leadership')"
      >
        {{ t('communitySettings.tabLeadership') }}
      </button>
      <button
        type="button"
        role="tab"
        class="community-settings-page__tab"
        :class="{ 'community-settings-page__tab--active': activeTab === 'settings' }"
        :aria-selected="activeTab === 'settings'"
        @click="setTab('settings')"
      >
        {{ t('communitySettings.tabSettings') }}
      </button>
      <button
        type="button"
        role="tab"
        class="community-settings-page__tab"
        :class="{ 'community-settings-page__tab--active': activeTab === 'domains' }"
        :aria-selected="activeTab === 'domains'"
        @click="setTab('domains')"
      >
        {{ t('communitySettings.tabDomains') }}
      </button>
      <button
        type="button"
        role="tab"
        class="community-settings-page__tab"
        :class="{ 'community-settings-page__tab--active': activeTab === 'terms' }"
        :aria-selected="activeTab === 'terms'"
        @click="setTab('terms')"
      >
        {{ t('communitySettings.tabTerms') }}
      </button>
      <button
        type="button"
        role="tab"
        class="community-settings-page__tab"
        :class="{ 'community-settings-page__tab--active': activeTab === 'privacy' }"
        :aria-selected="activeTab === 'privacy'"
        @click="setTab('privacy')"
      >
        {{ t('communitySettings.tabPrivacy') }}
      </button>
    </div>

    <div
      v-show="activeTab === 'leadership'"
      role="tabpanel"
      class="community-settings-page__panel-wrap"
    >
      <CommunityLeadershipTab />
    </div>
    <div
      v-show="activeTab === 'settings'"
      role="tabpanel"
      class="community-settings-page__panel-wrap"
    >
      <CommunitySettingsFormTab />
    </div>
    <div
      v-show="activeTab === 'domains'"
      role="tabpanel"
      class="community-settings-page__panel-wrap"
    >
      <CommunityDomainsTab />
    </div>
    <div
      v-show="activeTab === 'terms'"
      role="tabpanel"
      class="community-settings-page__panel-wrap"
    >
      <CommunityLegalDocumentTab kind="terms" />
    </div>
    <div
      v-show="activeTab === 'privacy'"
      role="tabpanel"
      class="community-settings-page__panel-wrap"
    >
      <CommunityLegalDocumentTab kind="privacy" />
    </div>
  </section>
</template>

<style lang="scss" scoped>
.community-settings-page {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 52rem;
  margin: 0 auto;
}

.community-settings-page__intro {
  margin: 0;
  opacity: 0.85;
  max-width: 40rem;
}

.community-settings-page__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.35rem;
}

.community-settings-page__tab {
  cursor: pointer;
  padding: 0.45rem 0.85rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
  font-size: 0.9rem;
  color: inherit;
}

.community-settings-page__tab--active {
  font-weight: 600;
  border-color: color-mix(in srgb, var(--border) 70%, #1d4ed8);
  background: color-mix(in srgb, #1d4ed8 8%, var(--bg));
}

.community-settings-page__panel-wrap {
  margin-top: 0.35rem;
}
</style>
