<div class="space-y-6 text-sm leading-6 text-gray-700 dark:text-gray-200">
    <div class="rounded-xl bg-primary-50 p-4 text-primary-900 dark:bg-primary-950 dark:text-primary-100">
        <p class="font-semibold">Training Inbox adalah kotak masuk untuk memperbaiki pemahaman dan cara bicara chatbot.</p>
        <p class="mt-1">Percakapan tidak langsung menjadi pengetahuan. Admin harus memeriksa, menguji, menyetujui, lalu menerbitkannya.</p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-950 dark:text-white">Alur yang harus diikuti</h3>
        <ol class="mt-2 list-decimal space-y-2 pl-5">
            <li><strong>Review:</strong> tentukan intent, keputusan, respons yang benar, dan pola bahasa.</li>
            <li><strong>Uji:</strong> sistem memeriksa regex, dosis, link, jaminan medis, dan upaya mematikan safety.</li>
            <li><strong>Setujui:</strong> reviewer menyatakan hasil uji layak digunakan.</li>
            <li><strong>Publikasikan:</strong> rule menjadi aktif dan cache chatbot diperbarui.</li>
        </ol>
    </div>

    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
        <h3 class="font-semibold text-gray-950 dark:text-white">Contoh: “lutut ibu saya sakit kalau jalan”</h3>
        <dl class="mt-3 grid gap-2 sm:grid-cols-2">
            <div><dt class="font-medium">Intent</dt><dd>joint_health_complaint</dd></div>
            <div><dt class="font-medium">Keputusan</dt><dd>clarify</dd></div>
            <div class="sm:col-span-2"><dt class="font-medium">Pola</dt><dd><code>\b(?:lutut|sendi)\b.*\b(?:sakit|nyeri|pegal)\b</code></dd></div>
            <div class="sm:col-span-2"><dt class="font-medium">Respons</dt><dd>Aku ikut prihatin, kak. Keluhan lutut ibu pasti membuatnya kurang nyaman. Keluhannya sudah terasa berapa lama? Apakah ada cedera, bengkak, demam, sendi merah atau panas, atau kesulitan menapak?</dd></div>
        </dl>
        <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">Setelah informasi keselamatan cukup dan tidak ada tanda bahaya, rekomendasi produk sendi hanya boleh memakai manfaat yang telah disetujui di database produk.</p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-950 dark:text-white">Arti keputusan</h3>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li><strong>clarify:</strong> ajukan klarifikasi yang relevan.</li>
            <li><strong>off_topic:</strong> beri batasan ramah dan arahkan kembali.</li>
            <li><strong>reject_claim:</strong> tolak klaim yang tidak tersedia.</li>
            <li><strong>block:</strong> hentikan permintaan yang tidak aman.</li>
        </ul>
    </div>

    <div class="rounded-xl bg-warning-50 p-4 text-warning-900 dark:bg-warning-950 dark:text-warning-100">
        <p class="font-semibold">Yang tidak dapat diubah Training Inbox</p>
        <p class="mt-1">Alergi, kontraindikasi, usia minimum, obat rutin, tanda darurat, dosis, harga, stok, klaim produk, serta keputusan allow/consult tetap dikendalikan core safety dan database produk.</p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-950 dark:text-white">Tips membuat pola</h3>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>Gunakan pola umum seperti <code>\b(?:lutut|sendi)\b</code>, bukan nama atau identitas pelanggan.</li>
            <li>Jangan membuat pola terlalu luas seperti <code>.*</code>.</li>
            <li>Masukkan beberapa variasi kata dalam satu pola bila intent-nya sama.</li>
            <li>Setelah publikasi, uji langsung melalui Telegram dengan kalimat berbeda dari contoh.</li>
        </ul>
    </div>
</div>
