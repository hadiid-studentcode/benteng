@extends('benteng.layout')

@section('judul', 'Topologi jaringan')
@section('remah', 'Topologi jaringan')

@section('konten')
<div id="isi" data-segar>
@fragment('isi')
    @include('benteng.partials.segar')
    <div class="space-y-6">
        <x-kartu judul="Peta perangkat" keterangan="Server pusat, sensor di tepi jaringan, dan perangkat yang terhubung ke tiap sensor">
            @include('benteng.partials.topologi')
        </x-kartu>

        <x-kartu judul="Cara membaca diagram">
            <ul class="grid gap-4 text-sm text-slate-600 sm:grid-cols-2">
                <li class="flex gap-3">
                    <span class="mt-1.5 h-0.5 w-6 shrink-0 bg-brand-500"></span>
                    <span>Garis bergerak antara server dan sensor berarti sensor online dan rutin mengirim data. Garis merah putus-putus berarti sensor tidak merespons.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 size-2.5 shrink-0 rounded-full bg-red-500"></span>
                    <span>Perangkat merah sedang punya insiden yang menunggu keputusan analis.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 size-2.5 shrink-0 rounded-full bg-sky-500"></span>
                    <span>Perangkat biru dengan tanda silang sedang dikarantina: sensor memutus koneksinya ke internet dan jaringan lain.</span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-1 size-2.5 shrink-0 rounded-full bg-amber-500"></span>
                    <span>Perangkat kuning baru tersambung dan belum didaftarkan sebagai perangkat satuan.</span>
                </li>
            </ul>
        </x-kartu>
    </div>
@endfragment
</div>
@endsection
