@use('Illuminate\Support\Carbon')
@extends('benteng.layout')

@section('judul', 'Domain diblokir')
@section('keterangan', 'Domain di daftar ini tidak bisa dihubungi perangkat mana pun di jaringan sensor. Perangkat yang masih mencoba akan dilaporkan sebagai insiden, karena kemungkinan ikut terinfeksi.')
@section('remah')
    <li class="breadcrumb__item" aria-current="page">Domain diblokir</li>
@endsection

@section('konten')
<div class="grid grid-cols-12 items-start gap-4">
    <div id="isi" data-segar class="col-span-12 min-w-0 xl:col-span-8">
    @fragment('isi')
        @include('benteng.partials.segar')
        <div class="flex flex-col gap-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ringkasan label="Blokir aktif" :nilai="$blokir->whereNotNull('diterapkan_pada')->count()" ikon="larangan" warna="icon-box--info" />
                <x-ringkasan label="Menunggu sensor" :nilai="$blokir->whereNull('diterapkan_pada')->count()" ikon="jam" warna="icon-box--warning" />
                <x-ringkasan label="Percobaan tercegah" :nilai="(int) $blokir->sum('jumlah_percobaan')" ikon="perisai" warna="icon-box--success" />
            </div>

            <x-kartu rapat>
                @if ($blokir->isEmpty())
                    <div class="empty-state">
                        <span class="empty-state__media"><x-ikon nama="larangan" /></span>
                        <p class="empty-state__text">Belum ada domain yang diblokir. Domain bisa diblokir dari halaman detail insiden beaconing.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table--hover table--align-middle">
                            <thead class="table__head--alt">
                                <tr>
                                    <th scope="col">Domain</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Percobaan</th>
                                    <th scope="col"><span class="sr-only">Tindakan</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($blokir as $b)
                                    <tr>
                                        <td class="min-w-64">
                                            <p class="font-semibold break-all">{{ $b->domain }}</p>
                                            <p class="mt-0.5 text-xs text-muted-foreground">
                                                {{ $b->pelaku }}, {{ Carbon::parse($b->created_at)->locale('id')->diffForHumans() }}
                                                <br>Sensor {{ $b->sensor }}@if ($b->incident_id), dari <a href="{{ route('benteng.insiden.detail', $b->incident_id) }}" class="link">insiden #{{ $b->incident_id }}</a>@endif
                                            </p>
                                        </td>
                                        <td class="whitespace-nowrap">
                                            @if ($b->diterapkan_pada)
                                                <x-lencana warna="biru">Aktif</x-lencana>
                                            @else
                                                <x-lencana warna="kuning">Menunggu sensor</x-lencana>
                                            @endif
                                        </td>
                                        <td class="max-w-36 {{ $b->jumlah_percobaan ? 'font-medium text-danger-emphasis' : 'text-muted-foreground' }}">{{ number_format($b->jumlah_percobaan, 0, ',', '.') }} query dari {{ $b->jumlah_perangkat }} perangkat</td>
                                        <td class="text-end whitespace-nowrap">
                                            @if ($bisaBertindak)
                                                <form method="POST" action="{{ route('benteng.blokir.lepas', $b->id) }}" data-aksi
                                                    data-konfirmasi
                                                    data-varian="info"
                                                    data-judul="Lepas blokir {{ $b->domain }}?"
                                                    data-pesan="Perangkat di jaringan sensor {{ $b->sensor }} bisa menghubungi domain ini lagi."
                                                    data-tombol="Ya, lepas blokir"
                                                    data-rincian="{{ json_encode([['Domain', $b->domain], ['Sensor', $b->sensor]]) }}">
                                                    @csrf
                                                    <x-tombol type="submit" ukuran="kecil"><x-ikon nama="buka-kunci" class="size-4" />Lepas blokir</x-tombol>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-kartu>
        </div>
    @endfragment
    </div>

    {{-- Di luar fragmen agar isian form tidak hilang saat halaman disegarkan. --}}
    <div class="order-first col-span-12 flex min-w-0 flex-col gap-4 xl:order-last xl:col-span-4">
        @if ($bisaBertindak)
            <x-kartu judul="Blokir domain baru" keterangan="Misalnya dari peringatan satuan atas">
                <form method="POST" action="{{ route('benteng.blokir.tambah') }}" class="flex flex-col gap-3">
                    @csrf
                    <div class="field">
                        <label class="field__label" for="domain-baru">Nama domain</label>
                        <input type="text" id="domain-baru" name="domain" class="input" required placeholder="server-c2.example">
                    </div>
                    <div class="field">
                        <label class="field__label" for="sensor-blokir">Sensor</label>
                        <select id="sensor-blokir" name="sensor" class="select">
                            @foreach ($sensor as $s)
                                <option value="{{ $s['nama'] }}">{{ $s['nama'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-tombol type="submit" varian="bahaya" class="button--block"><x-ikon nama="larangan" class="size-4" />Blokir domain</x-tombol>
                </form>
            </x-kartu>
        @endif

        <x-kartu judul="Blokir atau karantina?">
            <ul class="flex flex-col gap-4 text-sm leading-6">
                <li class="flex gap-3">
                    <span class="icon-box icon-box--sm icon-box--info shrink-0"><x-ikon nama="larangan" class="size-3.5" /></span>
                    <span><strong class="font-semibold">Blokir domain</strong> untuk perangkat yang harus tetap bekerja, seperti CCTV. Hanya koneksi ke domain itu yang diputus, untuk seluruh jaringan.</span>
                </li>
                <li class="flex gap-3">
                    <span class="icon-box icon-box--sm icon-box--danger shrink-0"><x-ikon nama="kunci" class="size-3.5" /></span>
                    <span><strong class="font-semibold">Karantina</strong> untuk perangkat yang diduga terinfeksi. Seluruh koneksi perangkat itu diputus sampai diperiksa.</span>
                </li>
            </ul>
        </x-kartu>
    </div>
</div>
@endsection
