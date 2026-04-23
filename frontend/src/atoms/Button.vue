<template>
  <button
    :type="type"
    class="btn"
    :class="[
      `btn--${variant}`,
      `btn--${size}`,
      { 'is-loading': loading, 'is-disabled': isDisabled },
    ]"
    :disabled="isDisabled"
  >
    <span class="btn__inner">
      <slot name="left" />
      <span v-if="loading" class="btn__spinner" aria-hidden="true" />
      <slot />
      <slot name="right" />
    </span>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'ghost', 'danger'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  type: {
    type: String,
    default: 'button',
    validator: (v) => ['button', 'submit', 'reset'].includes(v),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const isDisabled = computed(() => props.disabled || props.loading)
</script>

<style lang="scss" scoped>
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  border: 1px solid transparent;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
  user-select: none;
  transition: background-color 140ms ease, color 140ms ease, border-color 140ms ease,
    transform 80ms ease, opacity 140ms ease;

  .btn__inner {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
  }

  &:not(:disabled):active {
    transform: translateY(1px);
  }

  &:disabled,
  &.is-disabled {
    cursor: not-allowed;
    opacity: 0.6;
  }

  .btn__spinner {
    width: 1em;
    height: 1em;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.45);
    border-top-color: currentColor;
    animation: btn-spin 0.8s linear infinite;
  }
}

/* Sizes */
.btn--sm {
  padding: 0.4rem 0.75rem;
  font-size: 0.875rem;
}

.btn--md {
  padding: 0.6rem 1rem;
  font-size: 1rem;
}

.btn--lg {
  padding: 0.85rem 1.25rem;
  font-size: 1.125rem;
}

/* Variants */
.btn--primary {
  background-color: #2563eb;
  color: #ffffff;
  border-color: rgba(37, 99, 235, 0.85);

  &:not(:disabled):hover {
    background-color: #1d4ed8;
  }
}

.btn--secondary {
  background-color: #e5e7eb;
  color: #111827;
  border-color: #e5e7eb;

  .btn__spinner {
    border-color: rgba(17, 24, 39, 0.2);
    border-top-color: currentColor;
  }

  &:not(:disabled):hover {
    background-color: #d1d5db;
  }
}

.btn--ghost {
  background-color: transparent;
  color: #2563eb;
  border-color: rgba(37, 99, 235, 0.35);

  .btn__spinner {
    border-color: rgba(37, 99, 235, 0.2);
    border-top-color: currentColor;
  }

  &:not(:disabled):hover {
    background-color: rgba(37, 99, 235, 0.08);
    border-color: rgba(37, 99, 235, 0.6);
  }
}

.btn--danger {
  background-color: #dc2626;
  color: #ffffff;
  border-color: rgba(220, 38, 38, 0.85);

  &:not(:disabled):hover {
    background-color: #b91c1c;
  }
}

@keyframes btn-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>