# Quick Deployment Guide - 404 Fix for Invitation Links

## Summary of Changes

Fixed the 404 error that appeared when accessing invitation links directly on the production server.

### Files Created:
1. `frontend/public/.htaccess` - Apache configuration for Vue Router
2. `frontend/public/404.html` - Custom 404 error page
3. `DEPLOYMENT_404_FIX.md` - Detailed documentation

### Files Modified:
1. `frontend/vite.config.js` - Ensured public directory is copied during build

## Deploy Now

```bash
# Navigate to project root
cd /home/mel1s4/Development/pluribus

# Deploy only the frontend (recommended)
./deploy.sh frontend

# Or deploy everything (frontend + backend)
./deploy.sh all
```

## What This Fixes

**Before:**
- Clicking invitation links like `https://chante.vzs.mx/join/abc123` showed Apache 404 error
- Users couldn't accept invitations sent via email
- Direct navigation to any Vue route failed

**After:**
- All invitation links work correctly
- Vue Router handles all frontend routes
- Custom 404 page for truly non-existent pages
- Better user experience with friendly error messages

## How It Works

The `.htaccess` file tells Apache to:
1. Check if the requested file exists
2. If not, serve `index.html` instead
3. Let Vue.js and Vue Router handle the routing

This is called "history mode" routing and is standard for single-page applications.

## Testing After Deployment

1. **Test Invitation Links:**
   - Log in to https://chante.vzs.mx
   - Go to Users page
   - Create a new invitation link
   - Copy the full URL
   - Open it in a new incognito/private browser window
   - ✅ Should see the invitation registration form

2. **Test Regular Navigation:**
   - Visit https://chante.vzs.mx/login
   - Visit https://chante.vzs.mx/contact
   - ✅ All routes should work

3. **Test 404 Page:**
   - Visit https://chante.vzs.mx/this-does-not-exist
   - ✅ Should see custom 404 page with home/login links

## Troubleshooting

If routes still don't work after deployment:

1. **Check .htaccess was uploaded:**
   ```bash
   ./deploy.sh cat prod/frontend/.htaccess
   ```

2. **Check Apache mod_rewrite is enabled:**
   ```bash
   # On server:
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

3. **Check Apache VirtualHost allows .htaccess:**
   Make sure `AllowOverride All` is set in Apache config

4. **Clear browser cache:**
   Hard refresh with Ctrl+Shift+R (or Cmd+Shift+R on Mac)

## Need Help?

See `DEPLOYMENT_404_FIX.md` for detailed explanation of the problem and solution.
