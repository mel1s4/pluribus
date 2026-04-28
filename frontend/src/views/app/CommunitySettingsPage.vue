<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Title from '../../atoms/Title.vue'
import CommunityLeadershipTab from '../../components/App/CommunityLeadershipTab.vue'
import CommunitySettingsFormTab from '../../components/App/CommunitySettingsFormTab.vue'
import { t } from '../../i18n/i18n'

const route = useRoute()
const router = useRouter()

const activeTab = computed(() => {
  const raw = route.params.tab
  if (raw === 'settings') return 'settings'
  if (raw === 'leadership' || raw === undefined) return 'leadership'
  return 'leadership'
})

function setTab(id) {
  if (id === 'leadership') {
    router.push({ name: 'communitySettings' })
  } else {
    router.push({ name: 'communitySettings', params: { tab: id } })
  }
}

watch(
  () => route.params.tab,
  (t) => {
    if (t != null && t !== 'leadership' && t !== 'settings') {
      router.replace({ name: 'communitySettings' })
    }
  },
  { immediate: true },
)
</script>

<template>
  <section class="community-settings-page">
    <Title tag="h1">{{ t('communitySettings.title') }}</Title>
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
