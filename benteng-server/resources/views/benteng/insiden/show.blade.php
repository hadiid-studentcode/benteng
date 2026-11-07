@use('Illuminate\Support\Carbon')
@extends('benteng.layout')

@section('judul', 'Insiden #'.$insiden->id)
@section('remah')
    <li class="breadcrumb__item"><a href="{{ route('benteng.insiden') }}">Insiden</a></li>
    <li class="breadcrumb__item" aria-current="page">#{{ $insiden->id }}</li>
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
        $penanda = [
            'abu' => '', 'merah' => 'timeline__marker--danger', 'biru' => 'timeline__marker--info',
            'hijau' => 'timeline__marker--success', 'kuning' => 'timeline__marker--warning',
        ];
    @endphp

    <div class="flex flex-col gap-6">
        {{-- Kepala insiden --}}
        <div class="card">
            <div class="card__body">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-tingkat :tingkat="$i->tingkat" />
                            <x-status-insiden :insiden="$i" />
                            <span class="text-xs text-muted-foreground">Skor risiko {{ $i->skor }} dari 99</span>
                        </div>
                        <h2 class="mt-2 text-xl font-semibold text-balance">{{ $i->judul }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ config('benteng.jenis')[$i->jenis] ?? $i->jenis }}, terdeteksi {{ $tanggal($i->created_at) }} WIB oleh detektor otomatis</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-aksi-insiden :insiden="$i" :bisa-bertindak="$bisaBertindak" />
                        @if ($bisaBertindak && $domain && ! $blokir && in_array($i->status, ['baru', 'dikarantina'], true))
                            <form method="POST" action="{{ route('benteng.blokir.insiden', $i->id) }}" data-aksi
                                data-konfirmasi
                                data-varian="bahaya"
                                data-judul="Blokir domain {{ $domain }}?"
                                data-pesan="Semua perangkat di jaringan sensor {{ $i->sensor }} tidak lagi bisa menghubungi domain ini. Perangkat tetap tersambung ke jaringan, dan perangkat lain yang masih mencoba akan dilaporkan sebagai insiden baru."
                                data-tombol="Ya, blokir domain"
                                data-rincian="{{ json_encode([['Domain', $domain], ['Sensor', $i->sensor], ['Insiden', '#'.$i->id.' '.$i->judul]]) }}">
                                @csrf
                                <x-tombol type="submit"><x-ikon nama="larangan" class="size-4" />Blokir domain</x-tombol>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($blokir)
                    <div class="alert alert--info mt-4">
                        <x-ikon nama="larangan" />
                        <div>
                            Domain {{ $blokir->domain }}
                            @if ($blokir->diterapkan_pada)
                                sudah diblokir untuk seluruh jaringan sensor {{ $blokir->sensor }} sejak {{ $tanggal($blokir->diterapkan_pada) }} WIB.
                            @else
                                menunggu diterapkan sensor {{ $blokir->sensor }}, biasanya dalam beberapa detik.
                            @endif
                        </div>
                        <div class="alert__action"><a href="{{ route('benteng.blokir') }}" class="alert__link">Lihat daftar blokir</a></div>
                    </div>
                @endif

                @if ($perintahMenunggu)
                    <div class="alert alert--warning mt-4">
                        <x-ikon nama="jam" />
                        @if ($umurPerintah > config('benteng.perintah_tertahan_detik'))
                            <div>Sensor {{ $i->sensor }} belum menerapkan perintah selama {{ $umurPerintah }} detik. Periksa layanan benteng-responder di sensor.</div>
                        @else
                            <div>Perintah sudah dikirim. Menunggu sensor {{ $i->sensor }} menerapkannya, biasanya kurang dari 5 detik.</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 flex flex-col gap-4 lg:col-span-8">
                @if ($playbook)
                    <x-kartu judul="Apa yang terjadi">
                        <p class="text-sm leading-6">{{ $playbook['arti'] }}</p>
                        <div class="alert alert--warning mt-4">
                            <x-ikon nama="insiden" />
                            <div class="alert__title">Risikonya</div>
                            <div class="alert__description">{{ $playbook['risiko'] }}</div>
                        </div>
                        @if ($i->attck)
                            <a href="https://attack.mitre.org/techniques/{{ str_replace('.', '/', $i->attck) }}/" target="_blank" rel="noopener noreferrer" class="link mt-4 inline-flex items-center gap-1.5 text-sm">
                                MITRE ATT&amp;CK {{ $i->attck }}{{ $namaTeknik ? ': '.$namaTeknik : '' }}<x-ikon nama="tautan-luar" class="size-3.5" />
                            </a>
                        @endif
                    </x-kartu>
                @endif

                <x-kartu judul="Bukti" keterangan="Data dari sensor yang menjadi dasar insiden ini">
                    <p class="text-sm leading-6">{{ $i->bukti }}</p>

                    @if (count($ketukan) >= 2)
                        @php
                            $t0 = $ketukan[0];
                            $t1 = $ketukan[count($ketukan) - 1];
                            $rentang = max(1, $t1 - $t0);
                        @endphp
                        <div class="mt-5">
                            <p class="mb-2 text-xs font-medium text-muted-foreground">Waktu query ke {{ $domain }} (setiap garis adalah satu query)</p>
                            <div class="relative h-10 rounded-md border border-border-strong bg-surface-2" role="img"
                                aria-label="{{ count($ketukan) }} query ke {{ $domain }} antara pukul {{ date('H:i:s', $t0) }} dan {{ date('H:i:s', $t1) }}">
                                @foreach ($ketukan as $t)
                                    <span class="absolute inset-y-1.5 w-0.5 -translate-x-1/2 rounded-full bg-danger" style="left:{{ round(($t - $t0) / $rentang * 100, 2) }}%"></span>
                                @endforeach
                            </div>
                            <div class="mt-1 flex justify-between text-[11px] text-muted-foreground tabular-nums">
                                <span>{{ date('H:i:s', $t0) }}</span><span>{{ date('H:i:s', $t1) }}</span>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-muted-foreground">Jarak antar garis yang hampir sama berarti perangkat menghubungi server ini secara otomatis dan terjadwal, bukan karena dibuka oleh pengguna.</p>
                        </div>
                    @endif

                    @if ($i->mulai_pada)
                        <p class="mt-4 text-xs text-muted-foreground">Aktivitas pertama tercatat {{ $tanggal($i->mulai_pada) }} WIB.</p>
                    @endif
                </x-kartu>

                @if ($playbook)
                    <x-kartu judul="Langkah penanganan yang disarankan">
                        <ol class="flex flex-col gap-3">
                            @foreach ($playbook['langkah'] as $n => $langkah)
                                <li class="flex gap-3 text-sm">
                                    <span class="icon-box icon-box--primary icon-box--circle icon-box--sm shrink-0">{{ $n + 1 }}</span>
                                    <span class="pt-0.5 leading-6">{{ $langkah }}</span>
                                </li>
                            @endforeach
                        </ol>
                        <p class="mt-4 text-xs text-muted-foreground">Saran dari playbook BENTENG. Keputusan akhir tetap di tangan analis.</p>
                    </x-kartu>
                @endif
            </div>

            <div class="col-span-12 flex flex-col gap-4 lg:col-span-4">
                <x-kartu judul="Perangkat">
                    <dl class="flex flex-col gap-3 text-sm">
                        @foreach ([
                            ['Alamat IP', $i->client_ip],
                            ['Nama host', $p?->hostname ?: 'Tidak diketahui'],
                            ['MAC', $p?->mac ?: 'Belum dilaporkan sensor'],
                            ['Sensor', $i->sensor],
                            ['Query DNS', $p ? number_format($p->jumlah, 0, ',', '.').' query ke '.number_format($p->domain_unik, 0, ',', '.').' domain' : '-'],
                            ['Terakhir terlihat', $p ? $relatif($p->akhir) : '-'],
                        ] as [$label, $nilai])
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">{{ $label }}</dt>
                                <dd class="text-end font-medium break-all">{{ $nilai }}</dd>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-muted-foreground">Status perangkat</dt>
                            <dd><x-status-perangkat :status="$p?->status ?? 'normal'" /></dd>
                        </div>
                    </dl>
                </x-kartu>

                <x-kartu judul="Linimasa penanganan">
                    <ol class="timeline">
                        @foreach ($linimasa as $l)
                            <li class="timeline__item" @if ($l['menunggu'] ?? false) data-state="current" @endif>
                                <span class="timeline__marker {{ $penanda[$l['warna']] }}"><x-ikon :nama="$l['menunggu'] ?? false ? 'jam' : 'centang'" class="size-3.5" /></span>
                                <div class="timeline__body">
                                    <div class="timeline__title">{{ $l['judul'] }}</div>
                                    <div class="timeline__text">{{ $l['ket'] }}</div>
                                    @if ($l['waktu'])
                                        <div class="timeline__time">{{ $tanggal($l['waktu']) }}</div>
                                    @endif
                                </div>
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
