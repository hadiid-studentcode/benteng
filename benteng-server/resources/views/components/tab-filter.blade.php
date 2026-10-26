@props(['pilihan', 'aktif'])
{{-- $pilihan: daftar [kunci, label, jumlah|null, url] --}}
<nav class="flex gap-6 overflow-x-auto border-b border-slate-200 text-sm" aria-label="Filter">
    @foreach ($pilihan as [$kunci, $label, $jumlah, $url])
        <a href="{{ $url }}" @if ($kunci === $aktif) aria-current="page" @endif
            class="-mb-px flex items-center gap-2 border-b-2 px-1 pb-3 font-medium whitespace-nowrap {{ $kunci === $aktif ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
            {{ $label }}
            @if (! is_null($jumlah))
                <span class="rounded-full px-2 py-0.5 text-xs {{ $kunci === $aktif ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' }}">{{ $jumlah }}</span>
            @endif
        </a>
    @endforeach
</nav>
