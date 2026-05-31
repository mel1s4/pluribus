<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { t } from '../../i18n/i18n'

const props = defineProps({
  communitySlug: {
    type: String,
    default: null,
  },
})

const slug = computed(() => {
  const s = props.communitySlug
  return typeof s === 'string' && s.trim() !== '' ? s.trim() : ''
})
</script>

<template>
  <nav class="join-invitation-legal-links" aria-labelledby="join-invitation-legal-heading">
    <h2 id="join-invitation-legal-heading" class="join-invitation-legal-links__heading">
      {{ t('joinInvitation.legalHeading') }}
    </h2>
    <p class="join-invitation-legal-links__intro">{{ t('joinInvitation.legalIntro') }}</p>
    <p class="join-invitation-legal-links__group-label">{{ t('joinInvitation.legalAppGroup') }}</p>
    <ul class="join-invitation-legal-links__list">
      <li>
        <RouterLink class="join-invitation-legal-links__link" to="/legal#terms">
          {{ t('joinInvitation.legalAppTerms') }}
        </RouterLink>
      </li>
      <li>
        <RouterLink class="join-invitation-legal-links__link" to="/legal#privacy">
          {{ t('joinInvitation.legalAppPrivacy') }}
        </RouterLink>
      </li>
    </ul>
    <template v-if="slug !== ''">
      <p class="join-invitation-legal-links__group-label join-invitation-legal-links__group-label--community">
        {{ t('joinInvitation.legalCommunityGroup') }}
      </p>
      <ul class="join-invitation-legal-links__list">
        <li>
          <RouterLink
            class="join-invitation-legal-links__link"
            :to="{ name: 'communityLegalPublic', params: { communitySlug: slug, document: 'terms' } }"
          >
            {{ t('joinInvitation.legalCommunityTerms') }}
          </RouterLink>
        </li>
        <li>
          <RouterLink
            class="join-invitation-legal-links__link"
            :to="{ name: 'communityLegalPublic', params: { communitySlug: slug, document: 'privacy' } }"
          >
            {{ t('joinInvitation.legalCommunityPrivacy') }}
          </RouterLink>
        </li>
      </ul>
    </template>
  </nav>
</template>

<style lang="scss" scoped>
.join-invitation-legal-links {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border, #e5e7eb);
}

.join-invitation-legal-links__heading {
  margin: 0 0 0.35rem;
  font-size: 1rem;
  font-weight: 600;
}

.join-invitation-legal-links__intro {
  margin: 0 0 0.75rem;
  font-size: 0.88rem;
  line-height: 1.45;
  color: var(--muted, #4b5563);
}

.join-invitation-legal-links__group-label {
  margin: 0 0 0.25rem;
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--muted, #6b7280);
}

.join-invitation-legal-links__group-label--community {
  margin-top: 0.65rem;
}

.join-invitation-legal-links__list {
  margin: 0;
  padding-left: 1.1rem;
  line-height: 1.55;
  font-size: 0.92rem;
}

.join-invitation-legal-links__link {
  color: var(--link, #2563eb);
  font-weight: 500;
}
</style>
