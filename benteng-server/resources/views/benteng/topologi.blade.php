@extends('benteng.layout')

@section('judul', 'Topologi jaringan')
@section('keterangan', 'Server pusat, sensor di tepi jaringan, dan perangkat yang terhubung ke tiap sensor.')
@section('remah')
    <li class="breadcrumb__item" aria-current="page">Topologi jaringan</li>
@endsection

@section('konten')
<div id="isi" data-segar>
@fragment('isi')
    @include('benteng.partials.segar')
    <div class="flex flex-col gap-4">
        <x-kartu judul="Peta perangkat">
            @include('benteng.partials.topologi')
        </x-kartu>

        <x-kartu judul="Cara membaca diagram">
            <ul class="grid gap-4 text-sm text-muted-foreground md:grid-cols-2">
                <li class="flex gap-3">
                    <span class="indicator indicator--success mt-1.5 shrink-0"></span>
                    <span>Garis bergerak antara server dan sensor berarti sensor online dan rutin mengirim data. Garis merah putus-putus berarti sensor tidak merespons.</span>
                </li>
                <li class="flex gap-3">
                    <span class="indicator indicator--danger mt-1.5 shrink-0"></span>
                    <span>Perangkat merah sedang punya insiden yang menunggu keputusan analis.</span>
                </li>
                <li class="flex gap-3">
                    <span class="indicator indicator--info mt-1.5 shrink-0"></span>
                    <span>Perangkat biru dengan tanda silang sedang dikarantina: sensor memutus koneksinya ke internet dan jaringan lain.</span>
                </li>
                <li class="flex gap-3">
                    <span class="indicator indicator--warning mt-1.5 shrink-0"></span>
                    <span>Perangkat kuning baru tersambung dan belum didaftarkan sebagai perangkat satuan.</span>
                </li>
            </ul>
        </x-kartu>
    </div>
@endfragment
</div>
@endsection
