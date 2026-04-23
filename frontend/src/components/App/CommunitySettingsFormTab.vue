<script setup>
import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Input from '../../atoms/Input.vue'
import { fetchCommunityBranding } from '../../composables/useCommunity'
import { sessionUser } from '../../composables/useSession'
import { t } from '../../i18n/i18n'
import { apiForm, apiJson } from '../../services/api'

const form = reactive({
  name: '',
  description: '',
  rules: '',
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

const canEdit = computed(() => Boolean(sessionUser.value?.is_root))

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
  const { ok, status, data } = await apiJson('GET', '/api/community')
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
  serverLogoUrl.value = typeof c.logo_url === 'string' && c.logo_url.length ? c.logo_url : ''
  logoFile.value = null
  removeLogoPending.value = false
  logoInputKey.value += 1
}

load()

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
      <p v-if="!canEdit" class="community-settings-form-tab__hint">{{ t('communitySettings.readOnlyHint') }}</p>

      <form class="community-settings-form-tab__form" @submit.prevent="onSubmit">
        <div class="community-settings-form-tab__fields">
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

          <div class="community-settings-form-tab__logo">
            <span class="community-settings-form-tab__logo-label">{{ t('communitySettings.fieldLogo') }}</span>
            <div v-if="logoPreviewSrc" class="community-settings-form-tab__logo-preview-wrap">
              <img
                :src="logoPreviewSrc"
                alt=""
                class="community-settings-form-tab__logo-preview"
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

.community-settings-form-tab__error {
  margin: 0;
  color: #b91c1c;
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
