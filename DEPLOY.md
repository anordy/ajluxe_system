# AJ LUXE AUTO — Deploying to a VPS (Ubuntu 24.04)

These steps install the system on a fresh Ubuntu 24.04 VPS (e.g. DigitalOcean, Contabo, Hetzner).
Replace `your-domain.co.tz` with your real domain. Commands are run as a user with `sudo`.

## 1. Install the server software (once)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server unzip git curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

Allow CSV imports and photos up to 10 MB (photos are already shrunk in the browser, CSV files can be large):
```bash
sudo sed -i 's/^upload_max_filesize.*/upload_max_filesize = 10M/; s/^post_max_size.*/post_max_size = 12M/' /etc/php/8.3/fpm/php.ini
sudo systemctl restart php8.3-fpm
```

## 2. Create the database

```bash
sudo mysql
```
```sql
CREATE DATABASE ajluxe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ajluxe'@'localhost' IDENTIFIED BY 'CHOOSE-A-STRONG-PASSWORD';
GRANT ALL PRIVILEGES ON ajluxe.* TO 'ajluxe'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Upload and install the app

Upload the project zip to the server (e.g. with WinSCP or `scp`), then:

```bash
sudo mkdir -p /var/www/ajluxe && sudo chown $USER:www-data /var/www/ajluxe
unzip ajluxe-phase1.zip -d /var/www/ajluxe
cd /var/www/ajluxe

composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env        # set APP_URL, DB_PASSWORD (and DB_* if different)
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # creates admin / Admin@123, settings, lists, services
php artisan storage:link
php artisan optimize

sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

> The CSS/JS are already built (`public/build`), so Node.js is **not** needed on the server.

## 4. Nginx site

```bash
sudo nano /etc/nginx/sites-available/ajluxe
```
```nginx
server {
    listen 80;
    server_name your-domain.co.tz www.your-domain.co.tz;
    root /var/www/ajluxe/public;
    index index.php;
    client_max_body_size 10M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/ajluxe /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

## 5. Free SSL certificate (https)

Point your domain's DNS **A record** to the VPS IP first, then:
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.co.tz -d www.your-domain.co.tz
```

## 6. First sign-in

Open `https://your-domain.co.tz`, sign in with **admin / Admin@123**. You will be asked to choose a
new password straight away. Then follow the **Getting Started** list on the dashboard.

## 7. Daily database backup (recommended)

```bash
sudo mkdir -p /var/backups/ajluxe
sudo crontab -e
```
Add this line (backs up at 1:30 am every night and keeps 30 days):
```
30 1 * * * mysqldump -u ajluxe -p'YOUR-DB-PASSWORD' ajluxe | gzip > /var/backups/ajluxe/ajluxe-$(date +\%F).sql.gz && find /var/backups/ajluxe -mtime +30 -delete
```
Copy backups off the server regularly (Google Drive, your PC).

## Updating to a new version (Phase 2, 3, ...)

```bash
cd /var/www/ajluxe
php artisan down
# upload/unzip the new files over the old ones (keep your .env and storage/)
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force     # only adds what is missing — never overwrites your data
php artisan optimize:clear && php artisan optimize
php artisan up
```

## Checking it works

`php artisan test` runs the automatic checks (needs `composer install` *with* dev packages).
If a page shows "Server Error", look at `storage/logs/laravel.log` and send me the last error.

## Continue with Google (website customers)

1. Google Cloud Console → APIs & Services → OAuth consent screen: app name "AJ LUXE AUTO", support e-mail, scopes `openid`, `email`, `profile`. Publish it.
2. Credentials → Create credentials → OAuth client ID → type **Web application**.
   Authorized redirect URIs (one per address the site is opened on):
   - `http://127.0.0.1:8000/account/google/callback` (your PC)
   - `https://YOUR-DOMAIN/account/google/callback` (the live site)
3. Put the two values in `.env`, then `php artisan config:clear`:
   ```
   GOOGLE_CLIENT_ID=....apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=....
   ```
The button appears on the website's log-in and sign-up pages only when both are set.
