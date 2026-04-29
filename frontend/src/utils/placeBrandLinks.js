export const PLACE_BRAND_ICON_OPTIONS = [
  { value: 'website', labelKey: 'myPlaces.brandIconWebsite', symbol: 'W' },
  { value: 'instagram', labelKey: 'myPlaces.brandIconInstagram', symbol: 'IG' },
  { value: 'facebook', labelKey: 'myPlaces.brandIconFacebook', symbol: 'f' },
  { value: 'tiktok', labelKey: 'myPlaces.brandIconTiktok', symbol: 'TT' },
  { value: 'x', labelKey: 'myPlaces.brandIconX', symbol: 'X' },
  { value: 'youtube', labelKey: 'myPlaces.brandIconYoutube', symbol: 'YT' },
  { value: 'linkedin', labelKey: 'myPlaces.brandIconLinkedin', symbol: 'in' },
  { value: 'whatsapp', labelKey: 'myPlaces.brandIconWhatsapp', symbol: 'WA' },
  { value: 'telegram', labelKey: 'myPlaces.brandIconTelegram', symbol: 'TG' },
]

export function normalizeBrandLinks(raw) {
  if (!Array.isArray(raw)) return []
  return raw
    .map((entry) => ({
      title: typeof entry?.title === 'string' ? entry.title.trim() : '',
      url: typeof entry?.url === 'string' ? entry.url.trim() : '',
      icon: typeof entry?.icon === 'string' ? entry.icon.trim() : '',
    }))
    .filter((entry) => entry.title && entry.url && entry.icon)
    .slice(0, 20)
}

export function brandIconSymbol(icon) {
  return PLACE_BRAND_ICON_OPTIONS.find((option) => option.value === icon)?.symbol || '?'
}
