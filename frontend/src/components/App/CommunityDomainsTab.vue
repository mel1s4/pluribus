<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Input from '../../atoms/Input.vue'
import { useActiveCommunity } from '../../composables/useActiveCommunity'
import { hasCapability } from '../../composables/useCapabilities'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { fetchCommunity, patchCommunityDomains } from '../../services/communityApi.js'

const { activeCommunitySlug } = useActiveCommunity()

/** @type {import('vue').Ref<Array<{ host: string, is_primary: boolean }>>} */
const domainRows = ref([])
const loadError = ref('')
const loading = ref(true)
const saveError = ref('')
const saving = ref(false)

function communityRequestOptions() {
  const s = activeCommunitySlug.value
  return s && s.trim() !== '' ? { headers: { 'X-Community-Slug': s.trim() } } : {}
}

const canManageDomains = computed(() => {
  const u = sessionUser.value
  if (!u) return false
  if (u.is_root) return true
  if (hasCapability('communities.manage')) return true
  const slug = activeCommunitySlug.value
  if (!slug) return false
  const list = Array.isArray(u.communities) ? u.communities : []
  const row = list.find((c) => c && String(c.slug || '').trim() === slug)
  return Boolean(row && row.role === 'admin')
})

function domainsPayload() {
  return domainRows.value
    .map((row) => ({
      host: String(row.host || '').trim(),
      is_primary: Boolean(row.is_primary),
    }))
    .filter((row) => row.host !== '')
}

function addDomainRow() {
  domainRows.value.push({ host: '', is_primary: domainRows.value.length === 0 })
}

function removeDomainRow(index) {
  domainRows.value.splice(index, 1)
  if (domainRows.value.length > 0 && !domainRows.value.some((r) => r.is_primary)) {
    domainRows.value[0].is_primary = true
  }
}

function setPrimaryDomain(index) {
  domainRows.value = domainRows.value.map((row, i) => ({
    ...row,
    is_primary: i === index,
  }))
}

async function load() {
  loadError.value = ''
  loading.value = true
  const { ok, status, data } = await fetchCommunity(communityRequestOptions())
  loading.value = false
  if (!ok) {
    loadError.value = t('communitySettings.domainsLoadError').replace('{status}', String(status))
    return
  }
  const c = data?.community
  const domains = c && Array.isArray(c.domains) ? c.domains : []
  domainRows.value = domains.map((d) => ({
    host: typeof d?.host === 'string' ? d.host : '',
    is_primary: Boolean(d?.is_primary),
  }))
}

async function onSave() {
  if (!canManageDomains.value) return
  saveError.value = ''
  saving.value = true
  const { ok, status, data } = await patchCommunityDomains(
    { domains: domainsPayload() },
    communityRequestOptions(),
  )
  saving.value = false
  if (!ok) {
    const msg =
      data && typeof data === 'object' && typeof data.message === 'string'
        ? data.message
        : t('communitySettings.domainsSaveError').replace('{status}', String(status))
    saveError.value = msg
    return
  }
  await load()
}

onMounted(() => {
  void load()
})

watch(activeCommunitySlug, () => {
  void load()
})
</script>

<template>
  <div class="community-domains-tab">
    <p v-if="loadError" class="community-domains-tab__error" role="alert">{{ loadError }}</p>
    <p v-else-if="loading" class="community-domains-tab__muted">{{ t('communitySettings.loading') }}</p>
    <Card v-else class="community-domains-tab__panel">
      <p class="community-domains-tab__intro">{{ t('communitySettings.domainsIntro') }}</p>
      <p class="community-domains-tab__hint">{{ t('communities.domainsHint') }}</p>
      <p v-if="!canManageDomains" class="community-domains-tab__muted">
        {{ t('communitySettings.domainsNoPermission') }}
      </p>
      <template v-else>
        <p v-if="domainRows.length === 0" class="community-domains-tab__muted">
          {{ t('communities.noDomains') }}
        </p>
        <div
          v-for="(row, index) in domainRows"
          :key="index"
          class="community-domains-tab__row"
        >
          <Input
            v-model="row.host"
            :label="t('communities.fieldDomains')"
            :placeholder="t('communities.domainHostPlaceholder')"
            autocomplete="off"
          />
          <label class="community-domains-tab__primary">
            <input
              type="radio"
              name="community-primary-domain"
              :checked="row.is_primary"
              @change="setPrimaryDomain(index)"
            />
            {{ t('communities.primaryDomain') }}
          </label>
          <Button type="button" variant="secondary" size="sm" @click="removeDomainRow(index)">
            ×
          </Button>
        </div>
        <Button type="button" variant="secondary" size="sm" @click="addDomainRow">
          {{ t('communities.addDomain') }}
        </Button>
        <p v-if="saveError" class="community-domains-tab__error" role="alert">{{ saveError }}</p>
        <Button type="button" variant="primary" :loading="saving" @click="onSave">
          {{ saving ? t('communitySettings.saving') : t('communitySettings.domainsSave') }}
        </Button>
      </template>
    </Card>
  </div>
</template>

<style lang="scss" scoped>
.community-domains-tab__muted {
  margin: 0;
  color: var(--muted, #6b7280);
  font-size: 0.9rem;
}

.community-domains-tab__intro {
  margin: 0 0 0.5rem;
  font-weight: 600;
}

.community-domains-tab__hint {
  margin: 0 0 1rem;
  color: var(--muted, #6b7280);
  font-size: 0.875rem;
  line-height: 1.45;
}

.community-domains-tab__panel {
  display: grid;
  gap: 0.75rem;
}

.community-domains-tab__row {
  display: grid;
  gap: 0.5rem;
  padding: 0.65rem;
  border: 1px dashed var(--border, #e5e7eb);
  border-radius: 0.375rem;
}

.community-domains-tab__primary {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8125rem;
}

.community-domains-tab__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}
</style>
