<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import Title from '../../atoms/Title.vue'
import PageToolbarTitle from '../../components/App/PageToolbarTitle.vue'
import FullCalendarWrapper from '../../components/Calendar/FullCalendarWrapper.vue'
import CalendarFilter from '../../components/Calendar/CalendarFilter.vue'
import EventDialog from '../../components/Calendar/EventDialog.vue'
import CreateCalendarDialog from '../../components/Calendar/CreateCalendarDialog.vue'
import { t } from '../../i18n/i18n'
import {
  deleteCalendar,
  fetchCalendarDiscovery,
  fetchCalendars,
  rescheduleCalendarEvent,
} from '../../services/contentApi'
import {
  buildCalendarLookup,
  discoveryRowsToFullCalendarEvents,
} from '../../utils/calendarEvents.js'
import { expandDiscoveryRowsForRange } from '../../utils/calendarRecurrence.js'

const MOBILE_MAX = 767

const calendars = ref([])
const rawEvents = ref([])
const loading = ref(false)
const error = ref('')
const rangeStart = ref(new Date())
const rangeEnd = ref(new Date())
const visibleCalendarIds = ref([])
const showPosts = ref(true)
const showTasks = ref(true)

const isMobile = ref(false)
let mql = null

function readIsMobile() {
  if (typeof window === 'undefined') return false
  return window.matchMedia(`(max-width: ${MOBILE_MAX}px)`).matches
}

function initialView() {
  return readIsMobile() ? 'listWeek' : 'dayGridMonth'
}

const currentView = ref('dayGridMonth')

const eventDialogOpen = ref(false)
const eventDialogSlot = ref(null)
const eventDialogEditing = ref(null)

const filterSheetOpen = ref(false)
const calendarFormOpen = ref(false)
const calendarFormEdit = ref(null)

const fcRef = ref(null)
const viewTitle = ref('')
const todayDayNum = ref(new Date().getDate())

let loadTimer = null

function unwrapList(payload) {
  if (!payload || typeof payload !== 'object') return []
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload.data)) return payload.data
  return []
}

const calendarById = computed(() => buildCalendarLookup(calendars.value))

const fcEvents = computed(() => {
  const rs = rangeStart.value
  const re = rangeEnd.value
  const filtered = rawEvents.value.filter((row) => {
    if (row.calendar_id != null && !visibleCalendarIds.value.includes(row.calendar_id)) {
      return false
    }
    if (row.entity_type === 'task' && !showTasks.value) return false
    if (row.entity_type === 'post' && !showPosts.value) return false
    return true
  })
  const expanded = expandDiscoveryRowsForRange(filtered, rs, re)
  return discoveryRowsToFullCalendarEvents(expanded, calendarById.value)
})

watch(
  calendars,
  (cals) => {
    if (!Array.isArray(cals) || cals.length === 0) return
    if (visibleCalendarIds.value.length === 0) {
      visibleCalendarIds.value = cals.map((c) => c.id)
    }
  },
  { immediate: true },
)

function onMq() {
  isMobile.value = readIsMobile()
}

async function loadDiscoveryForRange(start, end) {
  loading.value = true
  error.value = ''
  const startIso = start instanceof Date ? start.toISOString() : String(start)
  const endIso = end instanceof Date ? end.toISOString() : String(end)
  const discRes = await fetchCalendarDiscovery({
    start: startIso,
    end: endIso,
    skipCache: true,
  })
  loading.value = false
  if (!discRes.ok) {
    error.value = `HTTP ${discRes.status}`
    return
  }
  const data = discRes.data || {}
  rawEvents.value = unwrapList(data.events)
}

function scheduleLoadDiscovery(start, end) {
  rangeStart.value = start instanceof Date ? start : new Date(start)
  rangeEnd.value = end instanceof Date ? end : new Date(end)
  clearTimeout(loadTimer)
  loadTimer = setTimeout(() => {
    loadDiscoveryForRange(rangeStart.value, rangeEnd.value)
  }, 200)
}

async function loadCalendars() {
  const calRes = await fetchCalendars()
  if (!calRes.ok) {
    error.value = `HTTP ${calRes.status}`
    return
  }
  calendars.value = unwrapList(calRes.data)
}

async function load() {
  await loadCalendars()
  await loadDiscoveryForRange(rangeStart.value, rangeEnd.value)
}

function onDatesSet(info) {
  viewTitle.value = info.view?.title || ''
  todayDayNum.value = new Date().getDate()
  scheduleLoadDiscovery(info.start, info.end)
}

function onEventClick(info) {
  const ev = info.event
  eventDialogEditing.value = {
    title: ev.title,
    start: ev.start,
    end: ev.end,
    allDay: ev.allDay,
    extendedProps: { ...ev.extendedProps },
  }
  eventDialogSlot.value = null
  eventDialogOpen.value = true
  filterSheetOpen.value = false
}

function onDateClick(info) {
  eventDialogEditing.value = null
  eventDialogSlot.value = {
    date: info.date,
    allDay: info.allDay,
  }
  eventDialogOpen.value = true
  filterSheetOpen.value = false
}

function openNewEvent() {
  eventDialogEditing.value = null
  eventDialogSlot.value = null
  eventDialogOpen.value = true
  filterSheetOpen.value = false
}

function fcEventToReschedulePayload(info) {
  const ev = info.event
  return {
    start_at: ev.start ? ev.start.toISOString() : null,
    end_at: ev.end ? ev.end.toISOString() : null,
    all_day: ev.allDay,
  }
}

async function onEventDrop(info) {
  const ext = info.event.extendedProps
  const res = await rescheduleCalendarEvent(ext.entityType, ext.entityId, fcEventToReschedulePayload(info))
  if (!res.ok) {
    info.revert()
    error.value = `HTTP ${res.status}`
    return
  }
  await loadDiscoveryForRange(rangeStart.value, rangeEnd.value)
}

async function onEventResize(info) {
  await onEventDrop(info)
}

function openCreateCalendar() {
  calendarFormEdit.value = null
  calendarFormOpen.value = true
}

function openEditCalendar(cal) {
  calendarFormEdit.value = cal
  calendarFormOpen.value = true
}

async function onCalendarFormSaved(payload = {}) {
  if (payload?.created?.id != null) {
    visibleCalendarIds.value = [...new Set([...visibleCalendarIds.value, payload.created.id])]
  }
  await loadCalendars()
  await loadDiscoveryForRange(rangeStart.value, rangeEnd.value)
}

async function onDeleteCalendar(cal) {
  if (!cal?.id) return
  const res = await deleteCalendar(cal.id)
  if (res.ok) {
    visibleCalendarIds.value = visibleCalendarIds.value.filter((id) => id !== cal.id)
    await loadCalendars()
    await loadDiscoveryForRange(rangeStart.value, rangeEnd.value)
  } else {
    error.value = `HTTP ${res.status}`
  }
}

function goPrev() {
  fcRef.value?.getApi?.()?.prev?.()
}

function goNext() {
  fcRef.value?.getApi?.()?.next?.()
}

function goToday() {
  fcRef.value?.getApi?.()?.today?.()
}

function setView(view) {
  currentView.value = view
  fcRef.value?.getApi?.()?.changeView?.(view)
}

function onEventDialogSaved() {
  loadDiscoveryForRange(rangeStart.value, rangeEnd.value)
}

function closeFilterSheet() {
  filterSheetOpen.value = false
}

onMounted(() => {
  isMobile.value = readIsMobile()
  currentView.value = initialView()
  mql = window.matchMedia?.(`(max-width: ${MOBILE_MAX}px)`)
  if (mql?.addEventListener) mql.addEventListener('change', onMq)
  else if (mql?.addListener) mql.addListener(onMq)

  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1)
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59)
  rangeStart.value = start
  rangeEnd.value = end
  load()
})

onUnmounted(() => {
  if (mql?.removeEventListener) mql.removeEventListener('change', onMq)
  else if (mql?.removeListener) mql.removeListener(onMq)
})
</script>

<template>
  <section class="page page--calendar">
    <header class="page__header">
      <PageToolbarTitle route-key="calendar">
        <Title tag="h1">{{ t('calendar.title') }}</Title>
      </PageToolbarTitle>
      <p class="page__muted">{{ t('calendar.intro') }}</p>
    </header>

    <p v-if="loading" class="page__muted page__loading">{{ t('calendar.loading') }}</p>
    <p v-if="error" class="page__error">{{ t('calendar.error').replace('{status}', error.replace('HTTP ', '')) }}</p>

    <div class="calendar-shell" :class="{ 'calendar-shell--mobile': isMobile }">
      <!-- Desktop side panel -->
      <aside v-if="!isMobile" class="calendar-side">
        <CalendarFilter
          variant="panel"
          :calendars="calendars"
          :visible-calendar-ids="visibleCalendarIds"
          :show-posts="showPosts"
          :show-tasks="showTasks"
          @update:visible-calendar-ids="visibleCalendarIds = $event"
          @update:show-posts="showPosts = $event"
          @update:show-tasks="showTasks = $event"
          @edit-calendar="openEditCalendar"
          @delete-calendar="onDeleteCalendar"
          @add-calendar="openCreateCalendar"
        />
      </aside>

      <div class="calendar-main">
        <div class="calendar-toolbar">
          <div class="calendar-toolbar__primary">
            <button
              v-if="isMobile"
              type="button"
              class="calendar-toolbar__filter btn btn--ghost"
              :aria-label="t('calendar.filters')"
              :title="t('calendar.filters')"
              @click="filterSheetOpen = true"
            >
              <svg class="calendar-toolbar__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 6h16M7 12h10M10 18h4" stroke-linecap="round" />
              </svg>
            </button>

            <div class="calendar-toolbar__nav">
              <button type="button" class="btn btn--ghost calendar-toolbar__chev" :aria-label="t('calendar.prev')" @click="goPrev">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
              <button type="button" class="calendar-toolbar__today" @click="goToday">
                <span class="calendar-toolbar__today-num">{{ todayDayNum }}</span>
                <span class="calendar-toolbar__today-label">{{ t('calendar.today') }}</span>
              </button>
              <button type="button" class="btn btn--ghost calendar-toolbar__chev" :aria-label="t('calendar.next')" @click="goNext">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
            </div>

            <span class="calendar-toolbar__title">{{ viewTitle }}</span>
          </div>

          <div class="calendar-toolbar__views" :class="{ 'calendar-toolbar__views--mobile': isMobile }">
            <template v-if="isMobile">
              <button
                type="button"
                class="calendar-toolbar__seg"
                :class="{ 'calendar-toolbar__seg--on': currentView === 'timeGridDay' }"
                :title="t('calendar.viewDay')"
                @click="setView('timeGridDay')"
              >
                <span class="calendar-toolbar__seg-text">{{ t('calendar.viewDay') }}</span>
                <span class="calendar-toolbar__seg-icon" aria-hidden="true">D</span>
              </button>
              <button
                type="button"
                class="calendar-toolbar__seg"
                :class="{ 'calendar-toolbar__seg--on': currentView === 'timeGridWeek' }"
                :title="t('calendar.viewWeek')"
                @click="setView('timeGridWeek')"
              >
                <span class="calendar-toolbar__seg-text">{{ t('calendar.viewWeek') }}</span>
                <span class="calendar-toolbar__seg-icon" aria-hidden="true">W</span>
              </button>
              <button
                type="button"
                class="calendar-toolbar__seg"
                :class="{ 'calendar-toolbar__seg--on': currentView === 'listWeek' }"
                :title="t('calendar.viewAgenda')"
                @click="setView('listWeek')"
              >
                <span class="calendar-toolbar__seg-text">{{ t('calendar.viewAgenda') }}</span>
                <span class="calendar-toolbar__seg-icon" aria-hidden="true">≡</span>
              </button>
            </template>
            <template v-else>
              <button
                type="button"
                class="btn"
                :class="{ 'btn--primary': currentView === 'dayGridMonth' }"
                @click="setView('dayGridMonth')"
              >
                {{ t('calendar.viewMonth') }}
              </button>
              <button
                type="button"
                class="btn"
                :class="{ 'btn--primary': currentView === 'timeGridWeek' }"
                @click="setView('timeGridWeek')"
              >
                {{ t('calendar.viewWeek') }}
              </button>
              <button
                type="button"
                class="btn"
                :class="{ 'btn--primary': currentView === 'timeGridDay' }"
                @click="setView('timeGridDay')"
              >
                {{ t('calendar.viewDay') }}
              </button>
              <button
                type="button"
                class="btn"
                :class="{ 'btn--primary': currentView === 'listWeek' }"
                @click="setView('listWeek')"
              >
                {{ t('calendar.viewAgenda') }}
              </button>
            </template>
          </div>
        </div>

        <div class="calendar-grid-wrap">
          <FullCalendarWrapper
            ref="fcRef"
            :events="fcEvents"
            :current-view="currentView"
            @dates-set="onDatesSet"
            @event-click="onEventClick"
            @date-click="onDateClick"
            @event-drop="onEventDrop"
            @event-resize="onEventResize"
          />
        </div>
      </div>
    </div>

    <!-- Mobile FAB -->
    <button
      v-if="isMobile"
      type="button"
      class="calendar-fab"
      :aria-label="t('calendar.createEvent')"
      @click="openNewEvent"
    >
      +
    </button>

    <!-- Mobile filter bottom sheet -->
    <Teleport to="body">
      <Transition name="cal-sheet">
        <div v-if="isMobile && filterSheetOpen" class="cal-sheet-backdrop" @click.self="closeFilterSheet">
          <div class="cal-sheet-panel" role="dialog" aria-modal="true" :aria-label="t('calendar.filters')">
            <div class="cal-sheet-panel__head">
              <span class="cal-sheet-panel__title">{{ t('calendar.filters') }}</span>
              <button type="button" class="cal-sheet-panel__close" :aria-label="t('calendar.close')" @click="closeFilterSheet">
                ×
              </button>
            </div>
            <CalendarFilter
              variant="sheet"
              :calendars="calendars"
              :visible-calendar-ids="visibleCalendarIds"
              :show-posts="showPosts"
              :show-tasks="showTasks"
              @update:visible-calendar-ids="visibleCalendarIds = $event"
              @update:show-posts="showPosts = $event"
              @update:show-tasks="showTasks = $event"
              @edit-calendar="
                (c) => {
                  openEditCalendar(c)
                  closeFilterSheet()
                }
              "
              @delete-calendar="onDeleteCalendar"
              @add-calendar="
                () => {
                  openCreateCalendar()
                  closeFilterSheet()
                }
              "
            />
          </div>
        </div>
      </Transition>
    </Teleport>

    <CreateCalendarDialog
      v-model:open="calendarFormOpen"
      :calendar="calendarFormEdit"
      @saved="onCalendarFormSaved"
    />

    <EventDialog
      v-model:open="eventDialogOpen"
      :calendars="calendars"
      :initial-slot="eventDialogSlot"
      :editing="eventDialogEditing"
      @saved="onEventDialogSaved"
    />
  </section>
</template>

<style scoped lang="scss">
$bp-md: 768px;

.page--calendar {
  padding: 1rem;
  display: grid;
  gap: 0.75rem;
  padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0));
}
.page__header {
  min-width: 0;
}
.page__muted {
  margin: 0;
  opacity: 0.8;
  font-size: 0.95rem;
}
.page__loading {
  font-size: 0.88rem;
}
.page__error {
  color: #b00020;
  margin: 0;
  font-size: 0.9rem;
}

.calendar-shell {
  display: grid;
  grid-template-columns: minmax(220px, 260px) 1fr;
  gap: 1rem;
  align-items: start;
}
.calendar-shell--mobile {
  grid-template-columns: 1fr;
}
.calendar-side {
  position: sticky;
  top: 0.75rem;
}
.calendar-main {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.calendar-toolbar {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  @media (min-width: $bp-md) {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
  }
}
.calendar-toolbar__primary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.calendar-toolbar__filter {
  padding: 0.45rem;
  border-radius: 0.55rem;
}
.calendar-toolbar__icon {
  width: 1.35rem;
  height: 1.35rem;
  display: block;
}
.calendar-toolbar__nav {
  display: flex;
  align-items: center;
  gap: 0.15rem;
}
.calendar-toolbar__chev {
  padding: 0.35rem;
  border-radius: 0.5rem;
}
.calendar-toolbar__today {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: 1px solid var(--border, #e2e8f0);
  background: var(--surface, #fff);
  border-radius: 0.65rem;
  padding: 0.35rem 0.65rem;
  font: inherit;
  cursor: pointer;
  color: inherit;
  transition: background 0.15s ease, box-shadow 0.15s ease;
  &:hover {
    background: var(--surface-2, #f1f5f9);
  }
}
.calendar-toolbar__today-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.85rem;
  height: 1.85rem;
  border-radius: 0.45rem;
  background: #2563eb;
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
}
.calendar-toolbar__today-label {
  font-size: 0.85rem;
  font-weight: 600;
}
.calendar-toolbar__title {
  font-size: 0.95rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  flex: 1;
  min-width: 0;
  text-align: right;
  @media (min-width: $bp-md) {
    flex: none;
    margin-left: 0.35rem;
  }
}
.calendar-toolbar__views {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
}
.calendar-toolbar__views--mobile {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.25rem;
  width: 100%;
}
.calendar-toolbar__seg {
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 0.55rem;
  padding: 0.45rem 0.35rem;
  font: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  background: var(--surface, #fff);
  color: inherit;
  opacity: 0.85;
  &--on {
    opacity: 1;
    background: var(--surface-2, #f1f5f9);
    box-shadow: inset 0 0 0 2px var(--border, #e2e8f0);
  }
}
.calendar-toolbar__seg-icon {
  display: none;
}
@media (max-width: 479px) {
  .calendar-toolbar__seg-text {
    display: none;
  }
  .calendar-toolbar__seg-icon {
    display: inline;
    font-weight: 800;
    font-size: 0.95rem;
  }
}

.calendar-grid-wrap {
  border: 1px solid var(--border, #e2e8f0);
  border-radius: 0.85rem;
  overflow: hidden;
  background: var(--surface, #fff);
  min-height: 0;
}
.calendar-shell--mobile .calendar-grid-wrap {
  border-radius: 0.75rem;
}

.calendar-fab {
  position: fixed;
  right: calc(1rem + env(safe-area-inset-right, 0));
  bottom: calc(1rem + env(safe-area-inset-bottom, 0));
  z-index: 1200;
  width: 3.35rem;
  height: 3.35rem;
  border-radius: 50%;
  border: none;
  background: #2563eb;
  color: #fff;
  font-size: 1.85rem;
  line-height: 1;
  font-weight: 300;
  cursor: pointer;
  box-shadow: 0 8px 24px rgba(37, 99, 235, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  &:hover {
    transform: scale(1.04);
    box-shadow: 0 10px 28px rgba(37, 99, 235, 0.5);
  }
}

.cal-sheet-enter-active,
.cal-sheet-leave-active {
  transition: opacity 0.2s ease;
}
.cal-sheet-enter-from,
.cal-sheet-leave-to {
  opacity: 0;
}
.cal-sheet-enter-active .cal-sheet-panel,
.cal-sheet-leave-active .cal-sheet-panel {
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.cal-sheet-enter-from .cal-sheet-panel,
.cal-sheet-leave-to .cal-sheet-panel {
  transform: translateY(100%);
}

.cal-sheet-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1400;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: flex-end;
  justify-content: stretch;
}
.cal-sheet-panel {
  width: 100%;
  max-height: min(88dvh, 32rem);
  overflow: auto;
  background: var(--surface, #fff);
  border-radius: 1rem 1rem 0 0;
  box-shadow: 0 -8px 40px rgba(0, 0, 0, 0.12);
}
.cal-sheet-panel__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 0.85rem 0;
  position: sticky;
  top: 0;
  background: var(--surface, #fff);
  z-index: 1;
}
.cal-sheet-panel__title {
  font-weight: 700;
  font-size: 1rem;
}
.cal-sheet-panel__close {
  border: none;
  background: transparent;
  font-size: 1.65rem;
  line-height: 1;
  cursor: pointer;
  opacity: 0.55;
  padding: 0.25rem;
  border-radius: 0.45rem;
  &:hover {
    opacity: 1;
    background: var(--surface-2, #f1f5f9);
  }
}

@media (max-width: 767px) {
  .page--calendar {
    padding-bottom: calc(5rem + env(safe-area-inset-bottom, 0));
  }
}
</style>
