<script setup>
import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Input from '../../atoms/Input.vue'
import CommunityMapCenterPicker from './CommunityMapCenterPicker.vue'
import { fetchCommunityBranding } from '../../composables/useCommunity'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { DEFAULT_LANGUAGE, SUPPORTED_LANGUAGES } from '../../i18n/locales'
import { invalidateCache } from '../../services/cachedApi.js'
import { fetchCommunity, patchCommunityCurrency } from '../../services/communityApi.js'
import { apiForm, apiJson } from '../../services/api'

const form = reactive({
  name: '',
  description: '',
  rules: '',
  default_language: DEFAULT_LANGUAGE,
  currency_code: '',
  latitude: null,
  longitude: null,
})

const mapCenter = computed({
  get: () => ({ latitude: form.latitude, longitude: form.longitude }),
  set: (v) => {
    form.latitude = v?.latitude != null && v.latitude !== '' ? Number(v.latitude) : null
    form.longitude = v?.longitude != null && v.longitude !== '' ? Number(v.longitude) : null
  },
})

const serverLogoUrl = ref('')
const logoFile = ref(null)
const removeLogoPending = ref(false)
const logoInputKey = ref(0)
const logoBlobUrl = ref('')

function revokeLogoBlob() {
  if (logoBlobUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(logoBlobUrl.value)
  }
  logoBlobUrl.value = ''
}

watch(
  logoFile,
  (file) => {
    revokeLogoBlob()
    if (file instanceof File) {
      logoBlobUrl.value = URL.createObjectURL(file)
    }
  },
)

onUnmounted(revokeLogoBlob)

const loadError = ref('')
const loading = ref(true)
const saveError = ref('')
const saveLoading = ref(false)
const currencySaveError = ref('')
const currencySaving = ref(false)

const canEdit = computed(() => Boolean(sessionUser.value?.is_root))
const canEditCurrency = computed(() => {
  const u = sessionUser.value
  if (!u) return false
  if (u.is_root) return true
  return u.user_type === 'admin'
})
const languageOptions = computed(() =>
  SUPPORTED_LANGUAGES.map((entry) => ({
    value: entry.code,
    label: t(entry.labelKey),
  })),
)

const logoPreviewSrc = computed(() => {
  if (removeLogoPending.value) {
    return ''
  }
  if (logoBlobUrl.value) {
    return logoBlobUrl.value
  }
  return serverLogoUrl.value || ''
})

function apiErrorMessage(data, status, fallback) {
  if (data && typeof data === 'object') {
    if (typeof data.message === 'string' && data.message.length) {
      return data.message
    }
    if (data.errors && typeof data.errors === 'object') {
      return Object.values(data.errors)
        .flat()
        .map(String)
        .join(' ')
    }
  }
  return fallback.replace('{status}', String(status))
}

async function load() {
  loadError.value = ''
  loading.value = true
  const { ok, status, data } = await fetchCommunity()
  loading.value = false
  if (!ok) {
    loadError.value = apiErrorMessage(data, status, t('communitySettings.loadError'))
    return
  }
  const c = data?.community
  if (!c || typeof c !== 'object') {
    loadError.value = t('communitySettings.loadError').replace('{status}', String(status))
    return
  }
  form.name = c.name ?? ''
  form.description = c.description ?? ''
  form.rules = c.rules ?? ''
  form.default_language = c.default_language ?? DEFAULT_LANGUAGE
  form.currency_code = typeof c.currency_code === 'string' ? c.currency_code : ''
  form.latitude = c.latitude != null && c.latitude !== '' ? Number(c.latitude) : null
  form.longitude = c.longitude != null && c.longitude !== '' ? Number(c.longitude) : null
  if (
    (form.latitude != null && !Number.isFinite(form.latitude))
    || (form.longitude != null && !Number.isFinite(form.longitude))
  ) {
    form.latitude = null
    form.longitude = null
  }
  serverLogoUrl.value = typeof c.logo_url === 'string' && c.logo_url.length ? c.logo_url : ''
  logoFile.value = null
  removeLogoPending.value = false
  logoInputKey.value += 1
}

load()

function normalizeCurrencyInput(raw) {
  const s = String(raw ?? '').trim()
  if (!s.length) return null
  return s.slice(0, 4)
}

async function saveCurrencyOnly() {
  if (!canEditCurrency.value) return
  currencySaveError.value = ''
  currencySaving.value = true
  const { ok, status, data } = await patchCommunityCurrency({
    currency_code: normalizeCurrencyInput(form.currency_code),
  })
  currencySaving.value = false
  if (!ok) {
    currencySaveError.value = apiErrorMessage(data, status, t('communitySettings.currencySaveError'))
    return
  }
  await load()
  await fetchCommunityBranding()
}

function onLogoFile(ev) {
  const file = ev.target.files?.[0]
  logoFile.value = file ?? null
  removeLogoPending.value = false
}

function onClearLogo() {
  logoFile.value = null
  removeLogoPending.value = true
  logoInputKey.value += 1
}

async function onSubmit() {
  if (!canEdit.value) return
  saveError.value = ''
  saveLoading.value = true

  const useMultipart = Boolean(logoFile.value) || removeLogoPending.value
  let ok
  let status
  let data
  if (useMultipart) {
    const fd = new FormData()
    fd.append('name', form.name.trim())
    fd.append('description', form.description.trim() === '' ? '' : form.description.trim())
    fd.append('rules', form.rules.trim() === '' ? '' : form.rules.trim())
    fd.append('default_language', form.default_language)
    fd.append('currency_code', form.currency_code.trim() === '' ? '' : normalizeCurrencyInput(form.currency_code) ?? '')
    if (form.latitude != null && form.longitude != null) {
      fd.append('latitude', String(form.latitude))
      fd.append('longitude', String(form.longitude))
    } else {
      fd.append('latitude', '')
      fd.append('longitude', '')
    }
    if (logoFile.value) {
      fd.append('logo_upload', logoFile.value)
    }
    if (removeLogoPending.value && !logoFile.value) {
      fd.append('remove_logo', '1')
    }
    const res = await apiForm('PATCH', '/api/community', fd)
    ok = res.ok
    status = res.status
    data = res.data
  } else {
    const res = await apiJson('PATCH', '/api/community', {
      name: form.name.trim(),
      description: form.description.trim() === '' ? null : form.description.trim(),
      rules: form.rules.trim() === '' ? null : form.rules.trim(),
      default_language: form.default_language,
      currency_code: normalizeCurrencyInput(form.currency_code),
      latitude: form.latitude,
      longitude: form.longitude,
    })
    ok = res.ok
    status = res.status
    data = res.data
  }

  saveLoading.value = false
  if (!ok) {
    saveError.value = apiErrorMessage(data, status, t('communitySettings.saveError'))
    return
  }
  invalidateCache(/^\/api\/community/)
  invalidateCache('/api/community/branding')
  await load()
  await fetchCommunityBranding()
}
</script>

<template>
  <div class="community-settings-form-tab">
    <p v-if="loadError" class="community-settings-form-tab__error" role="alert">
      {{ loadError }}
    </p>
    <p v-else-if="loading" class="community-settings-form-tab__muted">{{ t('communitySettings.loading') }}</p>

    <Card v-else class="community-settings-form-tab__panel">
      <p v-if="!canEdit && !canEditCurrency" class="community-settings-form-tab__hint">
        {{ t('communitySettings.readOnlyHint') }}
      </p>
      <p v-else-if="!canEdit && canEditCurrency" class="community-settings-form-tab__hint">
        {{ t('communitySettings.adminCurrencyHint') }}
      </p>

      <form class="community-settings-form-tab__form" @submit.prevent="onSubmit">
        <div class="community-settings-form-tab__fields">
          <label class="community-settings-form-tab__areaLabel" for="community-currency-code">{{
            t('communitySettings.fieldCurrency')
          }}</label>
          <input
            id="community-currency-code"
            v-model="form.currency_code"
            class="community-settings-form-tab__currencyInput"
            type="text"
            name="community-currency-code"
            maxlength="4"
            :disabled="!canEditCurrency || (canEdit && saveLoading) || currencySaving"
            :placeholder="t('communitySettings.currencyPlaceholder')"
          >
          <p class="community-settings-form-tab__muted community-settings-form-tab__muted--small">
            {{ t('communitySettings.currencyHelp') }}
          </p>
          <Button
            v-if="canEditCurrency && !canEdit"
            type="button"
            variant="primary"
            :loading="currencySaving"
            @click="saveCurrencyOnly"
          >
            {{ t('communitySettings.saveCurrency') }}
          </Button>
          <p v-if="currencySaveError" class="community-settings-form-tab__error" role="alert">
            {{ currencySaveError }}
          </p>

          <Input
            v-model="form.name"
            name="community-name"
            type="text"
            :label="t('communitySettings.fieldName')"
            autocomplete="organization"
            :disabled="!canEdit"
            required
          />
          <label class="community-settings-form-tab__areaLabel" for="community-description">{{
            t('communitySettings.fieldDescription')
          }}</label>
          <textarea
            id="community-description"
            v-model="form.description"
            class="community-settings-form-tab__textarea"
            name="community-description"
            rows="3"
            :disabled="!canEdit"
          ></textarea>
          <label class="community-settings-form-tab__areaLabel" for="community-rules">{{
            t('communitySettings.fieldRules')
          }}</label>
          <textarea
            id="community-rules"
            v-model="form.rules"
            class="community-settings-form-tab__textarea"
            name="community-rules"
            rows="6"
            :disabled="!canEdit"
          ></textarea>
          <label class="community-settings-form-tab__areaLabel" for="community-default-language">{{
            t('communitySettings.fieldDefaultLanguage')
          }}</label>
          <select
            id="community-default-language"
            v-model="form.default_language"
            class="community-settings-form-tab__select"
            name="community-default-language"
            :disabled="!canEdit"
          >
            <option v-for="option in languageOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>

          <div class="community-settings-form-tab__mapCenter">
            <span class="community-settings-form-tab__mapCenter-label">{{ t('communitySettings.mapCenterField') }}</span>
            <CommunityMapCenterPicker v-model="mapCenter" :read-only="!canEdit" />
          </div>

          <div class="community-settings-form-tab__logo">
            <span class="community-settings-form-tab__logo-label">{{ t('communitySettings.fieldLogo') }}</span>
            <div v-if="logoPreviewSrc" class="community-settings-form-tab__logo-preview-wrap">
              <img
                :src="logoPreviewSrc"
                alt=""
                class="community-settings-form-tab__logo-preview"
                loading="lazy"
              />
            </div>
            <input
              :key="logoInputKey"
              class="community-settings-form-tab__file"
              type="file"
              name="community-logo-file"
              accept="image/*"
              :disabled="!canEdit || saveLoading"
              @change="onLogoFile"
            />
            <p class="community-settings-form-tab__muted community-settings-form-tab__muted--small">
              {{ t('communitySettings.logoHint') }}
            </p>
            <button
              v-if="canEdit && (logoPreviewSrc || serverLogoUrl || logoFile)"
              type="button"
              class="community-settings-form-tab__clear-logo"
              :disabled="saveLoading"
              @click="onClearLogo"
            >
              {{ t('communitySettings.removeLogo') }}
            </button>
          </div>
        </div>
        <p v-if="saveError" class="community-settings-form-tab__error" role="alert">
          {{ saveError }}
        </p>
        <Button v-if="canEdit" type="submit" variant="primary" :loading="saveLoading">
          {{ t('communitySettings.save') }}
        </Button>
      </form>
    </Card>
  </div>
</template>

<style lang="scss" scoped>
.community-settings-form-tab__muted {
  margin: 0;
  opacity: 0.8;
}

.community-settings-form-tab__muted--small {
  font-size: 0.85rem;
  margin-top: -0.35rem;
}

.community-settings-form-tab__hint {
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  color: var(--muted, #6b7280);
}

.community-settings-form-tab__panel {
  margin-top: 0.25rem;
}

.community-settings-form-tab__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-items: flex-start;
}

.community-settings-form-tab__fields {
  display: grid;
  gap: 0.75rem;
  width: 100%;
  max-width: 32rem;
}

.community-settings-form-tab__areaLabel {
  font-weight: 600;
  font-size: 0.9rem;
}

.community-settings-form-tab__textarea {
  width: 100%;
  max-width: 32rem;
  padding: 0.5rem 0.6rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  font: inherit;
  resize: vertical;
  min-height: 4rem;
}

.community-settings-form-tab__select {
  width: 100%;
  max-width: 32rem;
  padding: 0.5rem 0.6rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  font: inherit;
  background: var(--bg);
}

.community-settings-form-tab__currencyInput {
  width: 100%;
  max-width: 8rem;
  padding: 0.5rem 0.6rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
  font: inherit;
  background: var(--bg);
}

.community-settings-form-tab__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}

.community-settings-form-tab__mapCenter {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.community-settings-form-tab__mapCenter-label {
  font-weight: 600;
  font-size: 0.9rem;
}

.community-settings-form-tab__logo {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.community-settings-form-tab__logo-label {
  font-weight: 600;
  font-size: 0.9rem;
}

.community-settings-form-tab__logo-preview-wrap {
  max-width: 8rem;
}

.community-settings-form-tab__logo-preview {
  width: 100%;
  height: auto;
  border-radius: 8px;
  border: 1px solid var(--border);
  display: block;
}

.community-settings-form-tab__file {
  max-width: 32rem;
  font-size: 0.85rem;
}

.community-settings-form-tab__clear-logo {
  cursor: pointer;
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font: inherit;
  align-self: flex-start;
}
</style>
