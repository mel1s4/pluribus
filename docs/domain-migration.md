# Domain migration: pluribus.vzs.mx

Canonical SPA host: **https://pluribus.vzs.mx**  
API (unchanged): **https://chante-api.vzs.mx**  
Legacy SPA host: **https://chante.vzs.mx** → 301 to pluribus (same path and query)

## Infrastructure checklist (cPanel / DNS)

Complete these **before** or **with** the first deploy that includes the new `.htaccess`:

- [ ] DNS A or CNAME for `pluribus.vzs.mx` → same server as `chante.vzs.mx`
- [ ] Addon domain or alias: `pluribus.vzs.mx` document root = `prod/frontend` (same as `chante.vzs.mx`)
- [ ] SSL (AutoSSL / Let's Encrypt) for `pluribus.vzs.mx`
- [ ] Keep `chante.vzs.mx` on the **same** `prod/frontend` so [frontend/public/.htaccess](../frontend/public/.htaccess) redirect rules apply
- [ ] Do **not** use cPanel “redirect entire domain to homepage” — it breaks `/join/...` and other deep links

## Application configuration

| File | Purpose |
|------|---------|
| `backend/.env.production` | `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`; upload with `./deploy.sh env` |
| `frontend/.env.production` | `VITE_API_BASE_URL=https://chante-api.vzs.mx` |

After uploading backend env on the server:

```bash
php artisan config:clear
php artisan config:cache
```

## Deploy

```bash
./deploy.sh env        # backend .env
./deploy.sh frontend   # SPA + .htaccess
```

## Verification

```bash
# Use browser-like Accept; some hosts return 406 to curl's default Accept: */*
curl -sI -A 'Mozilla/5.0' -H 'Accept: text/html' \
  'https://chante.vzs.mx/join/test' | grep -iE '^(HTTP|location):'

curl -sI -A 'Mozilla/5.0' -H 'Accept: text/html' \
  'https://pluribus.vzs.mx/login' | grep -iE '^(HTTP|location):'
```

Manual checks:

- [ ] `https://chante.vzs.mx/login` → `https://pluribus.vzs.mx/login`
- [ ] `https://pluribus.vzs.mx/login` loads without redirect loop
- [ ] Login works (no 419 / CORS errors)
- [ ] New invitation share link redirects to `pluribus.vzs.mx/join/...`

## How redirects work

Apache rules at the top of `frontend/public/.htaccess`:

```apache
RewriteCond %{HTTP_HOST} ^(www\.)?chante\.vzs\.mx$ [NC]
RewriteRule ^ https://pluribus.vzs.mx%{REQUEST_URI} [R=301,L]
```

Email invitations that use `chante-api.vzs.mx/join-invitation-share/...` redirect to the SPA via `FRONTEND_URL` in Laravel (no change to API hostname required).
