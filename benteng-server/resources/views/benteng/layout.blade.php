@use('App\Models\User')
@php
    $user = auth()->user();
    $inisial = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('');
    $menu = [
        'Pemantauan' => [
            ['benteng.dashboard', 'Dashboard', 'dashboard', null],
            ['benteng.insiden', 'Insiden', 'insiden', 'insiden'],
            ['benteng.perangkat', 'Perangkat', 'perangkat', 'asing'],
            ['benteng.topologi', 'Topologi jaringan', 'topologi', null],
        ],
        'Sistem' => [
            ['benteng.sensor', 'Sensor', 'sensor', null],
            ['benteng.audit', 'Jejak audit', 'audit', null],
        ],
    ];
@endphp
<!doctype html>
<html lang="id" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul') - BENTENG</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">

<div id="lapisan-sidebar" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden" data-tutup-sidebar></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-sidebar text-slate-300 transition-transform duration-200 lg:translate-x-0">
    <a href="{{ route('benteng.dashboard') }}" class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
        <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-white">
            <svg class="size-5" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M16 3 L27 7 V15 C27 22 22 27 16 29 C10 27 5 22 5 15 V7 Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M11 16 L15 20 L21 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span class="leading-tight">
            <span class="block text-sm font-bold tracking-wider text-white">BENTENG</span>
            <span class="block text-xs text-slate-400">Pertahanan Jaringan Satuan</span>
        </span>
    </a>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5 text-sm" aria-label="Menu utama">
        @foreach ($menu as $grup => $item)
            <div>
                <p class="px-3 pb-2 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">{{ $grup }}</p>
                <ul class="space-y-1">
                    @foreach ($item as [$rute, $label, $ikon, $kunciLencana])
                        @php $aktif = request()->routeIs($rute.'*'); @endphp
                        <li>
                            <a href="{{ route($rute) }}" @if ($aktif) aria-current="page" @endif
                                class="flex items-center gap-3 rounded-md px-3 py-2 {{ $aktif ? 'bg-white/10 font-semibold text-white' : 'hover:bg-white/5 hover:text-white' }}">
                                <x-ikon :nama="$ikon" class="size-[18px] {{ $aktif ? 'text-brand-200' : 'text-slate-400' }}" />
                                {{ $label }}
                                @if ($kunciLencana)
                                    <span data-lencana="{{ $kunciLencana }}" @unless ($lencana[$kunciLencana]) hidden @endunless
                                        class="ml-auto min-w-5 rounded-full px-1.5 py-0.5 text-center text-xs font-semibold {{ $kunciLencana === 'insiden' ? 'bg-red-500 text-white' : 'bg-amber-400 text-amber-950' }}">{{ $lencana[$kunciLencana] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="border-t border-white/10 px-5 py-4 text-xs text-slate-400">
        <p class="font-medium text-slate-300">Lingkungan lab</p>
        <p>Data pada dashboard ini adalah hasil simulasi.</p>
    </div>
</aside>

<div class="flex min-h-full flex-col lg:pl-64">
    <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6 lg:px-8">
        <button type="button" class="-ml-1 rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden" data-buka-sidebar aria-label="Buka menu">
            <x-ikon nama="menu" />
        </button>

        <div class="min-w-0">
            <nav class="hidden text-xs text-slate-500 sm:block" aria-label="Breadcrumb">
                <a href="{{ route('benteng.dashboard') }}" class="hover:text-slate-700">Beranda</a>
                @hasSection('remah')
                    <span class="mx-1 text-slate-300">/</span>@yield('remah')
                @endif
            </nav>
            <h1 class="truncate text-lg font-semibold text-slate-900">@yield('judul')</h1>
        </div>

        <div class="ml-auto flex items-center gap-3 sm:gap-5">
            <span class="hidden items-center gap-2 text-xs text-slate-500 md:flex" id="status-segar" title="Halaman diperbarui otomatis tiap 5 detik">
                <span class="size-2 rounded-full bg-emerald-500" data-titik-segar></span>
                <span data-teks-segar>Diperbarui {{ $lencana['waktu'] }}</span>
            </span>

            <details class="relative" data-menu-akun>
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-md p-1 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                    <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">{{ $inisial }}</span>
                    <span class="hidden text-left leading-tight sm:block">
                        <span class="block text-sm font-semibold text-slate-800">{{ $user->name }}</span>
                        <span class="block text-xs text-slate-500">{{ User::PERAN[$user->peran] ?? $user->peran }}</span>
                    </span>
                    <x-ikon nama="bawah" class="hidden size-4 text-slate-400 sm:block" />
                </summary>
                <div class="absolute right-0 mt-2 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                    <div class="border-b border-slate-100 px-4 py-2.5 text-sm">
                        <p class="font-semibold text-slate-800">{{ $user->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                            <x-ikon nama="keluar" class="size-4 text-slate-400" />Keluar
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </header>

    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
        @yield('konten')
    </main>

    <footer class="px-4 pb-6 text-xs text-slate-500 sm:px-6 lg:px-8">
        BENTENG {{ date('Y') }}. Sistem pertahanan jaringan satuan, berjalan di server milik satuan sendiri.
    </footer>
</div>

{{-- Modal konfirmasi tindakan. Isinya diisi resources/js/app.js dari atribut data-* pada form. --}}
<dialog id="modal-konfirmasi" class="m-auto w-[calc(100%-2rem)] max-w-lg overflow-hidden rounded-xl p-0 text-slate-800 shadow-2xl backdrop:bg-slate-900/50" aria-labelledby="modal-judul">
    <div class="flex gap-4 p-6">
        <span data-ikon-varian="bahaya" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600"><x-ikon nama="kunci" /></span>
        <span data-ikon-varian="utama" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><x-ikon nama="centang" /></span>
        <span data-ikon-varian="info" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-700"><x-ikon nama="buka-kunci" /></span>
        <div class="min-w-0 flex-1">
            <h2 id="modal-judul" class="text-base font-semibold text-slate-900" data-modal-judul></h2>
            <p class="mt-2 text-sm leading-6 text-slate-600" data-modal-pesan></p>
            <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 rounded-lg bg-slate-50 p-3 text-sm" data-modal-rincian></dl>
            <p class="mt-3 text-xs text-slate-500">Tindakan ini tercatat di jejak audit atas nama {{ $user->name }}.</p>
        </div>
    </div>
    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
        <x-tombol data-modal-batal>Batal</x-tombol>
        <x-tombol varian="bahaya" data-modal-ya="bahaya"></x-tombol>
        <x-tombol varian="utama" data-modal-ya="utama"></x-tombol>
        <x-tombol varian="info" data-modal-ya="info"></x-tombol>
    </div>
</dialog>

{{-- Notifikasi singkat setelah tindakan --}}
<div id="wadah-toast" class="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col items-end gap-3 sm:left-auto sm:w-96" aria-live="polite">
    @foreach (['pesan' => ['Berhasil', 'centang-bulat', 'text-emerald-600'], 'galat' => ['Tidak dapat diproses', 'info', 'text-red-600']] as $kunci => [$judulToast, $ikonToast, $warnaToast])
        @if (session($kunci))
            <div class="pointer-events-auto flex w-full items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-lg" data-toast role="{{ $kunci === 'galat' ? 'alert' : 'status' }}">
                <x-ikon :nama="$ikonToast" class="mt-0.5 {{ $warnaToast }}" />
                <div class="flex-1 text-sm">
                    <p class="font-semibold text-slate-900">{{ $judulToast }}</p>
                    <p class="mt-0.5 text-slate-600">{{ session($kunci) }}</p>
                </div>
                <button type="button" class="rounded p-0.5 text-slate-400 hover:text-slate-600" data-tutup-toast aria-label="Tutup"><x-ikon nama="silang" class="size-4" /></button>
            </div>
        @endif
    @endforeach
</div>

<div id="tip" role="tooltip" class="pointer-events-none fixed z-50 hidden rounded-md bg-slate-900 px-3 py-2 text-xs whitespace-nowrap text-white shadow-lg">
    <strong class="block text-sm font-semibold" data-tip-nilai></strong><span class="text-slate-300" data-tip-label></span>
</div>

</body>
</html>
