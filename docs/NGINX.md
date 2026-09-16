# Deploy nginx — Notepad Share

`composer install` **tidak cukup**. File `.htaccess` hanya untuk Apache. Di nginx (sering di belakang Cloudflare) konfigurasi vhost wajib.

Jika HTML 403 berisi `<center>nginx</center>`, permintaan **belum sampai ke PHP** aplikasi.

## 1. Document root

`root` **harus** folder `public/`, bukan root git:

```
/var/www/notepad-share/public
```

Root proyek tidak punya `index.html`. nginx dengan `autoindex off` lalu 403.

## 2. Contoh server block

Sesuaikan `server_name`, path, dan socket PHP-FPM (`ls /run/php/`).

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name contoh-anda.com;
    root /var/www/notepad-share/public;
    index index.php;
    charset utf-8;

    add_header X-Content-Type-Options nosniff;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_read_timeout 120s;
        fastcgi_buffering off;
    }

    location ~ ^/(data|app|vendor|docs|cron)/ {
        deny all;
        return 404;
    }

    location ~ /\. {
        deny all;
    }
}
```

HTTPS: sertifikat (Let’s Encrypt / Cloudflare Full) di `listen 443 ssl` dengan `root` yang sama.

Aktifkan site:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

## 3. Dependensi & izin

Di **root proyek** (satu tingkat di atas `public/`):

```bash
cd /var/www/notepad-share
composer install --no-dev --optimize-autoloader
mkdir -p data/notes data/history data/uploads data/yjs
sudo chown -R www-data:www-data data
sudo chmod -R u+rwX,g+rwX data
```

Ganti `www-data` jika PHP-FPM memakai user lain (`nginx`, `caddy`).

PHP 8.2: `json`, `session`, `fileinfo`, `dom`, `mbstring`.

## 4. SSE (kolaborasi realtime)

Aplikasi mengirim `X-Accel-Buffering: no`. Tetap set `fastcgi_buffering off` pada `location ~ \.php$` agar EventSource tidak tertahan buffer nginx.

## 5. Cloudflare

403 di atas sering **hanya diteruskan** dari nginx. Setelah vhost benar:

- Purge cache Cloudflare
- Jika masih 403: Security / WAF, atau sementara DNS only

## 6. Diagnosa

| Uji | Arti |
| --- | --- |
| `https://domain/` 403 | `root` salah atau izin folder |
| `https://domain/index.php` 200, `/n/x` 404 | kurang `try_files` ke `index.php` |
| `index.php` 403 | FastCGI / `open_basedir` / izin `public/` |
| Landing muncul, API 403 | `location` memblokir `/api` |

Log: `error.log` nginx dan log PHP-FPM.

Local Apache: [SETUP.md](SETUP.md).

## 7. aaPanel / BT — `note.ergiyonest.my.id`

Struktur Anda:

```
/www/wwwroot/note.ergiyonest.my.id/     ← website root panel (app, public, vendor, data)
  public/index.php                      ← harus jadi document root nginx
```

Panel menunjuk ke folder **proyek**. Tidak ada `index.html` di situ → nginx **403**. Folder `public/` ada, tapi nginx tidak memakainya.

### Cara di panel (pilih salah satu)

**A. Running directory (paling mudah di aaPanel)**

1. Website → `note.ergiyonest.my.id` → Settings → **Website directory**
2. **Running directory** / 运行目录: `/public` (bukan `/`)
3. Simpan, lalu buka lagi `https://note.ergiyonest.my.id/`

Jika PHP error `open_basedir`: di Site PHP / `open_basedir` izinkan induk proyek:

`/www/wwwroot/note.ergiyonest.my.id/:/tmp`

Tanpa itu `vendor/autoload.php` di luar `public/` ditolak.

**B. Edit konfigurasi nginx site**

Di Config file site, `root` harus:

`/www/wwwroot/note.ergiyonest.my.id/public`

dan `location /` memakai `try_files $uri $uri/ /index.php?$query_string;`

Contoh lengkap: [deploy/nginx-note.ergiyonest.my.id.conf](../deploy/nginx-note.ergiyonest.my.id.conf)

Jangan hanya `composer install`. Tanpa langkah A atau B, URL tetap 403.

Izin data (user PHP aaPanel sering `www`):

```bash
mkdir -p /www/wwwroot/note.ergiyonest.my.id/data/{notes,history,uploads,yjs}
chown -R www:www /www/wwwroot/note.ergiyonest.my.id/data
chmod -R 775 /www/wwwroot/note.ergiyonest.my.id/data
```
