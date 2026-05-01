<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '../atoms/Icon.vue'
import PlaceServiceScheduleDisplay from './PlaceServiceScheduleDisplay.vue'
import { t } from '../i18n/i18n'
import { getPlaceScheduleOpenStatus } from '../utils/placeScheduleOpenNow.js'
import { normalizeBrandLinks } from '../utils/placeBrandLinks.js'

const props = defineProps({
  place: {
    type: Object,
    required: true,
  },
})

const router = useRouter()
const clockTick = ref(0)
const activeTab = ref(/** @type {'overview' | 'hours'} */ ('overview'))
const shareHint = ref('')
/** @type {ReturnType<typeof setInterval> | null} */
let clockTimer = null

onMounted(() => {
  clockTimer = setInterval(() => {
    clockTick.value += 1
  }, 60000)
})

onBeforeUnmount(() => {
  if (clockTimer != null) {
    clearInterval(clockTimer)
    clockTimer = null
  }
})

const openStatus = computed(() => {
  void clockTick.value
  return getPlaceScheduleOpenStatus(props.place?.service_schedule, new Date())
})

const hoursPrimaryLine = computed(() => {
  const s = openStatus.value
  if (s.code === 'no_hours') {
    return t('map.placeHoursLineNoHours')
  }
  if (s.code === 'open' && s.closesAtDisplay) {
    return t('map.placeHoursLineOpen').replace('{time}', s.closesAtDisplay)
  }
  if (s.code === 'closed_today') {
    if (!s.opensNextDisplay) return t('map.placeHoursLineClosedTodayShort')
    if (s.opensNextDayOffset === 1) {
      return t('map.placeHoursLineClosedTomorrow').replace('{time}', s.opensNextDisplay)
    }
    if (s.opensNextDayOffset != null && s.opensNextDayOffset > 1 && s.opensNextWeekday) {
      return t('map.placeHoursLineClosedFuture')
        .replace('{weekday}', s.opensNextWeekday)
        .replace('{time}', s.opensNextDisplay)
    }
    return t('map.placeHoursLineClosedTodayShort')
  }
  if (s.code === 'closed') {
    if (s.opensNextDisplay && s.opensNextDayOffset === 0) {
      return t('map.placeHoursLineClosedLaterToday').replace('{time}', s.opensNextDisplay)
    }
    if (s.opensNextDisplay && s.opensNextDayOffset === 1) {
      return t('map.placeHoursLineClosedTomorrow').replace('{time}', s.opensNextDisplay)
    }
    if (s.opensNextDisplay && s.opensNextDayOffset != null && s.opensNextDayOffset > 1 && s.opensNextWeekday) {
      return t('map.placeHoursLineClosedFuture')
        .replace('{weekday}', s.opensNextWeekday)
        .replace('{time}', s.opensNextDisplay)
    }
    if (s.todaySummary) {
      return `${t('map.placeHoursLineClosedSimple')} · ${t('map.placeTodayHours').replace('{hours}', s.todaySummary)}`
    }
    return t('map.placeHoursLineClosedSimple')
  }
  return t('map.placeHoursLineNoHours')
})

const isOpenNow = computed(() => openStatus.value.code === 'open')

const categoryLine = computed(() => {
  const tags = props.place?.tags
  if (Array.isArray(tags) && tags.length) {
    return tags.join(' · ')
  }
  return t('map.placeCategoryFallback')
})

const storefrontHref = computed(() => {
  const slug = props.place?.slug
  if (typeof slug !== 'string' || !slug.trim()) return ''
  return router.resolve({ name: 'placePublic', params: { slug: slug.trim() } }).href
})

const mapsUrl = computed(() => {
  const lat = Number(props.place?.latitude)
  const lng = Number(props.place?.longitude)
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return ''
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${lat},${lng}`)}`
})

const brandRows = computed(() => normalizeBrandLinks(props.place?.brand_links).slice(0, 6))

function iconForUrl(url) {
  if (/^tel:/i.test(url)) return 'phone'
  if (/^mailto:/i.test(url)) return 'link-simple'
  return 'link-simple'
}

async function onShare() {
  const url = storefrontHref.value
  if (!url) return
  shareHint.value = ''
  try {
    if (navigator.share) {
      await navigator.share({ title: props.place?.name || '', url })
      return
    }
    await navigator.clipboard.writeText(url)
    shareHint.value = t('map.placeShareCopied')
    window.setTimeout(() => {
      shareHint.value = ''
    }, 2500)
  } catch {
    shareHint.value = ''
  }
}
</script>

<template>
  <div class="place-map-msp">
    <div class="place-map-msp__cover">
      <img
        v-if="place.logo_url"
        :src="place.logo_url"
        alt=""
        class="place-map-msp__coverImg"
        loading="lazy"
      />
      <div class="place-map-msp__coverShade" aria-hidden="true" />
      <span v-if="place.logo_url" class="place-map-msp__photosChip">{{ t('map.placeSeePhotos') }}</span>
    </div>

    <div class="place-map-msp__body">
      <h3 class="place-map-msp__title">{{ place.name }}</h3>
      <p class="place-map-msp__category">{{ categoryLine }}</p>

      <div class="place-map-msp__tabs" role="tablist">
        <button
          type="button"
          role="tab"
          class="place-map-msp__tab"
          :class="{ 'place-map-msp__tab--active': activeTab === 'overview' }"
          :aria-selected="activeTab === 'overview'"
          @click="activeTab = 'overview'"
        >
          {{ t('map.placeTabOverview') }}
        </button>
        <button
          type="button"
          role="tab"
          class="place-map-msp__tab"
          :class="{ 'place-map-msp__tab--active': activeTab === 'hours' }"
          :aria-selected="activeTab === 'hours'"
          @click="activeTab = 'hours'"
        >
          {{ t('map.placeTabHours') }}
        </button>
      </div>

      <div v-show="activeTab === 'overview'" class="place-map-msp__panel">
        <div class="place-map-msp__actionRow">
          <a
            v-if="storefrontHref"
            class="place-map-msp__action"
            :href="storefrontHref"
            target="_blank"
            rel="noopener noreferrer"
          >
            <span class="place-map-msp__actionIcon" aria-hidden="true">
              <Icon name="square-arrow-up-right" />
            </span>
            <span class="place-map-msp__actionLabel">{{ t('map.placeActionSite') }}</span>
          </a>
          <a
            v-if="mapsUrl"
            class="place-map-msp__action"
            :href="mapsUrl"
            target="_blank"
            rel="noopener noreferrer"
          >
            <span class="place-map-msp__actionIcon" aria-hidden="true">
              <Icon name="map-location-dot" />
            </span>
            <span class="place-map-msp__actionLabel">{{ t('map.placeActionDirections') }}</span>
          </a>
          <button
            v-if="storefrontHref"
            type="button"
            class="place-map-msp__action"
            @click="onShare"
          >
            <span class="place-map-msp__actionIcon" aria-hidden="true">
              <Icon name="share-nodes" />
            </span>
            <span class="place-map-msp__actionLabel">{{ t('map.placeActionShare') }}</span>
          </button>
        </div>
        <p v-if="shareHint" class="place-map-msp__shareHint">{{ shareHint }}</p>

        <a
          v-if="storefrontHref"
          class="place-map-msp__cta"
          :href="storefrontHref"
          target="_blank"
          rel="noopener noreferrer"
        >
          <Icon class="place-map-msp__ctaIcon" name="store" aria-hidden="true" />
          {{ t('map.openMiniSiteNewTab') }}
        </a>
        <p v-else class="place-map-msp__noSlug">{{ t('map.placeNoPublicMiniSite') }}</p>

        <p v-if="place.description" class="place-map-msp__desc">{{ place.description }}</p>

        <ul
          v-if="Array.isArray(place.tags) && place.tags.length"
          class="place-map-msp__tags"
          :aria-label="t('map.placeHighlights')"
        >
          <li v-for="(tag, i) in place.tags" :key="i" class="place-map-msp__tag">
            <Icon class="place-map-msp__tagIcon" name="list-check" aria-hidden="true" />
            {{ tag }}
          </li>
        </ul>

        <div class="place-map-msp__info">
          <div v-if="mapsUrl" class="place-map-msp__infoRow">
            <span class="place-map-msp__infoIcon" aria-hidden="true">
              <Icon name="map-location-dot" />
            </span>
            <div class="place-map-msp__infoText">
              <a class="place-map-msp__infoLink" :href="mapsUrl" target="_blank" rel="noopener noreferrer">
                {{ t('map.placeOpenInMaps') }}
              </a>
              <span class="place-map-msp__infoSub">
                {{ Number(place.latitude).toFixed(4) }}, {{ Number(place.longitude).toFixed(4) }}
              </span>
            </div>
          </div>

          <div class="place-map-msp__infoRow">
            <span
              class="place-map-msp__infoIcon"
              :class="{ 'place-map-msp__infoIcon--open': isOpenNow }"
              aria-hidden="true"
            >
              <Icon name="clock" />
            </span>
            <div class="place-map-msp__infoText">
              <p class="place-map-msp__hoursLine" :class="{ 'place-map-msp__hoursLine--open': isOpenNow }">
                {{ hoursPrimaryLine }}
              </p>
              <button type="button" class="place-map-msp__seeHours" @click="activeTab = 'hours'">
                {{ t('map.placeSeeFullHours') }}
              </button>
            </div>
          </div>

          <div
            v-for="(row, idx) in brandRows"
            :key="`${row.url}-${idx}`"
            class="place-map-msp__infoRow"
          >
            <span class="place-map-msp__infoIcon" aria-hidden="true">
              <Icon :name="iconForUrl(row.url)" />
            </span>
            <div class="place-map-msp__infoText">
              <a class="place-map-msp__infoLink" :href="row.url" target="_blank" rel="noopener noreferrer">
                {{ row.title }}
              </a>
              <span class="place-map-msp__infoSub">{{ row.url }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-show="activeTab === 'hours'" class="place-map-msp__panel place-map-msp__panel--hours">
        <div class="place-map-msp__schedule">
          <PlaceServiceScheduleDisplay :schedule="place.service_schedule" />
        </div>
        <button type="button" class="place-map-msp__backOverview" @click="activeTab = 'overview'">
          {{ t('map.placeBackOverview') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
@import './placeMapMiniSitePreview.scss';
</style>
