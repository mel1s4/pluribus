<template>
  <div class="public-layout">
    <PublicNav />
    <main class="public-layout__main">
      <slot />
    </main>
    <footer class="public-layout__footer" role="contentinfo">
      <p class="public-layout__copyright">{{ copyrightLine }}</p>
      <p class="public-layout__tagline">{{ t('publicFooter.tagline') }}</p>
      <nav class="public-layout__footer-nav" aria-label="Footer">
        <RouterLink class="public-layout__footer-link" to="/contact">
          {{ t('publicNav.contact') }}
        </RouterLink>
        <RouterLink class="public-layout__footer-link" to="/legal">
          {{ t('publicNav.legal') }}
        </RouterLink>
      </nav>
    </footer>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import PublicNav from '../components/public/PublicNav.vue'
import { useCommunity } from '../composables/useCommunity'
import { language, t } from '../i18n/i18n'

const { displayName } = useCommunity()

const copyrightLine = computed(() => {
  void language.value
  const year = new Date().getFullYear()
  return t('publicFooter.copyright')
    .replace('{year}', String(year))
    .replace('{name}', displayName.value)
})
</script>

<style lang="scss" scoped>
.public-layout {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
}

.public-layout__main {
  flex: 1 1 auto;
  min-width: 0;
}

.public-layout__footer {
  margin-top: auto;
  padding: 1.25rem 1.5rem 1.5rem;
  border-top: 1px solid var(--border);
  background: var(--bg);
}

.public-layout__copyright {
  margin: 0;
  font-size: 0.9rem;
  opacity: 0.88;
}

.public-layout__tagline {
  margin: 0.35rem 0 0.75rem;
  font-size: 0.8rem;
  opacity: 0.75;
}

.public-layout__footer-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
}

.public-layout__footer-link {
  color: var(--link);
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: none;

  &:hover {
    text-decoration: underline;
  }
}
</style>
