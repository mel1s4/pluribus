# Public pages content questionnaire

Use this document to gather copy, facts, and legal inputs for the **home**, **contact**, and **legal** public routes (`/`, `/contact`, `/legal`). Answers here can drive i18n strings, layout decisions, and legal drafts.

**Related code:** `frontend/src/router/index.js` (routes), `frontend/src/components/public/PublicNav.vue` (nav), `frontend/src/i18n/locales/*.js` (`home.`*, `contact.`*, `legal.*`).

---

## How to use

- Answer in this file, a spreadsheet, or a shared doc; keep **one clear answer per question** where possible.
- Mark **Owner** (who must approve) and **Source of truth** (URL, lawyer, company registry) for legal and company facts.
- For skipped items, write **N/A** or **Later** so implementers do not invent copy.

---

## A. Product, audience, and voice


| #   | Question                                                                                                   | Answer                                                                                      |
| --- | ---------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| A1  | **One-line product definition** — what the product is, in plain language for a stranger.                   | Nuestra Chante its a platform for Communities. We sell the administration of one community. |
| A2  | **Primary audience** (e.g. residents, organizers, merchants) and **secondary** audience, if any.           | Anyone who wishes to start, or be part of, a community.                                     |
| A3  | **Primary job-to-be-done** — the main problem you solve, in one sentence.                                  | Nuestra Chante is a Social Network for Neighborhs that aims to generate Circular Economies  |
| A4  | **Tone** — pick 2–3 adjectives (e.g. warm, direct, formal) and list **words or phrases to avoid**.         | warm. Formal. diract.                                                                       |
| A5  | **Languages/locales** at launch — which content is professionally translated vs. English-only for v1?      | everything needs to be translated to spanish ans english                                    |
| A6  | **Community vs. product name** — legal entity name vs. name shown in the public header; same or different? | The name of the platform and therefore the name of the product is Nuestra Chante            |
| A7  | **Stage messaging** — private beta, waitlist, general availability; what should the home page imply?       | The software is currently in beta.                                                          |


---

## B. Home page (`/`)

### Hero (above the fold)


| #   | Question                                                                                                                            | Answer                                                                                     |
| --- | ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| B1  | **Headline** — short, memorable (aim for ~60 characters or less).                                                                   | "Una red social de economia circular"                                                      |
| B2  | **Subheadline** — one or two sentences under the headline.                                                                          | Ayudamos a comunidades a ser mas productivas y a organizarse para lograr proyectos juntos. |
| B3  | **Primary CTA** — button label and destination (route or URL).                                                                      | Crea una Comunidad                                                                         |
| B4  | **Secondary CTA** (optional) — label and destination.                                                                               | Ya eres parte de una? Inicia sesion.                                                       |
| B5  | **Hero visual** — photo, illustration, screenshot, or none? If an asset: preferred aspect ratio and **alt text** for accessibility. | houses                                                                                     |


### Trust and clarity


| #   | Question                                                                                                         | Answer                                                                                                                                                                                                                                                                                                                                                    |
| --- | ---------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| B6  | **Value props** — 3–5 items: **title + one sentence** each, in priority order (1 = most important).              | Leverage the power of your community. Sell online to multiple languages. Achieve common goals together.                                                                                                                                                                                                                                                   |
| B7  | **Social proof** (if any) — quotes, logos, metrics; exact wording and confirmation that names/logos may be used. | "The trusted platform for online sales at Coyoacan Mexico. See the results"                                                                                                                                                                                                                                                                               |
| B8  | **“How it works”** (optional) — three steps: step title + one line each.                                         | We allow members of communities to publish what they offer and what they need, as well as voting in projets they care about. With known systems that have been proven to help people organize themselves, we aim at communities to create the best digital experience that can help them grow and prosper wherever they are, and for anyone who joins it. |


### Product highlights (optional on home)


| #   | Question                                                                                                                       | Answer                                                                                                                                                                                                                               |
| --- | ------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| B9  | **Feature blocks** to highlight (e.g. map, chats, tasks): name, benefit-oriented blurb, link (in-app, docs, or “coming soon”). | See the Places your Neighbors wants you to know about in an interactive map. Organize with your neighborhs on any topic with Folder-Organized unlimited 1v1 chats. Track the progress of your goals with the inegrated task manager. |
| B10 | **Screenshots or demo** — yes/no; if yes, list screens and captions.                                                           | yes, place minisite                                                                                                                                                                                                                  |


### Footer and cross-page


| #   | Question                                                                                                               | Answer                                                |
| --- | ---------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------- |
| B11 | **Copyright line** — exact wording; use auto-updating year or a fixed year rule.                                       | Rights reserved to Nuestra Chante (2026).             |
| B12 | **Footer links** — mirror Contact and Legal only, or add social links, status page, blog, etc.? List each label + URL. | Contact, Legal, social list (use a json) status page, |


---

## C. Contact page (`/contact`)

### Purpose and structure


| #   | Question                                                                                                                           | Answer            |
| --- | ---------------------------------------------------------------------------------------------------------------------------------- | ----------------- |
| C1  | **Purpose** — general inquiries, support, press, partnerships, or combined? Should the page use **separate sections** per channel? | general inquires. |


### Channels (publish exactly what should appear)


| #   | Question                                                                                                             | Answer                                                                                                                              |
| --- | -------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| C2  | **Email(s)** — address, purpose per address, and **expected response time** (SLA copy for users).                    | [melisa@vzs.mx](mailto:melisa@vzs.mx) [soporte@chante.vzs.mx](mailto:soporte@vzs.mx)[support@chante.vzs.mx](mailto:support@vzs.mx) |
| C3  | **Phone** (if public) — number, hours, timezone, languages spoken.                                                   |                                                                                                                                     |
| C4  | **Physical address** (if shown) — full formatted address; note if a short one-line variant is needed for the footer. | none                                                                                                                                |
| C5  | **Social links** — platform, URL, display style (icon only vs. @handle text).                                        | later, mock em with #                                                                                                               |
| C6  | **Contact method for v1** — mailto links only, single public email, or plan for a form later?                        | a form that send an email. add some bot protection                                                                                  |


### Operations copy


| #   | Question                                                                                                       | Answer                                                               |
| --- | -------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| C7  | **Support hours** and out-of-office / holiday note (short paragraph).                                          | mon - friday 11am to 3pm.                                            |
| C8  | **What to include when contacting** — bullets (e.g. browser, account email, steps to reproduce).               | hm make a basic, industry standard one                               |
| C9  | **Abuse or safety reporting** — same inbox or separate; one-line instruction.                                  | send emails to [contact@chante.vzs.mx](mailto:contact@chante.vzs.mx) |
| C10 | **Privacy / data protection contact** — DPO or dedicated privacy inbox, if published (often on Legal instead). | [privacy@chante.vzs.mx](mailto:privacy@chante.vzs.mx)                |


### Legal identity on contact


| #   | Question                                                                                      | Answer                             |
| --- | --------------------------------------------------------------------------------------------- | ---------------------------------- |
| C11 | **Legal name** and **trading name** (if different) as they should appear on the contact page. | Nuestra Chante Organizacion Social |


---

## D. Legal page (`/legal`)

*This section collects **inputs for drafting**; final terms and privacy text should be reviewed by qualified counsel.*

### Structure and maintenance


| #   | Question                                                                                                      | Answer         |
| --- | ------------------------------------------------------------------------------------------------------------- | -------------- |
| D1  | **Structure** — single page with anchor sections vs. separate URLs (e.g. `/privacy`); list final URL pattern. | separate urls  |
| D2  | **“Last updated” policy** — who sets the date and how often documents are reviewed.                           | hardcoded date |


### Identity and jurisdiction


| #   | Question                                                                                                    | Answer                                              |
| --- | ----------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| D3  | **Legal entity** — full registered name, registration number, registered address, country of incorporation. | Organizacion Social Nuestra Chante                  |
| D4  | **Governing law and venue** — jurisdiction for disputes (lawyer-supplied wording; note region here).        | Mexico Jurisdiction,                                |
| D5  | **Data controller / operator** — name and address if different from the legal entity above.                 | Melisa Viroz, [melisa@vzs.mx](mailto:melisa@vzs.mx) |


### Privacy (factual inputs)


| #   | Question                                                                                                                              | Answer                                                          |
| --- | ------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| D6  | **Categories of personal data** collected (e.g. account, profile, location, messages, payments).                                      | check the code to answer                                        |
| D7  | **Purposes of processing** (service delivery, analytics, marketing, legal obligation, etc.).                                          | all of the above                                                |
| D8  | **Legal bases** (where applicable, e.g. GDPR) — lawyer maps; you list what you actually do (consent, contract, legitimate interests). |                                                                 |
| D9  | **Third parties / subprocessors** — names or categories (hosting, email, maps, analytics) and policy links if required.               | we host at inmotionhosting                                      |
| D10 | **International transfers** — do data leave the user’s region? To which regions or safeguards?                                        |                                                                 |
| D11 | **Retention** — high-level rules for accounts, logs, messages, backups.                                                               | be as protective of owr interest as possible                    |
| D12 | **User rights** — which rights you honor and **how to exercise** them (email, in-app form, etc.).                                     | email only. [urgent@chante.vzs.mx](mailto:urgent@chante.vzs.mx) |
| D13 | **Children** — minimum age; whether the service is directed at minors.                                                                | No., Adults only                                                |
| D14 | **Cookies and local storage** — categories used; whether a cookie banner is planned for v1.                                           | check the code                                                  |


### Terms of use (factual inputs)


| #   | Question                                                                                                      | Answer                                                                                                                                               |
| --- | ------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| D15 | **Eligibility** — who may use the service (age, residency, organization type).                                | anyone in Mexico                                                                                                                                     |
| D16 | **Account and acceptable use** — prohibited conduct you care to call out (bullets).                           | do not publish criminal stuff                                                                                                                        |
| D17 | **User content** — ownership, license needed to run the service, moderation or takedown process (high level). | protext our interests.                                                                                                                               |
| D18 | **Subscriptions and payments** — billing, refunds, price changes; or **N/A** if not applicable.               | N/A. Relates to the admins of the Place (in case of community based purchases) subscriptions to Nuestra Chante as a Community does not have a refund |
| D19 | **Beta or free tier** — factual description for liability / disclaimer context (lawyer drafts language).      | it is in beta. There is no free tier                                                                                                                 |
| D20 | **Termination** — when accounts may be suspended or deleted; how users may delete their account.              | for criminal use. If we can suspect that someone is planning to use it for criminal purposes                                                         |
| D21 | **Changes to terms** — how users are notified (email, in-app, posted date only).                              | in-app. Updated date and sometimes via email                                                                                                         |


### Other legal blocks


| #   | Question                                                                         | Answer    |
| --- | -------------------------------------------------------------------------------- | --------- |
| D22 | **Cookie policy** — separate page or section; link targets.                      | please do |
| D23 | **Imprint / legal notice** — fields required in your jurisdictions (e.g. EU).    |           |
| D24 | **Accessibility statement** — target conformance level and contact for feedback. |           |
| D25 | **Open-source notices** — link to `NOTICE` or dedicated licenses page, if any.   |           |


---

## E. SEO, sharing, and analytics


| #   | Question                                                                                 | Answer |
| --- | ---------------------------------------------------------------------------------------- | ------ |
| E1  | `**<title>` pattern** for home, contact, and legal (e.g. `{page} · {product name}`).     |        |
| E2  | **Meta descriptions** — one per public page (~150 characters each).                      |        |
| E3  | **Open Graph / Twitter** — title, description, image URL, or “none for v1.”              |        |
| E4  | **Canonical host** — www vs non-www, HTTPS enforcement.                                  |        |
| E5  | **Indexing** — index all public pages or `noindex` for any?                              |        |
| E6  | **Analytics on public pages** — yes/no; tool name and what must be disclosed in privacy. |        |


---

## F. Design and implementation handoff


| #   | Question                                                                      | Answer |
| --- | ----------------------------------------------------------------------------- | ------ |
| F1  | **Assets** — paths or links for logo (SVG/PNG), favicon, press kit.           |        |
| F2  | **Do-not-translate** list — product name, legal names, addresses, trademarks. |        |
| F3  | **Sign-off** — who approves product copy, brand, and legal text.              |        |


---

## Suggested fill order

1. **Sections A and B** — unblocks home and overall voice.
2. **Section C** — unblocks contact.
3. **Section D** — with legal review; depends on accurate product and data practices.
4. **Sections E and F** — in parallel once naming and legal entity are stable.

---

## Sign-off checklist

- Product / marketing owner reviewed A and B  
- Operations or support owner reviewed C  
- Counsel reviewed D (or external policy templates applied to your facts)  
- SEO / growth reviewed E  
- Design or frontend has F assets and do-not-translate list  

---

## Draft answers (from repository, pending stakeholder)

*Generated from [README.md](README.md), app routes, and shipped i18n copy.
Replace or delete rows after your team confirms. **TBD** = must be supplied;
do not treat stubs as legal fact.*

### A. Product, audience, voice

| # | Draft |
|---|--------|
| A1 | Community platform with WhatsApp-like chat UX, extended with stores, map, offers, tasks, and related tools (“Pluribus — world of ideas”). |
| A2 | **Primary:** community members and organizers. **Secondary:** merchants where stores/commerce are enabled. |
| A3 | Coordinate a named community in one place: chat, places, offers, orders, without losing context. |
| A4 | **Tone:** calm, direct, utilitarian (industrial design README). **Avoid:** overpromising legal guarantees; fake metrics. |
| A5 | English and Spanish strings exist in the app; other locales **TBD**. |
| A6 | Header shows API-driven community name when set; falls back to “Community”. Legal entity **TBD** (C11/D3). |
| A7 | Neutral: no waitlist copy in UI; stage **TBD** per product launch. |

### B. Home

| # | Draft |
|---|--------|
| B1 | “Your community, one calm workspace.” (`home.heroTitle`) |
| B2 | Hero subtitle ties Pluribus to `{name}` (branding) and chats/places/offers/tasks (`home.heroSubtitle`). |
| B3 | Primary: “Sign in” → `/login`. Secondary: “How it works” → `#how` on home. |
| B4 | See B3 (secondary is in-page anchor). |
| B5 | **TBD** — no hero image in v1. |
| B6 | Three value props: chat-first coordination; places and commerce; multilingual + theme (`home.valueProp*`). |
| B7 | **TBD** — no quotes or logos until approved. |
| B8 | Three steps: join community; use sidebar modules; commerce next to chat (`home.howStep*`). |
| B9 | Covered implicitly in value props / steps; deep links to app **TBD** for logged-out users. |
| B10 | No screenshots in v1. |
| B11 | Footer: `© {year} {name}.` with dynamic year and `displayName` (`publicFooter.copyright`). |
| B12 | Footer: Contact + Legal routes only; tagline line “Pluribus — world of ideas.” |

### C. Contact

| # | Draft |
|---|--------|
| C1 | Combined: general, community-specific, abuse (`contact.*` sections). |
| C2 | **TBD** unless `VITE_PUBLIC_CONTACT_EMAIL` is set in deployment; then that address is shown with mailto. |
| C3–C5 | **TBD** (phone, postal address, social) — not shown without data. |
| C6 | Mailto when env set; otherwise copy-only. Form **Later**. |
| C7 | **TBD** — no SLA copy until operations defines it. |
| C8 | **TBD** — add bullets when support process is defined. |
| C9 | Abuse paragraph directs to operator / env email / authorities (`contact.abuseBody`). |
| C10 | **TBD** — DPO inbox not published. |
| C11 | **TBD** — legal / trading name for contact page. |

### D. Legal

| # | Draft |
|---|--------|
| D1 | Single `/legal` page with anchor sections `#terms`, `#privacy`, `#cookies` (stub intros, not binding policies). |
| D2 | **TBD** — “last updated” not shown until counsel assigns owner. |
| D3–D5 | **TBD** — entity, governing law, controller (operator fills). |
| D6–D14 | High-level factual list only (accounts, messaging, places, commerce) in UI; full privacy notice **TBD**. |
| D15–D21 | Stub text defers to operator; eligibility, payments, etc. **TBD** per deployment. |
| D22 | Cookies covered as stub section on same page. |
| D23–D25 | **TBD** (imprint, a11y statement, OSS notices). |

### E. SEO / analytics

| # | Draft |
|---|--------|
| E1 | Implemented: `{pageTitle} · {brand}` via `documentTitle.js` and route names. |
| E2–E3 | **TBD** — meta descriptions and OG image not wired in this pass. |
| E4–E5 | **TBD** per hosting. |
| E6 | **TBD** — disclose in privacy when analytics added. |

### F. Handoff

| # | Draft |
|---|--------|
| F1 | Community logo from branding API when present; press kit **TBD**. |
| F2 | “Pluribus” as product name in footer tagline; community name from API. |
| F3 | **TBD** — name reviewers for copy vs. legal. |