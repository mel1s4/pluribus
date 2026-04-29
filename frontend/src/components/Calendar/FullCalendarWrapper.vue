<script setup>
import { onMounted, ref, watch } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list/index.js'
import interactionPlugin from '@fullcalendar/interaction'

const props = defineProps({
  /** FullCalendar event objects */
  events: { type: Array, default: () => [] },
  /** dayGridMonth | timeGridWeek | timeGridDay | listWeek | listMonth */
  currentView: { type: String, default: 'dayGridMonth' },
})

const emit = defineEmits([
  'event-click',
  'date-click',
  'event-drop',
  'event-resize',
  'dates-set',
])

const fcRef = ref(null)

const calendarOptions = ref({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  initialView: props.currentView,
  headerToolbar: false,
  events: [],
  editable: true,
  selectable: true,
  selectMirror: true,
  dayMaxEvents: true,
  weekends: true,
  nowIndicator: true,
  slotMinTime: '06:00:00',
  slotMaxTime: '23:00:00',
  height: 'auto',
  listDayFormat: { weekday: 'long' },
  listDaySideFormat: { month: 'short', day: 'numeric', year: 'numeric' },
  eventClick(info) {
    emit('event-click', info)
  },
  dateClick(info) {
    emit('date-click', info)
  },
  eventDrop(info) {
    emit('event-drop', info)
  },
  eventResize(info) {
    emit('event-resize', info)
  },
  datesSet(info) {
    emit('dates-set', info)
  },
})

watch(
  () => props.events,
  (ev) => {
    calendarOptions.value.events = Array.isArray(ev) ? [...ev] : []
  },
  { immediate: true, deep: true },
)

watch(
  () => props.currentView,
  (view) => {
    const api = fcRef.value?.getApi?.()
    if (api && view && api.view?.type !== view) {
      api.changeView(view)
    }
  },
)

onMounted(() => {
  calendarOptions.value.initialView = props.currentView
})

function getApi() {
  return fcRef.value?.getApi?.() ?? null
}

defineExpose({ getApi })
</script>

<template>
  <div class="fc-root">
    <FullCalendar ref="fcRef" :options="calendarOptions" />
  </div>
</template>

<style scoped lang="scss">
.fc-root {
  min-height: 32rem;
  --fc-border-color: var(--border, #e2e8f0);
  --fc-button-bg-color: var(--surface-2, #f1f5f9);
  --fc-button-border-color: var(--border, #e2e8f0);
  --fc-button-text-color: var(--text, #0f172a);
  --fc-today-bg-color: rgba(34, 197, 94, 0.06);
  --fc-now-indicator-color: #ef4444;
}
.fc-root :deep(.fc) {
  font-family: inherit;
}
.fc-root :deep(.fc-toolbar-title) {
  font-size: 1rem;
}
.fc-root :deep(.fc-event) {
  cursor: pointer;
  border-radius: 0.35rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  border-width: 0;
  padding-inline: 0.2rem;
}
.fc-root :deep(.fc-daygrid-event) {
  border-radius: 1rem;
  margin-top: 1px;
}
.fc-root :deep(.fc-timegrid-event) {
  border-radius: 0.5rem;
}
.fc-root :deep(.fc-list-event) {
  border-radius: 0.35rem;
}
.fc-root :deep(.fc-daygrid-day-frame) {
  min-height: 5rem;
}
/* Post = circle marker, task = square */
.fc-root :deep(.fc-event-entity-post .fc-event-title::before) {
  content: '';
  display: inline-block;
  width: 0.35rem;
  height: 0.35rem;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.85;
  margin-right: 0.35rem;
  vertical-align: 0.05em;
}
.fc-root :deep(.fc-event-entity-task .fc-event-title::before) {
  content: '';
  display: inline-block;
  width: 0.35rem;
  height: 0.35rem;
  border-radius: 2px;
  background: currentColor;
  opacity: 0.85;
  margin-right: 0.35rem;
  vertical-align: 0.05em;
}
.fc-root :deep(.fc-event-entity-post .fc-list-event-title::before) {
  content: '';
  display: inline-block;
  width: 0.35rem;
  height: 0.35rem;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.85;
  margin-right: 0.35rem;
  vertical-align: 0.05em;
}
.fc-root :deep(.fc-event-entity-task .fc-list-event-title::before) {
  content: '';
  display: inline-block;
  width: 0.35rem;
  height: 0.35rem;
  border-radius: 2px;
  background: currentColor;
  opacity: 0.85;
  margin-right: 0.35rem;
  vertical-align: 0.05em;
}
.fc-root :deep(.fc-list-event-title) {
  font-weight: 600;
}
.fc-root :deep(.fc-timegrid-now-indicator-line) {
  border-width: 2px 0 0;
}
.fc-root :deep(.fc-timegrid-now-indicator-arrow) {
  border-width: 5px 0 5px 6px;
  border-top-color: transparent;
  border-bottom-color: transparent;
  margin-top: -5px;
}
@media (max-width: 767px) {
  .fc-root {
    min-height: 18rem;
  }
}
</style>
