<script setup>
import { computed } from 'vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  /** When true, badge is hidden (e.g. no folio). */
  modelValue: {
    type: Boolean,
    default: false,
  },
})

const show = computed(() => Boolean(props.modelValue))

const ariaLabel = computed(() => t('votingId.registeredBadgeAria'))
</script>

<template>
  <span
    v-if="show"
    class="voting-id-verified-badge"
    role="img"
    :aria-label="ariaLabel"
    :title="ariaLabel"
  >
    <svg
      class="voting-id-verified-badge__icon"
      viewBox="0 0 24 24"
      width="1em"
      height="1em"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="11" fill="#1d4ed8" />
      <path
        fill="#fff"
        d="M10.2 16.2 6.8 12.8l1.4-1.4 2 2 5.6-5.6 1.4 1.4-7 7z"
      />
    </svg>
  </span>
</template>

<style lang="scss" scoped>
.voting-id-verified-badge {
  display: inline-flex;
  align-items: center;
  vertical-align: middle;
  line-height: 1;
}

.voting-id-verified-badge__icon {
  display: block;
  width: 1.05em;
  height: 1.05em;
}
</style>
