@use('Illuminate\Support\Carbon')
@extends('benteng.layout')

@section('judul', 'Sensor')
@section('keterangan', 'Sensor dipasang di tepi jaringan satuan. Sensor mengirim data tiap menit dan memeriksa perintah dari server tiap 5 detik.')
@section('remah')
    <li class="breadcrumb__item" aria-current="page">Sensor</li>
@endsection

@section('konten')
<div id="isi" data-segar>
@fragment('isi')
    @include('benteng.partials.segar')
    @php
        $relatif = fn ($t) => $t ? Carbon::parse($t)->locale('id')->diffForHumans() : 'Belum pernah';
    @endphp
    <div class="grid grid-cols-12 gap-4">
        @forelse ($sensor as $s)
            <x-kartu class="col-span-12 lg:col-span-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="icon-box icon-box--primary icon-box--lg"><x-ikon nama="sensor" /></span>
                        <div>
                            <p class="font-semibold">Sensor {{ $s['nama'] }}</p>
                            <p class="text-xs text-muted-foreground">{{ $s['info']['model'] ?? 'Sensor tepi jaringan' }}{{ isset($s['info']['lokasi']) ? ', '.$s['info']['lokasi'] : '' }}</p>
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
                            <dt class="text-xs text-muted-foreground">{{ $label }}</dt>
                            <dd class="mt-0.5 font-medium">{{ $nilai }}</dd>
                        </div>
                    @endforeach
                </dl>
                @unless ($s['online'])
                    <div class="alert alert--warning mt-5">
                        <x-ikon nama="info" />
                        <div>Periksa sensor lewat SSH, lalu jalankan <code>/etc/init.d/benteng-responder restart</code>. Karantina yang sedang aktif dipulihkan otomatis saat sensor terhubung kembali.</div>
                    </div>
                @endunless
            </x-kartu>
        @empty
            <x-kartu class="col-span-12">
                <p class="text-sm text-muted-foreground">Belum ada sensor yang terhubung. Pasang skrip sensor sesuai panduan di docs/PANDUAN-SENSOR.md.</p>
            </x-kartu>
        @endforelse

        <x-kartu class="col-span-12 lg:col-span-6">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="icon-box icon-box--info icon-box--lg"><x-ikon nama="cpu" /></span>
                    <div>
                        <p class="font-semibold">Detektor otomatis</p>
                        <p class="text-xs text-muted-foreground">Berjalan di server pusat setiap menit</p>
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
                    <dt class="text-xs text-muted-foreground">Pemeriksaan terakhir</dt>
                    <dd class="mt-0.5 font-medium">{{ $relatif($detektor['detak']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Yang diperiksa</dt>
                    <dd class="mt-0.5 font-medium">Beaconing, domain acak, perangkat asing, domain terblokir</dd>
                </div>
            </dl>
            @unless ($detektor['jalan'])
                <div class="alert alert--warning mt-5">
                    <x-ikon nama="info" />
                    <div>Jalankan <code>php artisan schedule:work</code> di server agar detektor memeriksa data setiap menit.</div>
                </div>
            @endunless
        </x-kartu>

        <x-kartu judul="Alur kerja sensor" keterangan="Dari lalu lintas jaringan sampai tindakan analis" class="col-span-12">
            <ol class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['sensor', 'Mencatat', 'Sensor mencatat query DNS dan daftar DHCP di jaringan.'],
                    ['aktivitas', 'Mengirim', 'Data dikirim ke server satuan tiap menit.'],
                    ['cpu', 'Memeriksa', 'Detektor mencari pola ancaman dan membuat insiden.'],
                    ['kunci', 'Menerapkan', 'Keputusan analis diterapkan sensor dalam beberapa detik.'],
                ] as $n => [$ikon, $judul, $teks])
                    <li class="flex gap-3">
                        <span class="icon-box icon-box--primary shrink-0"><x-ikon :nama="$ikon" class="size-5" /></span>
                        <div>
                            <p class="font-semibold">{{ $n + 1 }}. {{ $judul }}</p>
                            <p class="mt-0.5 text-sm text-muted-foreground">{{ $teks }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-kartu>
    </div>
@endfragment
</div>
@endsection
