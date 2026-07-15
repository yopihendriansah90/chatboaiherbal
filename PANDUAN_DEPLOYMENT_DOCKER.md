# Panduan Deployment Chatbot Laravel dengan Docker

Panduan ini menjelaskan deployment dari server kosong sampai chatbot Telegram berjalan menggunakan Laravel 12, Filament 4, MySQL, Redis, queue, Horizon, dan scheduler.

Contoh menggunakan asumsi berikut:

- server: Ubuntu 24.04 LTS;
- domain: `chatbot.example.com`;
- direktori project: `/opt/chatbot`;
- file Compose: `compose.production.yml`;
- file environment: `.env.production`;
- Docker Compose plugin modern: `docker compose`, bukan binary lama `docker-compose`.

Ganti seluruh nilai contoh sesuai server dan domain sebenarnya.

## 1. Arsitektur production

```text
Telegram
    ↓ HTTPS webhook
Reverse proxy/TLS
    ↓
Nginx container
    ↓ FastCGI
Laravel app container
    ↓ durable inbound event
MySQL ←→ Redis queue/cache/lock
              ↓
       Horizon container
          ↙        ↘
    inbound       outbound
       ↓             ↓
  proses AI     kirim Telegram

Scheduler container
    ↓
recovery, cleanup, Horizon metrics, kurs, health heartbeat
```

Container yang digunakan:

| Service | Fungsi | Boleh lebih dari satu? |
|---|---|---|
| `web` | Nginx dan static assets | Ya, jika memakai load balancer |
| `app` | PHP-FPM untuk Laravel/Filament/webhook | Ya |
| `horizon` | Memproses queue Redis | Mulai dari satu |
| `scheduler` | Menjalankan Laravel scheduler | Tidak |
| `mysql` | Database utama dan durable state | Tidak tanpa arsitektur cluster |
| `redis` | Queue, cache, lock, rate limit, Horizon | Tidak tanpa arsitektur cluster |

Database adalah sumber utama state percakapan. Redis digunakan untuk queue, cache, lock, rate limit, dan informasi Horizon.

## 2. Kebutuhan server

Rekomendasi awal untuk satu perusahaan:

- 2–4 vCPU;
- RAM minimal 4 GB, disarankan 8 GB jika worker AI cukup ramai;
- disk SSD minimal 40 GB;
- Ubuntu 22.04 atau 24.04 LTS 64-bit;
- domain yang sudah mengarah ke IP server;
- port publik 80 dan 443;
- port SSH dibatasi hanya dari IP administrator jika memungkinkan.

Jangan membuka port MySQL `3306` atau Redis `6379` ke internet. Keduanya cukup berada di private network Docker.

## 3. Persiapan server Ubuntu

Masuk melalui SSH:

```bash
ssh user@IP_SERVER
```

Perbarui paket dan pasang utilitas dasar:

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y ca-certificates curl git unzip ufw
```

Atur timezone:

```bash
sudo timedatectl set-timezone Asia/Jakarta
timedatectl
```

Atur firewall dasar:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

Docker dapat membuat aturan jaringan yang melewati sebagian aturan UFW. Karena itu, jangan menambahkan mapping port untuk MySQL dan Redis pada Compose. Informasi keamanan firewall Docker tersedia di [dokumentasi Docker untuk Ubuntu](https://docs.docker.com/engine/install/ubuntu/).

## 4. Instalasi Docker Engine dan Compose

Hapus paket Docker lama yang berpotensi konflik:

```bash
sudo apt remove -y docker.io docker-compose docker-compose-v2 docker-doc podman-docker containerd runc || true
```

Tambahkan repository resmi Docker:

```bash
sudo apt update
sudo apt install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

sudo tee /etc/apt/sources.list.d/docker.sources >/dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

Aktifkan Docker:

```bash
sudo systemctl enable --now docker
sudo systemctl status docker
```

Verifikasi:

```bash
sudo docker run --rm hello-world
docker compose version
```

Opsional, izinkan user deployment menggunakan Docker tanpa `sudo`:

```bash
sudo usermod -aG docker "$USER"
```

Logout lalu login kembali agar group baru aktif. Anggota group `docker` secara praktis memiliki akses setara root; batasi ke user administrator.

Referensi instalasi resmi: [Docker Engine Ubuntu](https://docs.docker.com/engine/install/ubuntu/) dan [Docker Compose plugin](https://docs.docker.com/compose/install/linux/).

## 5. Mengambil project chatbot

Buat direktori aplikasi:

```bash
sudo mkdir -p /opt/chatbot
sudo chown -R "$USER":"$USER" /opt/chatbot
```

Clone repository:

```bash
git clone REPOSITORY_GIT_ANDA /opt/chatbot
cd /opt/chatbot
```

Untuk repository private, gunakan deploy key khusus dengan akses read-only. Jangan menyimpan personal access token di command history.

## 6. File Docker production

Repo saat ini hanya mempunyai `.docker/php-test/Dockerfile` untuk test. Jangan memakai image test tersebut sebagai image production.

Buat direktori production:

```bash
mkdir -p docker/production
```

### 6.1 Dockerfile

Buat `docker/production/Dockerfile`:

```dockerfile
FROM node:22-alpine AS assets

WORKDIR /src
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /src
COPY . .
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

FROM php:8.4-fpm-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        posix \
        xml \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /src /var/www/html
COPY --from=assets --chown=www-data:www-data /src/public/build /var/www/html/public/build

RUN mkdir -p \
        storage/app \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

CMD ["php-fpm", "-F"]

FROM nginx:1.27-alpine AS web

COPY docker/production/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
```

Image `app`, `horizon`, dan `scheduler` harus berasal dari target `app` yang sama agar seluruh proses memakai versi kode yang sama.

### 6.2 Konfigurasi Nginx

Buat `docker/production/nginx.conf`:

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 20M;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        fastcgi_pass app:9000;
        fastcgi_read_timeout 120s;
    }

    location ~ /\. {
        deny all;
    }
}
```

### 6.3 Docker ignore

Buat atau lengkapi `.dockerignore`:

```text
.git
.github
.env
.env.*
vendor
node_modules
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
database/database.sqlite
tests
phpunit.xml
phpunit.mysql.xml
```

Jangan masukkan `.env.production` atau secret ke image.

## 7. Docker Compose production

Buat `compose.production.yml`:

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/production/Dockerfile
      target: app
    image: chatbot-app:${APP_IMAGE_TAG:-latest}
    restart: unless-stopped
    init: true
    env_file:
      - .env.production
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    volumes:
      - app-storage:/var/www/html/storage
    networks:
      - backend

  web:
    build:
      context: .
      dockerfile: docker/production/Dockerfile
      target: web
    image: chatbot-web:${APP_IMAGE_TAG:-latest}
    restart: unless-stopped
    depends_on:
      - app
    ports:
      - "127.0.0.1:8080:80"
    networks:
      - backend

  horizon:
    image: chatbot-app:${APP_IMAGE_TAG:-latest}
    restart: unless-stopped
    init: true
    command: ["php", "artisan", "horizon"]
    env_file:
      - .env.production
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    stop_grace_period: 180s
    volumes:
      - app-storage:/var/www/html/storage
    networks:
      - backend

  scheduler:
    image: chatbot-app:${APP_IMAGE_TAG:-latest}
    restart: unless-stopped
    init: true
    command: ["php", "artisan", "schedule:work"]
    env_file:
      - .env.production
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    stop_grace_period: 60s
    volumes:
      - app-storage:/var/www/html/storage
    networks:
      - backend

  mysql:
    image: mysql:8.4
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      TZ: Asia/Jakarta
    command:
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -uroot -p$$MYSQL_ROOT_PASSWORD"]
      interval: 10s
      timeout: 5s
      retries: 20
      start_period: 30s
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - backend

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    environment:
      REDIS_PASSWORD: ${REDIS_PASSWORD}
    command:
      - sh
      - -c
      - exec redis-server --appendonly yes --requirepass "$$REDIS_PASSWORD"
    healthcheck:
      test: ["CMD-SHELL", "redis-cli -a $$REDIS_PASSWORD ping | grep PONG"]
      interval: 10s
      timeout: 5s
      retries: 10
    volumes:
      - redis-data:/data
    networks:
      - backend

networks:
  backend:
    driver: bridge

volumes:
  app-storage:
  mysql-data:
  redis-data:
```

Port web hanya dibuka pada `127.0.0.1:8080` karena diasumsikan TLS ditangani reverse proxy di host atau stack proxy Docker lain. Jangan menambahkan `ports` pada service MySQL dan Redis.

## 8. Konfigurasi environment production

Salin template:

```bash
cp .env.example .env.production
chmod 600 .env.production
```

Isi minimal berikut:

```dotenv
APP_NAME="Chatbot Walatra"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://chatbot.example.com
APP_TIMEZONE=Asia/Jakarta

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=dbchatbot
DB_USERNAME=chatbot
DB_PASSWORD=GANTI_PASSWORD_DATABASE
MYSQL_ROOT_PASSWORD=GANTI_PASSWORD_ROOT_DATABASE

SESSION_DRIVER=database
SESSION_LIFETIME=120

QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE_RETRY_AFTER=180
QUEUE_FAILED_DRIVER=database-uuids

CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=GANTI_PASSWORD_REDIS
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

TELEGRAM_BOT_TOKEN=GANTI_TOKEN_BOT
TELEGRAM_WEBHOOK_SECRET=GANTI_SECRET_WEBHOOK
TELEGRAM_WEBHOOK_URL=https://chatbot.example.com/api/telegram/webhook
TELEGRAM_TIMEOUT=10

AI_PARSER_PROVIDER=groq
AI_RENDERER_PROVIDER=groq
AI_PARSER_FALLBACK=true
AI_PARSER_FALLBACK_ORDER=groq,openai,gemini
GROQ_API_KEY=GANTI_API_KEY
GROQ_MODEL=openai/gpt-oss-20b
GROQ_PARSER_MODEL=openai/gpt-oss-20b
GROQ_RENDERER_MODEL=qwen/qwen3.6-27b
GROQ_TIMEOUT=25
GROQ_RENDERER_TIMEOUT=12

GEMINI_API_KEY=
OPENAI_API_KEY=

CHATBOT_MEMORY_TTL_HOURS=24
CHATBOT_HISTORY_LIMIT=6
CHATBOT_HISTORY_ENABLED=true
CHATBOT_HISTORY_RETENTION_DAYS=90
CHATBOT_RATE_LIMIT_PER_MINUTE=30
CHATBOT_NATURAL_RENDERER=true

INTERNAL_HEALTH_TOKEN=GANTI_TOKEN_HEALTH_INTERNAL
```

Catatan penting:

- `DB_HOST=mysql`, bukan `127.0.0.1`;
- `REDIS_HOST=redis`, bukan `127.0.0.1`;
- gunakan password acak yang panjang;
- `APP_KEY` wajib sama pada seluruh container dan tidak boleh berubah setelah data terenkripsi tersimpan;
- perubahan `APP_KEY` akan membuat credential, state, dan data terenkripsi lama tidak dapat dibaca;
- Telegram membutuhkan webhook HTTPS valid.

Generate secret dan APP key:

```bash
openssl rand -base64 48
openssl rand -hex 32
```

Setelah image dibangun, APP key juga dapat dibuat dengan:

```bash
docker compose -f compose.production.yml --env-file .env.production run --rm app php artisan key:generate --show
```

Salin hasilnya ke `APP_KEY` pada `.env.production`.

## 9. Validasi konfigurasi Compose

```bash
docker compose -f compose.production.yml --env-file .env.production config --quiet
```

Tampilkan daftar service:

```bash
docker compose -f compose.production.yml --env-file .env.production config --services
```

Pastikan hasilnya mencakup:

```text
app
web
horizon
scheduler
mysql
redis
```

## 10. Build dan deployment pertama

Definisikan alias agar command lebih ringkas:

```bash
alias dc='docker compose -f compose.production.yml --env-file .env.production'
```

Alias hanya berlaku pada shell saat ini.

Build image:

```bash
dc build app web
```

Jalankan MySQL dan Redis terlebih dahulu:

```bash
dc up -d mysql redis
dc ps
```

Tunggu sampai keduanya berstatus sehat:

```bash
dc ps mysql redis
```

Jalankan migrasi:

```bash
dc run --rm app php artisan migrate --force
```

Jalankan seeder profil bisnis dan admin hanya pada deployment awal jika datanya belum tersedia:

```bash
dc run --rm app php artisan db:seed --class=BusinessProfileSeeder --force
dc run --rm app php artisan db:seed --class=AdminUserSeeder --force
```

Jalankan seluruh service aplikasi:

```bash
dc up -d app web horizon scheduler
dc ps
```

Periksa Laravel:

```bash
dc exec app php artisan about
dc exec app php artisan migrate:status
dc exec scheduler php artisan schedule:list
dc exec horizon php artisan horizon:status
```

Horizon harus menyatakan aktif/running.

## 11. Konfigurasi HTTPS dan domain

Telegram hanya menerima webhook HTTPS. Arahkan DNS `chatbot.example.com` ke IP server.

Compose di atas membuka aplikasi pada:

```text
127.0.0.1:8080
```

Hubungkan alamat tersebut ke reverse proxy yang sudah digunakan server, misalnya Nginx Proxy Manager, Traefik, Caddy, atau Nginx host. Konfigurasi proxy harus meneruskan:

```text
https://chatbot.example.com → http://127.0.0.1:8080
```

Aktifkan sertifikat TLS valid dan redirect HTTP ke HTTPS. Jangan memakai sertifikat self-signed untuk webhook Telegram.

Tes dari internet:

```bash
curl -I https://chatbot.example.com
curl https://chatbot.example.com/health
```

Jika reverse proxy juga berada dalam Docker network yang sama, service `web` dapat memakai `expose: ["80"]` tanpa mapping port host, lalu proxy diarahkan ke `web:80`.

## 12. Konfigurasi Telegram webhook

Pastikan `.env.production` sudah berisi token, secret, dan URL webhook. Kemudian:

```bash
dc exec app php artisan telegram:webhook set
dc exec app php artisan telegram:webhook info
```

Webhook yang benar harus menunjuk ke:

```text
https://chatbot.example.com/api/telegram/webhook
```

Jika konfigurasi Telegram disimpan melalui Filament, buka panel admin, simpan konfigurasi bot, lalu gunakan tombol pemeriksaan/pemasangan webhook atau jalankan command di atas.

## 13. Cara kerja queue chatbot

Pipeline pesan:

```text
Webhook Telegram
    ↓
channel_events di MySQL
    ↓
queue inbound di Redis
    ↓
ProcessChannelEvent
    ↓
ChatOrchestrator dan AI
    ↓
chatbot_messages/outbox di MySQL
    ↓
queue outbound di Redis
    ↓
DeliverOutboundMessage
    ↓
Telegram
```

Queue proyek:

- `inbound`: menerima dan memproses event channel;
- `outbound`: mengirim balasan yang sudah tersimpan di outbox;
- `default`: pekerjaan umum aplikasi.

Konfigurasi terdapat di `config/horizon.php`:

```php
'queue' => ['inbound', 'outbound', 'default'],
```

Jangan menjalankan `php artisan queue:work` bersamaan dengan Horizon untuk queue yang sama, kecuali memang dirancang sebagai tambahan worker. Worker ganda dengan versi image berbeda dapat menghasilkan perilaku yang tidak konsisten.

## 14. Operasional Horizon

### Status

```bash
dc exec horizon php artisan horizon:status
dc exec horizon php artisan horizon:supervisor-status supervisor-1
```

### Log realtime

```bash
dc logs -f --tail=100 horizon
```

### Pause dan lanjutkan

```bash
dc exec horizon php artisan horizon:pause
dc exec horizon php artisan horizon:continue
```

### Restart setelah perubahan kode

```bash
dc exec horizon php artisan horizon:terminate || true
dc up -d --force-recreate horizon
```

Laravel merekomendasikan `horizon:terminate` saat deployment agar process monitor menjalankan Horizon kembali dengan kode terbaru. Pada Docker, `restart: unless-stopped` dan `docker compose up --force-recreate` berperan sebagai process monitor. Lihat [dokumentasi Laravel 12 Horizon](https://laravel.com/docs/12.x/horizon#deploying-horizon).

### Melihat dashboard

Buka:

```text
https://chatbot.example.com/horizon
```

Pada environment production, login menggunakan user dengan role yang mempunyai akses `analyst`.

### Jumlah worker

Konfigurasi production saat ini mengizinkan hingga 10 process. Sesuaikan dengan kapasitas server di `config/horizon.php`:

```php
'production' => [
    'supervisor-1' => [
        'maxProcesses' => 5,
    ],
],
```

Mulai dari 3–5 process pada server 4 GB, lalu pantau RAM, latency, dan backlog sebelum menaikkan jumlahnya.

## 15. Scheduler

Container `scheduler` menjalankan:

```bash
php artisan schedule:work
```

Scheduler proyek menangani antara lain:

- recovery event/pengiriman tertinggal setiap lima menit;
- snapshot metrik Horizon;
- heartbeat health setiap menit;
- penghapusan riwayat berdasarkan retensi;
- sinkronisasi kurs jika diaktifkan.

Periksa:

```bash
dc exec scheduler php artisan schedule:list
dc logs -f --tail=100 scheduler
```

Pastikan hanya ada satu container scheduler agar task tidak berjalan ganda.

## 16. Health check setelah deployment

```bash
dc ps
dc exec app php artisan migrate:status
dc exec horizon php artisan horizon:status
dc exec redis sh -c 'redis-cli -a "$REDIS_PASSWORD" ping'
curl -fsS https://chatbot.example.com/health
dc logs --tail=100 app horizon scheduler web
```

Uji Telegram:

1. kirim `halo` ke bot;
2. webhook harus cepat mengembalikan HTTP 200;
3. job muncul di Horizon;
4. pesan masuk tersimpan;
5. balasan dikirim melalui queue outbound;
6. tidak ada job failed/dead.

## 17. Deployment versi baru

Masuk ke project:

```bash
cd /opt/chatbot
alias dc='docker compose -f compose.production.yml --env-file .env.production'
```

Ambil kode dan build image baru:

```bash
git pull --ff-only
export APP_IMAGE_TAG="$(git rev-parse --short HEAD)"
dc build app web
```

Jalankan quality gate sebelum mengganti container jika resource server memungkinkan:

```bash
dc run --rm app php artisan chatbot:evaluate
```

Jalankan migrasi:

```bash
dc run --rm app php artisan migrate --force
```

Hentikan Horizon lama secara graceful dan recreate semua proses kode:

```bash
dc exec horizon php artisan horizon:terminate || true
dc up -d --force-recreate app web horizon scheduler
```

Verifikasi:

```bash
dc ps
dc exec horizon php artisan horizon:status
dc logs --tail=100 horizon app
curl -fsS https://chatbot.example.com/health
```

Jangan menjalankan container app versi baru bersama Horizon versi lama dalam waktu lama.

## 18. Backup

Buat direktori backup:

```bash
sudo mkdir -p /var/backups/chatbot
sudo chown "$USER":"$USER" /var/backups/chatbot
```

Backup MySQL:

```bash
dc exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' \
  | gzip > "/var/backups/chatbot/mysql-$(date +%F-%H%M%S).sql.gz"
```

Backup file storage:

```bash
docker run --rm \
  -v chatbot_app-storage:/data:ro \
  -v /var/backups/chatbot:/backup \
  alpine sh -c 'tar czf /backup/storage-$(date +%F-%H%M%S).tar.gz -C /data .'
```

Nama volume dapat berbeda karena Compose menambahkan project prefix. Periksa dengan:

```bash
docker volume ls | grep storage
```

Simpan backup di lokasi lain/off-site dan uji proses restore secara berkala. Redis bukan satu-satunya sumber state, tetapi volume Redis tetap membantu mempertahankan queue ketika container direstart.

## 19. Keamanan production

- Gunakan `APP_ENV=production` dan `APP_DEBUG=false`.
- Jangan commit `.env.production`.
- Batasi akses SSH dan gunakan key, bukan password.
- Jangan publish port MySQL dan Redis.
- Gunakan HTTPS valid.
- Batasi dashboard Horizon dan panel Filament dengan autentikasi/role.
- Rotasi API key dan secret bila bocor.
- Jangan mengganti `APP_KEY` setelah data terenkripsi tersimpan.
- Jalankan container sebagai user non-root jika image mendukungnya.
- Update base image, Docker Engine, dan dependency secara terjadwal setelah diuji di staging.
- Pantau disk agar log, image lama, dan backup tidak memenuhi server.
- Hindari `docker system prune --volumes` karena dapat menghapus data persistent.

## 20. Troubleshooting

### Webhook masuk tetapi bot tidak membalas

```bash
dc ps
dc exec horizon php artisan horizon:status
dc logs --tail=200 horizon
dc logs --tail=200 app
dc exec app php artisan telegram:webhook info
```

Periksa `channel_events`, failed job, koneksi provider AI, Redis, serta queue outbound.

### Horizon tidak dapat terhubung ke Redis

```bash
dc exec redis sh -c 'redis-cli -a "$REDIS_PASSWORD" ping'
dc exec horizon php artisan tinker --execute="dump(Illuminate\\Support\\Facades\\Redis::ping());"
```

Pastikan:

```dotenv
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PASSWORD=password_yang_sama
```

### Laravel tidak dapat terhubung ke MySQL

```bash
dc ps mysql
dc logs --tail=100 mysql
dc exec app php artisan migrate:status
```

Pastikan `DB_HOST=mysql` dan credential cocok.

### Balasan memakai kode lama

Periksa versi image:

```bash
docker inspect "$(dc ps -q app)" --format '{{.Config.Image}}'
docker inspect "$(dc ps -q horizon)" --format '{{.Config.Image}}'
```

Kemudian:

```bash
dc exec horizon php artisan horizon:terminate || true
dc up -d --force-recreate app horizon scheduler
```

### Ada Horizon ganda

```bash
dc ps horizon
docker ps --format '{{.Names}}\t{{.Image}}\t{{.Command}}' | grep -i horizon
```

Jangan menjalankan:

```bash
dc exec app php artisan horizon
```

jika service `horizon` sudah aktif.

### Permission storage

```bash
dc exec --user root app chown -R www-data:www-data storage bootstrap/cache
dc restart app horizon scheduler
```

### Container terus restart

```bash
dc ps
dc logs --tail=200 NAMA_SERVICE
docker inspect "$(dc ps -q NAMA_SERVICE)" --format '{{json .State}}'
```

Periksa `APP_KEY`, extension PHP, permission, koneksi database, Redis, dan memory limit server.

## 21. Checklist final

- [ ] Domain mengarah ke IP server.
- [ ] Docker Engine dan Compose plugin aktif.
- [ ] `.env.production` berizin `600` dan tidak masuk Git.
- [ ] `APP_KEY` dibuat dan disimpan aman.
- [ ] `DB_HOST=mysql`.
- [ ] `REDIS_HOST=redis`.
- [ ] Port MySQL dan Redis tidak dipublish.
- [ ] MySQL dan Redis sehat.
- [ ] Migrasi berhasil.
- [ ] Profil bisnis dan admin tersedia.
- [ ] Container `app`, `web`, `horizon`, dan `scheduler` aktif.
- [ ] Hanya ada satu scheduler.
- [ ] Horizon memproses `inbound`, `outbound`, dan `default`.
- [ ] HTTPS valid.
- [ ] Webhook Telegram terpasang.
- [ ] `/health` berhasil.
- [ ] Dashboard Filament dan Horizon terlindungi.
- [ ] Backup MySQL dan storage berhasil diuji.
- [ ] Uji pesan Telegram menghasilkan tepat satu balasan.

## 22. Command ringkas harian

```bash
cd /opt/chatbot
alias dc='docker compose -f compose.production.yml --env-file .env.production'

# Status
dc ps
dc exec horizon php artisan horizon:status

# Log
dc logs -f --tail=100 horizon

# Restart Horizon
dc exec horizon php artisan horizon:terminate || true
dc up -d --force-recreate horizon

# Status webhook
dc exec app php artisan telegram:webhook info

# Health
curl -fsS https://chatbot.example.com/health
```

Panduan khusus operasional Horizon yang lebih singkat tersedia di `PANDUAN_HORIZON.md`.
