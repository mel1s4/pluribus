<template>
  <div class="page--legal">
    <Title tag="h1">{{ t('legal.title') }}</Title>
    <p class="page--legal__intro">{{ t('legal.intro') }}</p>
    <p class="page--legal__warn">{{ t('legal.disclaimer') }}</p>

    <nav class="page--legal__toc" aria-label="Legal sections">
      <a class="page--legal__toc-link" href="#terms">{{ t('legal.navTerms') }}</a>
      <a class="page--legal__toc-link" href="#privacy">{{ t('legal.navPrivacy') }}</a>
      <a class="page--legal__toc-link" href="#cookies">{{ t('legal.navCookies') }}</a>
    </nav>

    <section id="terms" class="page--legal__section" tabindex="-1">
      <Title tag="h2">{{ t('legal.sectionTermsTitle') }}</Title>
      <p>{{ t('legal.sectionTermsBody') }}</p>
    </section>

    <section id="privacy" class="page--legal__section" tabindex="-1">
      <Title tag="h2">{{ t('legal.sectionPrivacyTitle') }}</Title>
      <p>{{ t('legal.sectionPrivacyBody') }}</p>
    </section>

    <section id="cookies" class="page--legal__section" tabindex="-1">
      <Title tag="h2">{{ t('legal.sectionCookiesTitle') }}</Title>
      <p>{{ t('legal.sectionCookiesBody') }}</p>
    </section>

    <section class="page--legal__section" aria-labelledby="legal-ctx-heading">
      <Title id="legal-ctx-heading" tag="h2">{{ t('legal.softwareContextTitle') }}</Title>
      <p>{{ t('legal.softwareContextIntro') }}</p>
      <ul class="page--legal__list">
        <li>{{ t('legal.ctxAccounts') }}</li>
        <li>{{ t('legal.ctxMessaging') }}</li>
        <li>{{ t('legal.ctxPlaces') }}</li>
        <li>{{ t('legal.ctxCommerce') }}</li>
      </ul>
    </section>

    <p class="page--legal__hint">
      {{ t('legal.contactHint') }}
      <RouterLink to="/contact">{{ t('legal.contactLink') }}</RouterLink>
    </p>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import { t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'
</script>

<style lang="scss" scoped>
.page--legal {
  padding: 2rem;
  max-width: 42rem;
  margin: 0 auto;
}

.page--legal__intro,
.page--legal__warn {
  line-height: 1.55;
  margin: 0 0 0.75rem;
}

.page--legal__warn {
  font-size: 0.95rem;
  opacity: 0.9;
}

.page--legal__toc {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1rem;
  margin: 1.25rem 0 1.5rem;
}

.page--legal__toc-link {
  color: var(--link);
  font-weight: 600;
  font-size: 0.95rem;
}

.page--legal__section {
  margin-top: 1.75rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--border);

  p {
    margin: 0.5rem 0 0;
    line-height: 1.55;
    font-size: 0.98rem;
  }
}

.page--legal__list {
  margin: 0.75rem 0 0;
  padding-left: 1.25rem;
  line-height: 1.55;
}

.page--legal__hint {
  margin-top: 2rem;
  font-size: 0.95rem;
  line-height: 1.5;
  opacity: 0.9;
}
</style>
