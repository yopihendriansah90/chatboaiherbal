<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 dark:border-white/10">
                <th class="px-3 py-2 text-left">Berlaku sejak</th>
                <th class="px-3 py-2 text-right">Input USD</th>
                <th class="px-3 py-2 text-right">Cached USD</th>
                <th class="px-3 py-2 text-right">Output USD</th>
                <th class="px-3 py-2 text-right">Estimasi input IDR</th>
                <th class="px-3 py-2 text-right">Estimasi output IDR</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($prices as $price)
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="px-3 py-2">{{ $price->effective_at->format('d M Y H:i') }}</td>
                    <td class="px-3 py-2 text-right" title="Nilai presisi: ${{ $price->input_price_per_million_usd }}">${{ number_format((float) $price->input_price_per_million_usd, 2, '.', ',') }}</td>
                    <td class="px-3 py-2 text-right" title="{{ $price->cached_input_price_per_million_usd !== null ? 'Nilai presisi: $'.$price->cached_input_price_per_million_usd : '' }}">{{ $price->cached_input_price_per_million_usd !== null ? '$'.number_format((float) $price->cached_input_price_per_million_usd, 2, '.', ',') : 'Sama dengan input' }}</td>
                    <td class="px-3 py-2 text-right" title="Nilai presisi: ${{ $price->output_price_per_million_usd }}">${{ number_format((float) $price->output_price_per_million_usd, 2, '.', ',') }}</td>
                    <td class="px-3 py-2 text-right">{{ $rate ? 'Rp'.number_format((float) $price->input_price_per_million_usd * (float) $rate->rate, 2, ',', '.') : '-' }}</td>
                    <td class="px-3 py-2 text-right">{{ $rate ? 'Rp'.number_format((float) $price->output_price_per_million_usd * (float) $rate->rate, 2, ',', '.') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">Belum ada riwayat harga.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
