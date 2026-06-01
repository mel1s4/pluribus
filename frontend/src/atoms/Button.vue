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
    validator: (v) => ['primary', 'secondary', 'ghost', 'danger', 'link'].includes(v),
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
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  font-weight: var(--font-weight-semibold);
  line-height: var(--line-height-tight);
  cursor: pointer;
  user-select: none;
  transition:
    background-color var(--motion-standard) var(--ease-standard),
    color var(--motion-standard) var(--ease-standard),
    border-color var(--motion-standard) var(--ease-standard),
    transform var(--motion-fast) var(--ease-standard),
    opacity var(--motion-standard) var(--ease-standard);

  .btn__inner {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
  }

  &:focus-visible {
    outline: 2px solid var(--color-focus-ring);
    outline-offset: 2px;
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
    border: 2px solid var(--color-spinner-track);
    border-top-color: currentColor;
    animation: btn-spin 0.8s linear infinite;
  }
}

.btn--sm {
  padding: var(--space-1) var(--space-3);
  font-size: var(--text-sm);
}

.btn--md {
  padding: var(--space-2) var(--space-4);
  font-size: var(--text-md);
}

.btn--lg {
  padding: var(--space-3) var(--space-5);
  font-size: var(--text-lg);
}

.btn--primary {
  background-color: var(--color-primary);
  color: var(--color-on-primary);
  border-color: var(--color-primary);

  &:not(:disabled):hover {
    background-color: var(--color-primary-hover);
    border-color: var(--color-primary-hover);
  }
}

.btn--secondary {
  background-color: var(--color-secondary-bg);
  color: var(--color-on-secondary);
  border-color: var(--color-secondary-bg);

  .btn__spinner {
    border-color: var(--color-outline);
    border-top-color: currentColor;
  }

  &:not(:disabled):hover {
    background-color: var(--color-secondary-hover);
    border-color: var(--color-secondary-hover);
  }
}

.btn--ghost {
  background-color: transparent;
  color: var(--color-primary);
  border-color: var(--color-ghost-border);

  .btn__spinner {
    border-color: var(--color-ghost-border);
    border-top-color: currentColor;
  }

  &:not(:disabled):hover {
    background-color: var(--color-ghost-hover);
    border-color: var(--color-ghost-border-hover);
  }
}

.btn--danger {
  background-color: var(--color-danger);
  color: var(--color-on-danger);
  border-color: var(--color-danger);

  &:not(:disabled):hover {
    background-color: var(--color-danger-hover);
    border-color: var(--color-danger-hover);
  }
}

.btn--link {
  background-color: transparent;
  color: var(--color-primary);
  border-color: transparent;
  padding-left: 0;
  padding-right: 0;
  text-decoration: none;

  .btn__spinner {
    border-color: var(--color-ghost-border);
    border-top-color: currentColor;
  }

  &:not(:disabled):hover {
    text-decoration: underline;
    background-color: transparent;
  }

  &:not(:disabled):active {
    transform: none;
  }
}

@keyframes btn-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
