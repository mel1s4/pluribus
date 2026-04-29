import { computed, reactive, ref } from 'vue'
import { fromDatetimeLocal, toDatetimeLocal } from '../utils/datetimeLocal.js'

function emptyForm() {
  return {
    type: 'info',
    title: '',
    description: '',
    content_markdown: '',
    tagsInput: '',
    showSummary: false,
    visibility_scope: 'private',
    shared_group_id: '',
    calendar_id: '',
    place_id: '',
    startLocal: '',
    endLocal: '',
    all_day: false,
    recurrence_rule: '',
    influence_area_type: 'none',
    influence_radius_meters: '',
    latitude: '',
    longitude: '',
    influence_geojsonInput: '',
  }
}

function stableSerialize(form) {
  const o = { ...form }
  return JSON.stringify(o)
}

function parseTags(input) {
  if (!input || typeof input !== 'string') return []
  return input
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
    .slice(0, 64)
}

function optionalNumber(s) {
  if (s === '' || s == null) return null
  const n = Number(s)
  return Number.isFinite(n) ? n : null
}

export function usePostComposerForm() {
  const form = reactive(emptyForm())
  const baseline = ref(stableSerialize(form))
  const postId = ref(null)

  const dirty = computed(() => stableSerialize(form) !== baseline.value)

  function markClean() {
    baseline.value = stableSerialize(form)
  }

  function resetForCreate() {
    postId.value = null
    Object.assign(form, emptyForm())
    markClean()
  }

  /**
   * @param {Record<string, unknown>} post
   */
  function applyFromPost(post) {
    postId.value = post.id != null ? Number(post.id) : null
    form.type = post.type === 'event' || post.type === 'announcement' || post.type === 'info' ? post.type : 'info'
    form.title = typeof post.title === 'string' ? post.title : ''
    form.description = typeof post.description === 'string' ? post.description : ''
    form.content_markdown = typeof post.content_markdown === 'string' ? post.content_markdown : ''
    form.tagsInput = Array.isArray(post.tags) ? post.tags.join(', ') : ''
    form.showSummary = Boolean(form.description)
    form.visibility_scope =
      post.visibility_scope === 'community' || post.visibility_scope === 'group' ? post.visibility_scope : 'private'
    form.shared_group_id = post.shared_group_id != null ? String(post.shared_group_id) : ''
    form.calendar_id = post.calendar_id != null ? String(post.calendar_id) : ''
    form.place_id = post.place_id != null ? String(post.place_id) : ''
    form.startLocal = post.start_at ? toDatetimeLocal(post.start_at) : ''
    form.endLocal = post.end_at ? toDatetimeLocal(post.end_at) : ''
    form.all_day = Boolean(post.all_day)
    form.recurrence_rule = typeof post.recurrence_rule === 'string' ? post.recurrence_rule : ''
    form.influence_area_type =
      post.influence_area_type === 'radius' || post.influence_area_type === 'polygon'
        ? post.influence_area_type
        : 'none'
    form.influence_radius_meters =
      post.influence_radius_meters != null ? String(post.influence_radius_meters) : ''
    form.latitude = post.latitude != null ? String(post.latitude) : ''
    form.longitude = post.longitude != null ? String(post.longitude) : ''
    try {
      form.influence_geojsonInput =
        post.influence_area_geojson && typeof post.influence_area_geojson === 'object'
          ? JSON.stringify(post.influence_area_geojson, null, 2)
          : ''
    } catch {
      form.influence_geojsonInput = ''
    }
    markClean()
  }

  /**
   * @returns {{ ok: true, payload: Record<string, unknown> } | { ok: false, errorKey: string }}
   */
  function buildPayload() {
    const title = form.title.trim()
    if (!title) return { ok: false, errorKey: 'posts.composerValidationTitle' }

    if (form.visibility_scope === 'group') {
      const gid = optionalNumber(form.shared_group_id)
      if (gid == null) return { ok: false, errorKey: 'posts.composerValidationGroup' }
    }

    let influence_area_geojson = null
    if (form.influence_area_type === 'polygon') {
      const raw = form.influence_geojsonInput.trim()
      if (raw) {
        try {
          const parsed = JSON.parse(raw)
          if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return { ok: false, errorKey: 'posts.composerValidationGeojson' }
          }
          influence_area_geojson = parsed
        } catch {
          return { ok: false, errorKey: 'posts.composerValidationGeojson' }
        }
      }
    }

    const tags = parseTags(form.tagsInput)
    const start_at = fromDatetimeLocal(form.startLocal)
    const end_at = fromDatetimeLocal(form.endLocal)
    if (form.startLocal && form.endLocal && start_at && end_at) {
      if (new Date(end_at) < new Date(start_at)) {
        return { ok: false, errorKey: 'posts.composerValidationEndBeforeStart' }
      }
    }

    const influenceType = form.influence_area_type || 'none'
    const payload = {
      type: form.type,
      title,
      description: form.description.trim() || null,
      content_markdown: form.content_markdown.trim() || null,
      tags: tags.length ? tags : null,
      visibility_scope: form.visibility_scope,
      shared_group_id:
        form.visibility_scope === 'group' ? optionalNumber(form.shared_group_id) : null,
      calendar_id: optionalNumber(form.calendar_id),
      place_id: optionalNumber(form.place_id),
      start_at,
      end_at,
      all_day: Boolean(form.all_day),
      recurrence_rule: form.recurrence_rule.trim() || null,
      influence_area_type: influenceType,
      influence_radius_meters:
        influenceType === 'radius' ? optionalNumber(form.influence_radius_meters) : null,
      latitude: optionalNumber(form.latitude),
      longitude: optionalNumber(form.longitude),
      influence_area_geojson: influenceType === 'polygon' ? influence_area_geojson : null,
    }

    if (influenceType === 'none') {
      payload.influence_radius_meters = null
      payload.influence_area_geojson = null
    }
    if (influenceType === 'radius') {
      payload.influence_area_geojson = null
    }

    return { ok: true, payload }
  }

  return {
    form,
    postId,
    dirty,
    markClean,
    resetForCreate,
    applyFromPost,
    buildPayload,
  }
}
