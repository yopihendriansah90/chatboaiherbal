<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Chatbot Herbal Telegram

MVP ini menerima pesan melalui webhook Telegram dan memakai AI hanya sebagai parser/renderer terbatas. Runtime produk mengutamakan database, sedangkan `12_TERBARU_Produk_Herbal_Terstruktur_n8n_Gemini.json` menjadi sumber import dan fallback selama transisi. State aktif disimpan dalam cache, sedangkan identitas pengguna, sesi, dan riwayat pesan disimpan terenkripsi di database untuk monitoring admin.

### Konfigurasi

Salin `.env.example` ke `.env`, lalu isi minimal:

```dotenv
CACHE_STORE=file
TELEGRAM_BOT_TOKEN=token_dari_botfather
TELEGRAM_WEBHOOK_SECRET=secret_acak_hanya_huruf_angka_underscore_strip
TELEGRAM_WEBHOOK_URL=https://domain-anda.example/api/telegram/webhook
GEMINI_API_KEY=api_key_google_ai_studio
GEMINI_MODEL=gemini-3.5-flash
GROQ_API_KEY=api_key_dari_console_groq
GROQ_MODEL=llama-3.3-70b-versatile
AI_PROVIDER=groq
```

Webhook Telegram membutuhkan URL HTTPS publik. Setelah aplikasi tersedia secara publik, pasang dan periksa webhook dengan:

```bash
php artisan telegram:webhook set
php artisan telegram:webhook info
```

Gunakan `php artisan telegram:webhook delete` untuk melepas webhook. Jalankan pengujian dengan `php artisan test`.

Bot mendukung `/start` dan `/reset`. Tanda darurat akan menghentikan rekomendasi produk dan mengarahkan pengguna mencari pertolongan medis.

### Arsitektur guardrail

Model AI hanya digunakan untuk mengubah pesan menjadi domain, intent, kategori keluhan, dan fakta terstruktur. Model tidak memilih produk. Domain router Laravel memilih `company_profile` atau `health_herbal`; produk dipilih dari matriks kurasi database dengan `config/herbal_rules.php` sebagai fallback deployment. Seluruh keputusan screening, rekomendasi, pantangan, harga, stok, link, dan respons darurat ditentukan Laravel.

### Domain Pack Walatra

Satu Business Profile **Walatra Herbal** memiliki dua Domain Pack yang dapat diaktifkan melalui **Operasional → Pengaturan Bot → Domain Pack**:

- **Profile Company** untuk profil, alamat, kontak, jam operasional, legalitas, pemesanan, pengiriman, reseller, dan FAQ.
- **AI Asisten Herbal** untuk keluhan, screening, edukasi, pantangan, serta rekomendasi produk.

Informasi perusahaan dikelola melalui **Walatra Herbal → Profil Perusahaan**. Produk, klaim tervalidasi, pantangan, harga, stok, link, serta matriks kategori dikelola melalui menu **Produk Herbal** dan **Kategori & Rekomendasi**.

Katalog awal dapat divalidasi atau diimpor ulang secara idempotent:

```bash
php artisan herbal:import-catalog --dry-run
php artisan herbal:import-catalog
php artisan herbal:import-catalog --update # timpa data produk dari JSON secara eksplisit
```

RAG belum menjadi dependensi. Informasi perusahaan dan produk hanya berasal dari data terstruktur yang dikelola admin.

### Prompt AI yang dapat dikustomisasi

Menu **Walatra Herbal → Prompt AI** menyediakan prompt branding, parser, dan renderer per domain. Perubahan disimpan sebagai draft, diperiksa oleh policy validator, lalu dipublikasikan dengan versi baru. Versi aktif dapat dikembalikan ke default.

Core JSON schema, larangan membuat produk/harga/link, prompt-injection guard, dan batas kewenangan renderer tetap berada di source code serta tidak dapat dihapus melalui panel. Prompt custom hanya mengubah branding dan gaya komunikasi.

Groq digunakan sebagai provider aktif saat `AI_PROVIDER=groq`; Gemini tetap tersedia di kode tetapi tidak dipanggil. Gunakan `AI_PROVIDER=gemini` untuk Gemini saja atau `AI_PROVIDER=auto` untuk mencoba Gemini lalu berpindah ke Groq ketika kuota Gemini habis.

### Natural response renderer

Parser dan renderer memakai model terpisah. Parser hanya mengekstrak intent/kategori/fakta, sedangkan renderer hanya memperhalus `ResponsePlan` yang sudah diputuskan Laravel. Nama produk, manfaat, cara kerja, dan link selalu dirender Laravel serta tidak dapat diubah renderer.

```dotenv
GROQ_PARSER_MODEL=openai/gpt-oss-20b
GROQ_RENDERER_MODEL=qwen/qwen3.6-27b
GROQ_RENDERER_TIMEOUT=12
CHATBOT_NATURAL_RENDERER=true
CHATBOT_RENDERER_MAX_WORDS=45
```

Matikan renderer untuk rollback instan tanpa menghentikan bot:

```dotenv
CHATBOT_NATURAL_RENDERER=false
```

Jalankan regresi deterministik dengan `php artisan chatbot:evaluate` dan seluruh test dengan `php artisan test`.

### Health endpoint

`GET /up` mengembalikan status JSON aman untuk monitoring: aplikasi, cache, jumlah produk, serta boolean konfigurasi Telegram, parser, dan renderer. Endpoint tidak menampilkan token, API key, webhook URL, isi percakapan, atau stack trace. Status `down` memakai HTTP 503; `ok` dan `degraded` memakai HTTP 200.

Endpoint internal `GET /api/internal/health` memberikan diagnosis lebih lengkap dan wajib dilindungi token:

```dotenv
INTERNAL_HEALTH_TOKEN=isi_dengan_token_acak_yang_panjang
INTERNAL_HEALTH_FAILURES_LIMIT=10
```

```bash
curl -H "Authorization: Bearer $INTERNAL_HEALTH_TOKEN" https://domain.example/api/internal/health
```

Header `X-Internal-Health-Token` juga didukung. Respons internal berisi versi runtime, status storage/logging, model dan timeout AI, detail host webhook, konfigurasi state percakapan, metadata katalog, dan kegagalan AI terbaru yang sudah difilter. Secret tetap tidak pernah ditampilkan.

Informasi yang sama tersedia langsung di panel Filament pada `/admin/system-health`. Halaman **Status Sistem** hanya dapat dibuka setelah login admin, tersedia di grup navigasi **Operasional**, dan memiliki tombol **Perbarui status**. Endpoint internal tetap dipertahankan untuk health checker atau integrasi monitoring mesin.

### Pengaturan bot melalui Filament

Admin dapat membuka `/admin/bot-settings` atau menu **Operasional → Pengaturan Bot** untuk mengelola Telegram, routing AI, natural renderer, memori, dan retensi percakapan. Token Telegram dan webhook secret dipindahkan bertahap ke registry `channel_integrations`; tabel `bot_settings` tetap menjadi fallback selama masa transisi. API key AI disimpan terenkripsi per provider di tabel `ai_providers`. Input secret yang dibiarkan kosong mempertahankan nilai lama.

Konfigurasi database yang aktif menggantikan nilai `.env`, sementara `.env` tetap menjadi fallback untuk secret yang belum disimpan. Halaman menyediakan action untuk menguji Telegram/Groq serta memeriksa, memasang, dan menghapus webhook. Jalankan migrasi sebelum menggunakan halaman:

```bash
php artisan migrate
```

### Multi-provider AI

Menu **Operasional → AI Providers** (`/admin/ai-providers`) mengelola koneksi Groq, OpenAI, dan Gemini. Setiap API key disimpan terenkripsi dan tidak pernah dimuat kembali ke form. Konfigurasi provider hanya berisi koneksi, status, prioritas, dan timeout agar pengaturannya tetap sederhana.

Setiap provider memiliki tab **Model**. Di sana admin menambahkan model ID API, nama tampilan, kemampuan parser/renderer/structured output, status, context window, dan versi harga token. Harga dalam rupiah pada tabel model selalu dihitung menggunakan record **Nilai Dolar** terbaru.

Model parser utama, model renderer, dan urutan model fallback dipilih pada tab **Strategi AI** di `/admin/bot-settings`. Satu daftar model dapat berisi model dari provider berbeda, sehingga routing cukup diatur sekali. Parser mencoba model fallback berikutnya ketika model utama timeout, terkena rate limit, menghasilkan JSON invalid, atau tidak tersedia. Setelah tiga kegagalan, circuit breaker melewati kombinasi provider-model tersebut selama lima menit. Renderer tidak memakai fallback generatif; kegagalannya langsung memakai template Laravel.

Fallback `.env` untuk deployment baru:

```dotenv
AI_PARSER_PROVIDER=groq
AI_RENDERER_PROVIDER=groq
AI_PARSER_FALLBACK=true
AI_PARSER_FALLBACK_ORDER=groq,openai,gemini
OPENAI_API_KEY=
OPENAI_PARSER_MODEL=gpt-5.4-mini
OPENAI_RENDERER_MODEL=gpt-5.4-mini
```

### Monitoring token dan estimasi biaya AI

Menu **AI Usage → Laporan Usage** (`/admin/ai-usage`) mencatat setiap attempt API Groq, Gemini, dan OpenAI, termasuk fallback dan respons gagal. Laporan menyimpan provider, model, peran parser/renderer, input token, cached input, output/reasoning token, latency, status API, serta estimasi biaya USD dan rupiah. Isi prompt dan kondisi kesehatan pengguna tidak disimpan.

Halaman **Operasional → AI Providers** menjadi pusat pengelolaan setiap provider. Buka action **Kelola** pada Groq, Gemini, atau OpenAI untuk mengakses tab **Konfigurasi**, **Model**, dan **Usage Provider** dalam satu halaman. Ringkasan token/biaya bulanan, kesiapan harga dan kurs, tingkat keberhasilan HTTP, serta grafik 30 hari otomatis difilter untuk provider yang sedang dibuka. Laporan Usage global tetap tersedia untuk membandingkan seluruh provider.

Harga tidak diambil atau diubah otomatis. Admin menekan **Perbarui harga** pada model terkait dan memasukkan harga resmi per satu juta token beserta URL sumber, kemudian memasukkan nilai USD/IDR yang sudah diverifikasi melalui **AI Usage → Nilai Dolar**. Setiap perubahan harga dan nilai dolar dibuat sebagai record baru agar histori audit tetap utuh. Nilai terbaru yang tanggal berlakunya tidak berada di masa depan otomatis menjadi acuan untuk request AI berikutnya dan estimasi IDR pada UI.

Biaya dihitung saat response API diterima dan menyimpan snapshot harga serta kurs yang digunakan. Jika harga model belum tersedia, token tetap tercatat tetapi biaya ditampilkan sebagai belum dihitung. Jika kurs belum tersedia, biaya USD tetap dihitung sedangkan biaya rupiah menunggu kurs pada request berikutnya. Data historis tidak dihitung ulang ketika harga atau kurs baru ditambahkan.

Setelah deployment, jalankan:

```bash
php artisan migrate --seed --force
```

Seeder standar juga menyiapkan Business Profile Walatra, dua Domain Pack, prompt default, 13 kategori rekomendasi, 18 produk, dan 44 baris komposisi. Seluruh seeder bersifat idempotent dan aman dijalankan kembali. API key serta secret Telegram tidak ditulis ke source code. Harga awal model AI merupakan snapshot USD bertanggal tetap dengan URL sumber resmi. Untuk perubahan harga berikutnya, tambahkan versi baru melalui panel agar histori tetap utuh.

Seeder tidak membuat kurs USD/IDR karena kurs dikelola manual dan berubah dari waktu ke waktu. Setelah deployment pertama, tambahkan **Nilai Dolar** terbaru melalui panel admin.

### Kurs USD/IDR dari CurrencyFreaks

Halaman **AI Usage → Nilai Dolar** mendukung input manual dan pengambilan kurs dari CurrencyFreaks. Tekan **Konfigurasi API** untuk menyimpan API key secara terenkripsi, mengatur batas peringatan perubahan kurs, dan memilih sinkronisasi otomatis. API key yang sudah disimpan tidak pernah ditampilkan kembali.

Tekan **Ambil kurs API** untuk memanggil endpoint `latest`, mengambil hanya nilai `rates.IDR` dari respons, membandingkannya dengan kurs aktif, lalu mengonfirmasi sebelum menyimpan. Endpoint sengaja dipanggil tanpa parameter `symbols` agar kompatibel dengan paket gratis; menurut dokumentasi provider, filter mata uang tertentu hanya tersedia pada paket berbayar. Setiap nilai tersimpan sebagai record histori baru; kegagalan API tidak menonaktifkan atau menimpa kurs lama. Kurs ini bersifat indikatif dari provider dan bukan kurs transaksi bank atau JISDOR. Referensi format respons dan parameter tersedia di [dokumentasi CurrencyFreaks](https://currencyfreaks.com/documentation).

Perintah operasional:

```bash
php artisan exchange-rate:sync --dry-run
php artisan exchange-rate:sync
php artisan exchange-rate:sync --force
```

Sinkronisasi otomatis berjalan setiap hari pukul 09.00 WIB jika toggle-nya diaktifkan dan scheduler Laravel berjalan. `CURRENCYFREAKS_API_KEY` di `.env` hanya disediakan sebagai fallback untuk deployment awal; konfigurasi database diprioritaskan.

Di production, token bot, webhook secret, dan webhook URL Telegram dapat disimpan langsung melalui **Operasional → Pengaturan Bot → Telegram**. Runtime Telegram dan validasi webhook memprioritaskan record `telegram-primary` pada `channel_integrations`, lalu memakai `bot_settings` dan `.env` sebagai fallback transisi. Secret disimpan memakai encrypted cast Laravel dan tidak pernah ditampilkan kembali oleh form.

### Pengguna dan riwayat percakapan

Semua pesan Telegram baru dinormalisasi melalui channel adapter sebelum masuk ke chatbot core. Identitas channel, kontak, sesi konsultasi, pesan masuk, pesan keluar, dan status pengiriman disimpan pada tabel terpisah. Isi pesan dan metadata channel memakai encrypted cast Laravel; payload webhook lengkap tidak disimpan.

Menu **Chatbot → Pengguna Chatbot** (`/admin/chatbot-contacts`) menampilkan pengguna terbaru, channel, username/ID, Chat ID, status, serta jumlah aktivitas. Detail identitas ditampilkan melalui modal ringkas. Menu **Chatbot → Percakapan** (`/admin/chatbot-conversations`) menampilkan sesi terbaru dan riwayat pesan untuk admin.

Halaman Percakapan mendukung pencarian kata atau kalimat di dalam isi pesan terenkripsi, filter pengirim, tanggal, channel, pengguna, kategori, produk, status, dan kondisi darurat. Pencarian dilakukan di server dalam batch dan hasilnya di-cache singkat tanpa membuat kolom isi chat plaintext.

Admin dapat mengunduh satu percakapan, beberapa percakapan terpilih, atau seluruh hasil filter sebagai JSON versi `1.0`. Identitas pengguna dianonimkan secara default; opsi **Sertakan identitas pengguna** harus diaktifkan secara eksplisit jika audit memang membutuhkan nama, username, atau ID channel. Setiap ekspor dicatat pada `conversation_exports` tanpa menyimpan isi chat atau kata pencarian mentah di log audit.

Webhook mendaftarkan update `message` dan `my_chat_member`. Update kedua dipakai untuk menandai pengguna yang memblokir atau mengaktifkan kembali bot. Pasang ulang webhook setelah deployment:

```bash
php artisan telegram:webhook set
```

Retensi pesan dan batas pengguna tidak aktif dapat diatur melalui tab **Percakapan** di Pengaturan Bot. Pembersihan berjalan melalui scheduler dan dapat dijalankan manual:

```bash
php artisan chatbot:purge-history
php artisan chatbot:purge-history --days=90
```

Server production perlu menjalankan scheduler Laravel. `APP_KEY` tidak boleh diganti setelah data tersimpan karena dipakai untuk mendekripsi credential, metadata, dan isi pesan.

Untuk deployment di belakang Cloudflare, Traefik, atau reverse proxy lain, gunakan `APP_URL=https://domain-production.example` dan `ASSET_URL=https://domain-production.example`. Aplikasi mempercayai header proxy dan memakai asset root eksplisit agar dynamic import Filament/Livewire selalu menggunakan HTTPS dan tidak terkena mixed content.

Nilai biaya merupakan estimasi berdasarkan harga manual, bukan pengganti invoice resmi provider.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
# chatbotWalatraherbal
# chatboaiherbal
