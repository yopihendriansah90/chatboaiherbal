# Implementasi Chatbot Single-Company

Status: core tahap 1–7 sudah diimplementasikan. WhatsApp dan API mobile/web tetap menjadi tahap berikutnya setelah core berjalan stabil di staging.

## Yang sudah dikerjakan

- Quality gate SQLite in-memory dan MySQL 8.4, CI GitHub Actions, serta evaluation dataset 12/12.
- Inbound event terenkripsi dan idempotent, Redis queue, lock percakapan, retry/backoff, recovery, outbox, serta dead-letter state.
- State percakapan terenkripsi di database; Redis hanya dipakai untuk cache, lock, rate limit, dan queue.
- Usia aktual (`age_years`) dipisahkan dari kelompok usia, termasuk status hamil dan menyusui.
- Safety assessment `allow`, `caution`, `consult`, dan `block` dengan reason code dan guidance.
- Validasi klaim/harga berdasarkan masa berlaku, approval klaim, reviewer pantangan, stok, serta satu link utama.
- CS Inbox: handoff, assignment, balasan agen melalui outbox, pause/return bot, SLA, note, tag, priority, resolution, dan audit event.
- Role admin: `super_admin`, `supervisor`, `agent`, `content_reviewer`, dan `analyst`.
- Persona terstruktur, `ResponsePlan` terstruktur, renderer tervalidasi, serta memori pelanggan berbasis consent.
- Health report untuk database, Redis, Horizon, provider, Telegram, queue backlog/dead-letter, latency, biaya, fallback, handoff, SLA, dan feedback.
- Kontrak `MessagingChannel` tetap menjadi batas integrasi channel; orchestrator tidak mengirim melalui Telegram client secara langsung.
- Sesi konsultasi eksplisit: ucapan seperti “apakah bisa konsultasi dulu” membuka satu kasus durable, mengaitkan pesan masuk/keluar, menyimpan fase/fakta/safety secara terenkripsi, dan mencatat perubahan ke audit event.
- Daftar percakapan Filament menampilkan status serta tahap konsultasi dan dapat difilter untuk antrean operasional CS.

## Menjalankan quality gate

```bash
# Jika pdo_sqlite aktif pada PHP host
composer test
php artisan chatbot:evaluate

# Alternatif reproducible tanpa mengubah PHP host
docker build -t chatbot-php-test:8.4 .docker/php-test
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp \
  -v "$PWD:/app" -w /app chatbot-php-test:8.4 php artisan test

# MySQL integration test
composer test:mysql
```

## Runtime production

- Gunakan MySQL dan Redis, lalu jalankan `php artisan horizon` sebagai service yang selalu hidup.
- Jalankan `php artisan schedule:work` atau cron `schedule:run` setiap menit.
- Pastikan queue `inbound`, `outbound`, dan `default` dikonsumsi Horizon.
- Jalankan migrasi dan seeder profil bisnis sebelum webhook Telegram diaktifkan.
- Pantau `/health`, internal health endpoint, Horizon, dead event, dead delivery, dan SLA inbox.

## Tahap berikutnya

1. Uji staging dengan Telegram sungguhan, Redis sungguhan, dan simulasi provider timeout/rate-limit.
2. Tambahkan golden conversation lebih banyak dari transkrip anonim yang sudah mendapat izin.
3. Stabilkan SOP CS dan target SLA.
4. Tambahkan aksi pelanggan untuk menutup konsultasi atau membuka kasus baru setelah SOP wording disetujui.
5. Implementasikan `WhatsAppChannel` tanpa mengubah orchestrator.
6. Implementasikan adapter API terautentikasi untuk mobile/web dan identity linking berbasis consent.
