# Pluribus: World of Ideas

We want to clone the UX from WhatsApp
and extend the functionalities of communities
Adding support for Stores, Map, Offers, and more.


## Tech Stack

- Frontend: Vue 3
- Backend: Laravel
- Database: PostgreSQL
- Hosting: Inmotion Hosting

### Production domains

See [domain-migration.md](domain-migration.md) for SPA/API hosts, legacy redirects, and deploy checklist.

See [community-custom-domains.md](community-custom-domains.md) for per-community SPA hosts and guest access.

### Frontend environment (Vite)

Optional variables for the Vue app (see `frontend/.env` or deployment env):

- **`VITE_PUBLIC_CONTACT_EMAIL`** — If set to a valid email, the public
  **Contact** page shows a `mailto:` link for platform or operator support.
  If unset, the page explains how to reach organizers without inventing an
  address.

### Philosophies
- No more than 80 characters per line
- No more than 300 lines per file
- Atomic design for components

## Features

- User Authentication
- Community Management
- Store Management
- Multi lingual support
- Light / Dark mode

### Admin
- Login
- Dashboard
- Users
- Settings


## Design Philosophy
"Industrial Design"
- Simple
- Clean
- Utilitarian
- Mobile-first