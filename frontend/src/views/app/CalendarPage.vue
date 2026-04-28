<script setup>
import { computed, onMounted, ref } from 'vue'
import Title from '../../atoms/Title.vue'
import { t } from '../../i18n/i18n'
import { createCalendar, fetchCalendarDiscovery, fetchCalendars } from '../../services/contentApi'

const calendars = ref([])
const events = ref([])
const loading = ref(false)
const error = ref('')
const name = ref('')
const visibility = ref('private')

const datedEvents = computed(() => {
  return events.value
    .slice()
    .sort((a, b) => String(a.start_at || '').localeCompare(String(b.start_at || '')))
})

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

async function load() {
  loading.value = true
  error.value = ''
  const [calRes, discoveryRes] = await Promise.all([fetchCalendars(), fetchCalendarDiscovery()])
  loading.value = false
  if (!calRes.ok || !discoveryRes.ok) {
    error.value = `HTTP ${!calRes.ok ? calRes.status : discoveryRes.status}`
    return
  }
  calendars.value = unwrapList(calRes.data)
  events.value = unwrapList(discoveryRes.data?.events)
}

async function onCreate() {
  if (!name.value.trim()) return
  const res = await createCalendar({
    name: name.value.trim(),
    visibility_scope: visibility.value,
  })
  if (res.ok) {
    name.value = ''
    await load()
  } else {
    error.value = `HTTP ${res.status}`
  }
}

onMounted(load)
</script>

<template>
  <section class="page page--calendar">
    <header class="page__header">
      <Title tag="h1">{{ t('calendar.title') }}</Title>
      <p class="page__muted">{{ t('calendar.intro') }}</p>
    </header>

    <div class="calendar-create">
      <input v-model="name" :placeholder="t('calendar.namePlaceholder')" />
      <select v-model="visibility">
        <option value="private">{{ t('calendar.private') }}</option>
        <option value="community">{{ t('calendar.community') }}</option>
        <option value="group">{{ t('calendar.group') }}</option>
      </select>
      <button class="btn btn--primary" @click="onCreate">{{ t('calendar.create') }}</button>
    </div>

    <p v-if="loading" class="page__muted">{{ t('calendar.loading') }}</p>
    <p v-if="error" class="page__error">{{ t('calendar.error').replace('{status}', error.replace('HTTP ', '')) }}</p>

    <div class="calendar-grid">
      <article class="calendar-card">
        <h3>{{ t('calendar.calendars') }}</h3>
        <ul>
          <li v-for="calendar in calendars" :key="calendar.id">
            <span class="dot" :style="{ backgroundColor: calendar.color || '#22c55e' }" />
            {{ calendar.name }}
          </li>
        </ul>
      </article>
      <article class="calendar-card">
        <h3>{{ t('calendar.events') }}</h3>
        <ul>
          <li v-for="event in datedEvents" :key="event.id">
            <strong>{{ event.title }}</strong>
            <span class="page__muted">{{ event.start_at || t('calendar.noDate') }}</span>
          </li>
        </ul>
      </article>
    </div>
  </section>
</template>

<style scoped lang="scss">
.page--calendar { padding: 1rem; display: grid; gap: 0.8rem; }
.calendar-create { display: grid; grid-template-columns: 1fr 180px auto; gap: 0.5rem; }
.calendar-create input, .calendar-create select { padding: 0.5rem; border: 1px solid var(--border); border-radius: 0.5rem; }
.calendar-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.8rem; }
.calendar-card { border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.6rem; }
.calendar-card ul { margin: 0; padding-left: 1rem; display: grid; gap: 0.25rem; }
.dot { width: 0.7rem; height: 0.7rem; border-radius: 50%; display: inline-block; margin-right: 0.35rem; }
.page__muted { margin: 0; opacity: 0.8; }
.page__error { color: #b00020; margin: 0; }
@media (max-width: 960px) { .calendar-grid { grid-template-columns: 1fr; } }
</style>

