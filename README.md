<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Chatbot Herbal Telegram

MVP ini menerima pesan melalui webhook Telegram, menggunakan Gemini untuk alur edukasi dan screening, serta mengambil seluruh rekomendasi dari `12_TERBARU_Produk_Herbal_Terstruktur_n8n_Gemini.json`. Percakapan disimpan pada cache file selama 24 jam dan tidak memerlukan database.

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

Model AI hanya digunakan untuk mengubah pesan menjadi intent, kategori keluhan, dan fakta terstruktur. Model tidak menulis jawaban Telegram dan tidak memilih produk. Domain gate Laravel menolak pesan di luar kesehatan, sedangkan produk dipilih dari matriks kurasi pada `config/herbal_rules.php`. Seluruh sapaan, klarifikasi, screening, rekomendasi, pantangan, dan respons darurat dirender dari template Laravel.

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
