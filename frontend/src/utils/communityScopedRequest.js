/**
 * @param {string | null | undefined} communitySlug
 * @returns {{ headers: Record<string, string> } | Record<string, never>}
 */
export function communitySlugRequestOptions(communitySlug) {
  const s = typeof communitySlug === 'string' ? communitySlug.trim() : ''
  if (!s) {
    return {}
  }
  return { headers: { 'X-Community-Slug': s } }
}
