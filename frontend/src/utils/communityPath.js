export function withCommunityPath(path, communitySlug) {
  const normalized = path.startsWith('/') ? path : `/${path}`
  if (!communitySlug) {
    return normalized
  }
  if (normalized === '/') {
    return `/${communitySlug}`
  }
  return `/${communitySlug}${normalized}`
}
