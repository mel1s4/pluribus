<script setup>
import { ref } from 'vue'
import { t } from '../i18n/i18n'
import Button from '../atoms/Button.vue'
import Input from '../atoms/Input.vue'

defineProps({
  submitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['submit'])

const email = ref('')

function handleSubmit() {
  emit('submit', { email: email.value })
}
</script>

<template>
  <form class="forgot-form" @submit.prevent="handleSubmit">
    <Input
      v-model="email"
      type="email"
      name="email"
      autocomplete="username"
      :label="t('passwordReset.email')"
      :placeholder="t('passwordReset.emailPlaceholder')"
      required
    />

    <Button
      type="submit"
      variant="primary"
      size="md"
      :loading="submitting"
    >
      {{ t('passwordReset.requestSubmit') }}
    </Button>
  </form>
</template>

<style scoped lang="scss">
.forgot-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
</style>
