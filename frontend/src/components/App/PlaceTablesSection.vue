<script setup>
import { onMounted, ref } from 'vue'
import QRCode from 'qrcode'
import Button from '../../atoms/Button.vue'
import Card from '../../atoms/Card.vue'
import {
  createPlaceTable,
  createTableAccessLink,
  deletePlaceTable,
  fetchPlaceTables,
  rotateTableAccessLink,
} from '../../services/placeTablesApi.js'

const props = defineProps({
  placeId: { type: [String, Number], required: true },
})

const tables = ref([])
const loading = ref(false)
const error = ref('')
const name = ref('')
const qrByTableId = ref({})

async function load() {
  loading.value = true
  error.value = ''
  const { ok, data, status } = await fetchPlaceTables(props.placeId)
  loading.value = false
  if (!ok) {
    error.value = `HTTP ${status}`
    return
  }
  tables.value = Array.isArray(data?.data) ? data.data : []
}

async function addTable() {
  const n = name.value.trim()
  if (!n) return
  const { ok, status } = await createPlaceTable(props.placeId, n)
  if (!ok) {
    error.value = `HTTP ${status}`
    return
  }
  name.value = ''
  await load()
}

async function removeTable(tableId) {
  const { ok, status } = await deletePlaceTable(props.placeId, tableId)
  if (!ok) {
    error.value = `HTTP ${status}`
    return
  }
  await load()
}

async function generateQr(tableId, rotate = false) {
  const call = rotate ? rotateTableAccessLink : createTableAccessLink
  const { ok, status, data } = await call(props.placeId, tableId)
  if (!ok) {
    error.value = `HTTP ${status}`
    return
  }
  const url = data?.access_link?.url
  if (typeof url !== 'string' || !url) return
  const image = await QRCode.toDataURL(url, { width: 280, margin: 2, errorCorrectionLevel: 'M' })
  qrByTableId.value = {
    ...qrByTableId.value,
    [tableId]: { url, image },
  }
}

onMounted(load)
</script>

<template>
  <Card>
    <h3>Tables</h3>
    <p v-if="loading">Loading...</p>
    <p v-if="error" class="place-tables__error">{{ error }}</p>
    <div class="place-tables__create">
      <input v-model="name" type="text" placeholder="Table name" />
      <Button variant="primary" @click="addTable">Add table</Button>
    </div>
    <ul class="place-tables__list">
      <li v-for="table in tables" :key="table.id" class="place-tables__item">
        <strong>{{ table.name }}</strong>
        <div class="place-tables__actions">
          <Button size="sm" variant="secondary" @click="generateQr(table.id, false)">Create QR</Button>
          <Button size="sm" variant="secondary" @click="generateQr(table.id, true)">Rotate QR</Button>
          <Button size="sm" variant="danger" @click="removeTable(table.id)">Delete</Button>
        </div>
        <div v-if="qrByTableId[table.id]" class="place-tables__qr">
          <img :src="qrByTableId[table.id].image" alt="Table QR code" />
          <a :href="qrByTableId[table.id].url" target="_blank" rel="noreferrer">{{ qrByTableId[table.id].url }}</a>
        </div>
      </li>
    </ul>
  </Card>
</template>

<style scoped lang="scss">
.place-tables__error { color: #b91c1c; }
.place-tables__create { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.place-tables__list { list-style: none; padding: 0; margin: 0; display: grid; gap: 0.75rem; }
.place-tables__item { border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem; }
.place-tables__actions { display: flex; gap: 0.35rem; margin-top: 0.5rem; flex-wrap: wrap; }
.place-tables__qr { margin-top: 0.75rem; display: grid; gap: 0.5rem; }
.place-tables__qr img { width: 180px; height: 180px; object-fit: contain; }
</style>
