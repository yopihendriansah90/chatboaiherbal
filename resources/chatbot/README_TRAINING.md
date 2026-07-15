# Dataset Pembelajaran Chatbot

Dataset percakapan di folder ini digunakan sebagai **behavioral specification** dan evaluation fixture. Dataset tidak dikirim langsung untuk fine-tuning provider AI. Keputusan produk, safety, dan klaim tetap dijalankan secara deterministik oleh aplikasi.

## Dataset Radimax

- `conversation_training_radimax.json`: percakapan konsultasi lengkap sampai soft-selling.
- `conversation_training_radimax_extended.json`: edge case bahasa Indonesia, slang, bahasa vulgar, safety, consent, privasi, dan keadaan darurat.

## Menjalankan evaluasi

```bash
php artisan chatbot:evaluate-training
```

Perintah gagal dengan exit code non-zero apabila JSON tidak valid, ID skenario duplikat, produk `RAD` tidak tersedia, atau keputusan aktual berbeda dari `expected_decision`.

Jalankan juga seluruh test setelah mengubah dataset atau kebijakan:

```bash
php artisan test
```

## Menambahkan skenario

1. Gunakan ID unik.
2. Isi `input`, `expected_decision`, dan konteks/fakta bila diperlukan.
3. Jangan memasukkan harga, stok, promosi, atau klaim yang tidak berasal dari data aktif.
4. Tambahkan pola umum ke policy/normalizer. Jangan membuat pengecualian yang hanya cocok dengan satu kalimat contoh.
5. Jalankan evaluator dan seluruh test.

Keputusan yang didukung oleh dataset extended adalah `allow`, `caution`, `consult`, `block`, `clarify`, `off_topic`, `reject_claim`, dan `emergency`.

## Training Inbox Filament

Buka menu **Chatbot → Training Inbox** di panel admin. Pengguna dengan role `content_reviewer`, `supervisor`, atau `super_admin` dapat mengaksesnya.

Workflow publikasi:

```text
Baru → Review/Draft → Uji → Setujui → Publikasikan
```

Kandidat otomatis dibuat ketika chatbot menghasilkan fallback generik, parser gagal, confidence rendah, atau pengguna meminta handoff. Contoh juga dapat ditambahkan melalui tombol **Tambah contoh**.

Klik tombol **Cara menggunakan** pada bagian atas Training Inbox untuk membuka tutorial lengkap dalam modal. Rule dinamis hanya dapat memakai keputusan `clarify`, `off_topic`, `reject_claim`, dan `block`; core safety, dosis, harga, klaim produk, serta keputusan medis tidak dapat diubah dari Training Inbox.
