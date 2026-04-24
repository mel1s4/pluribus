<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import Button from '../../atoms/Button.vue'

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
})

const emit = defineEmits(['delete'])

const displayContact = computed(() => {
  const emails = props.user.contact_emails
  if (Array.isArray(emails) && emails.length && typeof emails[0] === 'string' && emails[0].trim()) {
    return emails[0].trim()
  }
  const phones = props.user.phone_numbers
  if (Array.isArray(phones) && phones.length && typeof phones[0] === 'string' && phones[0].trim()) {
    return phones[0].trim()
  }
  return '—'
})
</script>

<template>
  <tr class="users-table-row">
    <td class="users-table-row__cell users-table-row__cell--name">
      <RouterLink
        v-if="memberProfileTo"
        class="users-table-row__profileLink"
        :to="memberProfileTo"
      >
        {{ user.name }}
      </RouterLink>
      <span v-else>{{ user.name }}</span>
    </td>
    <td class="users-table-row__cell users-table-row__cell--contact">
      <RouterLink
        v-if="memberProfileTo"
        class="users-table-row__profileLink users-table-row__profileLink--contact"
        :class="{ 'users-table-row__dash': displayContact === '—' }"
        :to="memberProfileTo"
      >
        {{ displayContact }}
      </RouterLink>
      <span v-else :class="{ 'users-table-row__dash': displayContact === '—' }">{{ displayContact }}</span>
    </td>
    <td v-if="showActions" class="users-table-row__cell users-table-row__cell--actions">
      <div class="users-table-row__actions">
        <RouterLink
          v-if="showEdit && editTo && !editDisabled"
          class="users-table-row__editLink"
          :to="editTo"
        >
          {{ editLabel }}
        </RouterLink>
        <span
          v-else-if="showEdit"
          class="users-table-row__editMuted"
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
    </td>
  </tr>
</template>

<style lang="scss" scoped>
.users-table-row__cell {
  padding: 0.65rem 0.75rem;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}

.users-table-row__cell--name {
  font-weight: 600;
}

.users-table-row__profileLink {
  color: inherit;
  text-decoration: none;
  font-weight: 600;
}

.users-table-row__profileLink--contact {
  font-weight: 400;
}

.users-table-row__profileLink--contact.users-table-row__dash {
  color: var(--muted, #6b7280);
}

.users-table-row__profileLink:hover {
  text-decoration: underline;
  color: #1d4ed8;
}

.users-table-row__cell--actions {
  text-align: right;
  white-space: nowrap;
}

.users-table-row__actions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.users-table-row__editLink {
  font-size: 0.85rem;
  font-weight: 600;
  color: #1d4ed8;
  text-decoration: none;
}

.users-table-row__editLink:hover {
  text-decoration: underline;
}

.users-table-row__editMuted {
  font-size: 0.85rem;
  color: var(--muted, #9ca3af);
}

.users-table-row__dash {
  color: var(--muted, #6b7280);
}
</style>
