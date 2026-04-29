<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import Button from '../atoms/Button.vue'
import { t } from '../i18n/i18n'
import { fetchGroups } from '../services/contentApi.js'

const props = defineProps({
  folder: {
    type: Object,
    default: null,
  },
  open: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'share', 'unshare'])

const dialogRef = ref(null)
const groups = ref([])
const selectedGroups = ref([])
const loading = ref(false)

// Current sharing info from the folder
const currentSharedGroup = computed(() => {
  if (!props.folder?.shared_group_id) return null
  return groups.value.find(g => Number(g.id) === Number(props.folder.shared_group_id))
})

const isShared = computed(() => {
  return Boolean(props.folder?.shared_group_id)
})

async function loadGroups() {
  const res = await fetchGroups()
  if (res.ok && Array.isArray(res.data?.data)) {
    groups.value = res.data.data
  }
}

function onBackdrop(e) {
  if (e.target === dialogRef.value) {
    emit('close')
  }
}

function isGroupSelected(groupId) {
  return selectedGroups.value.some(group => Number(group.id) === Number(groupId))
}

function toggleGroup(group) {
  const id = Number(group.id)
  if (!Number.isFinite(id)) return
  
  if (isGroupSelected(id)) {
    selectedGroups.value = selectedGroups.value.filter(g => Number(g.id) !== id)
  } else {
    selectedGroups.value = [group] // Only one group for now
  }
}

function removeSelectedGroup(groupId) {
  selectedGroups.value = selectedGroups.value.filter(g => Number(g.id) !== Number(groupId))
}

async function handleSave() {
  if (!props.folder) return
  
  loading.value = true
  
  try {
    // For now, we'll use the existing backend structure with shared_group_id
    const selectedGroup = selectedGroups.value[0] || null
    
    emit('share', {
      folderId: props.folder.id,
      shared_group_id: selectedGroup ? selectedGroup.id : null,
    })
  } finally {
    loading.value = false
  }
}

async function handleMakePrivate() {
  if (!props.folder) return
  
  loading.value = true
  
  try {
    emit('unshare', {
      folderId: props.folder.id,
    })
  } finally {
    loading.value = false
  }
}

// Reset selections when folder changes
watch(() => props.folder, (newFolder) => {
  selectedGroups.value = []
  
  // Pre-select current shared group if exists
  if (newFolder?.shared_group_id) {
    const sharedGroup = groups.value.find(g => Number(g.id) === Number(newFolder.shared_group_id))
    if (sharedGroup) {
      selectedGroups.value = [sharedGroup]
    }
  }
})

// Show/hide dialog
watch(() => props.open, (isOpen) => {
  if (isOpen) {
    dialogRef.value?.showModal()
  } else {
    dialogRef.value?.close()
  }
})

onMounted(() => {
  loadGroups()
})
</script>

<template>
  <dialog ref="dialogRef" class="folder-share-dialog" @click="onBackdrop">
    <div class="folder-share-dialog__panel" @click.stop>
      <header class="folder-share-dialog__header">
        <h2>{{ t('folders.shareTitle') }}</h2>
        <p class="folder-share-dialog__hint">{{ t('folders.shareHint') }}</p>
      </header>

      <div class="folder-share-dialog__content">
        <!-- Current sharing status -->
        <section v-if="isShared" class="folder-share-dialog__section">
          <h3>{{ t('folders.currentSharing') }}</h3>
          <div class="folder-share-dialog__currentSharing">
            <div v-if="currentSharedGroup" class="folder-share-dialog__sharedItem">
              <span class="folder-share-dialog__sharedItemName">
                {{ t('folders.sharedWithGroup', { name: currentSharedGroup.name }) }}
              </span>
              <button 
                type="button" 
                class="folder-share-dialog__removeBtn"
                :disabled="loading"
                @click="handleMakePrivate"
              >
                {{ t('folders.removeFromSharing') }}
              </button>
            </div>
          </div>
        </section>

        <!-- Share with groups -->
        <section class="folder-share-dialog__section">
          <h3>{{ t('folders.shareWithGroups') }}</h3>
          
          <div v-if="groups.length === 0" class="folder-share-dialog__empty">
            <p>{{ t('folders.noGroupsAvailable') }}</p>
          </div>
          
          <div v-else class="folder-share-dialog__groupsList">
            <div 
              v-for="group in groups" 
              :key="group.id"
              class="folder-share-dialog__groupItem"
            >
              <button
                type="button"
                class="folder-share-dialog__groupBtn"
                :class="{ 'is-selected': isGroupSelected(group.id) }"
                :disabled="loading"
                @click="toggleGroup(group)"
              >
                <div class="folder-share-dialog__groupInfo">
                  <span class="folder-share-dialog__groupName">{{ group.name }}</span>
                  <span v-if="group.description" class="folder-share-dialog__groupDesc">
                    {{ group.description }}
                  </span>
                </div>
              </button>
            </div>
          </div>
          
          <!-- Selected groups preview -->
          <div v-if="selectedGroups.length > 0" class="folder-share-dialog__selected">
            <h4>Selected groups:</h4>
            <div class="folder-share-dialog__selectedItems">
              <span 
                v-for="group in selectedGroups" 
                :key="group.id"
                class="folder-share-dialog__selectedPill"
              >
                {{ group.name }}
                <button
                  type="button"
                  class="folder-share-dialog__selectedRemove"
                  :disabled="loading"
                  @click="removeSelectedGroup(group.id)"
                >
                  ×
                </button>
              </span>
            </div>
          </div>
        </section>

      </div>

      <footer class="folder-share-dialog__actions">
        <Button 
          type="button" 
          variant="secondary" 
          :disabled="loading"
          @click="emit('close')"
        >
          {{ t('folders.cancel') }}
        </Button>
        
        <Button 
          v-if="isShared"
          type="button" 
          variant="danger"
          :disabled="loading"
          @click="handleMakePrivate"
        >
          {{ t('folders.makePrivate') }}
        </Button>
        
        <Button 
          type="button"
          :disabled="loading || selectedGroups.length === 0"
          @click="handleSave"
        >
          {{ loading ? t('folders.loading') : t('folders.save') }}
        </Button>
      </footer>
    </div>
  </dialog>
</template>

<style scoped lang="scss">
.folder-share-dialog {
  border: none;
  padding: 0;
  max-width: 36rem;
  width: calc(100% - 2rem);
  background: transparent;
  border-radius: 0.75rem;

  &::backdrop {
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(2px);
  }
}

.folder-share-dialog__panel {
  background: var(--bg, #fff);
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.75rem;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
  max-height: 80vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.folder-share-dialog__header {
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid var(--border, #e5e7eb);

  h2 {
    margin: 0 0 0.5rem;
    font-size: 1.1rem;
    font-weight: 700;
  }
}

.folder-share-dialog__hint {
  margin: 0;
  font-size: 0.875rem;
  color: var(--text-muted, #6b7280);
  line-height: 1.4;
}

.folder-share-dialog__content {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 1.5rem;
}

.folder-share-dialog__section {
  margin-bottom: 1.5rem;

  &:last-child {
    margin-bottom: 0;
  }

  h3 {
    margin: 0 0 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted, #6b7280);

    small {
      font-size: 0.75rem;
      font-weight: normal;
      text-transform: none;
      opacity: 0.8;
      margin-left: 0.5rem;
    }
  }

  h4 {
    margin: 0 0 0.5rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--text, #374151);
  }
}

.folder-share-dialog__empty {
  padding: 1rem;
  background: var(--surface-2, #f3f4f6);
  border-radius: 0.5rem;
  text-align: center;

  p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-muted, #6b7280);
  }
}

.folder-share-dialog__currentSharing {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.folder-share-dialog__sharedItem {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem;
  background: var(--surface-2, #f3f4f6);
  border-radius: 0.5rem;
}

.folder-share-dialog__sharedItemName {
  font-size: 0.875rem;
  font-weight: 500;
}

.folder-share-dialog__removeBtn {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  background: var(--danger, #dc2626);
  color: white;
  border: none;
  border-radius: 0.25rem;
  cursor: pointer;

  &:hover:not(:disabled) {
    background: var(--danger-dark, #b91c1c);
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.folder-share-dialog__groupsList {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.folder-share-dialog__groupItem {
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  overflow: hidden;
}

.folder-share-dialog__groupBtn {
  width: 100%;
  padding: 0.75rem;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  transition: background 120ms ease;

  &:hover:not(:disabled) {
    background: var(--surface-2, rgba(0, 0, 0, 0.04));
  }

  &.is-selected {
    background: var(--accent-bg, rgba(37, 99, 235, 0.1));
    border-color: var(--accent, #2563eb);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.folder-share-dialog__groupInfo {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.folder-share-dialog__groupName {
  font-weight: 500;
  font-size: 0.875rem;
}

.folder-share-dialog__groupDesc {
  font-size: 0.8rem;
  opacity: 0.75;
}

.folder-share-dialog__selected {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border, #e5e7eb);
}

.folder-share-dialog__selectedItems {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.folder-share-dialog__selectedPill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.6rem;
  background: rgba(37, 99, 235, 0.1);
  color: var(--accent, #2563eb);
  border-radius: 2rem;
  font-size: 0.825rem;
  font-weight: 500;
}

.folder-share-dialog__selectedRemove {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  padding: 0;
  font-size: 1.1rem;
  line-height: 1;
  opacity: 0.8;

  &:hover:not(:disabled) {
    opacity: 1;
  }

  &:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
}

.folder-share-dialog__actions {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border, #e5e7eb);
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  flex-wrap: wrap;
}
</style>