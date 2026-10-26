@php
    $puncak = collect($aktivitas)->max('jumlah');
    $total = collect($aktivitas)->sum('jumlah');
    $menitPuncak = collect($aktivitas)->firstWhere('jumlah', $puncak)['menit'] ?? null;
    // Batas atas sumbu dibulatkan ke 1/2/5 x 10^n supaya angka sumbu rapi.
    $skala = 1;
    if ($puncak > 0) {
        $pangkat = 10 ** floor(log10($puncak));
        foreach ([1, 2, 5, 10] as $k) {
            if ($k * $pangkat >= $puncak) {
                $skala = (int) ($k * $pangkat);
                break;
            }
        }
    }
    $jumlahMenit = count($aktivitas);
@endphp
<div class="grid grid-cols-[auto_1fr] grid-rows-[160px_auto] gap-x-2 gap-y-1.5">
    <div class="relative w-8 text-[11px] text-slate-400 tabular-nums" aria-hidden="true">
        <span class="absolute right-0 translate-y-1/2" style="bottom:100%">{{ $skala }}</span>
        @if ($skala % 2 === 0)
            <span class="absolute right-0 translate-y-1/2" style="bottom:50%">{{ $skala / 2 }}</span>
        @endif
        <span class="absolute right-0 translate-y-1/2" style="bottom:0">0</span>
    </div>
    <div class="relative border-b border-slate-300" role="img"
        aria-label="Grafik batang jumlah query DNS per menit. {{ $total ? "Total $total query, tertinggi $puncak query pada pukul $menitPuncak." : 'Tidak ada query.' }}">
        <div class="absolute inset-x-0 top-0 border-t border-slate-100"></div>
        <div class="absolute inset-x-0 top-1/2 border-t border-slate-100"></div>
        <div class="absolute inset-0 flex items-stretch">
            @foreach ($aktivitas as $a)
                <div class="group flex flex-1 items-end justify-center px-px hover:bg-slate-50" data-nilai="{{ $a['jumlah'] }} query" data-label="pukul {{ $a['menit'] }} WIB">
                    @if ($a['jumlah'])
                        <div class="w-full max-w-[22px] rounded-t bg-brand-500 group-hover:bg-brand-700" style="height:max(2px, {{ round($a['jumlah'] / $skala * 100, 2) }}%)"></div>
                    @endif
                </div>
            @endforeach
        </div>
        @unless ($total)
            <div class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">Belum ada query DNS dalam {{ $jumlahMenit }} menit terakhir.</div>
        @endunless
    </div>
    <div class="relative col-start-2 h-4 text-[11px] text-slate-400 tabular-nums" aria-hidden="true">
        @foreach ($aktivitas as $n => $a)
            @if ($n % 15 === 0 || $n === $jumlahMenit - 1)
                <span class="absolute -translate-x-1/2" style="left:{{ round(($n + 0.5) / $jumlahMenit * 100, 2) }}%">{{ $a['menit'] }}</span>
            @endif
        @endforeach
    </div>
</div>
