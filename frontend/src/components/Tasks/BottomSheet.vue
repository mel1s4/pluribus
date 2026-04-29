<script setup>
import { onUnmounted, watch } from 'vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  open: { type: Boolean, default: false },
  /** Optional label for sheet header */
  title: { type: String, default: '' },
})

const emit = defineEmits(['update:open'])

function close() {
  emit('update:open', false)
}

function onBackdrop() {
  close()
}

watch(
  () => props.open,
  (v) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = v ? 'hidden' : ''
  },
)

onUnmounted(() => {
  if (typeof document !== 'undefined') document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="bs">
      <div v-if="open" class="bs-root" aria-modal="true" role="dialog">
        <div class="bs-backdrop" @click="onBackdrop" />
        <div class="bs-panel" @click.stop>
          <div class="bs-handle" aria-hidden="true" />
          <header v-if="title" class="bs-head">
            <h2 class="bs-title">{{ title }}</h2>
            <button type="button" class="bs-close" :aria-label="t('tasks.closeSheet')" @click="close">
              ×
            </button>
          </header>
          <div class="bs-body">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped lang="scss">
.bs-root {
  position: fixed;
  inset: 0;
  z-index: 2100;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  pointer-events: auto;
}

.bs-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
}

.bs-panel {
  position: relative;
  width: 100%;
  max-height: min(85vh, 32rem);
  background: var(--surface, #fff);
  color: var(--text, #0f172a);
  border-radius: 0.85rem 0.85rem 0 0;
  box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.12);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.bs-handle {
  width: 2.5rem;
  height: 0.28rem;
  border-radius: 999px;
  background: var(--border, #d1d5db);
  margin: 0.5rem auto 0.25rem;
  flex-shrink: 0;
}

.bs-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.35rem 0.65rem 0.5rem;
  border-bottom: 1px solid var(--border, #e5e7eb);
  flex-shrink: 0;
}

.bs-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.bs-close {
  border: none;
  background: transparent;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  padding: 0.2rem 0.45rem;
}

.bs-body {
  overflow: auto;
  padding: 0.75rem 1rem 1.25rem;
  -webkit-overflow-scrolling: touch;
}

.bs-enter-active,
.bs-leave-active {
  transition: opacity 0.2s ease;
  .bs-backdrop {
    transition: opacity 0.2s ease;
  }
  .bs-panel {
    transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1);
  }
}

.bs-enter-from,
.bs-leave-to {
  opacity: 0;
  .bs-backdrop {
    opacity: 0;
  }
  .bs-panel {
    transform: translateY(100%);
  }
}
</style>
