@extends('benteng.layout')

@section('judul', 'Insiden')
@section('remah', 'Insiden')

@section('konten')
<div class="space-y-4">
    <p class="text-sm text-slate-600">Ancaman yang ditemukan detektor dari data sensor. Buka detail untuk melihat bukti dan langkah penanganan.</p>

    <div id="isi" data-segar>
    @fragment('isi')
        @include('benteng.partials.segar')
        <div class="space-y-4">
            <x-tab-filter :aktif="$filter" :pilihan="[
                ['semua', 'Semua', $jumlah['semua'], route('benteng.insiden')],
                ['aktif', 'Perlu tindakan', $jumlah['aktif'], route('benteng.insiden', ['status' => 'aktif'])],
                ['dikarantina', 'Dikarantina', $jumlah['dikarantina'], route('benteng.insiden', ['status' => 'dikarantina'])],
                ['ditutup', 'Ditutup', $jumlah['ditutup'], route('benteng.insiden', ['status' => 'ditutup'])],
            ]" />

            <x-kartu rapat>
                @include('benteng.partials.tabel-insiden', ['kosong' => match ($filter) {
                    'aktif' => 'Tidak ada insiden yang menunggu tindakan.',
                    'dikarantina' => 'Tidak ada perangkat yang sedang dikarantina.',
                    'ditutup' => 'Belum ada insiden yang ditutup.',
                    default => 'Insiden akan muncul di sini ketika detektor menemukan aktivitas mencurigakan.',
                }])
            </x-kartu>
        </div>
    @endfragment
    </div>
</div>
@endsection
