@extends('benteng.layout')

@section('judul', 'Dashboard')
@section('keterangan', 'Ringkasan keamanan jaringan satuan.')

@section('konten')
<div id="isi" data-segar>
@fragment('isi')
    @include('benteng.partials.segar')
    @php
        $relatif = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->diffForHumans() : 'belum pernah';
        $durasi = function (?int $d): string {
            if ($d === null) {
                return 'Belum ada data';
            }
            if ($d < 60) {
                return $d.' detik';
            }
            if ($d < 3600) {
                return intdiv($d, 60).' menit '.($d % 60).' detik';
            }

            return intdiv($d, 3600).' jam '.intdiv($d % 3600, 60).' menit';
        };
        $sensorMati = $sensor->where('online', false);
        $semuaAman = ! $metrik['insidenAktif'] && $sensorMati->isEmpty() && $detektor['jalan'] && $sensor->isNotEmpty();
    @endphp

    <div class="flex flex-col gap-6">
        {{-- Kondisi yang perlu diketahui analis, dalam bahasa sederhana --}}
        @if ($metrik['insidenAktif'] || $sensorMati->isNotEmpty() || ! $detektor['jalan'] || $semuaAman)
            <div class="flex flex-col gap-3">
                @if ($metrik['insidenAktif'])
                    <div class="alert alert--danger">
                        <x-ikon nama="insiden" />
                        <div class="alert__title">{{ $metrik['insidenAktif'] }} insiden perlu ditindaklanjuti</div>
                        <div class="alert__description">Periksa bukti tiap insiden, lalu putuskan: karantina perangkat atau tandai bukan ancaman.</div>
                        <div class="alert__action"><x-tombol varian="bahaya" ukuran="kecil" :href="route('benteng.insiden', ['status' => 'aktif'])">Tinjau insiden</x-tombol></div>
                    </div>
                @endif
                @foreach ($sensorMati as $s)
                    <div class="alert alert--warning">
                        <x-ikon nama="sensor" />
                        <div class="alert__title">Sensor {{ $s['nama'] }} tidak merespons</div>
                        <div class="alert__description">Terakhir terhubung {{ $relatif($s['detak']) }}. Perintah karantina belum bisa diterapkan sampai sensor kembali online.</div>
                        <div class="alert__action"><x-tombol ukuran="kecil" :href="route('benteng.sensor')">Lihat sensor</x-tombol></div>
                    </div>
                @endforeach
                @unless ($detektor['jalan'])
                    <div class="alert alert--warning">
                        <x-ikon nama="cpu" />
                        <div class="alert__title">Detektor otomatis tidak berjalan</div>
                        <div class="alert__description">Insiden baru tidak akan muncul. Jalankan <code>php artisan schedule:work</code> di server.</div>
                    </div>
                @endunless
                @if ($semuaAman)
                    <div class="alert alert--success">
                        <x-ikon nama="centang-bulat" />
                        <div class="alert__title">Jaringan dalam kondisi aman</div>
                        <div class="alert__description">Tidak ada insiden yang menunggu tindakan. Sensor dan detektor berjalan normal.</div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Ringkasan angka --}}
        <div class="grid grid-cols-12 gap-4">
            @foreach ([
                ['Insiden aktif', $metrik['insidenAktif'], 'Menunggu keputusan analis', 'insiden', 'icon-box--danger', route('benteng.insiden', ['status' => 'aktif'])],
                ['Perangkat dikarantina', $metrik['dikarantina'], 'Koneksinya sedang diputus', 'kunci', 'icon-box--info', route('benteng.insiden', ['status' => 'dikarantina'])],
                ['Perangkat terpantau', $metrik['perangkat'], $metrik['asing'] ? $metrik['asing'].' perangkat belum dikenal' : 'Semua perangkat dikenal', 'perangkat', 'icon-box--success', route('benteng.perangkat')],
                ['Sensor online', $metrik['sensorOnline'].' dari '.$metrik['sensorTotal'], 'Sensor yang terhubung ke server', 'sensor', 'icon-box--primary', route('benteng.sensor')],
            ] as [$label, $nilai, $catatan, $ikon, $warnaIkon, $tautan])
                <div class="col-span-12 sm:col-span-6 xl:col-span-3">
                    <a href="{{ $tautan }}" class="card card--stat">
                        <div class="card__body">
                            <span class="icon-box {{ $warnaIkon }} icon-box--lg"><x-ikon :nama="$ikon" /></span>
                            <div class="stat">
                                <div class="stat__value">{{ $nilai }}</div>
                                <div class="stat__label text-eyebrow">{{ $label }}</div>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ $catatan }}</p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-12 gap-4">
            <x-kartu judul="Aktivitas DNS" keterangan="Jumlah query per menit dalam 60 menit terakhir" class="col-span-12 xl:col-span-8">
                <x-slot:aksi>
                    <span class="text-xs text-muted-foreground">{{ number_format($metrik['query24'], 0, ',', '.') }} query dalam 24 jam</span>
                </x-slot:aksi>
                @include('benteng.partials.grafik-dns')
            </x-kartu>

            <x-kartu judul="Status sistem" class="col-span-12 xl:col-span-4">
                <dl class="flex flex-col gap-3 text-sm">
                    @foreach ($sensor as $s)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted-foreground">Sensor {{ $s['nama'] }}</dt>
                            <dd>
                                @if ($s['online'])
                                    <x-lencana warna="hijau">Online</x-lencana>
                                @else
                                    <x-lencana warna="merah">Tidak merespons</x-lencana>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted-foreground">Detektor otomatis</dt>
                        <dd>
                            @if ($detektor['jalan'])
                                <x-lencana warna="hijau">Berjalan</x-lencana>
                            @else
                                <x-lencana warna="kuning">Tidak berjalan</x-lencana>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted-foreground">Waktu deteksi</dt>
                        <dd class="font-semibold">{{ $durasi($metrik['waktuDeteksi']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted-foreground">Waktu respons sensor</dt>
                        <dd class="font-semibold">{{ $durasi($metrik['responsSensor']) }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs leading-5 text-muted-foreground">Waktu deteksi dihitung dari aktivitas mencurigakan pertama sampai insiden muncul. Respons sensor dihitung dari klik analis sampai karantina diterapkan. Nilai yang ditampilkan adalah median.</p>
            </x-kartu>
        </div>

        <x-kartu judul="Insiden terbaru" keterangan="Yang perlu tindakan ditampilkan paling atas" rapat>
            <x-slot:aksi>
                <x-tombol varian="teks" ukuran="kecil" :href="route('benteng.insiden')">Lihat semua<x-ikon nama="panah-kanan" class="size-4" /></x-tombol>
            </x-slot:aksi>
            @include('benteng.partials.tabel-insiden', ['incidents' => $insidenTerbaru, 'kosong' => 'Belum ada insiden. Detektor memeriksa data sensor setiap menit.'])
        </x-kartu>

        <x-kartu judul="Topologi jaringan" keterangan="Arahkan kursor ke perangkat untuk melihat detailnya">
            <x-slot:aksi>
                <x-tombol varian="teks" ukuran="kecil" :href="route('benteng.topologi')">Buka halaman topologi<x-ikon nama="panah-kanan" class="size-4" /></x-tombol>
            </x-slot:aksi>
            @include('benteng.partials.topologi')
        </x-kartu>
    </div>
@endfragment
</div>
@endsection
