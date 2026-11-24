# AJ LUXE AUTO — Going live on the Contabo VPS (Ubuntu 24.04) at ajluxeauto.co.tz

You will move the system **with all your data** from your PC (`C:\WWW\AJ\ajluxe_system`) to the server:
spare parts, garage job cards, customers, invoices, photos, website texts and staff logins.

The only thing to replace is `SERVER-IP` → your Contabo server's IP address
(Contabo welcome e-mail, or Contabo customer panel → **VPS** → IP address).
Contabo's welcome e-mail also has the **root password**.

About 1 hour the first time. The code comes from GitHub; only your database and photos are copied by hand. Commands in grey boxes are copied and pasted exactly.

---

## 0. Before you start (on your PC)

1. **Domain DNS** — log in where you bought `ajluxeauto.co.tz` (your .co.tz registrar) and in its DNS
   settings add two **A records**: `@ → SERVER-IP` and `www → SERVER-IP` (delete any old A records for
   `@`/`www`). It can take from minutes to a few hours to work, so do it first.
   Check from your PC: `ping ajluxeauto.co.tz` should show your SERVER-IP.
2. **Change the old passwords.** The old garage workbook kept passwords as plain text
   (`Admin@123`, `Cocuh@123`). Make sure no staff login uses them.
3. Have the server **root password** (or SSH key) from your VPS provider.

---

## 1. Pack your data on your PC

The **code** comes from GitHub (`github.com/anordy/ajluxe_system`). Push your latest work first:
```bash
git status          # should say "nothing to commit"; if not: git add -A && git commit -m "Ready to go live"
git push
```

Your **data is not on GitHub** (on purpose), so pack it. Open **Git Bash** in `C:\WWW\AJ\ajluxe_system`:

```bash
# uploaded photos and logo
tar -czf ajluxe-photos.tar.gz -C storage/app public

# the database (asks for your local MySQL password, the DB_PASSWORD in your PC's .env)
mysqldump -u ajluxe -p --single-transaction --routines --no-tablespaces ajluxe_db > ajluxe.sql
```

> `mysqldump: command not found`? It is in your MySQL `bin` folder. Find it with
> `where.exe mysqldump` in PowerShell, or use the full path, e.g.
> `"/c/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe"`.
> Check the file is not tiny: `ls -lh ajluxe.sql` should be hundreds of KB or more.

> Never `git add` these two files: they hold customer data. (They are only made for this move.)

You now have two files: `ajluxe-photos.tar.gz` and `ajluxe.sql`.

---

## 2. First login and a safe user (on the server)

From Git Bash or PowerShell on your PC:
```bash
ssh root@SERVER-IP
```
Then on the server:
```bash
passwd                          # first: replace the password Contabo e-mailed you
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
CREATE DATABASE ajluxe_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ajluxe'@'localhost' IDENTIFIED BY 'CHOOSE-A-LONG-STRONG-PASSWORD';
GRANT ALL PRIVILEGES ON ajluxe_db.* TO 'ajluxe'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 5. Upload your data (2 files)

On **your PC** (Git Bash, in `C:\WWW\AJ\ajluxe_system`):
```bash
scp ajluxe-photos.tar.gz ajluxe.sql ajadmin@SERVER-IP:~
```
(Or drag them across with **WinSCP**.)

---

## 6. Install the system (on the server)

**a) Let the server read your GitHub repository** (a read-only "deploy key"):
```bash
sudo apt install -y git
ssh-keygen -t ed25519 -C "contabo-ajluxe" -f ~/.ssh/github_ajluxe -N ""
printf "Host github.com\n  IdentityFile ~/.ssh/github_ajluxe\n  IdentitiesOnly yes\n" >> ~/.ssh/config
chmod 600 ~/.ssh/config
cat ~/.ssh/github_ajluxe.pub
```
Copy the line it prints (starts with `ssh-ed25519`). On GitHub open **anordy/ajluxe_system → Settings →
Deploy keys → Add deploy key**: title `Contabo server`, paste the key, leave **Allow write access unticked**, Add.

```bash
ssh -T git@github.com        # answer "yes"; it should say "successfully authenticated"
```

**b) Get the code, your photos and your database:**
```bash
sudo mkdir -p /var/www/ajluxe_system && sudo chown ajadmin:www-data /var/www/ajluxe_system
git clone git@github.com:anordy/ajluxe_system.git /var/www/ajluxe_system
tar -xzf ~/ajluxe-photos.tar.gz -C /var/www/ajluxe_system/storage/app
mysql -u ajluxe -p ajluxe_db < ~/ajluxe.sql          # your data, exactly as on your PC

cd /var/www/ajluxe_system
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
APP_URL=https://ajluxeauto.co.tz

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ajluxe_db
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
rm ~/ajluxe.sql ~/ajluxe-photos.tar.gz   # don't leave customer data lying around
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
    server_name ajluxeauto.co.tz www.ajluxeauto.co.tz;
    root /var/www/ajluxe_system/public;
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

When `http://ajluxeauto.co.tz` opens the website (DNS from step 0 is working):
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ajluxeauto.co.tz -d www.ajluxeauto.co.tz --redirect -m YOUR-EMAIL --agree-tos -n
```
It renews itself.

---

## 9. Check it

1. `https://ajluxeauto.co.tz` — the shop, with your products and photos.
2. `https://ajluxeauto.co.tz/login` — staff login with **your usual username and password** (same as on your PC).
3. Dashboard figures match your PC; open a job card and an invoice.
4. Switch the website to Swahili and back.

Something shows "Server Error"? Run `tail -50 /var/www/ajluxe_system/storage/logs/laravel.log` and send me the result.

---

## 10. Continue with Google (website customers)

1. Google Cloud Console → **APIs & Services → OAuth consent screen**: app name "AJ LUXE AUTO",
   support e-mail, scopes `openid`, `email`, `profile`. **Publish** it.
2. **Credentials → Create credentials → OAuth client ID → Web application.**
   Authorized redirect URIs:
   - `https://ajluxeauto.co.tz/account/google/callback`
   - `http://127.0.0.1:8000/account/google/callback` (only if you also want it on your PC)
3. Put the two values in `/var/www/ajluxe_system/.env` (`GOOGLE_CLIENT_ID=`, `GOOGLE_CLIENT_SECRET=`), then
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
30 1 * * * mysqldump --single-transaction --no-tablespaces ajluxe_db | gzip > /var/backups/ajluxe/db-$(date +\%F).sql.gz && tar -czf /var/backups/ajluxe/photos-$(date +\%F).tar.gz -C /var/www/ajluxe_system/storage/app public && find /var/backups/ajluxe -mtime +30 -delete
```
Every week copy the newest files to your PC or Google Drive (WinSCP: `/var/backups/ajluxe`).

---

## 12. Staff portal on dashboard.ajluxeauto.co.tz

1. **Duhosting DNS** → add: Type **CNAME** · Host `dashboard` · Value `ajluxeauto.co.tz`.
2. **Server** — tell Nginx about the new name (add it to the `server_name` line):
   ```bash
   sudo sed -i 's/server_name ajluxeauto.co.tz www.ajluxeauto.co.tz;/server_name ajluxeauto.co.tz www.ajluxeauto.co.tz dashboard.ajluxeauto.co.tz;/' /etc/nginx/sites-available/ajluxe
   sudo nginx -t && sudo systemctl reload nginx
   ```
3. When `nslookup dashboard.ajluxeauto.co.tz 8.8.8.8` shows your server IP, add it to the certificate:
   ```bash
   sudo certbot --nginx --expand -d ajluxeauto.co.tz -d www.ajluxeauto.co.tz -d dashboard.ajluxeauto.co.tz --redirect -n
   ```
4. Switch it on: add `ADMIN_DOMAIN=dashboard.ajluxeauto.co.tz` to `/var/www/ajluxe_system/.env`, then
   `php artisan optimize`.

Staff now sign in at **https://dashboard.ajluxeauto.co.tz**. On https://ajluxeauto.co.tz, `/login`,
`/dashboard` and every staff page answer "404 Not Found", and the footer has no staff link.

## Updating later

On your PC: test the update, then `git add -A && git commit -m "…" && git push`. On the server:
```bash
cd /var/www/ajluxe_system
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
php artisan up
```
`.env`, photos and logs are not in Git, so `git pull` never touches them.

From the day you go live, **the server is the real system** — staff should use the website address,
not your PC. Your PC copy becomes a test copy.
