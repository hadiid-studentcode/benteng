@props(['judul' => null, 'keterangan' => null, 'rapat' => false])
<section {{ $attributes->merge(['class' => 'min-w-0 rounded-xl border border-slate-200 bg-white shadow-xs']) }}>
    @if ($judul || isset($aksi))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-slate-900">{{ $judul }}</h2>
                @if ($keterangan)
                    <p class="mt-0.5 text-xs text-slate-500">{{ $keterangan }}</p>
                @endif
            </div>
            {{ $aksi ?? '' }}
        </div>
    @endif
    <div class="{{ $rapat ? '' : 'p-5' }}">{{ $slot }}</div>
</section>
