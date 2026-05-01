<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'
import { t } from '../../i18n/i18n'

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
  showActions: {
    type: Boolean,
    default: false,
  },
  showEdit: {
    type: Boolean,
    default: false,
  },
  editTo: {
    type: Object,
    default: null,
  },
  editDisabled: {
    type: Boolean,
    default: false,
  },
  editLabel: {
    type: String,
    default: '',
  },
  showDelete: {
    type: Boolean,
    default: false,
  },
  deleteDisabled: {
    type: Boolean,
    default: false,
  },
  deleteLoading: {
    type: Boolean,
    default: false,
  },
  deleteLabel: {
    type: String,
    default: '',
  },
  memberProfileTo: {
    type: Object,
    default: null,
  },
  showPersonify: {
    type: Boolean,
    default: false,
  },
  personifyTo: {
    type: Object,
    default: null,
  },
  personifyDisabled: {
    type: Boolean,
    default: false,
  },
  personifyLabel: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['delete'])

const firstPhone = computed(() => {
  const phones = props.user.phone_numbers
  if (Array.isArray(phones) && phones.length && typeof phones[0] === 'string' && phones[0].trim()) {
    return phones[0].trim()
  }
  return null
})

const firstEmail = computed(() => {
  const emails = props.user.contact_emails
  if (Array.isArray(emails) && emails.length && typeof emails[0] === 'string' && emails[0].trim()) {
    return emails[0].trim()
  }
  if (props.user.email && String(props.user.email).trim()) {
    return String(props.user.email).trim()
  }
  return null
})

const firstLink = computed(() => {
  const links = props.user.external_links
  if (!Array.isArray(links) || !links.length) {
    return null
  }
  const row = links[0]
  if (!row || typeof row !== 'object') {
    return null
  }
  const url = String(row.url ?? '').trim()
  if (!url) {
    return null
  }
  return { title: String(row.title ?? '').trim() || url, url }
})

const initials = computed(() => {
  const name = props.user.name
  if (typeof name !== 'string' || !name.trim()) {
    return '?'
  }
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase()
  }
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
})
</script>

<template>
  <article class="user-card">
    <div class="user-card__header">
      <div class="user-card__avatarWrap">
        <img
          v-if="user.avatar_url"
          class="user-card__avatarImg"
          :src="user.avatar_url"
          alt=""
        >
        <div v-else class="user-card__avatarFallback" aria-hidden="true">
          {{ initials }}
        </div>
      </div>
      <h3 class="user-card__name">
        <RouterLink
          v-if="memberProfileTo"
          class="user-card__nameLink"
          :to="memberProfileTo"
        >
          {{ user.name }}
        </RouterLink>
        <span v-else>{{ user.name }}</span>
      </h3>
    </div>

    <ul class="user-card__details">
      <li class="user-card__row">
        <div class="user-card__lineLabel">
          <span class="user-card__icon" aria-hidden="true">📞</span>
          <span class="user-card__label">{{ t('users.cardPhone') }}</span>
        </div>
        <div class="user-card__lineValue">{{ firstPhone || '—' }}</div>
      </li>
      <li class="user-card__row">
        <div class="user-card__lineLabel">
          <span class="user-card__icon" aria-hidden="true">✉️</span>
          <span class="user-card__label">{{ t('users.cardEmail') }}</span>
        </div>
        <div class="user-card__lineValue user-card__lineValue--wrap">{{ firstEmail || '—' }}</div>
      </li>
      <li class="user-card__row">
        <div class="user-card__lineLabel">
          <span class="user-card__icon" aria-hidden="true">🔗</span>
          <span class="user-card__label">{{ t('users.cardLink') }}</span>
        </div>
        <div v-if="firstLink" class="user-card__lineValue">
          <a
            class="user-card__external"
            :href="firstLink.url"
            rel="noopener noreferrer"
            target="_blank"
          >{{ firstLink.title }}</a>
        </div>
        <div v-else class="user-card__lineValue">—</div>
      </li>
    </ul>

    <div v-if="showActions" class="user-card__actions">
      <RouterLink
        v-if="showPersonify && personifyTo && !personifyDisabled"
        class="user-card__personifyBtn"
        :to="personifyTo"
      >
        {{ personifyLabel }}
      </RouterLink>
      <span
        v-else-if="showPersonify && personifyDisabled"
        class="user-card__personifyBtn user-card__personifyBtn--disabled"
        aria-disabled="true"
      >
        {{ personifyLabel }}
      </span>
      <RouterLink
        v-if="showEdit && editTo && !editDisabled"
        class="user-card__editBtn"
        :to="editTo"
      >
        {{ editLabel }}
      </RouterLink>
      <span
        v-else-if="showEdit"
        class="user-card__editBtn user-card__editBtn--disabled"
        aria-disabled="true"
      >
        {{ editLabel }}
      </span>
      <Button
        v-if="showDelete"
        type="button"
        variant="danger"
        size="sm"
        :disabled="deleteDisabled"
        :loading="deleteLoading"
        @click="emit('delete', user)"
      >
        {{ deleteLabel }}
      </Button>
    </div>
  </article>
</template>

<style lang="scss" scoped>
.user-card {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.6rem;
  background: var(--bg);
  min-height: 100%;
}

.user-card__header {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.5rem;
}

.user-card__avatarWrap {
  width: 4rem;
  height: 4rem;
  border-radius: 999px;
  overflow: hidden;
  flex-shrink: 0;
  border: 1px solid var(--border);
}

.user-card__avatarImg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.user-card__avatarFallback {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  font-weight: 600;
  background: color-mix(in srgb, var(--link, #1d4ed8) 15%, var(--bg));
  color: inherit;
}

.user-card__name {
  margin: 0;
  font-size: 1.05rem;
  line-height: 1.3;
  word-break: break-word;
}

.user-card__nameLink {
  color: inherit;
  text-decoration: none;
  font-weight: 600;
}
.user-card__nameLink:hover {
  text-decoration: underline;
  color: #1d4ed8;
}

.user-card__details {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  font-size: 0.88rem;
}

.user-card__row {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.user-card__lineLabel {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.user-card__icon {
  line-height: 1.2;
  flex-shrink: 0;
}
.user-card__label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--muted, #6b7280);
}
.user-card__lineValue {
  padding-left: 1.4rem;
  min-width: 0;
  word-break: break-word;
  color: var(--text, inherit);
}
.user-card__lineValue--wrap {
  overflow-wrap: anywhere;
}

.user-card__external {
  color: #1d4ed8;
  text-decoration: none;
  word-break: break-all;
}
.user-card__external:hover {
  text-decoration: underline;
}

.user-card__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin-top: auto;
  padding-top: 0.25rem;
  border-top: 1px solid var(--border);
}

.user-card__editBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.4rem 0.75rem;
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  background-color: #e5e7eb;
  color: #111827;
  text-decoration: none;
  transition: background-color 140ms ease, border-color 140ms ease;
}
.user-card__editBtn:hover:not(.user-card__editBtn--disabled) {
  background-color: #d1d5db;
  border-color: #d1d5db;
}
.user-card__personifyBtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.35rem 0.65rem;
  border-radius: 0.45rem;
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  border: 1px solid var(--border);
  background: color-mix(in srgb, #b45309 12%, var(--bg));
  color: inherit;
}

.user-card__personifyBtn--disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.user-card__editBtn--disabled {
  cursor: not-allowed;
  opacity: 0.55;
  border-color: var(--border, #e5e7eb);
  background-color: color-mix(in srgb, var(--border, #e5e7eb) 80%, var(--bg, #fff));
  color: var(--muted, #6b7280);
}
</style>
