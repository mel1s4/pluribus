<script setup>
import { computed, onMounted, ref } from 'vue'
import { t } from '../i18n/i18n'
import { resolveSession, sessionUser } from '../composables/useSession'
import PlaceRequirementOfferResponseDialog from './PlaceRequirementOfferResponseDialog.vue'
import { deleteRequirementResponse } from '../services/placesApi.js'

const props = defineProps({
  placeId: {
    type: [Number, String],
    required: true,
  },
  canManagePlace: {
    type: Boolean,
    default: false,
  },
  requirements: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['updated'])

const list = computed(() => (Array.isArray(props.requirements) ? props.requirements : []))

const dialogOpen = ref(false)
const dialogRequirementId = ref(null)

const dayLabelKey = {
  mon: 'myPlaces.scheduleDayMon',
  tue: 'myPlaces.scheduleDayTue',
  wed: 'myPlaces.scheduleDayWed',
  thu: 'myPlaces.scheduleDayThu',
  fri: 'myPlaces.scheduleDayFri',
  sat: 'myPlaces.scheduleDaySat',
  sun: 'myPlaces.scheduleDaySun',
}

function formatWeekdays(keys) {
  if (!Array.isArray(keys) || keys.length === 0) {
    return ''
  }
  return keys.map((k) => t(dayLabelKey[k] || k)).join(', ')
}

function recurrenceLine(r) {
  if (r.recurrence_mode === 'weekly' && Array.isArray(r.recurrence_weekdays) && r.recurrence_weekdays.length) {
    return t('places.viewRequirementWeekly').replace('{days}', formatWeekdays(r.recurrence_weekdays))
  }
  return t('places.viewRequirementOnce')
}

function openRespond(requirementId) {
  dialogRequirementId.value = requirementId
  dialogOpen.value = true
}

function closeDialog() {
  dialogOpen.value = false
  dialogRequirementId.value = null
}

function onResponseSaved() {
  emit('updated')
  closeDialog()
}

const meId = computed(() => (sessionUser.value?.id != null ? Number(sessionUser.value.id) : null))

const isLoggedIn = computed(() => Boolean(sessionUser.value))

async function removeOwnResponse(req, resp) {
  if (!window.confirm(t('places.requirementResponseDeleteOwnConfirm'))) return
  const { ok } = await deleteRequirementResponse(props.placeId, req.id, resp.id)
  if (ok) {
    emit('updated')
  }
}

onMounted(() => {
  resolveSession()
})
</script>

<template>
  <div class="place-reqs-public">
    <p v-if="list.length === 0" class="place-reqs-public__empty">
      {{ t('places.viewRequirementsEmpty') }}
    </p>
    <ul v-else class="place-reqs-public__list" role="list">
      <li v-for="r in list" :key="r.id" class="place-reqs-public__card">
        <div v-if="r.photo_url" class="place-reqs-public__photoWrap">
          <img
            :src="r.photo_url"
            :alt="r.title || ''"
            class="place-reqs-public__photo"
            loading="lazy"
          />
        </div>
        <div class="place-reqs-public__body">
          <h3 class="place-reqs-public__title">{{ r.title }}</h3>
          <p v-if="r.description" class="place-reqs-public__desc">{{ r.description }}</p>
          <p class="place-reqs-public__qty">
            {{ t('places.viewRequirementQuantity').replace('{q}', r.quantity).replace('{u}', r.unit) }}
          </p>
          <p class="place-reqs-public__recur">{{ recurrenceLine(r) }}</p>
          <ul
            v-if="Array.isArray(r.tags) && r.tags.length"
            class="place-reqs-public__tags"
            aria-label="Tags"
          >
            <li v-for="(tag, i) in r.tags" :key="i" class="place-reqs-public__tag">
              {{ tag }}
            </li>
          </ul>

          <div v-if="r.example_offer" class="place-reqs-public__example">
            <h4 class="place-reqs-public__subhead">{{ t('places.viewRequirementExampleHeading') }}</h4>
            <div class="place-reqs-public__exampleCard">
              <img
                v-if="r.example_offer.photo_url"
                :src="r.example_offer.photo_url"
                alt=""
                class="place-reqs-public__exampleImg"
                loading="lazy"
              />
              <div>
                <p class="place-reqs-public__exampleTitle">{{ r.example_offer.title }}</p>
                <p v-if="r.example_offer.source_place?.name" class="place-reqs-public__examplePlace">
                  {{ t('places.viewRequirementExampleFrom').replace('{name}', r.example_offer.source_place.name) }}
                </p>
                <p class="place-reqs-public__examplePrice">{{ r.example_offer.price }}</p>
              </div>
            </div>
          </div>

          <div v-if="r.community_responses?.length" class="place-reqs-public__responses">
            <h4 class="place-reqs-public__subhead">{{ t('places.viewRequirementCommunityOffers') }}</h4>
            <ul class="place-reqs-public__respList">
              <li v-for="resp in r.community_responses" :key="resp.id" class="place-reqs-public__respRow">
                <div>
                  <p class="place-reqs-public__respTitle">{{ resp.title }}</p>
                  <p v-if="resp.user?.name" class="place-reqs-public__respBy">
                    {{ t('places.viewRequirementResponseBy').replace('{name}', resp.user.name) }}
                  </p>
                  <p class="place-reqs-public__respPrice">{{ resp.price }}</p>
                </div>
                <button
                  v-if="meId != null && Number(resp.user?.id) === meId"
                  type="button"
                  class="place-reqs-public__respDel"
                  @click="removeOwnResponse(r, resp)"
                >
                  {{ t('places.requirementResponseDelete') }}
                </button>
              </li>
            </ul>
          </div>

          <div v-if="isLoggedIn" class="place-reqs-public__cta">
            <button type="button" class="place-reqs-public__offerBtn" @click="openRespond(r.id)">
              {{ t('places.viewRequirementMakeOffer') }}
            </button>
          </div>

          <div v-if="canManagePlace && r.offers_made?.length" class="place-reqs-public__made">
            <h4 class="place-reqs-public__subhead">{{ t('places.viewRequirementOffersMade') }}</h4>
            <ul class="place-reqs-public__madeList">
              <li v-for="resp in r.offers_made" :key="'m-'+resp.id" class="place-reqs-public__madeRow">
                <span class="place-reqs-public__madeTitle">{{ resp.title }}</span>
                <span class="place-reqs-public__madePrice">{{ resp.price }}</span>
                <span class="place-reqs-public__madeVis">
                  {{ resp.visibility === 'community' ? t('myPlaces.requirementVisibilityCommunity') : t('myPlaces.requirementVisibilityCreator') }}
                </span>
                <span v-if="resp.user?.name" class="place-reqs-public__madeBy">{{ resp.user.name }}</span>
              </li>
            </ul>
          </div>
        </div>
      </li>
    </ul>

    <PlaceRequirementOfferResponseDialog
      v-if="dialogOpen && dialogRequirementId != null"
      :open="dialogOpen"
      :place-id="placeId"
      :requirement-id="dialogRequirementId"
      @close="closeDialog"
      @saved="onResponseSaved"
    />
  </div>
</template>

<style lang="scss" scoped>
.place-reqs-public__empty {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.8;
}

.place-reqs-public__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.place-reqs-public__card {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--card-bg, transparent);
}

.place-reqs-public__photoWrap {
  flex: 0 0 8rem;
  max-width: 100%;
}

.place-reqs-public__photo {
  width: 100%;
  height: auto;
  border-radius: 0.35rem;
  display: block;
}

.place-reqs-public__body {
  flex: 1 1 12rem;
  min-width: 0;
}

.place-reqs-public__title {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
}

.place-reqs-public__desc {
  margin: 0 0 0.5rem;
  font-size: 0.9rem;
  opacity: 0.9;
  white-space: pre-wrap;
}

.place-reqs-public__qty {
  margin: 0 0 0.25rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.place-reqs-public__recur {
  margin: 0 0 0.35rem;
  font-size: 0.85rem;
  opacity: 0.88;
}

.place-reqs-public__tags {
  list-style: none;
  margin: 0.35rem 0 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.place-reqs-public__tag {
  font-size: 0.75rem;
  padding: 0.15rem 0.45rem;
  border-radius: 0.35rem;
  background: rgba(37, 99, 235, 0.1);
  color: #1d4ed8;
}

.place-reqs-public__subhead {
  margin: 0.85rem 0 0.4rem;
  font-size: 0.9rem;
  font-weight: 600;
}

.place-reqs-public__exampleCard {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding: 0.65rem;
  border: 1px solid var(--border);
  border-radius: 0.35rem;
  background: rgba(37, 99, 235, 0.04);
}

.place-reqs-public__exampleImg {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.25rem;
}

.place-reqs-public__exampleTitle {
  margin: 0 0 0.2rem;
  font-weight: 600;
  font-size: 0.95rem;
}

.place-reqs-public__examplePlace {
  margin: 0 0 0.2rem;
  font-size: 0.8rem;
  opacity: 0.85;
}

.place-reqs-public__examplePrice {
  margin: 0;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.place-reqs-public__respList,
.place-reqs-public__madeList {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.place-reqs-public__respRow {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--border);
  border-radius: 0.35rem;
}

.place-reqs-public__respTitle {
  margin: 0 0 0.15rem;
  font-weight: 600;
  font-size: 0.9rem;
}

.place-reqs-public__respBy {
  margin: 0 0 0.15rem;
  font-size: 0.75rem;
  opacity: 0.85;
}

.place-reqs-public__respPrice {
  margin: 0;
  font-size: 0.85rem;
  font-variant-numeric: tabular-nums;
}

.place-reqs-public__respDel {
  cursor: pointer;
  font: inherit;
  font-size: 0.8rem;
  padding: 0.2rem 0.45rem;
  border-radius: 4px;
  border: 1px solid var(--border);
  background: var(--bg);
  align-self: flex-start;
}

.place-reqs-public__cta {
  margin-top: 0.65rem;
}

.place-reqs-public__offerBtn {
  cursor: pointer;
  font: inherit;
  font-weight: 600;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  border: 1px solid var(--accent, #3b5bdb);
  background: var(--accent, #3b5bdb);
  color: #fff;
}

.place-reqs-public__made {
  margin-top: 0.65rem;
  padding: 0.5rem 0.65rem;
  border-radius: 6px;
  border: 1px dashed var(--border);
  background: var(--btn-bg, rgba(0, 0, 0, 0.02));
}

.place-reqs-public__madeRow {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.65rem;
  font-size: 0.85rem;
  align-items: baseline;
}

.place-reqs-public__madeTitle {
  flex: 1;
  min-width: 6rem;
  font-weight: 600;
}

.place-reqs-public__madePrice {
  font-variant-numeric: tabular-nums;
}

.place-reqs-public__madeVis {
  font-size: 0.75rem;
  opacity: 0.85;
}

.place-reqs-public__madeBy {
  font-size: 0.75rem;
  opacity: 0.85;
}
</style>
