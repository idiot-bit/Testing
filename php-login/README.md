# PHP Two-Step Login (Mobile UI + cPanel)

## Included
- Screen 1: Username.
- Screen 2: Password.
- Mobile-first half-screen layout.
- Credentials and branding loaded from `.env`.

## Configure `.env`
Edit `php-login/.env`:
- `SITE_NAME`
- `LOGO_TEXT`
- `FAVICON_EMOJI`
- `LOGIN_USER_1`, `LOGIN_PASS_1`
- `LOGIN_USER_2`, `LOGIN_PASS_2`

## Fresh cPanel Steps
1. Login to cPanel.
2. Open **File Manager**.
3. Go to `public_html` (or addon domain root).
4. Create folder `login` (optional).
5. Upload these files:
   - `index.php`
   - `.env`
6. Open URL:
   - `https://yourdomain.com/login/` (if uploaded to `public_html/login/`)
   - or `https://yourdomain.com/`
7. Test:
   - Enter username (screen 1)
   - Enter password (screen 2)
   - Logout

## Important
- This is demo-style auth.
- For production: use database users + hashed passwords (`password_hash` / `password_verify`) + CSRF token + HTTPS.
