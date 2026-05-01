<script setup>
import { computed, ref } from 'vue'
import { t } from '../i18n/i18n'
import Button from '../atoms/Button.vue'
import Input from '../atoms/Input.vue'
import PasswordStrengthMeter from '../atoms/PasswordStrengthMeter.vue'

defineProps({
  submitting: {
    type: Boolean,
    default: false,
  },
  passwordError: {
    type: String,
    default: '',
  },
  emailError: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['submit'])

const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const meetsLength = computed(() => password.value.length >= 12)
const hasMixedCase = computed(
  () => /[a-z]/.test(password.value) && /[A-Z]/.test(password.value),
)
const hasNumber = computed(() => /\d/.test(password.value))
const hasSymbol = computed(() => /[^A-Za-z0-9]/.test(password.value))
const matchesConfirmation = computed(
  () => password.value.length > 0 && password.value === passwordConfirmation.value,
)

const allRulesPass = computed(
  () =>
    meetsLength.value
    && hasMixedCase.value
    && hasNumber.value
    && hasSymbol.value
    && matchesConfirmation.value,
)

function handleSubmit() {
  emit('submit', {
    email: email.value,
    password: password.value,
    password_confirmation: passwordConfirmation.value,
  })
}
</script>

<template>
  <form class="reset-form" @submit.prevent="handleSubmit">
    <Input
      v-model="email"
      type="email"
      name="email"
      autocomplete="username"
      :label="t('passwordReset.email')"
      :placeholder="t('passwordReset.emailPlaceholder')"
      required
    />
    <p v-if="emailError" class="reset-form__error">{{ emailError }}</p>

    <Input
      v-model="password"
      type="password"
      name="password"
      autocomplete="new-password"
      :label="t('passwordReset.newPassword')"
      :placeholder="t('passwordReset.newPasswordPlaceholder')"
      required
    />
    <PasswordStrengthMeter :password="password" />

    <Input
      v-model="passwordConfirmation"
      type="password"
      name="password_confirmation"
      autocomplete="new-password"
      :label="t('passwordReset.confirmPassword')"
      :placeholder="t('passwordReset.confirmPasswordPlaceholder')"
      required
    />
    <p v-if="passwordError" class="reset-form__error">{{ passwordError }}</p>

    <ul class="reset-form__rules" :aria-label="t('passwordReset.rulesTitle')">
      <li :class="meetsLength ? 'reset-form__rule--met' : ''">
        {{ t('passwordReset.rule.length') }}
      </li>
      <li :class="hasMixedCase ? 'reset-form__rule--met' : ''">
        {{ t('passwordReset.rule.case') }}
      </li>
      <li :class="hasNumber ? 'reset-form__rule--met' : ''">
        {{ t('passwordReset.rule.number') }}
      </li>
      <li :class="hasSymbol ? 'reset-form__rule--met' : ''">
        {{ t('passwordReset.rule.symbol') }}
      </li>
      <li :class="matchesConfirmation ? 'reset-form__rule--met' : ''">
        {{ t('passwordReset.rule.match') }}
      </li>
    </ul>

    <Button
      type="submit"
      variant="primary"
      size="md"
      :loading="submitting"
      :disabled="!allRulesPass"
    >
      {{ t('passwordReset.resetSubmit') }}
    </Button>
  </form>
</template>

<style scoped lang="scss">
.reset-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.reset-form__error {
  color: var(--color-danger, #b91c1c);
  font-size: 0.8125rem;
  margin: -0.5rem 0 0;
}

.reset-form__rules {
  list-style: none;
  padding: 0;
  margin: 0.25rem 0 0;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.8125rem;
  color: var(--color-text-muted, rgba(0, 0, 0, 0.6));
}

.reset-form__rules li::before {
  content: '○';
  display: inline-block;
  width: 1.1em;
  text-align: center;
  margin-right: 0.25rem;
}

.reset-form__rule--met {
  color: #15803d;
}

.reset-form__rule--met::before {
  content: '●' !important;
}
</style>
