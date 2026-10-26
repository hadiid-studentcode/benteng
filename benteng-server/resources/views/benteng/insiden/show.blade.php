@use('Illuminate\Support\Carbon')
@extends('benteng.layout')

@section('judul', 'Insiden #'.$insiden->id)
@section('remah')
    <a href="{{ route('benteng.insiden') }}" class="hover:text-slate-700">Insiden</a><span class="mx-1 text-slate-300">/</span>#{{ $insiden->id }}
@endsection

@section('konten')
<div id="isi" data-segar>
@fragment('isi')
    @include('benteng.partials.segar')
    @php
        $i = $insiden;
        $p = $i->perangkat;
        $relatif = fn ($t) => $t ? Carbon::parse($t)->locale('id')->diffForHumans() : '-';
        $tanggal = fn ($t) => $t ? Carbon::parse($t)->locale('id')->translatedFormat('d M Y, H:i:s') : '-';
        $perintahMenunggu = $i->perintah?->status === 'pending' && $i->status !== 'false_positive';
        $umurPerintah = $perintahMenunggu ? (int) Carbon::parse($i->perintah->created_at)->diffInSeconds($sekarang) : 0;
        $warnaTitik = [
            'abu' => 'bg-slate-400', 'merah' => 'bg-red-500', 'biru' => 'bg-sky-500',
            'hijau' => 'bg-emerald-500', 'kuning' => 'bg-amber-500',
        ];
    @endphp

    <div class="space-y-6">
        <a href="{{ route('benteng.insiden') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900">
            <x-ikon nama="panah-kiri" class="size-4" />Kembali ke daftar insiden
        </a>

        {{-- Kepala insiden --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-tingkat :tingkat="$i->tingkat" />
                        <x-status-insiden :insiden="$i" />
                        <span class="text-xs text-slate-500">Skor risiko {{ $i->skor }} dari 99</span>
                    </div>
                    <h2 class="mt-2 text-xl font-semibold text-balance text-slate-900">{{ $i->judul }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ config('benteng.jenis')[$i->jenis] ?? $i->jenis }}, terdeteksi {{ $tanggal($i->created_at) }} WIB oleh detektor otomatis</p>
                </div>
                <x-aksi-insiden :insiden="$i" :bisa-bertindak="$bisaBertindak" />
            </div>

            @if ($perintahMenunggu)
                <div class="mt-4 flex items-start gap-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                    <x-ikon nama="jam" class="mt-0.5 size-4 text-amber-600" />
                    @if ($umurPerintah > config('benteng.perintah_tertahan_detik'))
                        <p>Sensor {{ $i->sensor }} belum menerapkan perintah selama {{ $umurPerintah }} detik. Periksa layanan benteng-responder di sensor.</p>
                    @else
                        <p>Perintah sudah dikirim. Menunggu sensor {{ $i->sensor }} menerapkannya, biasanya kurang dari 5 detik.</p>
                    @endif
                </div>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @if ($playbook)
                    <x-kartu judul="Apa yang terjadi">
                        <p class="text-sm leading-6 text-slate-700">{{ $playbook['arti'] }}</p>
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">
                            <p class="font-semibold text-amber-900">Risikonya</p>
                            <p class="mt-1 leading-6 text-amber-900">{{ $playbook['risiko'] }}</p>
                        </div>
                        @if ($i->attck)
                            <a href="https://attack.mitre.org/techniques/{{ str_replace('.', '/', $i->attck) }}/" target="_blank" rel="noopener noreferrer"
                                class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:underline">
                                MITRE ATT&amp;CK {{ $i->attck }}{{ $namaTeknik ? ': '.$namaTeknik : '' }}<x-ikon nama="tautan-luar" class="size-3.5" />
                            </a>
                        @endif
                    </x-kartu>
                @endif

                <x-kartu judul="Bukti" keterangan="Data dari sensor yang menjadi dasar insiden ini">
                    <p class="text-sm leading-6 text-slate-700">{{ $i->bukti }}</p>

                    @if (count($ketukan) >= 2)
                        @php
                            $t0 = $ketukan[0];
                            $t1 = $ketukan[count($ketukan) - 1];
                            $rentang = max(1, $t1 - $t0);
                        @endphp
                        <div class="mt-5">
                            <p class="mb-2 text-xs font-medium text-slate-500">Waktu query ke {{ $domain }} (setiap garis adalah satu query)</p>
                            <div class="relative h-10 rounded-md border border-slate-200 bg-slate-50" role="img"
                                aria-label="{{ count($ketukan) }} query ke {{ $domain }} antara pukul {{ date('H:i:s', $t0) }} dan {{ date('H:i:s', $t1) }}">
                                @foreach ($ketukan as $t)
                                    <span class="absolute inset-y-1.5 w-0.5 -translate-x-1/2 rounded-full bg-red-500" style="left:{{ round(($t - $t0) / $rentang * 100, 2) }}%"></span>
                                @endforeach
                            </div>
                            <div class="mt-1 flex justify-between text-[11px] text-slate-400 tabular-nums">
                                <span>{{ date('H:i:s', $t0) }}</span><span>{{ date('H:i:s', $t1) }}</span>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-slate-500">Jarak antar garis yang hampir sama berarti perangkat menghubungi server ini secara otomatis dan terjadwal, bukan karena dibuka oleh pengguna.</p>
                        </div>
                    @endif

                    @if ($i->mulai_pada)
                        <p class="mt-4 text-xs text-slate-500">Aktivitas pertama tercatat {{ $tanggal($i->mulai_pada) }} WIB.</p>
                    @endif
                </x-kartu>

                @if ($playbook)
                    <x-kartu judul="Langkah penanganan yang disarankan">
                        <ol class="space-y-3">
                            @foreach ($playbook['langkah'] as $n => $langkah)
                                <li class="flex gap-3 text-sm text-slate-700">
                                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ $n + 1 }}</span>
                                    <span class="pt-0.5 leading-6">{{ $langkah }}</span>
                                </li>
                            @endforeach
                        </ol>
                        <p class="mt-4 text-xs text-slate-500">Saran dari playbook BENTENG. Keputusan akhir tetap di tangan analis.</p>
                    </x-kartu>
                @endif
            </div>

            <div class="space-y-6">
                <x-kartu judul="Perangkat">
                    <dl class="space-y-3 text-sm">
                        @foreach ([
                            ['Alamat IP', $i->client_ip],
                            ['Nama host', $p?->hostname ?: 'Tidak diketahui'],
                            ['MAC', $p?->mac ?: 'Belum dilaporkan sensor'],
                            ['Sensor', $i->sensor],
                            ['Query DNS', $p ? number_format($p->jumlah, 0, ',', '.').' query ke '.number_format($p->domain_unik, 0, ',', '.').' domain' : '-'],
                            ['Terakhir terlihat', $p ? $relatif($p->akhir) : '-'],
                        ] as [$label, $nilai])
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="text-right font-medium break-all text-slate-800">{{ $nilai }}</dd>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500">Status perangkat</dt>
                            <dd><x-status-perangkat :status="$p?->status ?? 'normal'" /></dd>
                        </div>
                    </dl>
                </x-kartu>

                <x-kartu judul="Linimasa penanganan">
                    <ol class="relative space-y-5 border-l border-slate-200 pl-5">
                        @foreach ($linimasa as $l)
                            <li class="relative">
                                <span class="absolute top-1.5 -left-[25px] size-2.5 rounded-full ring-4 ring-white {{ $warnaTitik[$l['warna']] }} {{ $l['menunggu'] ?? false ? 'animate-pulse' : '' }}"></span>
                                <p class="text-sm font-medium text-slate-800">{{ $l['judul'] }}</p>
                                <p class="text-xs text-slate-500">{{ $l['ket'] }}</p>
                                @if ($l['waktu'])
                                    <p class="mt-0.5 text-xs text-slate-400 tabular-nums">{{ $tanggal($l['waktu']) }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </x-kartu>
            </div>
        </div>
    </div>
@endfragment
</div>
@endsection
