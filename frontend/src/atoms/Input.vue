<script setup>
import { computed, ref } from 'vue'
import Icon from './Icon.vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'text',
    validator: (v) => ['text', 'email', 'password', 'number', 'search', 'url'].includes(v),
  },
  name: {
    type: String,
    default: '',
  },
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  autocomplete: {
    type: String,
    default: undefined,
  },
  id: {
    type: String,
    default: undefined,
  },
})

const emit = defineEmits(['update:modelValue'])

const passwordVisible = ref(false)

const resolvedId = computed(() => {
  if (props.id) return props.id
  if (!props.name) return undefined
  return `field-${props.name}`
})

const isPassword = computed(() => props.type === 'password')

const resolvedInputType = computed(() => {
  if (isPassword.value && passwordVisible.value) {
    return 'text'
  }
  return props.type
})

const toggleId = computed(() => {
  const base = resolvedId.value
  return base ? `${base}-password-toggle` : undefined
})

const toggleAriaLabel = computed(() =>
  passwordVisible.value ? t('input.hidePassword') : t('input.showPassword'),
)

function onInput(e) {
  emit('update:modelValue', e.target.value)
}

function togglePassword() {
  if (props.disabled) return
  passwordVisible.value = !passwordVisible.value
}
</script>

<template>
  <div class="field">
    <template v-if="label">
      <label class="field__label" :for="resolvedId">
        <span class="field__labelText">{{ label }}</span>
      </label>
      <span v-if="isPassword" class="field__inputWrap">
        <input
          class="field__input field__input--withToggle"
          :id="resolvedId"
          :type="resolvedInputType"
          :name="name"
          :placeholder="placeholder"
          :required="required"
          :disabled="disabled"
          :autocomplete="autocomplete"
          :value="modelValue"
          @input="onInput"
        />
        <button
          :id="toggleId"
          type="button"
          class="field__toggle"
          :disabled="disabled"
          :aria-label="toggleAriaLabel"
          :aria-pressed="passwordVisible"
          @click="togglePassword"
        >
          <Icon
            class="field__toggleIcon"
            :name="passwordVisible ? 'eye-slash' : 'eye'"
            aria-hidden="true"
          />
        </button>
      </span>
      <input
        v-else
        class="field__input"
        :id="resolvedId"
        :type="type"
        :name="name"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :autocomplete="autocomplete"
        :value="modelValue"
        @input="onInput"
      />
    </template>

    <span v-else-if="isPassword" class="field__inputWrap">
      <input
        class="field__input field__input--withToggle"
        :id="resolvedId"
        :type="resolvedInputType"
        :name="name"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :autocomplete="autocomplete"
        :value="modelValue"
        :aria-label="placeholder || name || 'input'"
        @input="onInput"
      />
      <button
        :id="toggleId"
        type="button"
        class="field__toggle"
        :disabled="disabled"
        :aria-label="toggleAriaLabel"
        :aria-pressed="passwordVisible"
        @click="togglePassword"
      >
        <Icon
          class="field__toggleIcon"
          :name="passwordVisible ? 'eye-slash' : 'eye'"
          aria-hidden="true"
        />
      </button>
    </span>

    <input
      v-else
      class="field__input"
      :id="resolvedId"
      :type="type"
      :name="name"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :autocomplete="autocomplete"
      :value="modelValue"
      :aria-label="placeholder || name || 'input'"
      @input="onInput"
    />
  </div>
</template>

<style lang="scss" scoped>
.field {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  width: 100%;
}

.field__label {
  display: block;
  cursor: pointer;
}

.field__labelText {
  font-size: var(--text-sm);
  font-weight: var(--font-weight-semibold);
  color: var(--text);
}

.field__inputWrap {
  position: relative;
  display: block;
  width: 100%;
}

.field__input {
  width: 100%;
  border-color: var(--color-outline);
  border-radius: var(--radius-md);
  transition:
    border-color var(--motion-standard) var(--ease-standard),
    box-shadow var(--motion-standard) var(--ease-standard);

  &:focus-visible {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px var(--color-focus-ring);
  }
}

.field__input--withToggle {
  padding-right: var(--space-8);
}

.field__toggle {
  position: absolute;
  right: var(--space-1);
  top: 50%;
  transform: translateY(-50%);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  padding: var(--space-1);
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--text);
  cursor: pointer;
  opacity: 0.65;
  transition: opacity var(--motion-fast) var(--ease-standard);

  &:focus-visible {
    outline: 2px solid var(--color-focus-ring);
    outline-offset: 1px;
    opacity: 1;
  }
}

.field__toggle:hover:not(:disabled) {
  opacity: 1;
  background: var(--color-surface);
}

.field__toggle:disabled {
  cursor: not-allowed;
  opacity: 0.35;
}

.field__toggleIcon {
  font-size: var(--text-md);
  line-height: 1;
  width: 1em;
}
</style>
