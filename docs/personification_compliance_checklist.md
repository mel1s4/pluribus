# User personification — compliance checklist (stakeholders)

This checklist supports privacy, employment, and security governance. It does not constitute legal advice; counsel should review for your jurisdictions (for example GDPR, LGPD, CCPA).

## Policy and transparency

- [ ] Document the **business purpose** of personification (support, incident response, QA) in internal policy.
- [ ] Update the **privacy notice** and/or terms to describe privileged access, who may use it, and typical duration.
- [ ] Decide whether **end users are notified** after a session (or in real time) and record the decision with rationale.

## Lawful basis and proportionality

- [ ] Assign a **lawful basis** under applicable privacy law (e.g. legitimate interest with balancing test, contract, legal obligation).
- [ ] Confirm personification is **necessary and proportionate** compared to alternatives (screen share, user-guided steps).
- [ ] Require a **specific, non-generic reason** and optional ticket reference for every session (enforced in product).

## Technical and organizational measures

- [ ] Restrict personification to **root and developer** roles; review developer roster regularly.
- [ ] Enforce **password re-entry** before starting a session; keep **time and idle limits** aligned with policy.
- [ ] Protect **audit logs** (append-only storage, access control, integrity monitoring as appropriate).
- [ ] Define **log retention** and deletion/anonymization procedures consistent with privacy obligations.

## Employment and access lifecycle

- [ ] Tie “developer” (and root) access to **HR onboarding/offboarding**; revoke promptly on role change or exit.
- [ ] Run **periodic access reviews** for accounts that may personify.

## Incidents and user rights

- [ ] Define process for **data subject requests** when personification may have accessed personal data.
- [ ] Include personification in **security incident** playbooks (abuse, credential theft, over-collection).

## Sign-off

- [ ] Privacy / DPO review  
- [ ] Security review  
- [ ] Product owner sign-off  
