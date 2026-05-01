/**
 * Build a safe iframe src for a post video URL (YouTube watch/share/embed or Google Drive file).
 *
 * @param {string} raw
 * @returns {string|null}
 */
export function postVideoEmbedSrc(raw) {
  const trimmed = typeof raw === 'string' ? raw.trim() : ''
  if (!trimmed) return null

  let url
  try {
    url = new URL(trimmed)
  } catch {
    return null
  }

  const host = url.hostname.toLowerCase()

  if (host === 'youtu.be') {
    const id = url.pathname.replace(/^\//, '').split('/')[0]
    if (id && /^[\w-]{11}$/.test(id)) {
      return `https://www.youtube.com/embed/${encodeURIComponent(id)}`
    }
    return null
  }

  if (/(^|\.)youtube\.com$/.test(host)) {
    const path = url.pathname
    const lowerPath = path.toLowerCase()

    if (lowerPath.startsWith('/embed/')) {
      const id = path.slice('/embed/'.length).split(/[/&?#]/)[0]
      if (id && /^[\w-]{11}$/.test(id)) {
        const base = `https://www.youtube.com/embed/${encodeURIComponent(id)}`
        const q = url.search
        return q ? `${base}${q}` : base
      }
    }

    if (lowerPath === '/watch' || lowerPath.startsWith('/watch/')) {
      const v = url.searchParams.get('v')
      if (v && /^[\w-]{11}$/.test(v)) {
        return `https://www.youtube.com/embed/${encodeURIComponent(v)}`
      }
    }

    const m = path.match(/^\/(shorts|live)\/([\w-]{11})/i)
    if (m) {
      return `https://www.youtube.com/embed/${encodeURIComponent(m[2])}`
    }
  }

  if (host === 'drive.google.com') {
    const fileMatch = url.pathname.match(/\/file\/d\/([^/]+)/i)
    if (fileMatch && fileMatch[1]) {
      return `https://drive.google.com/file/d/${encodeURIComponent(fileMatch[1])}/preview`
    }
    const id = url.searchParams.get('id')
    if (id && id.length >= 10 && id.length <= 128) {
      return `https://drive.google.com/file/d/${encodeURIComponent(id)}/preview`
    }
  }

  return null
}
