@extends('benteng.layout')

@section('judul', 'Dashboard')

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

    <div class="space-y-6">
        {{-- Kondisi yang perlu diketahui analis, dalam bahasa sederhana --}}
        <div class="space-y-3">
            @if ($metrik['insidenAktif'])
                <div class="flex flex-col gap-3 rounded-xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center">
                    <x-ikon nama="insiden" class="text-red-600" />
                    <div class="flex-1 text-sm">
                        <p class="font-semibold text-red-800">{{ $metrik['insidenAktif'] }} insiden perlu ditindaklanjuti</p>
                        <p class="text-red-700">Periksa bukti tiap insiden, lalu putuskan: karantina perangkat atau tandai bukan ancaman.</p>
                    </div>
                    <x-tombol varian="bahaya" :href="route('benteng.insiden', ['status' => 'aktif'])">Tinjau insiden</x-tombol>
                </div>
            @endif
            @foreach ($sensorMati as $s)
                <div class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center">
                    <x-ikon nama="sensor" class="text-amber-600" />
                    <div class="flex-1 text-sm">
                        <p class="font-semibold text-amber-900">Sensor {{ $s['nama'] }} tidak merespons</p>
                        <p class="text-amber-800">Terakhir terhubung {{ $relatif($s['detak']) }}. Perintah karantina belum bisa diterapkan sampai sensor kembali online.</p>
                    </div>
                    <x-tombol :href="route('benteng.sensor')">Lihat sensor</x-tombol>
                </div>
            @endforeach
            @unless ($detektor['jalan'])
                <div class="flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center">
                    <x-ikon nama="cpu" class="text-amber-600" />
                    <div class="flex-1 text-sm">
                        <p class="font-semibold text-amber-900">Detektor otomatis tidak berjalan</p>
                        <p class="text-amber-800">Insiden baru tidak akan muncul. Jalankan <code class="rounded bg-amber-100 px-1 font-mono text-xs">php artisan schedule:work</code> di server.</p>
                    </div>
                </div>
            @endunless
            @if ($semuaAman)
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <x-ikon nama="centang-bulat" class="text-emerald-600" />
                    <div class="text-sm">
                        <p class="font-semibold text-emerald-800">Jaringan dalam kondisi aman</p>
                        <p class="text-emerald-700">Tidak ada insiden yang menunggu tindakan. Sensor dan detektor berjalan normal.</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Ringkasan angka --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Insiden aktif', $metrik['insidenAktif'], 'Menunggu keputusan analis', 'insiden', 'bg-red-50 text-red-600', route('benteng.insiden', ['status' => 'aktif'])],
                ['Perangkat dikarantina', $metrik['dikarantina'], 'Koneksinya sedang diputus', 'kunci', 'bg-sky-50 text-sky-600', route('benteng.insiden', ['status' => 'dikarantina'])],
                ['Perangkat terpantau', $metrik['perangkat'], $metrik['asing'] ? $metrik['asing'].' perangkat belum dikenal' : 'Semua perangkat dikenal', 'perangkat', 'bg-brand-50 text-brand-600', route('benteng.perangkat')],
                ['Sensor online', $metrik['sensorOnline'].' dari '.$metrik['sensorTotal'], 'Sensor yang terhubung ke server', 'sensor', 'bg-violet-50 text-violet-600', route('benteng.sensor')],
            ] as [$label, $nilai, $catatan, $ikon, $warnaIkon, $tautan])
                <a href="{{ $tautan }}" class="group rounded-xl border border-slate-200 bg-white p-5 shadow-xs hover:border-slate-300">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                        <span class="flex size-9 items-center justify-center rounded-lg {{ $warnaIkon }}"><x-ikon :nama="$ikon" class="size-[18px]" /></span>
                    </div>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ $nilai }}</p>
                    <p class="mt-1 text-xs text-slate-500 group-hover:text-slate-700">{{ $catatan }}</p>
                </a>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-kartu judul="Aktivitas DNS" keterangan="Jumlah query per menit dalam 60 menit terakhir" class="xl:col-span-2">
                <x-slot:aksi>
                    <span class="text-xs text-slate-500">{{ number_format($metrik['query24'], 0, ',', '.') }} query dalam 24 jam</span>
                </x-slot:aksi>
                @include('benteng.partials.grafik-dns')
            </x-kartu>

            <x-kartu judul="Status sistem">
                <dl class="-my-3 divide-y divide-slate-100 text-sm">
                    @foreach ($sensor as $s)
                        <div class="flex items-center justify-between gap-3 py-3">
                            <dt class="text-slate-600">Sensor {{ $s['nama'] }}</dt>
                            <dd class="text-right">
                                @if ($s['online'])
                                    <x-lencana warna="hijau">Online</x-lencana>
                                @else
                                    <x-lencana warna="merah">Tidak merespons</x-lencana>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between gap-3 py-3">
                        <dt class="text-slate-600">Detektor otomatis</dt>
                        <dd>
                            @if ($detektor['jalan'])
                                <x-lencana warna="hijau">Berjalan</x-lencana>
                            @else
                                <x-lencana warna="kuning">Tidak berjalan</x-lencana>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-3">
                        <dt class="text-slate-600">Waktu deteksi</dt>
                        <dd class="font-semibold text-slate-900">{{ $durasi($metrik['waktuDeteksi']) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 py-3">
                        <dt class="text-slate-600">Waktu respons sensor</dt>
                        <dd class="font-semibold text-slate-900">{{ $durasi($metrik['responsSensor']) }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs leading-5 text-slate-500">Waktu deteksi dihitung dari aktivitas mencurigakan pertama sampai insiden muncul. Respons sensor dihitung dari klik analis sampai karantina diterapkan. Nilai yang ditampilkan adalah median.</p>
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
