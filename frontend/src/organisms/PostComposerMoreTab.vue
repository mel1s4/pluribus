<script setup>
import { ref } from 'vue'
import { t } from '../i18n/i18n'

const props = defineProps({
  form: { type: Object, required: true },
  calendars: { type: Array, default: () => [] },
})

const advancedRef = ref(null)

function setCalendarId(id) {
  props.form.calendar_id = id === '' ? '' : String(id)
}

function openAdvanced() {
  advancedRef.value?.showModal()
}

function closeAdvanced() {
  advancedRef.value?.close()
}

function onAdvancedBackdrop(e) {
  if (e.target === advancedRef.value) advancedRef.value?.close()
}
</script>

<template>
  <div class="post-composer-more">
    <div class="post-composer-more__section">
      <span class="post-composer-more__label">{{ t('calendar.eventCalendar') }}</span>
      <div class="post-composer-more__chips" role="group" :aria-label="t('calendar.eventCalendar')">
        <button
          type="button"
          class="post-composer-more__chip"
          :class="{ 'is-on': form.calendar_id === '' }"
          @click="setCalendarId('')"
        >
          <span class="post-composer-more__dot post-composer-more__dot--none" />
          {{ t('calendar.noCalendar') }}
        </button>
        <button
          v-for="c in calendars"
          :key="c.id"
          type="button"
          class="post-composer-more__chip"
          :class="{ 'is-on': form.calendar_id === String(c.id) }"
          @click="setCalendarId(String(c.id))"
        >
          <span class="post-composer-more__dot" :style="{ background: c.color || '#64748b' }" />
          {{ c.name }}
        </button>
      </div>
    </div>

    <label class="post-composer-more__field">
      <span class="post-composer-more__fieldLabel">{{ t('posts.composerPlaceIdLabel') }}</span>
      <input
        v-model="form.place_id"
        class="post-composer-more__input"
        type="text"
        inputmode="numeric"
        :placeholder="t('posts.composerPlaceIdPlaceholder')"
      />
    </label>

    <button type="button" class="post-composer-more__advancedBtn" @click="openAdvanced">
      {{ t('posts.composerAdvancedMap') }}
    </button>

    <dialog ref="advancedRef" class="post-composer-more__dialog" @click="onAdvancedBackdrop">
      <div class="post-composer-more__dialogPanel" @click.stop>
        <h2 class="post-composer-more__dialogTitle">{{ t('posts.composerAdvancedMapTitle') }}</h2>
        <label class="post-composer-more__field">
          <span class="post-composer-more__fieldLabel">{{ t('posts.composerInfluenceType') }}</span>
          <select v-model="form.influence_area_type" class="post-composer-more__input">
            <option value="none">{{ t('posts.composerInfluenceNone') }}</option>
            <option value="radius">{{ t('posts.composerInfluenceRadius') }}</option>
            <option value="polygon">{{ t('posts.composerInfluencePolygon') }}</option>
          </select>
        </label>
        <label v-if="form.influence_area_type === 'radius'" class="post-composer-more__field">
          <span class="post-composer-more__fieldLabel">{{ t('posts.composerRadiusMeters') }}</span>
          <input
            v-model="form.influence_radius_meters"
            class="post-composer-more__input"
            type="text"
            inputmode="numeric"
          />
        </label>
        <label class="post-composer-more__field">
          <span class="post-composer-more__fieldLabel">{{ t('posts.composerLatitude') }}</span>
          <input v-model="form.latitude" class="post-composer-more__input" type="text" />
        </label>
        <label class="post-composer-more__field">
          <span class="post-composer-more__fieldLabel">{{ t('posts.composerLongitude') }}</span>
          <input v-model="form.longitude" class="post-composer-more__input" type="text" />
        </label>
        <label v-if="form.influence_area_type === 'polygon'" class="post-composer-more__field">
          <span class="post-composer-more__fieldLabel">{{ t('posts.composerGeojson') }}</span>
          <textarea
            v-model="form.influence_geojsonInput"
            class="post-composer-more__textarea"
            rows="6"
            :placeholder="t('posts.composerGeojsonPlaceholder')"
          />
        </label>
        <div class="post-composer-more__dialogActions">
          <button type="button" class="post-composer-more__btn" @click="closeAdvanced">
            {{ t('posts.composerAdvancedDone') }}
          </button>
        </div>
      </div>
    </dialog>
  </div>
</template>

<style scoped lang="scss">
.post-composer-more {
  display: grid;
  gap: 1.25rem;
}

.post-composer-more__section {
  display: grid;
  gap: 0.5rem;
}

.post-composer-more__label {
  font-size: 0.875rem;
  font-weight: 600;
}

.post-composer-more__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.post-composer-more__chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.65rem;
  border-radius: 999px;
  border: 1px solid var(--border, #e5e7eb);
  background: var(--surface-2, #f3f4f6);
  font: inherit;
  font-size: 0.8rem;
  cursor: pointer;

  &.is-on {
    border-color: var(--btn-primary-bg, #2563eb);
    background: rgba(37, 99, 235, 0.08);
  }
}

.post-composer-more__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  flex-shrink: 0;
}

.post-composer-more__dot--none {
  background: transparent;
  border: 1px dashed var(--text-muted, #9ca3af);
}

.post-composer-more__field {
  display: grid;
  gap: 0.35rem;
  margin: 0;
  max-width: 28rem;
}

.post-composer-more__fieldLabel {
  font-size: 0.875rem;
  font-weight: 600;
}

.post-composer-more__input,
.post-composer-more__textarea {
  padding: 0.5rem 0.55rem;
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--bg, #fff);
  color: inherit;
  font: inherit;
  width: 100%;
  box-sizing: border-box;
}

.post-composer-more__advancedBtn {
  justify-self: start;
  padding: 0.45rem 0.85rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border, #e5e7eb);
  background: var(--surface-2, #f3f4f6);
  font: inherit;
  font-size: 0.875rem;
  cursor: pointer;
}

.post-composer-more__dialog {
  margin: auto;
  padding: 0;
  max-width: min(28rem, calc(100vw - 2rem));
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 0.75rem;
  background: var(--bg, #fff);
  color: inherit;
  box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
}

.post-composer-more__dialog::backdrop {
  background: rgba(15, 23, 42, 0.45);
}

.post-composer-more__dialogPanel {
  padding: 1rem 1.1rem;
  display: grid;
  gap: 0.85rem;
}

.post-composer-more__dialogTitle {
  margin: 0;
  font-size: 1.05rem;
}

.post-composer-more__dialogActions {
  display: flex;
  justify-content: flex-end;
  margin-top: 0.25rem;
}

.post-composer-more__btn {
  padding: 0.45rem 1rem;
  border-radius: 0.5rem;
  border: none;
  background: var(--btn-primary-bg, #2563eb);
  color: var(--btn-primary-fg, #fff);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
</style>
