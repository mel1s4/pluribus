<script setup>
import { computed } from 'vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
  /** @type {{ mode: 'unlimited' | 'once' | 'custom', custom: number }} */
  modelValue: {
    type: Object,
    required: true,
  },
  namePrefix: {
    type: String,
    default: 'invite-max',
  },
})

const emit = defineEmits(['update:modelValue'])

function patch(partial) {
  emit('update:modelValue', { ...props.modelValue, ...partial })
}

function onModeInput(mode) {
  if (mode === 'custom') {
    const custom =
      Number.isFinite(props.modelValue.custom) && props.modelValue.custom >= 2
        ? props.modelValue.custom
        : 5
    patch({ mode: 'custom', custom })
  } else {
    patch({ mode })
  }
}

const customStr = computed({
  get() {
    const n = props.modelValue.custom
    return Number.isFinite(n) ? String(n) : '5'
  },
  set(v) {
    const parsed = parseInt(String(v), 10)
    patch({ custom: Number.isFinite(parsed) ? parsed : 5 })
  },
})

const numberId = computed(() => `${props.namePrefix}-custom-n`)
</script>

<template>
  <fieldset class="users-invite-max-uses" :disabled="disabled">
    <legend class="users-invite-max-uses__legend">{{ t('users.inviteMaxUsesLegend') }}</legend>
    <div class="users-invite-max-uses__options">
      <label class="users-invite-max-uses__row">
        <input
          class="users-invite-max-uses__radio"
          type="radio"
          :name="`${namePrefix}-mode`"
          value="unlimited"
          :checked="modelValue.mode === 'unlimited'"
          @change="onModeInput('unlimited')"
        />
        <span>{{ t('users.inviteMaxUsesUnlimited') }}</span>
      </label>
      <label class="users-invite-max-uses__row">
        <input
          class="users-invite-max-uses__radio"
          type="radio"
          :name="`${namePrefix}-mode`"
          value="once"
          :checked="modelValue.mode === 'once'"
          @change="onModeInput('once')"
        />
        <span>{{ t('users.inviteMaxUsesOnce') }}</span>
      </label>
      <div class="users-invite-max-uses__custom">
        <label class="users-invite-max-uses__row">
          <input
            class="users-invite-max-uses__radio"
            type="radio"
            :name="`${namePrefix}-mode`"
            value="custom"
            :checked="modelValue.mode === 'custom'"
            @change="onModeInput('custom')"
          />
          <span>{{ t('users.inviteMaxUsesCustom') }}</span>
        </label>
        <div v-if="modelValue.mode === 'custom'" class="users-invite-max-uses__customFields">
          <label class="users-invite-max-uses__numberLabel" :for="numberId">{{
            t('users.inviteMaxUsesCustomNumberLabel')
          }}</label>
          <input
            :id="numberId"
            v-model="customStr"
            class="users-invite-max-uses__number"
            type="number"
            min="2"
            max="10000"
            step="1"
            :disabled="disabled"
            :aria-label="t('users.inviteMaxUsesCustomNumberLabel')"
          />
        </div>
      </div>
    </div>
    <p class="users-invite-max-uses__hint">{{ t('users.inviteMaxUsesHint') }}</p>
  </fieldset>
</template>

<style lang="scss" scoped>
.users-invite-max-uses {
  margin: 0 0 1rem;
  padding: 0.75rem 0.85rem;
  border: 1px solid var(--border);
  border-radius: 0.45rem;
}

.users-invite-max-uses__legend {
  padding: 0 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
}

.users-invite-max-uses__options {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  margin-top: 0.35rem;
}

.users-invite-max-uses__row {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  font-size: 0.9rem;
  cursor: pointer;
}

.users-invite-max-uses__radio {
  margin-top: 0.2rem;
  flex-shrink: 0;
}

.users-invite-max-uses__custom {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.users-invite-max-uses__customFields {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding-left: 1.6rem;
}

.users-invite-max-uses__numberLabel {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--muted, #4b5563);
}

.users-invite-max-uses__number {
  width: 100%;
  max-width: 8rem;
  padding: 0.45rem 0.5rem;
  border: 1px solid var(--border);
  border-radius: 0.35rem;
  font-size: 0.95rem;
}

.users-invite-max-uses__hint {
  margin: 0.65rem 0 0;
  font-size: 0.8rem;
  color: var(--muted, #6b7280);
}
</style>
