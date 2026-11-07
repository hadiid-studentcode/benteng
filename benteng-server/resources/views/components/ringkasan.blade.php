@props(['label', 'nilai', 'ikon', 'warna' => 'icon-box--primary'])
{{-- Kartu angka ringkas di atas tabel. $warna adalah kelas icon-box--*. --}}
<div {{ $attributes->merge(['class' => 'card min-w-0']) }}>
    <div class="card__body flex flex-row items-center gap-3">
        <span class="icon-box {{ $warna }} shrink-0"><x-ikon :nama="$ikon" class="size-5" /></span>
        <div class="min-w-0">
            <p class="text-2xl leading-tight font-semibold tabular-nums">{{ is_int($nilai) ? number_format($nilai, 0, ',', '.') : $nilai }}</p>
            <p class="text-sm leading-tight text-muted-foreground">{{ $label }}</p>
        </div>
    </div>
</div>
