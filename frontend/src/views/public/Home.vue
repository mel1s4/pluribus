<template>
  <div class="page--home">
    <section class="page--home__hero" id="top" aria-labelledby="home-hero-heading">
      <Title id="home-hero-heading" tag="h1">{{ t('home.heroTitle') }}</Title>
      <p class="page--home__lead">{{ heroSubtitle }}</p>
      <div class="page--home__actions">
        <RouterLink class="page--home__cta page--home__cta--primary" to="/login">
          {{ t('home.ctaSignIn') }}
        </RouterLink>
        <a class="page--home__cta page--home__cta--secondary" href="#how">
          {{ t('home.ctaSecondary') }}
        </a>
      </div>
    </section>

    <section class="page--home__section" aria-labelledby="home-values-heading">
      <Title id="home-values-heading" tag="h2">{{ t('home.valuePropsTitle') }}</Title>
      <ul class="page--home__cards">
        <li class="page--home__card">
          <h3 class="page--home__card-title">{{ t('home.valueProp1Title') }}</h3>
          <p>{{ t('home.valueProp1Body') }}</p>
        </li>
        <li class="page--home__card">
          <h3 class="page--home__card-title">{{ t('home.valueProp2Title') }}</h3>
          <p>{{ t('home.valueProp2Body') }}</p>
        </li>
        <li class="page--home__card">
          <h3 class="page--home__card-title">{{ t('home.valueProp3Title') }}</h3>
          <p>{{ t('home.valueProp3Body') }}</p>
        </li>
      </ul>
    </section>

    <section id="how" class="page--home__section" aria-labelledby="home-how-heading">
      <Title id="home-how-heading" tag="h2">{{ t('home.howTitle') }}</Title>
      <ol class="page--home__steps">
        <li>
          <span class="page--home__step-title">{{ t('home.howStep1Title') }}</span>
          <p>{{ t('home.howStep1Body') }}</p>
        </li>
        <li>
          <span class="page--home__step-title">{{ t('home.howStep2Title') }}</span>
          <p>{{ t('home.howStep2Body') }}</p>
        </li>
        <li>
          <span class="page--home__step-title">{{ t('home.howStep3Title') }}</span>
          <p>{{ t('home.howStep3Body') }}</p>
        </li>
      </ol>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useCommunity } from '../../composables/useCommunity'
import { language, t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'

const { displayName } = useCommunity()

const heroSubtitle = computed(() => {
  void language.value
  return t('home.heroSubtitle').replace('{name}', displayName.value)
})
</script>

<style lang="scss" scoped>
.page--home {
  padding: 2rem;
  max-width: 52rem;
  margin: 0 auto;
}

.page--home__hero {
  margin-bottom: 2.5rem;
}

.page--home__lead {
  margin: 0.75rem 0 1.25rem;
  font-size: 1.05rem;
  line-height: 1.55;
  color: var(--text);
  opacity: 0.92;
}

.page--home__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  align-items: center;
}

.page--home__cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.6rem 1rem;
  font-size: 1rem;
  font-weight: 600;
  line-height: 1;
  border-radius: 0.5rem;
  text-decoration: none;
  transition: background-color 140ms ease, color 140ms ease, border-color 140ms ease,
    opacity 140ms ease;
}

.page--home__cta--primary {
  background: var(--link);
  color: #fff;
  border: 1px solid var(--link);

  &:hover {
    opacity: 0.92;
  }
}

.page--home__cta--secondary {
  background: var(--btn-bg);
  color: var(--btn-text);
  border: 1px solid var(--border);

  &:hover {
    background: var(--btn-bg-hover);
  }
}

.page--home__section {
  margin-top: 2.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--border);
}

.page--home__cards {
  list-style: none;
  margin: 1rem 0 0;
  padding: 0;
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
}

.page--home__card {
  padding: 1rem 1.1rem;
  border: 1px solid var(--border);
  border-radius: 0.5rem;
  background: var(--bg);

  p {
    margin: 0.35rem 0 0;
    font-size: 0.95rem;
    line-height: 1.5;
    opacity: 0.9;
  }
}

.page--home__card-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.page--home__steps {
  margin: 1rem 0 0;
  padding-left: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;

  p {
    margin: 0.35rem 0 0;
    font-size: 0.95rem;
    line-height: 1.5;
    opacity: 0.9;
  }
}

.page--home__step-title {
  font-weight: 600;
}
</style>
