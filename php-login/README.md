# PHP Premium 2-Step Login (cPanel Setup)

## What this includes
- Screen 1: Username step.
- Screen 2: Password step.
- Session-based flow.
- Branding methods for logo and favicon in `index.php` config.

## Fresh cPanel Deployment Steps
1. Log in to cPanel.
2. Open **File Manager**.
3. Go to `public_html` (or your addon domain document root).
4. Create folder `login` (optional), then upload `index.php` into that folder.
5. If using image logo/favicon, create `assets/` and upload:
   - `assets/logo.svg` (or png/jpg)
   - `assets/favicon.ico`
6. Edit `index.php` and update config block at top:
   - `site_name`
   - `logo_mode` (`text` or `image`)
   - `logo_text` or `logo_image`
   - `favicon_mode` (`emoji` or `file`)
   - `favicon_emoji` or `favicon_file`
   - `users` credentials
7. Open URL:
   - `https://yourdomain.com/login/` (if uploaded in `public_html/login/`)
   - or `https://yourdomain.com/` (if uploaded directly in `public_html`)
8. Test flow:
   - Enter username on screen 1
   - Enter password on screen 2
   - Confirm login and logout

## Production Notes
- Replace hardcoded demo credentials with DB-backed authentication.
- Store hashed passwords (e.g. `password_hash()` and `password_verify()`).
- Enable HTTPS and force secure cookies.
- Add CSRF token protection on forms.
