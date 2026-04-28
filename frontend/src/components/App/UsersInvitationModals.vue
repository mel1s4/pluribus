<script setup>
import { nextTick, ref } from 'vue'
import QRCode from 'qrcode'
import Button from '../../atoms/Button.vue'
import Input from '../../atoms/Input.vue'
import UsersInvitationMaxUsesFields from './UsersInvitationMaxUsesFields.vue'
import { language, t } from '../../i18n/i18n'
import { apiJson, ensureCsrfCookie } from '../../services/api'

const defaultUsage = () => ({ mode: 'unlimited', custom: 5 })

function inviteUsageToMaxUses(usage) {
  if (!usage || usage.mode === 'unlimited') {
    return null
  }
  if (usage.mode === 'once') {
    return 1
  }
  const n = Math.floor(Number(usage.custom))
  if (!Number.isFinite(n)) {
    return 2
  }
  return Math.min(10000, Math.max(2, n))
}

function validateCustomUsage(usage) {
  if (!usage || usage.mode !== 'custom') {
    return ''
  }
  const n = Math.floor(Number(usage.custom))
  if (!Number.isFinite(n) || n < 2 || n > 10000) {
    return t('users.inviteMaxUsesInvalid')
  }
  return ''
}

const sendDialog = ref(null)
const linkDialog = ref(null)
const qrDialog = ref(null)

const sendEmail = ref('')
const sendError = ref('')
const sendLoading = ref(false)
const sendResultUrl = ref('')
const sendEmailSent = ref(false)
const sendResultMaxUses = ref(null)

const linkUsage = ref(defaultUsage())
const linkError = ref('')
const linkLoading = ref(false)
const linkResultUrl = ref('')
const linkResultMaxUses = ref(null)

const qrUsage = ref(defaultUsage())
const qrError = ref('')
const qrLoading = ref(false)
const qrResultUrl = ref('')
const qrResultMaxUses = ref(null)
const qrDataUrl = ref('')

const sendCopyHint = ref('')
const linkCopyHint = ref('')
const qrCopyHint = ref('')

function resetSendState() {
  sendEmail.value = ''
  sendError.value = ''
  sendLoading.value = false
  sendResultUrl.value = ''
  sendEmailSent.value = false
  sendResultMaxUses.value = null
  sendCopyHint.value = ''
}

function resetLinkState() {
  linkUsage.value = defaultUsage()
  linkError.value = ''
  linkLoading.value = false
  linkResultUrl.value = ''
  linkResultMaxUses.value = null
  linkCopyHint.value = ''
}

function resetQrState() {
  qrUsage.value = defaultUsage()
  qrError.value = ''
  qrLoading.value = false
  qrResultUrl.value = ''
  qrResultMaxUses.value = null
  qrDataUrl.value = ''
  qrCopyHint.value = ''
}

async function openSend() {
  resetSendState()
  await nextTick()
  sendDialog.value?.showModal()
}

async function openLink() {
  resetLinkState()
  await nextTick()
  linkDialog.value?.showModal()
}

async function openQr() {
  resetQrState()
  await nextTick()
  qrDialog.value?.showModal()
}

defineExpose({ openSend, openLink, openQr })

function closeSendDialog() {
  sendDialog.value?.close()
}

function closeLinkDialog() {
  linkDialog.value?.close()
}

function closeQrDialog() {
  qrDialog.value?.close()
}

function onSendDialogClick(ev) {
  if (ev.target === sendDialog.value) {
    closeSendDialog()
  }
}

function onLinkDialogClick(ev) {
  if (ev.target === linkDialog.value) {
    closeLinkDialog()
  }
}

function onQrDialogClick(ev) {
  if (ev.target === qrDialog.value) {
    closeQrDialog()
  }
}

async function submitSendInvitation() {
  sendError.value = ''
  sendResultUrl.value = ''
  sendEmailSent.value = false
  sendResultMaxUses.value = null
  sendCopyHint.value = ''
  const email = sendEmail.value.trim()
  if (!email) {
    sendError.value = t('users.inviteSendEmailRequired')
    return
  }
  sendLoading.value = true
  await ensureCsrfCookie()
  const { ok, status, data } = await apiJson('POST', '/api/invitations', {
    email,
    join_url_locale: language.value,
  })
  sendLoading.value = false
  if (!ok) {
    sendError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('users.inviteCreateError').replace('{status}', String(status))
    return
  }
  const inv = data?.invitation
  if (inv && typeof inv.join_url === 'string') {
    sendResultUrl.value = inv.join_url
    sendEmailSent.value = Boolean(inv.email_sent)
    sendResultMaxUses.value =
      inv.max_uses === null || inv.max_uses === undefined ? null : Number(inv.max_uses)
  }
}

async function submitCreateLink() {
  linkError.value = ''
  linkResultUrl.value = ''
  linkResultMaxUses.value = null
  linkCopyHint.value = ''
  const usageErr = validateCustomUsage(linkUsage.value)
  if (usageErr) {
    linkError.value = usageErr
    return
  }
  linkLoading.value = true
  await ensureCsrfCookie()
  const maxUses = inviteUsageToMaxUses(linkUsage.value)
  const { ok, status, data } = await apiJson('POST', '/api/invitations', {
    max_uses: maxUses,
    join_url_locale: language.value,
  })
  linkLoading.value = false
  if (!ok) {
    linkError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('users.inviteCreateError').replace('{status}', String(status))
    return
  }
  const inv = data?.invitation
  if (inv && typeof inv.join_url === 'string') {
    linkResultUrl.value = inv.join_url
    linkResultMaxUses.value =
      inv.max_uses === null || inv.max_uses === undefined ? null : Number(inv.max_uses)
  }
}

async function submitCreateQr() {
  qrError.value = ''
  qrResultUrl.value = ''
  qrResultMaxUses.value = null
  qrDataUrl.value = ''
  qrCopyHint.value = ''
  const usageErr = validateCustomUsage(qrUsage.value)
  if (usageErr) {
    qrError.value = usageErr
    return
  }
  qrLoading.value = true
  await ensureCsrfCookie()
  const maxUses = inviteUsageToMaxUses(qrUsage.value)
  const { ok, status, data } = await apiJson('POST', '/api/invitations', {
    max_uses: maxUses,
    join_url_locale: language.value,
  })
  qrLoading.value = false
  if (!ok) {
    qrError.value =
      (data && typeof data === 'object' && data.message && String(data.message)) ||
      t('users.inviteCreateError').replace('{status}', String(status))
    return
  }
  const inv = data?.invitation
  if (inv && typeof inv.join_url === 'string') {
    qrResultUrl.value = inv.join_url
    qrResultMaxUses.value =
      inv.max_uses === null || inv.max_uses === undefined ? null : Number(inv.max_uses)
    try {
      qrDataUrl.value = await QRCode.toDataURL(inv.join_url, {
        width: 280,
        margin: 2,
        errorCorrectionLevel: 'M',
      })
    } catch {
      qrError.value = t('users.inviteQrGenerateError')
    }
  }
}

function downloadQr() {
  if (!qrDataUrl.value) return
  const a = document.createElement('a')
  a.href = qrDataUrl.value
  a.download = 'invitation-qr.png'
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
}

async function copyUrl(url, which) {
  const hintRef =
    which === 'send' ? sendCopyHint : which === 'link' ? linkCopyHint : qrCopyHint
  hintRef.value = ''
  try {
    await navigator.clipboard.writeText(url)
    hintRef.value = t('users.inviteCopied')
  } catch {
    hintRef.value = t('users.inviteCopyFailed')
  }
}
</script>

<template>
  <div class="users-invitation-modals">
    <dialog
      ref="sendDialog"
      class="users-invitation-modals__dialog"
      @click="onSendDialogClick"
    >
      <form class="users-invitation-modals__panel" @submit.prevent="submitSendInvitation">
        <h2 class="users-invitation-modals__title">{{ t('users.inviteSendTitle') }}</h2>
        <p class="users-invitation-modals__intro">{{ t('users.inviteSendIntro') }}</p>
        <Input
          v-model="sendEmail"
          type="email"
          name="invite-email"
          :label="t('users.inviteEmailLabel')"
          autocomplete="email"
          :disabled="sendLoading || Boolean(sendResultUrl)"
        />
        <p v-if="sendError" class="users-invitation-modals__error" role="alert">
          {{ sendError }}
        </p>
        <div v-if="sendResultUrl" class="users-invitation-modals__result">
          <p class="users-invitation-modals__resultLabel">{{ t('users.inviteJoinUrlLabel') }}</p>
          <div class="users-invitation-modals__resultRow">
            <code class="users-invitation-modals__code">{{ sendResultUrl }}</code>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              @click="copyUrl(sendResultUrl, 'send')"
            >
              {{ t('users.inviteCopy') }}
            </Button>
          </div>
          <p v-if="sendEmailSent" class="users-invitation-modals__hint">
            {{ t('users.inviteEmailSentYes') }}
          </p>
          <p v-else class="users-invitation-modals__hint">
            {{ t('users.inviteEmailSentNo') }}
          </p>
          <p v-if="sendResultMaxUses === null" class="users-invitation-modals__hint">
            {{ t('users.inviteResultMaxUsesUnlimited') }}
          </p>
          <p v-else-if="sendResultMaxUses === 1" class="users-invitation-modals__hint">
            {{ t('users.inviteResultMaxUsesOnce') }}
          </p>
          <p v-else class="users-invitation-modals__hint">
            {{
              t('users.inviteResultMaxUses').replace('{n}', String(sendResultMaxUses))
            }}
          </p>
        </div>
        <p v-if="sendCopyHint" class="users-invitation-modals__hint">{{ sendCopyHint }}</p>
        <div class="users-invitation-modals__actions">
          <Button type="button" variant="secondary" @click="closeSendDialog">
            {{ sendResultUrl ? t('users.inviteClose') : t('users.inviteCancel') }}
          </Button>
          <Button
            v-if="!sendResultUrl"
            type="submit"
            variant="primary"
            :loading="sendLoading"
          >
            {{ t('users.inviteSendSubmit') }}
          </Button>
        </div>
      </form>
    </dialog>

    <dialog
      ref="linkDialog"
      class="users-invitation-modals__dialog"
      @click="onLinkDialogClick"
    >
      <div class="users-invitation-modals__panel">
        <h2 class="users-invitation-modals__title">{{ t('users.inviteLinkTitle') }}</h2>
        <p class="users-invitation-modals__intro">{{ t('users.inviteLinkIntro') }}</p>
        <UsersInvitationMaxUsesFields
          v-model="linkUsage"
          name-prefix="invite-link"
          :disabled="linkLoading || Boolean(linkResultUrl)"
        />
        <p v-if="linkError" class="users-invitation-modals__error" role="alert">
          {{ linkError }}
        </p>
        <div v-if="linkResultUrl" class="users-invitation-modals__result">
          <p class="users-invitation-modals__resultLabel">{{ t('users.inviteJoinUrlLabel') }}</p>
          <div class="users-invitation-modals__resultRow">
            <code class="users-invitation-modals__code">{{ linkResultUrl }}</code>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              @click="copyUrl(linkResultUrl, 'link')"
            >
              {{ t('users.inviteCopy') }}
            </Button>
          </div>
          <p v-if="linkResultMaxUses === null" class="users-invitation-modals__hint">
            {{ t('users.inviteResultMaxUsesUnlimited') }}
          </p>
          <p v-else-if="linkResultMaxUses === 1" class="users-invitation-modals__hint">
            {{ t('users.inviteResultMaxUsesOnce') }}
          </p>
          <p v-else class="users-invitation-modals__hint">
            {{
              t('users.inviteResultMaxUses').replace('{n}', String(linkResultMaxUses))
            }}
          </p>
        </div>
        <p v-if="linkCopyHint" class="users-invitation-modals__hint">{{ linkCopyHint }}</p>
        <div class="users-invitation-modals__actions">
          <Button type="button" variant="secondary" @click="closeLinkDialog">
            {{ linkResultUrl ? t('users.inviteClose') : t('users.inviteCancel') }}
          </Button>
          <Button
            v-if="!linkResultUrl"
            type="button"
            variant="primary"
            :loading="linkLoading"
            @click="submitCreateLink"
          >
            {{ t('users.inviteLinkSubmit') }}
          </Button>
        </div>
      </div>
    </dialog>

    <dialog
      ref="qrDialog"
      class="users-invitation-modals__dialog"
      @click="onQrDialogClick"
    >
      <div class="users-invitation-modals__panel">
        <h2 class="users-invitation-modals__title">{{ t('users.inviteQrTitle') }}</h2>
        <p class="users-invitation-modals__intro">{{ t('users.inviteQrIntro') }}</p>
        <UsersInvitationMaxUsesFields
          v-model="qrUsage"
          name-prefix="invite-qr"
          :disabled="qrLoading || Boolean(qrResultUrl)"
        />
        <p v-if="qrError" class="users-invitation-modals__error" role="alert">
          {{ qrError }}
        </p>
        <div v-if="qrResultUrl" class="users-invitation-modals__result">
          <template v-if="qrDataUrl">
            <p class="users-invitation-modals__resultLabel">{{ t('users.inviteQrImageLabel') }}</p>
            <img
              class="users-invitation-modals__qrImg"
              :src="qrDataUrl"
              :alt="t('users.inviteQrImageAlt')"
            />
            <div class="users-invitation-modals__resultRow">
              <Button type="button" variant="secondary" size="sm" @click="downloadQr">
                {{ t('users.inviteQrDownload') }}
              </Button>
            </div>
          </template>
          <p class="users-invitation-modals__resultLabel">{{ t('users.inviteJoinUrlLabel') }}</p>
          <div class="users-invitation-modals__resultRow">
            <code class="users-invitation-modals__code">{{ qrResultUrl }}</code>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              @click="copyUrl(qrResultUrl, 'qr')"
            >
              {{ t('users.inviteCopy') }}
            </Button>
          </div>
          <p v-if="qrResultMaxUses === null" class="users-invitation-modals__hint">
            {{ t('users.inviteResultMaxUsesUnlimited') }}
          </p>
          <p v-else-if="qrResultMaxUses === 1" class="users-invitation-modals__hint">
            {{ t('users.inviteResultMaxUsesOnce') }}
          </p>
          <p v-else class="users-invitation-modals__hint">
            {{
              t('users.inviteResultMaxUses').replace('{n}', String(qrResultMaxUses))
            }}
          </p>
        </div>
        <p v-if="qrCopyHint" class="users-invitation-modals__hint">{{ qrCopyHint }}</p>
        <div class="users-invitation-modals__actions">
          <Button type="button" variant="secondary" @click="closeQrDialog">
            {{ qrResultUrl ? t('users.inviteClose') : t('users.inviteCancel') }}
          </Button>
          <Button
            v-if="!qrResultUrl"
            type="button"
            variant="primary"
            :loading="qrLoading"
            @click="submitCreateQr"
          >
            {{ t('users.inviteQrSubmit') }}
          </Button>
        </div>
      </div>
    </dialog>
  </div>
</template>

<style lang="scss" scoped>
.users-invitation-modals__dialog {
  padding: 0;
  border: none;
  border-radius: 0.5rem;
  max-width: calc(100vw - 2rem);
  width: min(30rem, calc(100vw - 2rem));
  box-shadow: 0 18px 48px rgba(0, 0, 0, 0.18);
}

.users-invitation-modals__dialog::backdrop {
  background: rgba(15, 23, 42, 0.45);
}

.users-invitation-modals__panel {
  padding: 1.25rem 1.35rem;
}

.users-invitation-modals__title {
  margin: 0 0 0.5rem;
  font-size: 1.15rem;
}

.users-invitation-modals__intro {
  margin: 0 0 1rem;
  font-size: 0.9rem;
  color: var(--muted, #4b5563);
}

.users-invitation-modals__error {
  color: #b91c1c;
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
}

.users-invitation-modals__result {
  margin: 0 0 1rem;
}

.users-invitation-modals__resultLabel {
  margin: 0 0 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--muted, #4b5563);
}

.users-invitation-modals__resultRow {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.5rem;
}

.users-invitation-modals__code {
  flex: 1 1 12rem;
  display: block;
  padding: 0.45rem 0.5rem;
  font-size: 0.78rem;
  word-break: break-all;
  background: var(--table-head, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--border);
  border-radius: 0.35rem;
}

.users-invitation-modals__qrImg {
  display: block;
  max-width: 100%;
  height: auto;
  margin: 0 0 0.75rem;
  border: 1px solid var(--border);
  border-radius: 0.35rem;
}

.users-invitation-modals__hint {
  margin: 0 0 0.75rem;
  font-size: 0.85rem;
  color: var(--muted, #6b7280);
}

.users-invitation-modals__actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.25rem;
}
</style>
