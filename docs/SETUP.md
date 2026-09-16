# Setup & cara menjalankan — Notepad Share

Aplikasi PHP 8.2 native (tanpa Laravel/MySQL). Konten disimpan sebagai JSON di folder `data/`.

## Prasyarat

- PHP **8.2** (ekstensi: `json`, `session`, `fileinfo`, `dom`, `mbstring`)
- Composer
- Apache + `mod_rewrite` (Laragon), **nginx** (lihat [NGINX.md](NGINX.md)), **atau** PHP built-in server untuk development
- Folder `data/` dapat ditulis oleh PHP

## 1. Install dependensi

Dari root proyek (`C:\laragon\www\notepad-share`):

```bash
composer install
```

Pastikan folder ini ada dan writable:

- `data/notes/`
- `data/history/`
- `data/uploads/`
- `data/yjs/`

## 2. Pilih cara run

### A. Laragon (disarankan untuk kolaborasi realtime / SSE)

**Opsi A1 — virtual host (DocumentRoot = `public/`)**

1. Site `notepad-share.test` DocumentRoot: `C:\laragon\www\notepad-share\public` (otomatis Laragon).
2. `public/.htaccess` memakai `RewriteBase /` (wajib untuk domain `.test`).
3. Buka `http://notepad-share.test/`

**Opsi A2 — subfolder** (`http://localhost/notepad-share/`)

Root `.htaccess` meneruskan ke `public/`. Hanya untuk mode ini, ganti `RewriteBase` di `public/.htaccess` menjadi `/notepad-share/public/` — jangan dipakai bersamaan dengan `notepad-share.test` (akan loop 500).

Buka:

- Landing: `http://localhost/notepad-share/` atau `http://localhost/notepad-share/public/`
- Editor: `http://localhost/notepad-share/n/nama-ruang`

Apache harus `AllowOverride All` agar rewrite jalan.

### B. PHP built-in server (development)

Dari folder **`public/`**:

```bash
cd public
php -S 127.0.0.1:8765 index.php
```

Lalu buka [http://127.0.0.1:8765/](http://127.0.0.1:8765/)

Catatan: server ini **satu thread**. SSE dipersingkat; sinkron antar tab memakai polling ~1,5 detik. Untuk SSE penuh, pakai Laragon/Apache.

Hentikan server: `Ctrl+C` di terminal tersebut.

### C. nginx (produksi)

`.htaccess` **tidak dipakai**. `root` wajib `.../public` plus `try_files` ke `index.php`.

Untuk **aaPanel** domain `note.ergiyonest.my.id`: website root Anda adalah folder proyek (`app`, `public`, `vendor`). Set **Running directory** = `/public`. Detail: [NGINX.md](NGINX.md) bagian 7.

`composer install` saja tanpa vhost nginx yang benar hampir selalu **403 Forbidden**.

## 3. Pakai aplikasi

1. Isi slug (contoh: `rapat-tim`) lalu **Buka / buat ruang**.
2. Atau langsung: `{BASE}/n/rapat-tim`
3. Share URL yang sama ke rekan (browser lain / tab lain).
4. Markdown atau Plain Text; preview via PHP CommonMark; blok ` ```mermaid ` dirender di browser.
5. Menu: history, retensi, unggah gambar, enkripsi PQC / passphrase ruang.

## 4. Prune history (opsional)

Sesuai retensi catatan (1 jam … 7 hari, atau permanen):

```bash
php cron/prune.php
```

Bisa dijadwalkan (Task Scheduler) tiap 15 menit.

## 5. Cek cepat

| Cek | Hasil yang diharapkan |
| --- | --- |
| `composer install` | Folder `vendor/` ada |
| GET `/` | Halaman landing Notepad Share |
| GET `/n/demo` | Editor (brand NS) |
| GET `/api/session` | JSON `display_name`, `csrf` |
| Dua tab URL sama | Teks menyatu setelah debounce simpan |

## Troubleshooting

| Gejala | Perbaikan |
| --- | --- |
| 403 Forbidden (halaman nginx / Cloudflare) | Document root bukan `public/`, atau nginx tanpa `try_files`. Lihat [NGINX.md](NGINX.md) |
| 500 Internal Server Error / redirect loop | `RewriteBase` salah. Untuk `notepad-share.test` harus `/` |
| 404 semua URL kecuali file | `mod_rewrite` / `RewriteBase` tidak cocok path |
| Preview kosong / request nunggu lama | Jangan pakai SSE panjang di `php -S`; restart server dengan `index.php` sebagai router |
| Tidak bisa unggah / simpan | Hak tulis `data/` |
| Class CommonMark not found | Jalankan `composer install` di root, bukan hanya di `public/` |
| Cookie session hilang | Path cookie `/`; domain harus sama |

Desain lengkap: [SDD_Notepad_Share.md](SDD_Notepad_Share.md). Urutan fitur: [ROADMAP.md](ROADMAP.md).
