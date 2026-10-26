@use('Illuminate\Support\Carbon')
@extends('benteng.layout')

@section('judul', 'Perangkat')
@section('remah', 'Perangkat')

@section('konten')
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600">Semua perangkat yang terlihat oleh sensor, dari log DNS dan daftar DHCP.</p>
        <label class="relative block sm:w-72">
            <span class="sr-only">Cari perangkat</span>
            <x-ikon nama="cari" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
            <input type="search" data-cari-tabel="#tabel-perangkat" placeholder="Cari IP, nama host, atau MAC"
                class="w-full rounded-md border-0 bg-white py-2 pr-3 pl-9 text-sm text-slate-800 shadow-xs ring-1 ring-slate-300 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-brand-600">
        </label>
    </div>

    <div id="isi" data-segar>
    @fragment('isi')
        @include('benteng.partials.segar')
        @php
            $relatif = fn ($t) => $t ? Carbon::parse($t)->locale('id')->diffForHumans() : '-';
        @endphp
        <div class="space-y-4">
            <x-tab-filter :aktif="$filter" :pilihan="[
                ['semua', 'Semua', $jumlah['semua'], route('benteng.perangkat')],
                ['bermasalah', 'Bermasalah', $jumlah['bermasalah'], route('benteng.perangkat', ['status' => 'bermasalah'])],
                ['asing', 'Belum dikenal', $jumlah['asing'], route('benteng.perangkat', ['status' => 'asing'])],
            ]" />

            <x-kartu rapat>
                @if ($perangkat->isEmpty())
                    <div class="px-6 py-12 text-center text-sm text-slate-500">
                        {{ $filter === 'semua' ? 'Belum ada perangkat. Pastikan sensor mengirim data ke server.' : 'Tidak ada perangkat pada kategori ini.' }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table id="tabel-perangkat" class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <tr>
                                    <th scope="col" class="px-5 py-3">Perangkat</th>
                                    <th scope="col" class="px-3 py-3">MAC</th>
                                    <th scope="col" class="px-3 py-3">Sensor</th>
                                    <th scope="col" class="px-3 py-3 text-right">Query DNS</th>
                                    <th scope="col" class="px-3 py-3">Terakhir terlihat</th>
                                    <th scope="col" class="px-3 py-3">Status</th>
                                    <th scope="col" class="px-5 py-3 text-right"><span class="sr-only">Tindakan</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($perangkat as $p)
                                    @php $idInsiden = $insidenPerPerangkat->get($p->sensor.'|'.$p->ip); @endphp
                                    <tr data-cari="{{ strtolower($p->ip.' '.$p->hostname.' '.$p->mac) }}">
                                        <td class="px-5 py-3.5 whitespace-nowrap">
                                            <p class="font-semibold text-slate-900">{{ $p->ip }}</p>
                                            <p class="text-xs text-slate-500">{{ $p->hostname ?: 'Tanpa nama host' }}</p>
                                        </td>
                                        <td class="px-3 py-3.5 font-mono text-xs whitespace-nowrap text-slate-600">{{ $p->mac ?? '-' }}</td>
                                        <td class="px-3 py-3.5 whitespace-nowrap text-slate-600">{{ $p->sensor }}</td>
                                        <td class="px-3 py-3.5 text-right whitespace-nowrap text-slate-700 tabular-nums">
                                            {{ number_format($p->jumlah, 0, ',', '.') }}
                                            <p class="text-xs text-slate-400">{{ number_format($p->domain_unik, 0, ',', '.') }} domain</p>
                                        </td>
                                        <td class="px-3 py-3.5 whitespace-nowrap text-slate-600">{{ $relatif($p->akhir) }}</td>
                                        <td class="px-3 py-3.5"><x-status-perangkat :status="$p->status" /></td>
                                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                            @if ($idInsiden)
                                                <x-tombol ukuran="kecil" :href="route('benteng.insiden.detail', $idInsiden)">Lihat insiden</x-tombol>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p hidden class="px-5 py-8 text-center text-sm text-slate-500" data-cari-kosong>Tidak ada perangkat yang cocok dengan pencarian.</p>
                    </div>
                @endif
            </x-kartu>
        </div>
    @endfragment
    </div>
</div>
@endsection
