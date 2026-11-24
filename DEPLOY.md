# AJ LUXE AUTO — Going live on a VPS (Ubuntu 24.04)

You will move the system **with all your data** from your PC (`C:\WWW\AJ\ajluxe_system`) to the server:
spare parts, garage job cards, customers, invoices, photos, website texts and staff logins.

Replace in every command:
- `YOUR-DOMAIN.co.tz` → your domain
- `SERVER-IP` → the VPS IP address (from your VPS provider's panel)

About 1–2 hours the first time. Commands in grey boxes are copied and pasted exactly.

---

## 0. Before you start (on your PC)

1. **Domain DNS** — at the company where you bought the domain, add two **A records**:
   `@ → SERVER-IP` and `www → SERVER-IP`. It can take from minutes to a few hours to work, so do it first.
2. **Change the old passwords.** The old garage workbook kept passwords as plain text
   (`Admin@123`, `Cocuh@123`). Make sure no staff login uses them.
3. Have the server **root password** (or SSH key) from your VPS provider.

---

## 1. Pack the system on your PC

Open **Git Bash** in `C:\WWW\AJ\ajluxe_system`:

```bash
# the code (no vendor, no local settings, no logs)
tar -czf ajluxe-code.tar.gz --exclude=./vendor --exclude=./node_modules --exclude=./.env \
    --exclude=./storage/logs --exclude=./storage/framework --exclude=./storage/app/public \
    --exclude=./storage/app/backups --exclude=./database/database.sqlite --exclude='*.tar.gz' --exclude='*.sql' .

# the uploaded photos and logo
tar -czf ajluxe-photos.tar.gz -C storage/app public

# the database (enter your local MySQL password; change ajluxe if your local database has another name)
mysqldump -u root -p --single-transaction --routines ajluxe > ajluxe.sql
```

> `mysqldump: command not found`? Use the full path, e.g.
> `"/c/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe"` (or the `bin` folder of Laragon/XAMPP).
> Check your local database name in your PC's `.env` (`DB_DATABASE=`).

You now have three files: `ajluxe-code.tar.gz`, `ajluxe-photos.tar.gz`, `ajluxe.sql`.

---

## 2. First login and a safe user (on the server)

From Git Bash or PowerShell on your PC:
```bash
ssh root@SERVER-IP
```
Then on the server:
```bash
adduser ajadmin                 # choose a strong password
usermod -aG sudo ajadmin
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable
exit
```
From now on log in as `ssh ajadmin@SERVER-IP`.

---

## 3. Install the server software (once)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common unzip curl nginx mysql-server
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
     php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# photos and imports up to 10 MB
sudo sed -i 's/^upload_max_filesize.*/upload_max_filesize = 10M/; s/^post_max_size.*/post_max_size = 12M/' /etc/php/8.4/fpm/php.ini
sudo systemctl restart php8.4-fpm
```

---

## 4. Database

```bash
sudo mysql
```
```sql
CREATE DATABASE ajluxe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ajluxe'@'localhost' IDENTIFIED BY 'CHOOSE-A-LONG-STRONG-PASSWORD';
GRANT ALL PRIVILEGES ON ajluxe.* TO 'ajluxe'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 5. Upload the three files

On **your PC** (Git Bash, in `C:\WWW\AJ\ajluxe_system`):
```bash
scp ajluxe-code.tar.gz ajluxe-photos.tar.gz ajluxe.sql ajadmin@SERVER-IP:~
```
(Or drag them across with **WinSCP**.)

---

## 6. Install the system (on the server)

```bash
sudo mkdir -p /var/www/ajluxe && sudo chown ajadmin:www-data /var/www/ajluxe
tar -xzf ~/ajluxe-code.tar.gz -C /var/www/ajluxe
mkdir -p /var/www/ajluxe/storage/app && tar -xzf ~/ajluxe-photos.tar.gz -C /var/www/ajluxe/storage/app
mysql -u ajluxe -p ajluxe < ~/ajluxe.sql          # your data, exactly as on your PC

cd /var/www/ajluxe
mkdir -p storage/framework/{cache,sessions,views} storage/logs
composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env
```

In `.env` set (keep the other lines as they are):
```
APP_NAME="AJ LUXE AUTO"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-DOMAIN.co.tz

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ajluxe
DB_USERNAME=ajluxe
DB_PASSWORD=the-password-from-step-4

SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```
Save with **Ctrl+O, Enter, Ctrl+X**. Then:

```bash
php artisan key:generate
php artisan migrate --force          # adds anything newer than your PC's database
php artisan storage:link
php artisan optimize

sudo chown -R ajadmin:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
rm ~/ajluxe.sql                      # don't leave customer data lying around
```

> Do **not** run `php artisan db:seed` or `data:clear` here — your data is already in.
> The CSS/JS are already built (`public/build`), so Node.js is **not** needed.

---

## 7. Nginx (the web server)

```bash
sudo nano /etc/nginx/sites-available/ajluxe
```
Paste (change the domain):
```nginx
server {
    listen 80;
    server_name YOUR-DOMAIN.co.tz www.YOUR-DOMAIN.co.tz;
    root /var/www/ajluxe/public;
    index index.php;
    client_max_body_size 12M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    # pictures and built files: let browsers keep them
    location ~* ^/(build|storage|images)/ { expires 7d; access_log off; try_files $uri /index.php?$query_string; }
    location ~ /\.(?!well-known) { deny all; }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/ajluxe /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## 8. HTTPS (free certificate)

When `http://YOUR-DOMAIN.co.tz` opens the website (DNS from step 0 is working):
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d YOUR-DOMAIN.co.tz -d www.YOUR-DOMAIN.co.tz --redirect -m you@example.com --agree-tos -n
```
It renews itself.

---

## 9. Check it

1. `https://YOUR-DOMAIN.co.tz` — the shop, with your products and photos.
2. `https://YOUR-DOMAIN.co.tz/login` — staff login with **your usual username and password** (same as on your PC).
3. Dashboard figures match your PC; open a job card and an invoice.
4. Switch the website to Swahili and back.

Something shows "Server Error"? Run `tail -50 /var/www/ajluxe/storage/logs/laravel.log` and send me the result.

---

## 10. Continue with Google (website customers)

1. Google Cloud Console → **APIs & Services → OAuth consent screen**: app name "AJ LUXE AUTO",
   support e-mail, scopes `openid`, `email`, `profile`. **Publish** it.
2. **Credentials → Create credentials → OAuth client ID → Web application.**
   Authorized redirect URIs:
   - `https://YOUR-DOMAIN.co.tz/account/google/callback`
   - `http://127.0.0.1:8000/account/google/callback` (only if you also want it on your PC)
3. Put the two values in `/var/www/ajluxe/.env` (`GOOGLE_CLIENT_ID=`, `GOOGLE_CLIENT_SECRET=`), then
   `php artisan optimize`. The button appears on the website's log-in and sign-up pages.

---

## 11. Nightly backup

```bash
sudo mkdir -p /var/backups/ajluxe
sudo nano /root/.my.cnf
```
```
[mysqldump]
user=ajluxe
password=the-password-from-step-4
```
```bash
sudo chmod 600 /root/.my.cnf
sudo crontab -e
```
Add (1:30 am every night, database + photos, kept 30 days):
```
30 1 * * * mysqldump --single-transaction ajluxe | gzip > /var/backups/ajluxe/db-$(date +\%F).sql.gz && tar -czf /var/backups/ajluxe/photos-$(date +\%F).tar.gz -C /var/www/ajluxe/storage/app public && find /var/backups/ajluxe -mtime +30 -delete
```
Every week copy the newest files to your PC or Google Drive (WinSCP: `/var/backups/ajluxe`).

---

## Updating later (new zip from me)

On your PC unzip the update into `C:\WWW\AJ\ajluxe_system` as usual and test it. Then pack only the code
(step 1, first command), upload it, and on the server:
```bash
cd /var/www/ajluxe
php artisan down
tar -xzf ~/ajluxe-code.tar.gz -C /var/www/ajluxe      # keeps .env, photos and logs
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan up
```

From the day you go live, **the server is the real system** — staff should use the website address,
not your PC. Your PC copy becomes a test copy.
