@extends('benteng.layout')

@section('judul', 'Insiden')
@section('keterangan', 'Ancaman yang ditemukan detektor dari data sensor. Buka detail untuk melihat bukti dan langkah penanganan.')
@section('remah')
    <li class="breadcrumb__item" aria-current="page">Insiden</li>
@endsection

@section('konten')
<div id="isi" data-segar>
@fragment('isi')
    @include('benteng.partials.segar')
    <div class="flex flex-col gap-4">
        <x-tab-filter :aktif="$filter" :pilihan="[
            ['semua', 'Semua', $jumlah['semua'], route('benteng.insiden')],
            ['aktif', 'Perlu tindakan', $jumlah['aktif'], route('benteng.insiden', ['status' => 'aktif'])],
            ['dikarantina', 'Dikarantina', $jumlah['dikarantina'], route('benteng.insiden', ['status' => 'dikarantina'])],
            ['diblokir', 'Domain diblokir', $jumlah['diblokir'], route('benteng.insiden', ['status' => 'diblokir'])],
            ['ditutup', 'Ditutup', $jumlah['ditutup'], route('benteng.insiden', ['status' => 'ditutup'])],
        ]" />

        <x-kartu rapat>
            @include('benteng.partials.tabel-insiden', ['kosong' => match ($filter) {
                'aktif' => 'Tidak ada insiden yang menunggu tindakan.',
                'dikarantina' => 'Tidak ada perangkat yang sedang dikarantina.',
                'diblokir' => 'Belum ada insiden yang ditangani dengan memblokir domain.',
                'ditutup' => 'Belum ada insiden yang ditutup.',
                default => 'Insiden akan muncul di sini ketika detektor menemukan aktivitas mencurigakan.',
            }])
        </x-kartu>
    </div>
@endfragment
</div>
@endsection
