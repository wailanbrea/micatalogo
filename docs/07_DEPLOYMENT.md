# Deployment Guide — MiCatalogo

Target Infrastructure: Windows Server VPS, Apache 2.4, Cloudflare WAF/CDN, MySQL 8.x/MariaDB 11.x, Cloudflare R2 Standard Storage.

## 1. Apache VirtualHost Configuration

```apache
<VirtualHost *:80>
    ServerName micatalogo.com.do
    ServerAlias www.micatalogo.com.do
    DocumentRoot "C:/xampp/php/www/MiCatalogo/public"

    <Directory "C:/xampp/php/www/MiCatalogo/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/micatalogo-error.log"
    CustomLog "logs/micatalogo-access.log" combined
</VirtualHost>
```

Cloudflare handles SSL/TLS termination at the edge (set SSL mode to **Full (Strict)** or **Full**).

## 2. Environment Variables (.env)

```env
APP_NAME=MiCatalogo
APP_ENV=production
APP_DEBUG=false
APP_URL=https://micatalogo.com.do

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=micatalogo
DB_USERNAME=micatalogo_user
DB_PASSWORD=YOUR_STRONG_PASSWORD

QUEUE_CONNECTION=database

R2_ACCESS_KEY_ID=YOUR_R2_KEY
R2_SECRET_ACCESS_KEY=YOUR_R2_SECRET
R2_DEFAULT_REGION=auto
R2_BUCKET=micatalogo-media
R2_URL=https://media.micatalogo.com.do
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_USE_PATH_STYLE_ENDPOINT=false

TURNSTILE_SITE_KEY=YOUR_TURNSTILE_SITE_KEY
TURNSTILE_SECRET_KEY=YOUR_TURNSTILE_SECRET_KEY
```

## 3. Production Build & Optimization

Execute before launching:
```powershell
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
npm run build
```

## 4. Rollback Plan

1. Back up and verify the database using the restricted deployment account; store the
   backup outside the release directory before running migrations.
2. Rollback git release commit (`git checkout <PREVIOUS_STABLE_TAG>`).
3. Clear caches (`php artisan optimize:clear`).
4. Restore previous build assets.
