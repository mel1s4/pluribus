<script setup>
import { t } from '../i18n/i18n'
import { usePlaceSearchSuggestions } from '../composables/usePlaceSearchSuggestions.js'

defineProps({
  geolocateBusy: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['center-my-location', 'select-search-result'])

const {
  searchQuery,
  suggestions,
  listOpen,
  searchLoading,
  searchError,
  closeListAndClearSuggestions,
} = usePlaceSearchSuggestions()

const listboxId = 'place-location-search-listbox'
const inputId = 'place-location-search-input'

function onPick(row) {
  emit('select-search-result', {
    lat: row.lat,
    lng: row.lng,
    label: row.title + (row.subtitle ? `, ${row.subtitle}` : ''),
  })
  searchQuery.value = row.title
  closeListAndClearSuggestions()
}

function onInputBlur() {
  window.setTimeout(() => {
    listOpen.value = false
  }, 200)
}

function onInputFocus() {
  if (suggestions.value.length > 0) {
    listOpen.value = true
  }
}

function onInputKeydown(ev) {
  if (ev.key === 'Escape') {
    listOpen.value = false
  }
}

function onGeolocateClick() {
  emit('center-my-location')
}
</script>

<template>
  <div class="place-location-map-toolbar">
    <button
      type="button"
      class="place-location-map-toolbar__btn"
      :disabled="geolocateBusy"
      @click="onGeolocateClick"
    >
      {{ geolocateBusy ? t('myPlaces.geolocateLoading') : t('myPlaces.centerToMyLocation') }}
    </button>

    <div class="place-location-map-toolbar__search">
      <label class="place-location-map-toolbar__search-label" :for="inputId">
        {{ t('myPlaces.searchAddressLabel') }}
      </label>
      <div
        class="place-location-map-toolbar__combobox"
        role="combobox"
        :aria-expanded="listOpen ? 'true' : 'false'"
        :aria-owns="listboxId"
      >
        <input
          :id="inputId"
          v-model="searchQuery"
          type="text"
          class="place-location-map-toolbar__input"
          :placeholder="t('myPlaces.searchAddressPlaceholder')"
          autocomplete="off"
          aria-autocomplete="list"
          :aria-controls="listboxId"
          @focus="onInputFocus"
          @blur="onInputBlur"
          @keydown="onInputKeydown"
        />
        <ul
          v-show="listOpen && suggestions.length > 0"
          :id="listboxId"
          class="place-location-map-toolbar__list"
          role="listbox"
        >
          <li
            v-for="row in suggestions"
            :key="row.id"
            role="option"
            class="place-location-map-toolbar__option"
            tabindex="-1"
            @mousedown.prevent="onPick(row)"
          >
            <span class="place-location-map-toolbar__option-title">{{ row.title }}</span>
            <span
              v-if="row.subtitle"
              class="place-location-map-toolbar__option-sub"
            >{{ row.subtitle }}</span>
          </li>
        </ul>
      </div>
      <p
        v-if="searchLoading"
        class="place-location-map-toolbar__hint"
        aria-live="polite"
      >
        {{ t('myPlaces.searchAddressLoading') }}
      </p>
      <p
        v-if="searchError"
        class="place-location-map-toolbar__error"
        role="alert"
      >
        {{ searchError }}
      </p>
      <p class="place-location-map-toolbar__attr">
        {{ t('myPlaces.searchDataAttribution') }}
        <a
          class="place-location-map-toolbar__attr-link"
          href="https://www.openstreetmap.org/copyright"
          target="_blank"
          rel="noopener noreferrer"
        >OpenStreetMap</a>
      </p>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.place-location-map-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.75rem 1rem;
}

.place-location-map-toolbar__btn {
  cursor: pointer;
  padding: 0.45rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg);
  font-size: 0.9rem;
  white-space: nowrap;
}

.place-location-map-toolbar__btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.place-location-map-toolbar__search {
  flex: 1 1 12rem;
  min-width: 10rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.place-location-map-toolbar__search-label {
  font-size: 0.8rem;
  opacity: 0.85;
}

.place-location-map-toolbar__combobox {
  position: relative;
}

.place-location-map-toolbar__input {
  width: 100%;
  box-sizing: border-box;
  padding: 0.4rem 0.5rem;
  border-radius: 6px;
  border: 1px solid var(--border);
  font-size: 0.9rem;
}

.place-location-map-toolbar__list {
  position: absolute;
  z-index: 4000;
  left: 0;
  right: 0;
  top: 100%;
  margin: 0.15rem 0 0;
  padding: 0;
  list-style: none;
  max-height: 14rem;
  overflow-y: auto;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg, #fff);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.place-location-map-toolbar__option {
  padding: 0.45rem 0.55rem;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  border-bottom: 1px solid var(--border);
}

.place-location-map-toolbar__option:last-child {
  border-bottom: none;
}

.place-location-map-toolbar__option:hover,
.place-location-map-toolbar__option:focus {
  background: rgba(59, 91, 219, 0.08);
}

.place-location-map-toolbar__option-title {
  font-size: 0.9rem;
}

.place-location-map-toolbar__option-sub {
  font-size: 0.78rem;
  opacity: 0.8;
}

.place-location-map-toolbar__hint {
  margin: 0;
  font-size: 0.78rem;
  opacity: 0.75;
}

.place-location-map-toolbar__error {
  margin: 0;
  font-size: 0.8rem;
  color: var(--danger, #b00020);
}

.place-location-map-toolbar__attr {
  margin: 0;
  font-size: 0.72rem;
  opacity: 0.7;
}

.place-location-map-toolbar__attr-link {
  color: inherit;
}
</style>
