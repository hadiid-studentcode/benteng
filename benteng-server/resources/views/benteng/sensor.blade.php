@use('Illuminate\Support\Carbon')
@extends('benteng.layout')

@section('judul', 'Sensor')
@section('remah', 'Sensor')

@section('konten')
<div id="isi" data-segar>
@fragment('isi')
    @include('benteng.partials.segar')
    @php
        $relatif = fn ($t) => $t ? Carbon::parse($t)->locale('id')->diffForHumans() : 'Belum pernah';
    @endphp
    <div class="space-y-6">
        <p class="text-sm text-slate-600">Sensor dipasang di tepi jaringan satuan. Sensor mengirim data tiap menit dan memeriksa perintah dari server tiap 5 detik.</p>

        <div class="grid gap-6 lg:grid-cols-2">
            @forelse ($sensor as $s)
                <x-kartu>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-ikon nama="sensor" /></span>
                            <div>
                                <p class="font-semibold text-slate-900">Sensor {{ $s['nama'] }}</p>
                                <p class="text-xs text-slate-500">{{ $s['info']['model'] ?? 'Sensor tepi jaringan' }}{{ isset($s['info']['lokasi']) ? ', '.$s['info']['lokasi'] : '' }}</p>
                            </div>
                        </div>
                        @if ($s['online'])
                            <x-lencana warna="hijau">Online</x-lencana>
                        @else
                            <x-lencana warna="merah">Tidak merespons</x-lencana>
                        @endif
                    </div>
                    <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        @foreach ([
                            ['Alamat LAN', $s['info']['lan'] ?? '-'],
                            ['Terakhir terhubung', $relatif($s['detak'])],
                            ['Data DNS terakhir', $relatif($s['dataTerakhir'])],
                            ['Laporan DHCP terakhir', $relatif($s['dhcpTerakhir'])],
                            ['Perangkat terhubung', $s['jumlahPerangkat'].' perangkat'],
                            ['Perintah menunggu', $s['perintahMenunggu'] ? $s['perintahMenunggu'].' perintah' : 'Tidak ada'],
                        ] as [$label, $nilai])
                            <div>
                                <dt class="text-xs text-slate-500">{{ $label }}</dt>
                                <dd class="mt-0.5 font-medium text-slate-800">{{ $nilai }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @unless ($s['online'])
                        <div class="mt-5 rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-600">
                            Periksa sensor lewat SSH, lalu jalankan <code class="font-mono">/etc/init.d/benteng-responder restart</code>. Karantina yang sedang aktif akan dipulihkan otomatis saat sensor terhubung kembali.
                        </div>
                    @endunless
                </x-kartu>
            @empty
                <x-kartu class="lg:col-span-2">
                    <p class="text-sm text-slate-500">Belum ada sensor yang terhubung. Pasang skrip sensor sesuai panduan di docs/README.md.</p>
                </x-kartu>
            @endforelse

            <x-kartu>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-ikon nama="cpu" /></span>
                        <div>
                            <p class="font-semibold text-slate-900">Detektor otomatis</p>
                            <p class="text-xs text-slate-500">Berjalan di server pusat setiap menit</p>
                        </div>
                    </div>
                    @if ($detektor['jalan'])
                        <x-lencana warna="hijau">Berjalan</x-lencana>
                    @else
                        <x-lencana warna="kuning">Tidak berjalan</x-lencana>
                    @endif
                </div>
                <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">Pemeriksaan terakhir</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ $relatif($detektor['detak']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Yang diperiksa</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">Beaconing, domain acak, perangkat asing</dd>
                    </div>
                </dl>
                @unless ($detektor['jalan'])
                    <div class="mt-5 rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-600">
                        Jalankan <code class="font-mono">php artisan schedule:work</code> di server agar detektor memeriksa data setiap menit.
                    </div>
                @endunless
            </x-kartu>
        </div>
    </div>
@endfragment
</div>
@endsection
