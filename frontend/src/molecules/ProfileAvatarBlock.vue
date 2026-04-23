<script setup>
import { ref } from 'vue'
import Button from '../atoms/Button.vue'

defineProps({
  avatarUrl: {
    type: String,
    default: null,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  uploading: {
    type: Boolean,
    default: false,
  },
  removing: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: '',
  },
  uploadLabel: {
    type: String,
    default: '',
  },
  removeLabel: {
    type: String,
    default: '',
  },
  hint: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['upload', 'remove'])

const fileInputRef = ref(null)

function openPicker() {
  fileInputRef.value?.click()
}

function onFileChange(event) {
  const input = event.target
  const file = input.files?.[0]
  if (file) {
    emit('upload', file)
  }
  input.value = ''
}
</script>

<template>
  <div class="profile-avatar-block">
    <div class="profile-avatar-block__preview">
      <img
        v-if="avatarUrl"
        class="profile-avatar-block__image"
        :src="avatarUrl"
        width="96"
        height="96"
        alt=""
      />
      <div
        v-else
        class="profile-avatar-block__placeholder"
        aria-hidden="true"
      />
    </div>
    <p v-if="hint" class="profile-avatar-block__hint">
      {{ hint }}
    </p>
    <input
      ref="fileInputRef"
      class="profile-avatar-block__file"
      type="file"
      name="profile-avatar"
      accept="image/*"
      :disabled="disabled || uploading || removing"
      @change="onFileChange"
    />
    <div class="profile-avatar-block__actions">
      <Button
        type="button"
        variant="secondary"
        :disabled="disabled || uploading || removing"
        :loading="uploading"
        @click="openPicker"
      >
        {{ uploadLabel }}
      </Button>
      <Button
        v-if="avatarUrl"
        type="button"
        variant="secondary"
        :disabled="disabled || uploading || removing"
        :loading="removing"
        @click="emit('remove')"
      >
        {{ removeLabel }}
      </Button>
    </div>
    <p v-if="error" class="profile-avatar-block__error" role="alert">
      {{ error }}
    </p>
  </div>
</template>

<style lang="scss" scoped>
.profile-avatar-block {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.75rem;
}

.profile-avatar-block__preview {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  overflow: hidden;
  border: 1px solid var(--border);
  background: var(--bg);
}

.profile-avatar-block__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.profile-avatar-block__placeholder {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, var(--border), transparent);
}

.profile-avatar-block__hint {
  margin: 0;
  font-size: 0.85rem;
  color: var(--muted, #6b7280);
  max-width: 28rem;
}

.profile-avatar-block__file {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.profile-avatar-block__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.profile-avatar-block__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}
</style>
