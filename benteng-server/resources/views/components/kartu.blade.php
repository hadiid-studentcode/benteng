@props(['judul' => null, 'keterangan' => null, 'rapat' => false])
<section {{ $attributes->merge(['class' => 'card min-w-0']) }}>
    @if ($judul || isset($aksi))
        <div class="card__header flex-wrap">
            <div class="flex min-w-0 flex-col gap-0.5">
                <h2 class="card__title">{{ $judul }}</h2>
                @if ($keterangan)
                    <p class="card__subtitle">{{ $keterangan }}</p>
                @endif
            </div>
            @isset($aksi)
                <div class="card__action">{{ $aksi }}</div>
            @endisset
        </div>
    @endif
    @if ($rapat)
        {{ $slot }}
    @else
        <div class="card__body">{{ $slot }}</div>
    @endif
</section>
