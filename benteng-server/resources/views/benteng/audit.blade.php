@use('Illuminate\Support\Carbon')
@extends('benteng.layout')

@section('judul', 'Jejak audit')
@section('keterangan', 'Catatan setiap tindakan analis dan sensor. Catatan tidak dapat diubah atau dihapus dari dashboard.')
@section('remah')
    <li class="breadcrumb__item" aria-current="page">Jejak audit</li>
@endsection

@section('konten')
<div id="isi" data-segar>
@fragment('isi')
    @include('benteng.partials.segar')
    @php
        // Kalimat, ikon, dan warna tiap jenis tindakan.
        $tindakan = [
            'minta karantina' => ['Meminta karantina', 'kunci', 'icon-box--primary'],
            'lepas karantina' => ['Meminta lepas karantina', 'buka-kunci', 'icon-box--primary'],
            'tandai false positive' => ['Menandai bukan ancaman', 'centang', 'icon-box--primary'],
            'daftarkan perangkat' => ['Mendaftarkan perangkat sah', 'perangkat', 'icon-box--primary'],
            'blokir domain' => ['Meminta blokir domain', 'larangan', 'icon-box--primary'],
            'lepas blokir domain' => ['Melepas blokir domain', 'buka-kunci', 'icon-box--primary'],
            'blokir ok' => ['Menerapkan blokir domain', 'larangan', 'icon-box--success'],
            'quarantine ok' => ['Menerapkan karantina', 'kunci', 'icon-box--success'],
            'unquarantine ok' => ['Melepas karantina', 'buka-kunci', 'icon-box--success'],
            'quarantine gagal' => ['Gagal menerapkan karantina', 'silang', 'icon-box--danger'],
            'unquarantine gagal' => ['Gagal melepas karantina', 'silang', 'icon-box--danger'],
        ];
    @endphp
    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-ringkasan label="Total catatan" :nilai="$ringkasan['semua']" ikon="audit" />
            <x-ringkasan label="Oleh analis" :nilai="$ringkasan['semua'] - $ringkasan['sensor']" ikon="pengguna" />
            <x-ringkasan label="Diterapkan sensor" :nilai="$ringkasan['sensor'] - $ringkasan['gagal']" ikon="sensor" warna="icon-box--success" />
            <x-ringkasan label="Gagal diterapkan" :nilai="$ringkasan['gagal']" ikon="silang" warna="icon-box--danger" />
        </div>

        <x-tab-filter :aktif="$filter" :pilihan="[
            ['semua', 'Semua', null, route('benteng.audit')],
            ['analis', 'Tindakan analis', null, route('benteng.audit', ['pelaku' => 'analis'])],
            ['sensor', 'Tindakan sensor', null, route('benteng.audit', ['pelaku' => 'sensor'])],
        ]" />

        <x-kartu rapat>
            @if ($audit->isEmpty())
                <div class="empty-state">
                    <span class="empty-state__media"><x-ikon nama="audit" /></span>
                    <p class="empty-state__text">Belum ada tindakan yang tercatat.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table--hover table--align-middle">
                        <thead class="table__head--alt">
                            <tr>
                                <th scope="col">Waktu</th>
                                <th scope="col">Tindakan</th>
                                <th scope="col">Pelaku</th>
                                <th scope="col">Target</th>
                                <th scope="col">Insiden</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($audit as $a)
                                @php
                                    $dariSensor = str_starts_with($a->pelaku, 'sensor:');
                                    $gagal = str_ends_with($a->aksi, 'gagal');
                                    [$kalimat, $ikon, $warna] = $tindakan[$a->aksi] ?? [$a->aksi, 'info', 'icon-box--primary'];
                                    $waktu = Carbon::parse($a->created_at)->locale('id');
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap tabular-nums">
                                        <p class="font-medium">{{ $waktu->format('H:i:s') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $waktu->translatedFormat('d M Y') }}</p>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span class="inline-flex items-center gap-3">
                                            <span class="icon-box icon-box--sm icon-box--circle {{ $warna }}"><x-ikon :nama="$ikon" class="size-3.5" /></span>
                                            <span class="{{ $gagal ? 'font-medium text-danger-emphasis' : 'font-medium' }}">{{ $kalimat }}</span>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span class="inline-flex items-center gap-2 text-muted-foreground">
                                            <x-ikon :nama="$dariSensor ? 'sensor' : 'pengguna'" class="size-4" />
                                            <span class="text-foreground">{{ $dariSensor ? 'Sensor '.substr($a->pelaku, 7) : ucfirst($a->pelaku) }}</span>
                                        </span>
                                    </td>
                                    <td class="font-mono text-xs whitespace-nowrap">{{ $a->target ?? '-' }}</td>
                                    <td class="whitespace-nowrap">
                                        @if ($a->incident_id)
                                            <a href="{{ route('benteng.insiden.detail', $a->incident_id) }}" class="link">#{{ $a->incident_id }}</a>
                                        @else
                                            <span class="text-muted-foreground">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($audit->hasPages())
                    <div class="card__footer flex items-center justify-between gap-3 text-sm">
                        <p class="text-muted-foreground">Halaman {{ $audit->currentPage() }} dari {{ $audit->lastPage() }}, total {{ $audit->total() }} catatan</p>
                        <div class="flex gap-2">
                            @if ($audit->onFirstPage())
                                <x-tombol ukuran="kecil" disabled>Sebelumnya</x-tombol>
                            @else
                                <x-tombol ukuran="kecil" :href="$audit->previousPageUrl()">Sebelumnya</x-tombol>
                            @endif
                            @if ($audit->hasMorePages())
                                <x-tombol ukuran="kecil" :href="$audit->nextPageUrl()">Berikutnya</x-tombol>
                            @else
                                <x-tombol ukuran="kecil" disabled>Berikutnya</x-tombol>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </x-kartu>
    </div>
@endfragment
</div>
@endsection
