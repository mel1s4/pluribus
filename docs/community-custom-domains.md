# Community custom domains

Each community can serve the Pluribus SPA on its own hostname (for example `river.example.org`). The API stays on the central host (for example `chante-api.vzs.mx`); the browser sends session cookies cross-origin when the community domain is listed in Sanctum stateful domains.

## DNS and hosting (cPanel)

For each community domain:

1. Create an **addon domain** (or alias) pointing to the **same document root** as `pluribus.vzs.mx` (`prod/frontend`).
2. Enable **SSL** (AutoSSL / Let's Encrypt) for that host.
3. Do **not** use “redirect entire domain to homepage” — it breaks `/login`, `/visitor-auth/...`, and other deep links.
4. Add the hostname (without `www.`, or add both) in the admin UI under **Communities → Edit → Custom domains**.

## Backend configuration

| Variable | Purpose |
|----------|---------|
| `PLURIBUS_PLATFORM_HOSTS` | Comma-separated hosts that are **not** community sites (`localhost`, `pluribus.vzs.mx`, …) |
| `SANCTUM_STATEFUL_DOMAINS` | Must include every community SPA host (verified domains are merged automatically at boot when the table exists) |
| `FRONTEND_URL` | Default SPA origin for emails when a community has no primary domain |

Example (`backend/.env`):

```env
PLURIBUS_PLATFORM_HOSTS=localhost,127.0.0.1,pluribus.vzs.mx,www.pluribus.vzs.mx
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:9123,127.0.0.1,127.0.0.1:9123,pluribus.vzs.mx,river.example.org
```

After changing env on the server:

```bash
php artisan config:clear
php artisan config:cache
```

## How it works

1. The SPA calls `GET /api/community/resolve-host` using the page `Host`.
2. The API loads the community from `community_domains` and sets `active_community` for the request.
3. Authenticated requests from that host scope branding, capabilities, and guest membership to that community.
4. **Members** sign in with email/password (must already belong to the community).
5. **Guests** use “Enter as guest” (magic link) or password login with guest intent; they receive the `visitor` role in that community only.

## Local testing

Add entries to `/etc/hosts`:

```
127.0.0.1 river.local
```

Register `river.local` on a community in the admin UI, add it to `SANCTUM_STATEFUL_DOMAINS`, open `http://river.local:9123`, and use the Vite dev server on that host.

## Related docs

- [domain-migration.md](domain-migration.md) — canonical Pluribus SPA host and legacy redirects
