import { apiJson, ensureCsrfCookie } from './api.js'
import { cachedGet, invalidateCache } from './cachedApi.js'

function invalidateContactCaches() {
  invalidateCache(/^\/api\/contacts/)
}

export function fetchContacts() {
  return cachedGet('/api/contacts')
}

export async function searchContactByEmail(email) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/contacts/search', { email })
}

export async function addContact(contactUserId) {
  await ensureCsrfCookie()
  const result = await apiJson('POST', '/api/contacts', {
    contact_user_id: contactUserId,
  })
  if (result.ok) invalidateContactCaches()
  return result
}
