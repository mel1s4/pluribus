# 404 Error Page and Vue Router Configuration

## Problem

When accessing SPA routes directly on production (e.g. `https://pluribus.vzs.mx/join/abc123`), Apache can return 404 instead of the Vue app. Vue Router history mode needs requests rewritten to `index.html`.

Legacy links on `https://chante.vzs.mx/...` must keep working via **301** to the same path on `https://pluribus.vzs.mx`.

## Solution

### 1. Apache Configuration (`.htaccess`)

`frontend/public/.htaccess` includes:

- **301** from `chante.vzs.mx` (and `www`) to `pluribus.vzs.mx`, preserving path and query
- URL rewriting for Vue Router (`index.html` fallback)
- Custom 404 page, security headers, compression and caching

### 2. Custom 404 Error Page

`frontend/public/404.html` — friendly error UI with links home / login.

## Deployment

```bash
./deploy.sh env       # if FRONTEND_URL / Sanctum changed
./deploy.sh frontend  # SPA + .htaccess + 404.html

# Or
./deploy.sh all
```

See `docs/domain-migration.md` for DNS, SSL, and vhost checklist.

## How It Works

### Legacy host (chante.vzs.mx)

1. Request hits `chante.vzs.mx/join/TOKEN`
2. `.htaccess` matches host → **301** to `https://pluribus.vzs.mx/join/TOKEN`
3. Browser loads pluribus; Vue Router handles the route

### Canonical host (pluribus.vzs.mx)

1. User visits `https://pluribus.vzs.mx/join/TOKEN`
2. No host redirect; non-file paths rewrite to `index.html`
3. Vue Router shows the invitation page

### Invitation emails (API share page)

Links like `https://chante-api.vzs.mx/join-invitation-share/...` redirect to `FRONTEND_URL` + `/join/...` in Laravel. Set `FRONTEND_URL=https://pluribus.vzs.mx` in production `.env`.

## Testing

### Local (Vite)

```bash
cd frontend && npm run dev
# http://localhost:9123/join/test-token
```

### Production

```bash
curl -sI 'https://chante.vzs.mx/login' | grep -iE '^(HTTP|location):'
curl -sI 'https://pluribus.vzs.mx/login' | grep -iE '^(HTTP|location):'
```

- Old `chante.vzs.mx` URLs → pluribus with same path
- `pluribus.vzs.mx` deep links load the SPA
- Invalid routes may show `404.html`

## Files

- `frontend/public/.htaccess` — redirects + SPA fallback
- `frontend/public/404.html` — custom 404
- `docs/domain-migration.md` — infrastructure checklist

## MIME type errors on `/assets/*.js` (text/html)

If the browser console shows **“disallowed MIME type (text/html)”** for hashed files under `/assets/`, Apache is usually serving **`index.html` instead of the missing `.js` file** (SPA fallback). Common causes:

1. **Deploy race** — `index.html` uploaded before new hashed assets (or `mirror --delete` removed old files first). `./deploy.sh frontend` now uploads everything except `index.html`, then uploads `index.html` last.
2. **Stale tab + new deploy** — hard refresh loads a new `index.html` while assets are still uploading; wait for deploy to finish and refresh again.
3. **Service worker** — rare on hard refresh; unregister in DevTools → Application → Service Workers if it persists.

`.htaccess` no longer rewrites missing `/assets/*` (or `sw.js`, `workbox-*`, `version.json`) to `index.html`, so a missing file returns **404** instead of a confusing MIME error.

Verify a failing URL (use a browser User-Agent; some hosts return **406** to plain `curl`):

```bash
curl -sI -A 'Mozilla/5.0' 'https://pluribus.vzs.mx/assets/index-BgG2wUt3.js' | grep -iE '^(HTTP|content-type):'
# Expect: HTTP/2 200 and content-type: application/javascript
```

## Important Notes

- `.htaccess` applies to the **frontend** vhosts (`pluribus.vzs.mx`, `chante.vzs.mx` when pointed at the same docroot)
- **API** (`chante-api.vzs.mx`) uses Laravel’s `backend/public/.htaccess`
- Avoid cPanel redirects that send all paths to `/` only

## Verification Checklist

- [ ] `chante.vzs.mx/join/TOKEN` → 301 → `pluribus.vzs.mx/join/TOKEN`
- [ ] Direct pluribus deep links work
- [ ] Login / API (Sanctum) without CORS or 419 errors
- [ ] New invitation emails use pluribus in the final SPA URL
- [ ] Custom 404 for invalid routes on pluribus
