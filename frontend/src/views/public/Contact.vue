<template>
  <div class="page--contact">
    <Title tag="h1">{{ t('contact.title') }}</Title>
    <p class="page--contact__intro">{{ t('contact.intro') }}</p>

    <section class="page--contact__block" aria-labelledby="contact-general-heading">
      <h2 id="contact-general-heading" class="page--contact__h2">
        {{ t('contact.sectionGeneral') }}
      </h2>
      <p>{{ t('contact.generalBody') }}</p>
      <p v-if="supportEmail" class="page--contact__email">
        <a class="page--contact__mailto" :href="mailtoHref">{{ t('contact.emailLinkLabel') }}</a>
        <span class="page--contact__email-address">({{ supportEmail }})</span>
      </p>
    </section>

    <section class="page--contact__block" aria-labelledby="contact-community-heading">
      <h2 id="contact-community-heading" class="page--contact__h2">
        {{ t('contact.sectionCommunity') }}
      </h2>
      <p>{{ communityLine }}</p>
    </section>

    <section class="page--contact__block" aria-labelledby="contact-abuse-heading">
      <h2 id="contact-abuse-heading" class="page--contact__h2">
        {{ t('contact.sectionAbuse') }}
      </h2>
      <p>{{ t('contact.abuseBody') }}</p>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useCommunity } from '../../composables/useCommunity'
import { language, t } from '../../i18n/i18n'
import Title from '../../atoms/Title.vue'

const { displayName } = useCommunity()

const supportEmail = computed(() => {
  const raw = import.meta.env.VITE_PUBLIC_CONTACT_EMAIL
  if (typeof raw !== 'string') {
    return ''
  }
  const v = raw.trim()
  return v.length && v.includes('@') ? v : ''
})

const mailtoHref = computed(() => `mailto:${supportEmail.value}`)

const communityLine = computed(() => {
  void language.value
  return t('contact.communityBody').replace('{name}', displayName.value)
})
</script>

<style lang="scss" scoped>
.page--contact {
  padding: 2rem;
  max-width: 40rem;
  margin: 0 auto;
}

.page--contact__intro {
  margin: 0 0 1.5rem;
  line-height: 1.55;
}

.page--contact__block {
  margin-top: 1.75rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--border);

  p {
    margin: 0.5rem 0 0;
    line-height: 1.55;
    font-size: 0.98rem;
  }
}

.page--contact__h2 {
  margin: 0 0 0.5rem;
  font-size: 1.1rem;
  font-weight: 700;
}

.page--contact__email {
  margin-top: 0.75rem;
}

.page--contact__mailto {
  font-weight: 600;
  color: var(--link);
  word-break: break-all;
}

.page--contact__email-address {
  margin-left: 0.35rem;
  font-size: 0.9rem;
  opacity: 0.88;
  word-break: break-all;
}
</style>
