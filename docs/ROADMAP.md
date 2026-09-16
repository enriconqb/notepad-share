# Roadmap implementasi — Notepad Share

Mengikuti [SDD_Notepad_Share.md](SDD_Notepad_Share.md). Stack: PHP 8.2 native, JSON, `.htaccess`, `league/commonmark`, mermaid-js.

| Fase | Isi | Use case | Kriteria selesai |
| --- | --- | --- | --- |
| 0 | Dokumen roadmap ini | — | Tim punya urutan kerja |
| 1 | Composer, router, session/CSRF, MarkdownRenderer, `.htaccess` | fondasi | `composer install` sukses; rewrite ke `index.php` |
| 2 | Store JSON, API, SSE, presence, preview, prune, landing | UC-01, UC-02, UC-05, UC-15 | CRUD catatan + preview HTML GFM |
| 3 | UI editor dari `collabmark.html` | UC-03–UC-11, UC-14 | Dua klien sync; gambar; history; retensi |
| 4 | PQC + passphrase ruang | UC-12, UC-13 | `PQC1:` / `AES1:`; preview server-blind jika encrypted |
| 5 | Uji browser Laragon | NFR | Dua tab, mermaid, unggah, tema, mobile |

## Pemetaan use case

| ID | Fase |
| --- | --- |
| UC-01 Buat ruang | 2–3 |
| UC-02 Buka ruang | 2–3 |
| UC-03 Edit bersama | 2–3 |
| UC-04 Format MD/TXT | 3 |
| UC-05 Preview | 1–3 |
| UC-06 Gambar | 2–3 |
| UC-07 Share URL | 3 |
| UC-08 Snapshot | 2–3 |
| UC-09 Pulihkan history | 2–3 |
| UC-10 Retensi | 2–3 |
| UC-11 Hapus history | 2–3 |
| UC-12 Enkripsi PQC | 4 |
| UC-13 Dekripsi | 4 |
| UC-14 Ganti nama | 2–3 |
| UC-15 Prune | 2 |

## Menjalankan lokal

- Laragon: DocumentRoot `public/` atau buka `/notepad-share/` (rewrite ke `public/`).
- Pengembangan: `php -S 127.0.0.1:8765 index.php` di folder `public/`. Built-in server PHP satu thread — SSE dipersingkat; klien memakai polling 1,5 s.

- `public/.htaccess`, `public/index.php`, `public/assets/app.html`
- `app/*.php`, `app/controllers/*.php`
- `data/notes|history|uploads`
- `cron/prune.php`
