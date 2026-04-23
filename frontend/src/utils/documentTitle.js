import { language, t } from '../i18n/i18n'
import { communityName } from '../composables/useCommunity'

const PUBLIC_TITLE_KEYS = {
  home: 'home.title',
  contact: 'contact.title',
  legal: 'legal.title',
  login: 'login.title',
  joinInvitation: 'joinInvitation.title',
}

/**
 * @param {import('vue-router').RouteLocationNormalizedLoaded} route
 */
export function syncDocumentTitle(route) {
  void language.value
  const metaKey = route.meta?.headerTitleKey
  const pageFromMeta =
    typeof metaKey === 'string' && metaKey.length ? t(metaKey) : ''
  const publicKey =
    typeof route.name === 'string' ? PUBLIC_TITLE_KEYS[route.name] : undefined
  const pageFromPublic = publicKey ? t(publicKey) : ''
  const pageTitle = pageFromMeta || pageFromPublic
  const brand =
    typeof communityName.value === 'string' && communityName.value.trim().length
      ? communityName.value.trim()
      : t('nav.logo')

  if (pageTitle) {
    document.title = `${pageTitle} · ${brand}`
  } else {
    document.title = brand
  }
}
