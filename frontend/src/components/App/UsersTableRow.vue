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
})

const emit = defineEmits(['delete'])

const typeLabel = computed(() => {
  if (props.user.is_root) {
    return 'ROOT'
  }
  const ty = props.user.user_type
  if (ty === 'admin') return t('users.typeAdmin')
  if (ty === 'developer') return t('users.typeDeveloper')
  if (ty === 'member') return t('users.typeMember')
  return ty ?? '—'
})

const rootLabel = computed(() => (props.user.is_root ? 'ROOT' : null))
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
    <td class="users-table-row__cell">{{ user.email }}</td>
    <td class="users-table-row__cell">{{ user.username || '—' }}</td>
    <td class="users-table-row__cell">{{ typeLabel }}</td>
    <td class="users-table-row__cell">
      <span v-if="rootLabel" class="users-table-row__badge">{{ rootLabel }}</span>
      <span v-else class="users-table-row__dash">—</span>
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

.users-table-row__badge {
  display: inline-block;
  padding: 0.15rem 0.45rem;
  border-radius: 0.35rem;
  font-size: 0.75rem;
  font-weight: 600;
  background: rgba(37, 99, 235, 0.12);
  color: #1d4ed8;
}

.users-table-row__dash {
  color: var(--muted, #6b7280);
}
</style>
