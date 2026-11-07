@use('Illuminate\Support\Carbon')
@extends('benteng.layout')

@section('judul', 'Perangkat')
@section('keterangan', 'Semua perangkat yang terlihat oleh sensor, dari log DNS dan daftar DHCP.')
@section('remah')
    <li class="breadcrumb__item" aria-current="page">Perangkat</li>
@endsection

@section('aksi-halaman')
    <div class="input-group w-full md:w-72">
        <span class="input-group__text"><x-ikon nama="cari" class="size-4" /></span>
        <input type="search" class="input" data-cari-tabel="#tabel-perangkat" placeholder="Cari IP, nama host, atau MAC" aria-label="Cari perangkat">
    </div>
@endsection

@section('konten')
<div class="flex flex-col gap-4">
    <div id="isi" data-segar>
    @fragment('isi')
        @include('benteng.partials.segar')
        @php
            $relatif = fn ($t) => $t ? Carbon::parse($t)->locale('id')->diffForHumans() : '-';
            $warnaPerangkat = ['normal' => 'icon-box--success', 'insiden' => 'icon-box--danger', 'dikarantina' => 'icon-box--info', 'asing' => 'icon-box--warning'];
        @endphp
        <div class="flex flex-col gap-4">
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <x-ringkasan label="Normal" :nilai="$perStatus['normal'] ?? 0" ikon="centang-bulat" warna="icon-box--success" />
                <x-ringkasan label="Ada insiden" :nilai="$perStatus['insiden'] ?? 0" ikon="insiden" warna="icon-box--danger" />
                <x-ringkasan label="Dikarantina" :nilai="$perStatus['dikarantina'] ?? 0" ikon="kunci" warna="icon-box--info" />
                <x-ringkasan label="Belum dikenal" :nilai="$perStatus['asing'] ?? 0" ikon="info" warna="icon-box--warning" />
            </div>

            <x-tab-filter :aktif="$filter" :pilihan="[
                ['semua', 'Semua', $jumlah['semua'], route('benteng.perangkat')],
                ['bermasalah', 'Bermasalah', $jumlah['bermasalah'], route('benteng.perangkat', ['status' => 'bermasalah'])],
                ['asing', 'Belum dikenal', $jumlah['asing'], route('benteng.perangkat', ['status' => 'asing'])],
            ]" />

            <x-kartu rapat>
                @if ($perangkat->isEmpty())
                    <div class="empty-state">
                        <span class="empty-state__media"><x-ikon nama="perangkat" /></span>
                        <p class="empty-state__text">{{ $filter === 'semua' ? 'Belum ada perangkat. Pastikan sensor mengirim data ke server.' : 'Tidak ada perangkat pada kategori ini.' }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table id="tabel-perangkat" class="table table--hover table--align-middle">
                            <thead class="table__head--alt">
                                <tr>
                                    <th scope="col">Perangkat</th>
                                    <th scope="col">MAC</th>
                                    <th scope="col">Sensor</th>
                                    <th scope="col" class="text-end">Query DNS</th>
                                    <th scope="col">Terakhir terlihat</th>
                                    <th scope="col">Status</th>
                                    <th scope="col"><span class="sr-only">Tindakan</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($perangkat as $p)
                                    @php $idInsiden = $insidenPerPerangkat->get($p->sensor.'|'.$p->ip); @endphp
                                    <tr data-cari="{{ strtolower($p->ip.' '.$p->hostname.' '.$p->mac) }}">
                                        <td class="whitespace-nowrap">
                                            <div class="flex items-center gap-3">
                                                <span class="icon-box icon-box--sm {{ $warnaPerangkat[$p->status] ?? 'icon-box--primary' }}"><x-ikon nama="perangkat" class="size-3.5" /></span>
                                                <div>
                                                    <p class="font-semibold">{{ $p->ip }}</p>
                                                    <p class="text-xs text-muted-foreground">{{ $p->hostname ?: 'Tanpa nama host' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="font-mono text-xs whitespace-nowrap">{{ $p->mac ?? '-' }}</td>
                                        <td class="whitespace-nowrap">{{ $p->sensor }}</td>
                                        <td class="text-end whitespace-nowrap tabular-nums">
                                            {{ number_format($p->jumlah, 0, ',', '.') }}
                                            <p class="text-xs text-muted-foreground">{{ number_format($p->domain_unik, 0, ',', '.') }} domain</p>
                                        </td>
                                        <td class="whitespace-nowrap">{{ $relatif($p->akhir) }}</td>
                                        <td><x-status-perangkat :status="$p->status" /></td>
                                        <td class="text-end whitespace-nowrap">
                                            @if ($idInsiden)
                                                <x-tombol ukuran="kecil" :href="route('benteng.insiden.detail', $idInsiden)">Lihat insiden<x-ikon nama="panah-kanan" class="size-4" /></x-tombol>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p hidden class="py-8 text-center text-sm text-muted-foreground" data-cari-kosong>Tidak ada perangkat yang cocok dengan pencarian.</p>
                    </div>
                @endif
            </x-kartu>
        </div>
    @endfragment
    </div>
</div>
@endsection
