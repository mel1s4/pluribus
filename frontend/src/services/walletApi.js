import { apiJson, ensureCsrfCookie } from './api.js'

/**
 * @param {number} communityId
 * @param {{ page?: number, perPage?: number }} [opts]
 */
export function fetchWallet(communityId, opts = {}) {
  const page = opts.page ?? 1
  const perPage = opts.perPage ?? 20
  const q = new URLSearchParams({
    community_id: String(communityId),
    page: String(page),
    per_page: String(perPage),
  })
  return apiJson('GET', `/api/wallet?${q.toString()}`)
}

/**
 * @param {number} communityId
 * @param {number|string} transactionId
 */
export function fetchWalletTransaction(communityId, transactionId) {
  const q = new URLSearchParams({ community_id: String(communityId) })
  return apiJson('GET', `/api/wallet/transactions/${transactionId}?${q.toString()}`)
}

/**
 * @param {number} communityId
 * @param {{ page?: number, perPage?: number }} [opts]
 */
export function fetchWalletAuditLedger(communityId, opts = {}) {
  const page = opts.page ?? 1
  const perPage = opts.perPage ?? 50
  const q = new URLSearchParams({
    community_id: String(communityId),
    page: String(page),
    per_page: String(perPage),
  })
  return apiJson('GET', `/api/wallet/audit-ledger?${q.toString()}`)
}

/**
 * @param {number} communityId
 */
export function fetchWalletCommunityStats(communityId) {
  const q = new URLSearchParams({ community_id: String(communityId) })
  return apiJson('GET', `/api/wallet/community-stats?${q.toString()}`)
}

/**
 * @param {Record<string, unknown>} payload
 */
export async function postWalletTransfer(payload) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/wallet/transfer', payload)
}

/**
 * @param {Record<string, unknown>} payload
 */
export async function postWalletGrant(payload) {
  await ensureCsrfCookie()
  return apiJson('POST', '/api/wallet/grants', payload)
}
