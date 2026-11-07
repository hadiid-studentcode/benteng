@props(['pilihan', 'aktif'])
{{-- $pilihan: daftar [kunci, label, jumlah|null, url] --}}
<nav class="toggle-group max-w-full overflow-x-auto" aria-label="Filter">
    @foreach ($pilihan as [$kunci, $label, $jumlah, $url])
        <a href="{{ $url }}" class="toggle whitespace-nowrap" @if ($kunci === $aktif) aria-current="page" data-state="active" @endif>
            {{ $label }}
            @if (! is_null($jumlah))
                <span class="badge badge--soft {{ $kunci === $aktif ? 'badge--primary' : '' }}">{{ $jumlah }}</span>
            @endif
        </a>
    @endforeach
</nav>
