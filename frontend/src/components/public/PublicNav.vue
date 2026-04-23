<script setup>
import { useCommunity } from '../../composables/useCommunity'
import { t } from '../../i18n/i18n'

const { displayName } = useCommunity()
</script>

<template>
  <header class="public-nav" role="banner">
    <RouterLink class="public-nav__brand" to="/" :aria-label="displayName">
      {{ displayName }}
    </RouterLink>
    <nav class="public-nav__links" aria-label="Public">
      <RouterLink class="public-nav__link" to="/" active-class="is-active" exact-active-class="is-active">
        {{ t('nav.home') }}
      </RouterLink>
      <RouterLink class="public-nav__link" to="/contact" active-class="is-active">
        {{ t('publicNav.contact') }}
      </RouterLink>
      <RouterLink class="public-nav__link" to="/legal" active-class="is-active">
        {{ t('publicNav.legal') }}
      </RouterLink>
      <RouterLink class="public-nav__link public-nav__link--cta" to="/login" active-class="is-active">
        {{ t('nav.login') }}
      </RouterLink>
    </nav>
  </header>
</template>

<style lang="scss" scoped>
.public-nav {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--border);
  background: var(--bg);
}

.public-nav__brand {
  font-weight: 700;
  color: var(--text);
  text-decoration: none;

  &:hover {
    color: var(--link);
  }
}

.public-nav__links {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
}

.public-nav__link {
  color: var(--text);
  text-decoration: none;
  font-size: 0.95rem;
  opacity: 0.85;
  padding: 0.35rem 0.25rem;
  border-radius: 0.35rem;

  &:hover {
    opacity: 1;
    background: var(--btn-bg);
  }

  &.is-active {
    opacity: 1;
    color: var(--link);
    font-weight: 600;
  }
}

.public-nav__link--cta {
  border: 1px solid var(--border);
  padding: 0.35rem 0.75rem;

  &.is-active {
    border-color: var(--link);
  }
}
</style>
