import { apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

function invalidateSurveyCaches() {
  invalidateCache(/^\/api\/surveys/)
}

export function fetchSurveys(params = {}) {
  const q = new URLSearchParams()
  if (params.community_id) q.set('community_id', String(params.community_id))
  const qs = q.toString()
  return cachedGet(`/api/surveys${qs ? `?${qs}` : ''}`)
}

export function fetchSurvey(surveyId) {
  return cachedGet(`/api/surveys/${surveyId}`)
}

export async function createSurvey(payload) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/surveys', payload)
  if (result.ok) invalidateSurveyCaches()
  return result
}

export async function updateSurvey(surveyId, payload) {
  await ensureCsrfCookie()
  const result = await apiJson('PATCH', `/api/surveys/${surveyId}`, payload)
  if (result.ok) invalidateSurveyCaches()
  return result
}

export async function deleteSurvey(surveyId) {
  await ensureCsrfCookie()
  const result = await apiJson('DELETE', `/api/surveys/${surveyId}`)
  if (result.ok) invalidateSurveyCaches()
  return result
}

export async function castSurveyVote(surveyId, optionId) {
  await ensureCsrfCookie()
  const result = await apiJson('PUT', `/api/surveys/${surveyId}/vote`, { option_id: optionId })
  if (result.ok) invalidateSurveyCaches()
  return result
}
