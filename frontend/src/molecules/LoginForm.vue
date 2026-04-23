<script setup>
import { ref } from 'vue'
import { t } from '../i18n/i18n'
import Button from '../atoms/Button.vue'
import Checkbox from '../atoms/Checkbox.vue'
import Input from '../atoms/Input.vue'

defineProps({
  submitting: {
    type: Boolean,
    default: false,
  },
  emailError: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['submit'])

const email = ref('')
const password = ref('')
const remember = ref(false)

function handleSubmit() {
  emit('submit', {
    email: email.value,
    password: password.value,
    remember: remember.value,
  })
}
</script>

<template>
  <form class="login-form" @submit.prevent="handleSubmit">
    <Input
      v-model="email"
      type="text"
      name="email"
      autocomplete="username"
      :label="t('login.email')"
      :placeholder="t('login.emailPlaceholder')"
      required
    />
    <p v-if="emailError" class="login-form__error">{{ emailError }}</p>

    <Input
      v-model="password"
      type="password"
      name="password"
      autocomplete="current-password"
      :label="t('login.password')"
      :placeholder="t('login.passwordPlaceholder')"
      required
    />

    <div class="login-form__row">
      <Checkbox v-model="remember" :label="t('login.rememberMe')" />
    </div>

    <Button
      type="submit"
      variant="primary"
      size="md"
      :loading="submitting"
    >
      {{ t('login.signIn') }}
    </Button>
  </form>
</template>

<style lang="scss" scoped>
.login-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.login-form__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.login-form__error {
  color: var(--color-danger, #b91c1c);
  font-size: 0.8125rem;
  margin: -0.5rem 0 0;
}
</style>
