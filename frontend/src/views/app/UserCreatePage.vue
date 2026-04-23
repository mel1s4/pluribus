<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import Input from '../../atoms/Input.vue'
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'
import { createUser, userApiErrorMessage } from '../../services/usersApi.js'

const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
  username: '',
})
const saveError = ref('')
const saveLoading = ref(false)

async function onSubmit() {
  saveError.value = ''
  saveLoading.value = true
  const body = {
    name: form.name.trim(),
    email: form.email.trim(),
    password: form.password,
  }
  const u = form.username.trim()
  if (u) {
    body.username = u
  }
  const { ok, status, data } = await createUser(body)
  saveLoading.value = false
  if (!ok) {
    saveError.value = userApiErrorMessage(data, status, t('users.createError'))
    return
  }
  const id = data?.user?.id
  if (id != null) {
    await router.replace({ name: 'userEdit', params: { userId: String(id) } })
  }
}

function goBack() {
  router.push({ name: 'users' })
}
</script>

<template>
  <section class="user-create-page">
    <Title tag="h1">{{ t('users.createPageTitle') }}</Title>
    <p class="user-create-page__muted">{{ t('users.createPageIntro') }}</p>

    <div class="user-create-page__toolbar">
      <button type="button" class="user-create-page__btn" @click="goBack">
        {{ t('users.backToList') }}
      </button>
    </div>

    <Card class="user-create-page__panel">
      <form class="user-create-page__form" @submit.prevent="onSubmit">
        <div class="user-create-page__fields">
          <Input
            v-model="form.name"
            name="create-name"
            type="text"
            :label="t('users.fieldName')"
            autocomplete="name"
            required
          />
          <Input
            v-model="form.email"
            name="create-email"
            type="email"
            :label="t('users.fieldEmail')"
            autocomplete="email"
            required
          />
          <Input
            v-model="form.password"
            name="create-password"
            type="password"
            :label="t('users.fieldPassword')"
            autocomplete="new-password"
            required
          />
          <Input
            v-model="form.username"
            name="create-username"
            type="text"
            :label="t('users.fieldUsername')"
            autocomplete="username"
          />
        </div>
        <p v-if="saveError" class="user-create-page__error" role="alert">
          {{ saveError }}
        </p>
        <Button type="submit" variant="primary" :loading="saveLoading">
          {{ t('users.createSubmit') }}
        </Button>
      </form>
    </Card>
  </section>
</template>

<style lang="scss" scoped>
.user-create-page {
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 52rem;
  margin: 0 auto;
}

.user-create-page__muted {
  opacity: 0.8;
  margin: 0;
}

.user-create-page__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.user-create-page__btn {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
}

.user-create-page__panel {
  margin-top: 0.25rem;
}

.user-create-page__form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  align-items: flex-start;
}

.user-create-page__fields {
  display: grid;
  gap: 0.75rem;
  width: 100%;
  max-width: 28rem;
}

.user-create-page__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}
</style>
