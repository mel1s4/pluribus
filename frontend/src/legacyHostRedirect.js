const LEGACY_HOST = /^(www\.)?chante\.vzs\.mx$/i

export function isLegacyChanteHost() {
  return typeof location !== 'undefined' && LEGACY_HOST.test(location.hostname)
}

export function pluribusRedirectUrl() {
  return `https://pluribus.vzs.mx${location.pathname}${location.search}${location.hash}`
}

/** Unregister SW and move to pluribus (same path). Resolves true when redirect runs. */
export async function redirectLegacyHostIfNeeded() {
  if (!isLegacyChanteHost()) {
    return false
  }
  if (typeof navigator !== 'undefined' && 'serviceWorker' in navigator) {
    const registrations = await navigator.serviceWorker.getRegistrations()
    await Promise.all(registrations.map((r) => r.unregister()))
  }
  location.replace(pluribusRedirectUrl())
  return true
}
