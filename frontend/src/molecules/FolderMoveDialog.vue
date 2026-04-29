<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import Button from '../atoms/Button.vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  /** flat folder list */
  folders: {
    type: Array,
    default: () => [],
  },
  /** hide moving into same folder */
  excludeFolderId: {
    type: [Number, String, null],
    default: null,
  },
  title: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:open', 'confirm', 'cancel'])

const dialogRef = ref(null)
/** @type {import('vue').Ref<string>} */
const selectedId = ref('')

const titleText = computed(() => props.title || t('folders.moveDialogTitle'))

const folderOptions = computed(() =>
  props.folders.filter((f) => {
    if (props.excludeFolderId == null) return true
    return Number(f.id) !== Number(props.excludeFolderId)
  }),
)

watch(
  () => props.open,
  async (v) => {
    if (v) {
      selectedId.value = ''
      await nextTick()
      dialogRef.value?.showModal?.()
    } else {
      dialogRef.value?.close?.()
    }
  },
  { immediate: true },
)

function onBackdrop(e) {
  if (e.target === dialogRef.value) close()
}

function close() {
  emit('update:open', false)
  emit('cancel')
}

function confirm() {
  const v = selectedId.value
  if (v === 'root') emit('confirm', null)
  else if (v) emit('confirm', Number(v))
  emit('update:open', false)
}
</script>

<template>
  <dialog ref="dialogRef" class="folder-move-dialog" @click="onBackdrop">
    <div class="folder-move-dialog__panel" @click.stop>
      <h2 id="folder-move-title" class="folder-move-dialog__title">{{ titleText }}</h2>
      <p class="folder-move-dialog__hint">{{ t('folders.moveDialogHint') }}</p>
      <label class="folder-move-dialog__field">
        <span class="folder-move-dialog__label">{{ t('folders.targetFolder') }}</span>
        <select v-model="selectedId" class="folder-move-dialog__select">
          <option value="" disabled>{{ t('folders.pickFolder') }}</option>
          <option value="root">{{ t('folders.unfiledRoot') }}</option>
          <option v-for="f in folderOptions" :key="f.id" :value="String(f.id)">
            {{ f.name }}
          </option>
        </select>
      </label>
      <div class="folder-move-dialog__actions">
        <Button variant="secondary" @click="close">{{ t('common.cancel') }}</Button>
        <Button :disabled="selectedId === ''" @click="confirm">{{ t('folders.move') }}</Button>
      </div>
    </div>
  </dialog>
</template>

<style scoped lang="scss">
.folder-move-dialog {
  border: none;
  padding: 0;
  max-width: 26rem;
  width: calc(100% - 2rem);
  border-radius: 0.65rem;
  background: transparent;

  &::backdrop {
    background: rgba(0, 0, 0, 0.45);
  }
}

.folder-move-dialog__panel {
  padding: 1.25rem;
  background: var(--bg);
  border-radius: 0.65rem;
  border: 1px solid var(--border);
}

.folder-move-dialog__title {
  margin: 0 0 0.5rem;
  font-size: 1.15rem;
}

.folder-move-dialog__hint {
  margin: 0 0 1rem;
  font-size: 0.9rem;
  opacity: 0.85;
}

.folder-move-dialog__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 1.25rem;
}

.folder-move-dialog__select {
  width: 100%;
}

.folder-move-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
</style>
