# Panduan Deployment LUBIS 21 ke VPS Ubuntu 22.04 LTS

Panduan ini menjelaskan langkah-langkah untuk melakukan deployment production-ready sistem **LUBIS 21** (Backend Laravel API & Frontend Next.js SSR) menggunakan Nginx, MySQL, PHP-FPM, dan PM2.

---

## 1. Persiapan Server
Pastikan VPS Ubuntu Anda telah terpasang paket dasar berikut:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl git unzip zip nginx mariadb-server supervisor
```

### 1.1 Pasang PHP 8.2 & Ekstensi
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-gd php8.2-cli php8.2-bcmath
```

### 1.2 Pasang Node.js (v20 LTS) & PM2
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install --global pm2
```

### 1.3 Pasang Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 2. Setup Database MySQL/MariaDB
Masuk ke MySQL console:
```bash
sudo mysql -u root
```
Lalu jalankan query berikut untuk membuat database dan user:
```sql
CREATE DATABASE lubis21 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lubis_user'@'localhost' IDENTIFIED BY 'PasswordKuatLubis21#';
GRANT ALL PRIVILEGES ON lubis21.* TO 'lubis_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 3. Clone Repository & Setup Folder
Struktur folder yang direkomendasikan di `/var/www/lubis21`:
```bash
sudo mkdir -p /var/www/lubis21
sudo chown -R $USER:$USER /var/www/lubis21
cd /var/www/lubis21
git clone <URL_REPOSITORY_ANDA> .
```

---

## 4. Deployment Backend (Laravel API)
### 4.1 Install Dependensi
```bash
composer install --no-dev --optimize-autoloader
```

### 4.2 Konfigurasi Environment `.env`
Salin file `.env.example` dan sesuaikan nilainya:
```bash
cp .env.example .env
nano .env
```
Sesuaikan konfigurasi berikut di file `.env`:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.lubis21.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lubis21
DB_USERNAME=lubis_user
DB_PASSWORD=PasswordKuatLubis21#

SANCTUM_STATEFUL_DOMAINS=lubis21.com,www.lubis21.com
SESSION_DOMAIN=.lubis21.com
```

### 4.3 Key Generate, Migrasi, & Link Storage
```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=DemoSeeder --force # Opsional: hanya jika ingin data demo pertama kali
php artisan storage:link
```

### 4.4 Set Izin Folder (Permissions)
Kembalikan kepemilikan grup ke `www-data` agar PHP-FPM dapat menulis ke folder storage dan cache:
```bash
sudo chown -R $USER:www-data /var/www/lubis21
sudo chmod -R 775 /var/www/lubis21/storage
sudo chmod -R 775 /var/www/lubis21/bootstrap/cache
```

---

## 5. Deployment Frontend (Next.js)
### 5.1 Masuk ke Subfolder Frontend & Konfigurasi `.env.local`
```bash
cd /var/www/lubis21/frontend
nano .env.local
```
Tambahkan endpoint API backend:
```ini
NEXT_PUBLIC_API_URL=https://api.lubis21.com/api
```

### 5.2 Install Dependensi & Build
```bash
npm install --legacy-peer-deps
npm run build
```

### 5.3 Jalankan Next.js Menggunakan PM2
Gunakan PM2 untuk menjaga proses Next.js tetap berjalan di port default `3000` di latar belakang:
```bash
pm2 start npm --name "lubis-frontend" -- start
pm2 save
pm2 startup
```

---

## 6. Konfigurasi Nginx Web Server
Buat file konfigurasi server block baru di Nginx:
```bash
sudo nano /etc/nginx/sites-available/lubis21
```
Masukkan konfigurasi server block berikut (sesuaikan nama domain):

```nginx
# 1. Frontend Next.js Server Block (lubis21.com)
server {
    listen 80;
    server_name lubis21.com www.lubis21.com;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}

# 2. Backend API Server Block (api.lubis21.com)
server {
    listen 80;
    server_name api.lubis21.com;
    root /var/www/lubis21/public;

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan konfigurasi website baru tersebut dan restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/lubis21 /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## 7. Setup SSL HTTPS (Certbot)
Gunakan Certbot Let's Encrypt untuk mengotomatiskan penerbitan dan pembaruan sertifikat SSL gratis:
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d lubis21.com -d www.lubis21.com -d api.lubis21.com
```
*Pilih opsi redirect HTTP ke HTTPS otomatis saat ditanya oleh Certbot.*

---

## 8. Pemeliharaan & Troubleshooting
- **Melihat log backend Laravel**: `tail -f /var/www/lubis21/storage/logs/laravel.log`
- **Melihat status Next.js**: `pm2 status` atau `pm2 logs lubis-frontend`
- **Restart Next.js**: `pm2 restart lubis-frontend`
- **Restart PHP-FPM**: `sudo systemctl restart php8.2-fpm`
- **Clear cache Laravel**: `php artisan optimize:clear`
