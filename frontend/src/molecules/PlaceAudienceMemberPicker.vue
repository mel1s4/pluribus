<script setup>
import { computed, ref } from 'vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  members: {
    type: Array,
    default: () => [],
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const query = ref('')

const selectedSet = computed(() => new Set(props.modelValue.map(Number)))

const filteredMembers = computed(() => {
  const q = query.value.trim().toLowerCase()
  const list = Array.isArray(props.members) ? props.members : []
  if (!q) return list
  return list.filter((m) => {
    const name = String(m.name ?? '').toLowerCase()
    const un = String(m.username ?? '').toLowerCase()
    return name.includes(q) || un.includes(q)
  })
})

function initials(name) {
  if (typeof name !== 'string' || !name.trim()) {
    return '?'
  }
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase()
  }
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

function setId(id, checked) {
  if (props.disabled) return
  const n = Number(id)
  const next = new Set(selectedSet.value)
  if (checked) {
    next.add(n)
  } else {
    next.delete(n)
  }
  emit('update:modelValue', [...next].sort((a, b) => a - b))
}
</script>

<template>
  <div class="place-audience-member-picker">
    <input
      v-model="query"
      class="place-audience-member-picker__search"
      type="search"
      :disabled="disabled"
      autocomplete="off"
      :placeholder="t('myPlaces.audienceMemberSearchPlaceholder')"
    />
    <ul class="place-audience-member-picker__grid" role="list">
      <li
        v-for="m in filteredMembers"
        :key="m.id"
        class="place-audience-member-picker__cell"
      >
        <label class="place-audience-member-picker__label">
          <input
            class="place-audience-member-picker__check"
            type="checkbox"
            :checked="selectedSet.has(Number(m.id))"
            :disabled="disabled"
            @change="setId(m.id, ($event.target).checked)"
          />
          <span class="place-audience-member-picker__card">
            <span class="place-audience-member-picker__avatar-wrap">
              <img
                v-if="m.avatar_url"
                :src="m.avatar_url"
                alt=""
                class="place-audience-member-picker__avatar-img"
              />
              <span
                v-else
                class="place-audience-member-picker__avatar-fallback"
                aria-hidden="true"
              >{{ initials(m.name) }}</span>
            </span>
            <span class="place-audience-member-picker__name">{{ m.name }}</span>
          </span>
        </label>
      </li>
    </ul>
    <p v-if="!filteredMembers.length" class="place-audience-member-picker__empty">
      {{ t('myPlaces.audienceMemberSearchEmpty') }}
    </p>
  </div>
</template>

<style lang="scss" scoped>
.place-audience-member-picker {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.place-audience-member-picker__search {
  max-width: 20rem;
  width: 100%;
  box-sizing: border-box;
  padding: 0.35rem 0.5rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  font: inherit;
}

.place-audience-member-picker__grid {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
  gap: 0.5rem;
  max-height: min(50vh, 22rem);
  overflow: auto;
}

.place-audience-member-picker__label {
  display: block;
  cursor: pointer;
  margin: 0;
}

.place-audience-member-picker__check {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.place-audience-member-picker__card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  padding: 0.5rem;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--bg);
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
}

.place-audience-member-picker__label:hover .place-audience-member-picker__card {
  border-color: var(--accent, #3b5bdb);
}

.place-audience-member-picker__check:focus-visible + .place-audience-member-picker__card {
  outline: 2px solid var(--accent, #3b5bdb);
  outline-offset: 2px;
}

.place-audience-member-picker__check:checked + .place-audience-member-picker__card {
  border-color: var(--accent, #3b5bdb);
  box-shadow: 0 0 0 1px var(--accent, #3b5bdb);
}

.place-audience-member-picker__avatar-wrap {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--btn-bg, #e2e8f0);
}

.place-audience-member-picker__avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.place-audience-member-picker__avatar-fallback {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-muted, #64748b);
}

.place-audience-member-picker__name {
  font-size: 0.8rem;
  text-align: center;
  line-height: 1.2;
  word-break: break-word;
}

.place-audience-member-picker__empty {
  margin: 0;
  font-size: 0.85rem;
  opacity: 0.75;
}
</style>
