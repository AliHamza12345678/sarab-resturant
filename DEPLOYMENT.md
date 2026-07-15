# Production Deployment Checklist

This project is code-complete and has been tested end-to-end against a real
MySQL/MariaDB server and a real Apache server configured the way shared
hosting (cPanel, Hostinger, etc.) normally is. A few things depend on the
**hosting environment itself**, not the code — this file lists exactly what
to check when you deploy.

## 1. Database
- Import `database/schema.sql` into your production MySQL/MariaDB database.
- Update `config/db.php` with your real host/username/password/database name.
- **Change the default admin password immediately** after first login
  (`admin@sarabfood.com` / `Admin@123` from the seed data).

## 2. Apache `.htaccess` support (important!)
This project uses `.htaccess` files to protect sensitive folders:
- `/uploads/.htaccess` — blocks PHP execution inside uploaded files (defends against disguised-file attacks)
- `/backups/.htaccess` — blocks all direct web access to database backups (these contain password hashes)
- `/cache/.htaccess` — blocks direct web access to cached data

**These only work if your host has `AllowOverride All` enabled** for your
directory (this is the default on virtually all shared hosting — cPanel,
Hostinger, GoDaddy, etc. all support `.htaccess` out of the box). Verified
directly: with `AllowOverride All`, all three protections return `403
Forbidden` as expected; with `AllowOverride None` (a locked-down custom
Apache setup), they're silently ignored.

**If you deploy to Nginx instead of Apache**, `.htaccess` files do nothing —
Nginx ignores them completely. You'll need to add the equivalent rules
directly in your server block, for example:
```nginx
location ^~ /uploads/ {
    location ~ \.php$ { deny all; }
}
location ^~ /backups/ { deny all; }
location ^~ /cache/ { deny all; }
```

## 3. HTTPS / SSL
- Install an SSL certificate for your domain (Let's Encrypt is free and
  supported by virtually all hosts).
- Once HTTPS is live, call `force_https_redirect()` (defined in
  `config/security_headers.php`) at the top of `header.php` and
  `admin/html/include/auth.php` to force all traffic to HTTPS. It's not
  called automatically so local HTTP development keeps working without a
  certificate.
- The HSTS header (`Strict-Transport-Security`) is already sent
  automatically whenever a request arrives over HTTPS — no extra config
  needed for that part.

## 4. Email
- `mail()` (used by `config/mailer.php`) works out of the box on most
  shared hosting. If emails aren't arriving, check your host's mail logs
  or SPF/DKIM setup for the sending domain.
- For higher deliverability (Gmail, SendGrid, Mailgun, etc.), swap the
  `mail()` call inside `send_email()` in `config/mailer.php` for an SMTP
  library — that's the only function that needs to change.

## 5. Automated backups
- The admin panel (Backups page, Admin role only) can generate a backup
  on demand. For truly automated backups, add a cron job that hits a
  dedicated backup-trigger script on a schedule (e.g. daily) — ask your
  host how to set up cron jobs, since this varies by provider.
- Backups are kept to the 10 most recent automatically; download and
  store important ones off-server periodically.

## 6. File permissions
- `uploads/`, `backups/`, and `cache/` need to be writable by the web
  server user (usually `www-data` or similar) — typically `755` for
  directories is enough on shared hosting; ask your host if uploads fail.

## 7. PHP extensions
This project uses the `gd` extension (image processing) and works without
`mbstring` (the code has fallbacks), but if `mbstring` is available it will
be used automatically for more correct text truncation in a couple of
places. Both are enabled by default on virtually all PHP hosting.
