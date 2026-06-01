# Quick Deployment Guide - SPA routing and domain migration

## Canonical domains

- **SPA:** https://pluribus.vzs.mx
- **API:** https://chante-api.vzs.mx
- **Legacy SPA:** https://chante.vzs.mx → 301 to pluribus (same path; see `docs/domain-migration.md`)

## Deploy

```bash
cd /home/melisa/Development/pluribus

# Backend env (FRONTEND_URL, Sanctum domains)
./deploy.sh env

# Frontend build + upload (.htaccess includes legacy redirect)
./deploy.sh frontend

# Or both
./deploy.sh all
```

## What the frontend `.htaccess` does

1. **Legacy host:** `chante.vzs.mx` → 301 to `pluribus.vzs.mx` with the same URI
2. **Vue Router:** non-file requests → `index.html` (history mode)
3. **404:** custom `404.html` for routes the app does not handle

## Testing after deployment

1. **Legacy redirect:**
   ```bash
   curl -sI 'https://chante.vzs.mx/join/test' | grep -iE '^(HTTP|location):'
   ```
   Expect `301` and `Location: https://pluribus.vzs.mx/join/test`

2. **Invitation links:**
   - Log in to https://pluribus.vzs.mx
   - Create an invitation; open the join URL in a private window
   - Or open an old bookmark: `https://chante.vzs.mx/join/TOKEN` should land on pluribus

3. **Regular navigation:**
   - https://pluribus.vzs.mx/login
   - https://pluribus.vzs.mx/contact

4. **404 page:**
   - https://pluribus.vzs.mx/this-does-not-exist

5. **Login:** sign in on pluribus; confirm no 419 / CORS errors

## Troubleshooting

1. **Check `.htaccess` on server:**
   ```bash
   ./deploy.sh cat prod/frontend/.htaccess
   ```

2. **Apache `mod_rewrite` and `AllowOverride All`**

3. **Both domains must share `prod/frontend`** for the chante → pluribus redirect

4. **Config cache after env upload:** `php artisan config:clear && php artisan config:cache`

See `DEPLOYMENT_404_FIX.md` and `docs/domain-migration.md` for details.
