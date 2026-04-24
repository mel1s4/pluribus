import { cachedGet } from './cachedApi.js'

export function fetchCommunity() {
  return cachedGet('/api/community')
}

export function fetchCommunityLeadership() {
  return cachedGet('/api/community/leadership')
}
