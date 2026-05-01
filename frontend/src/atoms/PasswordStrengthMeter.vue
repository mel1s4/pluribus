<script setup>
import { computed } from 'vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  password: {
    type: String,
    default: '',
  },
})

function score(value) {
  if (!value) return 0
  let s = 0
  if (value.length >= 12) s += 1
  if (value.length >= 16) s += 1
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) s += 1
  if (/\d/.test(value)) s += 1
  if (/[^A-Za-z0-9]/.test(value)) s += 1
  return Math.min(s, 4)
}

const computedScore = computed(() => score(props.password))

const labelKey = computed(() => {
  switch (computedScore.value) {
    case 0:
      return 'passwordReset.strength.empty'
    case 1:
      return 'passwordReset.strength.veryWeak'
    case 2:
      return 'passwordReset.strength.weak'
    case 3:
      return 'passwordReset.strength.good'
    default:
      return 'passwordReset.strength.strong'
  }
})

const segments = computed(() => [1, 2, 3, 4].map((i) => i <= computedScore.value))
</script>

<template>
  <div
    class="password-strength"
    role="status"
    :aria-label="t(labelKey)"
  >
    <div class="password-strength__bars">
      <span
        v-for="(filled, i) in segments"
        :key="i"
        class="password-strength__bar"
        :class="[
          `password-strength__bar--level-${computedScore}`,
          filled ? 'password-strength__bar--filled' : '',
        ]"
      />
    </div>
    <p class="password-strength__label">{{ t(labelKey) }}</p>
  </div>
</template>

<style scoped lang="scss">
.password-strength {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.password-strength__bars {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.25rem;
}

.password-strength__bar {
  height: 0.4rem;
  border-radius: 0.2rem;
  background: var(--color-border, rgba(0, 0, 0, 0.12));
  transition: background 120ms ease;
}

.password-strength__bar--filled.password-strength__bar--level-1 {
  background: #b91c1c;
}

.password-strength__bar--filled.password-strength__bar--level-2 {
  background: #c2410c;
}

.password-strength__bar--filled.password-strength__bar--level-3 {
  background: #ca8a04;
}

.password-strength__bar--filled.password-strength__bar--level-4 {
  background: #15803d;
}

.password-strength__label {
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-text-muted, rgba(0, 0, 0, 0.6));
}
</style>
