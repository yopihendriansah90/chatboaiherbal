# Panduan Menjalankan Laravel Horizon

Dokumen ini menjelaskan cara menjalankan dan merawat Laravel Horizon pada proyek chatbot. Horizon memproses antrean Redis `inbound`, `outbound`, dan `default` untuk menerima pesan, menjalankan chatbot, serta mengirim balasan.

Ada dua cara menjalankannya:

1. systemd untuk instalasi langsung/non-Docker;
2. container terpisah untuk server Docker.

Jika server menggunakan Docker, gunakan bagian **Menjalankan Horizon dengan Docker Compose**. Jangan menjalankan systemd di dalam container.

Panduan lengkap mulai dari instalasi server, pembuatan image production, project Laravel, MySQL, Redis, queue, Horizon, scheduler, HTTPS, webhook, deployment, dan backup tersedia di [`PANDUAN_DEPLOYMENT_DOCKER.md`](PANDUAN_DEPLOYMENT_DOCKER.md).

## Konfigurasi proyek

- Direktori aplikasi: `/home/popes/Project/chatbot`
- Nama service: `chatbot-horizon.service`
- Service file: `/home/popes/.config/systemd/user/chatbot-horizon.service`
- Queue connection: Redis
- Queue yang diproses: `inbound`, `outbound`, dan `default`

Horizon sudah dikelola oleh systemd. Karena itu, jangan menjalankan `php artisan horizon` secara manual saat service systemd aktif. Dua Horizon yang berjalan bersamaan dapat membuat worker lama memproses pesan dengan kode yang belum diperbarui.

Bagian systemd di bawah digunakan untuk environment development saat ini. Pada server Docker, proses Horizon harus dikelola oleh Docker Compose dengan kebijakan restart container.

## 1. Masuk ke direktori aplikasi

```bash
cd /home/popes/Project/chatbot
```

## 2. Memastikan Redis aktif

```bash
redis-cli ping
```

Hasil yang benar:

```text
PONG
```

Jika Redis belum aktif:

```bash
sudo systemctl start redis-server
```

## 3. Menjalankan Horizon

```bash
systemctl --user start chatbot-horizon.service
```

Pastikan statusnya aktif:

```bash
systemctl --user is-active chatbot-horizon.service
```

Hasil yang diharapkan:

```text
active
```

Untuk melihat informasi worker secara lengkap:

```bash
systemctl --user status chatbot-horizon.service
```

Tekan `q` untuk keluar dari tampilan status.

## 4. Restart setelah perubahan kode

Setiap kali kode chatbot, job, service, atau konfigurasi queue berubah, muat ulang worker menggunakan:

```bash
cd /home/popes/Project/chatbot
php artisan optimize:clear
php artisan horizon:terminate
systemctl --user restart chatbot-horizon.service
```

Kemudian verifikasi:

```bash
systemctl --user is-active chatbot-horizon.service
```

`horizon:terminate` menghentikan semua master dan worker Horizon dengan aman. systemd kemudian menjalankan proses baru yang membaca kode terbaru.

## 5. Menghentikan Horizon

```bash
systemctl --user stop chatbot-horizon.service
```

Gunakan perintah ini saat melakukan maintenance yang mengharuskan queue berhenti diproses.

## 6. Menjalankan otomatis saat login atau boot

Aktifkan service:

```bash
systemctl --user enable chatbot-horizon.service
```

Periksa apakah sudah aktif otomatis:

```bash
systemctl --user is-enabled chatbot-horizon.service
```

Hasil yang diharapkan:

```text
enabled
```

## 7. Melihat log Horizon

Tampilkan log secara realtime:

```bash
journalctl --user -u chatbot-horizon.service -f
```

Tampilkan 100 baris terakhir:

```bash
journalctl --user -u chatbot-horizon.service -n 100 --no-pager
```

Log aplikasi Laravel juga dapat diperiksa dengan:

```bash
tail -f storage/logs/laravel.log
```

Tekan `Ctrl+C` untuk berhenti mengikuti log.

## 8. Membuka dashboard Horizon

Pastikan web server Laravel sedang berjalan, lalu buka:

```text
http://localhost/horizon
```

Di environment selain `local`, pengguna harus login dengan role yang memiliki akses `analyst` untuk membuka dashboard Horizon.

Dashboard menampilkan:

- job yang sedang menunggu dan diproses;
- job selesai atau gagal;
- waktu tunggu queue;
- supervisor dan worker aktif;
- metrik throughput.

## 9. Memeriksa worker ganda

Gunakan:

```bash
ps aux | grep '[p]hp artisan horizon'
```

Dalam setup ini seharusnya hanya ada satu master berikut:

```text
/usr/bin/php artisan horizon
```

Proses `horizon:supervisor` dan beberapa `horizon:work` adalah normal. Yang tidak boleh ada adalah dua master `php artisan horizon` dari systemd dan terminal manual.

Jika ditemukan lebih dari satu master:

```bash
cd /home/popes/Project/chatbot
php artisan horizon:terminate
systemctl --user restart chatbot-horizon.service
```

Jangan membuka terminal lain lalu menjalankan:

```bash
php artisan horizon
```

selama `chatbot-horizon.service` aktif.

## 10. Troubleshooting

### Bot menerima pesan tetapi tidak membalas

Jalankan pemeriksaan berikut:

```bash
redis-cli ping
systemctl --user status chatbot-horizon.service
php artisan horizon:status
tail -n 100 storage/logs/laravel.log
```

Jika Horizon tidak aktif:

```bash
systemctl --user restart chatbot-horizon.service
```

### Balasan masih memakai kode lama

```bash
php artisan optimize:clear
composer dump-autoload
php artisan horizon:terminate
systemctl --user restart chatbot-horizon.service
```

### Job gagal

Lihat penyebabnya di dashboard `/horizon` atau log Laravel. Setelah penyebab diperbaiki, job gagal dapat dicoba ulang melalui dashboard Horizon.

### Status service gagal

```bash
journalctl --user -u chatbot-horizon.service -n 150 --no-pager
```

Periksa terutama koneksi Redis, konfigurasi `.env`, izin direktori, dan error PHP.

## 11. Menjalankan Horizon dengan Docker Compose

Pada Docker, Horizon sebaiknya menjadi **service/container tersendiri**. Container web/PHP dan container Horizon memakai image aplikasi, source code, `.env`, network, serta koneksi Redis/MySQL yang sama, tetapi menjalankan command berbeda.

Arsitektur yang disarankan:

```text
Internet
   ↓
Web/Nginx → Container aplikasi Laravel
                    ↓
                 Redis
                    ↑
             Container Horizon
                    ↓
                  MySQL
```

Jangan menjalankan Horizon sebagai background process di dalam container aplikasi. Satu container sebaiknya memiliki satu tanggung jawab utama sehingga lifecycle, log, restart, dan health check dapat dikelola dengan jelas.

### Contoh service Horizon

Repo ini belum mempunyai Dockerfile dan Compose production. Contoh berikut adalah template yang harus digabungkan ke `compose.yml` server. Ganti nama image, direktori kerja, volume, dan network mengikuti service aplikasi yang sudah ada.

```yaml
services:
  app:
    image: registry.example.com/chatbot:latest
    env_file:
      - .env
    networks:
      - chatbot

  horizon:
    image: registry.example.com/chatbot:latest
    init: true
    restart: unless-stopped
    working_dir: /var/www/html
    command: ["php", "artisan", "horizon"]
    env_file:
      - .env
    depends_on:
      redis:
        condition: service_healthy
      mysql:
        condition: service_healthy
    stop_grace_period: 180s
    networks:
      - chatbot

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - chatbot

  mysql:
    image: mysql:8.4
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: dbchatbot
      MYSQL_USER: chatbot
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -uroot -p$$MYSQL_ROOT_PASSWORD"]
      interval: 10s
      timeout: 5s
      retries: 10
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - chatbot

networks:
  chatbot:

volumes:
  mysql-data:
```

Jika source code pada server menggunakan bind mount, berikan volume yang sama kepada `app` dan `horizon`:

```yaml
services:
  app:
    volumes:
      - ./:/var/www/html

  horizon:
    volumes:
      - ./:/var/www/html
```

Untuk production, source code yang sudah dimasukkan ke dalam immutable image lebih disarankan daripada bind mount. Dengan immutable image, versi kode container web dan Horizon pasti sama.

### Extension PHP yang diperlukan

Image aplikasi untuk Horizon minimal harus memiliki:

- `pcntl` untuk mengelola worker dan sinyal;
- `posix`;
- `pdo_mysql`;
- extension Redis atau client Redis yang digunakan aplikasi;
- extension lain yang diwajibkan Laravel dan aplikasi.

Pastikan image Horizon adalah image aplikasi production, bukan `.docker/php-test/Dockerfile`, karena Dockerfile tersebut dibuat khusus untuk test.

### Konfigurasi `.env` di Docker

Alamat `127.0.0.1` dari dalam container menunjuk ke container itu sendiri. Gunakan nama service Compose untuk koneksi antarkontainer:

```dotenv
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=dbchatbot
DB_USERNAME=chatbot
DB_PASSWORD=ubah_dengan_secret_yang_aman

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

Gunakan Docker secrets atau secret manager server untuk kredensial production. Jangan commit `.env` production ke Git.

### Menjalankan container Horizon

Dari direktori yang memiliki `compose.yml`:

```bash
docker compose up -d redis mysql
docker compose up -d app horizon
```

Periksa container:

```bash
docker compose ps
```

Periksa status Horizon dari container:

```bash
docker compose exec horizon php artisan horizon:status
```

### Melihat log

```bash
docker compose logs -f --tail=100 horizon
```

Log Laravel:

```bash
docker compose exec horizon tail -f storage/logs/laravel.log
```

Jika log aplikasi diarahkan ke `stderr`, log tersebut langsung muncul pada `docker compose logs` dan lebih sesuai untuk production Docker.

### Restart Horizon setelah perubahan kode

Jika source code menggunakan bind mount:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec horizon php artisan horizon:terminate
docker compose restart horizon
```

Jika source code dimasukkan ke image, build atau pull image baru kemudian recreate container:

```bash
docker compose pull app horizon
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan optimize:clear
docker compose up -d --force-recreate app horizon
```

Jika image dibangun langsung di server:

```bash
docker compose build app horizon
docker compose run --rm app php artisan migrate --force
docker compose up -d --force-recreate app horizon
```

### Menghentikan dan menjalankan ulang

```bash
# Hentikan Horizon saja
docker compose stop horizon

# Jalankan Horizon lagi
docker compose start horizon

# Restart Horizon
docker compose restart horizon
```

### Mencegah worker ganda

Jalankan satu service Horizon terlebih dahulu:

```bash
docker compose up -d --scale horizon=1 horizon
```

Periksa semua container yang menjalankan Horizon:

```bash
docker ps --format '{{.Names}}\t{{.Command}}' | grep horizon
```

Jangan menjalankan command berikut di container `app` jika service `horizon` sudah aktif:

```bash
docker compose exec app php artisan horizon
```

Menambah replica Horizon boleh dilakukan ketika beban memang membutuhkan, tetapi harus disengaja dan semua replica wajib memakai image serta versi kode yang sama.

### Troubleshooting Docker

#### Horizon terus restart

```bash
docker compose ps horizon
docker compose logs --tail=200 horizon
```

Periksa extension PHP, `APP_KEY`, koneksi Redis, permission direktori, dan konfigurasi `.env`.

#### Redis tidak dapat dihubungi

```bash
docker compose exec redis redis-cli ping
docker compose exec horizon php artisan tinker --execute="dump(Illuminate\\Support\\Facades\\Redis::ping());"
```

Pastikan `REDIS_HOST=redis`, bukan `127.0.0.1`.

#### Database tidak dapat dihubungi

```bash
docker compose exec horizon php artisan migrate:status
```

Pastikan `DB_HOST=mysql` dan container berada pada network Compose yang sama.

#### Balasan masih memakai kode lama

Periksa image yang digunakan kedua container:

```bash
docker inspect "$(docker compose ps -q app)" --format '{{.Config.Image}}'
docker inspect "$(docker compose ps -q horizon)" --format '{{.Config.Image}}'
```

Kemudian recreate keduanya:

```bash
docker compose exec horizon php artisan horizon:terminate
docker compose up -d --force-recreate app horizon
```

## Checklist deployment systemd/non-Docker

```bash
cd /home/popes/Project/chatbot
php artisan migrate --force
php artisan optimize:clear
composer dump-autoload --no-interaction
php artisan horizon:terminate
systemctl --user restart chatbot-horizon.service
systemctl --user is-active chatbot-horizon.service
php artisan horizon:status
```

Deployment berhasil jika service berstatus `active`, Horizon berstatus berjalan, dan tidak ada error baru di `storage/logs/laravel.log`.

## Checklist deployment Docker

```bash
docker compose pull app horizon
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan optimize:clear
docker compose up -d --force-recreate app horizon
docker compose ps
docker compose exec horizon php artisan horizon:status
docker compose logs --tail=100 horizon
```

Deployment Docker berhasil jika container `app`, `horizon`, `redis`, dan `mysql` berstatus berjalan, `horizon:status` menyatakan Horizon aktif, serta tidak ada job gagal atau error koneksi baru.
