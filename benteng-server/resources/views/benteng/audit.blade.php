@use('Illuminate\Support\Carbon')
@extends('benteng.layout')

@section('judul', 'Jejak audit')
@section('remah', 'Jejak audit')

@section('konten')
<div class="space-y-4">
    <p class="text-sm text-slate-600">Catatan setiap tindakan analis dan sensor. Catatan tidak dapat diubah atau dihapus dari dashboard.</p>

    <div id="isi" data-segar>
    @fragment('isi')
        @include('benteng.partials.segar')
        @php
            $kalimat = [
                'minta karantina' => 'Meminta karantina',
                'lepas karantina' => 'Meminta lepas karantina',
                'tandai false positive' => 'Menandai bukan ancaman',
                'daftarkan perangkat' => 'Mendaftarkan perangkat sah',
                'quarantine ok' => 'Menerapkan karantina',
                'quarantine gagal' => 'Gagal menerapkan karantina',
                'unquarantine ok' => 'Melepas karantina',
                'unquarantine gagal' => 'Gagal melepas karantina',
            ];
        @endphp
        <div class="space-y-4">
            <x-tab-filter :aktif="$filter" :pilihan="[
                ['semua', 'Semua', null, route('benteng.audit')],
                ['analis', 'Tindakan analis', null, route('benteng.audit', ['pelaku' => 'analis'])],
                ['sensor', 'Tindakan sensor', null, route('benteng.audit', ['pelaku' => 'sensor'])],
            ]" />

            <x-kartu rapat>
                @if ($audit->isEmpty())
                    <div class="px-6 py-12 text-center text-sm text-slate-500">Belum ada tindakan yang tercatat.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                                <tr>
                                    <th scope="col" class="px-5 py-3">Waktu</th>
                                    <th scope="col" class="px-3 py-3">Pelaku</th>
                                    <th scope="col" class="px-3 py-3">Tindakan</th>
                                    <th scope="col" class="px-3 py-3">Perangkat</th>
                                    <th scope="col" class="px-5 py-3">Insiden</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($audit as $a)
                                    @php
                                        $dariSensor = str_starts_with($a->pelaku, 'sensor:');
                                        $gagal = str_ends_with($a->aksi, 'gagal');
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-3 whitespace-nowrap text-slate-600 tabular-nums">{{ Carbon::parse($a->created_at)->locale('id')->translatedFormat('d M Y, H:i:s') }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="flex size-6 items-center justify-center rounded-full {{ $dariSensor ? 'bg-violet-50 text-violet-600' : 'bg-brand-50 text-brand-700' }}">
                                                    <x-ikon :nama="$dariSensor ? 'sensor' : 'pengguna'" class="size-3.5" />
                                                </span>
                                                <span class="font-medium text-slate-800">{{ $dariSensor ? 'Sensor '.substr($a->pelaku, 7) : ucfirst($a->pelaku) }}</span>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 {{ $gagal ? 'font-medium text-red-700' : 'text-slate-700' }}">{{ $kalimat[$a->aksi] ?? $a->aksi }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap text-slate-600">{{ $a->target ?? '-' }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            @if ($a->incident_id)
                                                <a href="{{ route('benteng.insiden.detail', $a->incident_id) }}" class="font-medium text-brand-700 hover:underline">#{{ $a->incident_id }}</a>
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($audit->hasPages())
                        <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-sm">
                            <p class="text-slate-500">Halaman {{ $audit->currentPage() }} dari {{ $audit->lastPage() }}, total {{ $audit->total() }} catatan</p>
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
</div>
@endsection
