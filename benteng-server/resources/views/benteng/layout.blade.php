@use('App\Models\User')
@php
    $user = auth()->user();
    $inisial = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('');
    $menu = [
        'Pemantauan' => [
            ['benteng.dashboard', 'Dashboard', 'dashboard', null],
            ['benteng.insiden', 'Insiden', 'insiden', 'insiden'],
            ['benteng.perangkat', 'Perangkat', 'perangkat', 'asing'],
            ['benteng.blokir', 'Domain diblokir', 'larangan', null],
            ['benteng.topologi', 'Topologi jaringan', 'topologi', null],
        ],
        'Sistem' => [
            ['benteng.sensor', 'Sensor', 'sensor', null],
            ['benteng.audit', 'Jejak audit', 'audit', null],
        ],
    ];
@endphp
<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul') - BENTENG</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/ikon.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell" data-stisla-app-shell data-stisla-app-shell-auto-collapse="true">
    <aside class="sidebar sidebar--lg sidebar--app" data-stisla-sidebar>
        <header class="sidebar__header">
            <a class="sidebar__brand" href="{{ route('benteng.dashboard') }}">
                <img src="{{ asset('assets/images/perisai-gelap.png') }}" alt="" class="!h-7 !w-auto">
                <span><img src="{{ asset('assets/images/teks-gelap.png') }}" alt="BENTENG" class="!h-[15px] !w-auto"></span>
            </a>
        </header>

        <div class="sidebar__content">
            <nav class="sidebar__menu" aria-label="Menu utama">
                @foreach ($menu as $grup => $item)
                    <div class="sidebar__group">
                        <span class="sidebar__group-title">{{ $grup }}</span>
                        <ul class="sidebar__list">
                            @foreach ($item as [$rute, $label, $ikon, $kunciLencana])
                                <li class="sidebar__item">
                                    <a class="sidebar__button" href="{{ route($rute) }}" @if (request()->routeIs($rute.'*')) aria-current="page" @endif>
                                        <x-ikon :nama="$ikon" /><span>{{ $label }}</span>
                                    </a>
                                    @if ($kunciLencana)
                                        <span class="sidebar__item-action">
                                            <span data-lencana="{{ $kunciLencana }}" @unless ($lencana[$kunciLencana]) hidden @endunless class="badge {{ $kunciLencana === 'insiden' ? 'badge--danger' : 'badge--warning' }}">{{ $lencana[$kunciLencana] }}</span>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </div>

        <footer class="sidebar__footer">
            <p class="copyright px-4 text-xs text-muted-foreground">Lingkungan lab: data pada dashboard ini adalah hasil simulasi.</p>
        </footer>
    </aside>

    <main class="app-shell__main">
        <header class="navbar">
            <button type="button" class="button button--ghost button--neutral button--icon-only button--flush-start" data-stisla-app-shell-toggle="auto" aria-label="Buka atau tutup menu">
                <x-ikon nama="menu" />
            </button>

            <div class="ms-auto flex items-center gap-3">
                @if (config('benteng.mode_demo'))
                    <span class="badge badge--soft badge--warning" title="Data simulasi dan sensor tiruan, tanpa perangkat sungguhan">Mode demo</span>
                @endif
                <span class="hidden items-center gap-2 text-xs text-muted-foreground md:flex" title="Halaman diperbarui otomatis tiap 5 detik">
                    <span class="indicator indicator--success" data-titik-segar></span>
                    <span data-teks-segar>Diperbarui {{ $lencana['waktu'] }}</span>
                </span>

                <div class="menu">
                    <button type="button" class="button button--ghost button--neutral flex items-center gap-2" data-stisla-menu-trigger="menu-akun" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-akun">
                        <span class="hidden font-medium sm:inline">{{ $user->name }}</span>
                        <span class="avatar avatar--sm avatar--circle"><span class="avatar__fallback">{{ $inisial }}</span></span>
                        <x-ikon nama="bawah" class="size-4" />
                    </button>
                    <div class="menu__popup w-56" id="menu-akun" data-stisla-menu role="menu" data-state="closed">
                        <div class="menu__group" role="group" aria-labelledby="menu-akun-kepala">
                            <h3 class="menu__group-label" id="menu-akun-kepala">{{ User::PERAN[$user->peran] ?? $user->peran }}: {{ $user->email }}</h3>
                        </div>
                        <hr class="menu__separator" role="separator">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="menu__item w-full" role="menuitem"><x-ikon nama="keluar" class="size-4" />Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <div class="page content">
            <div class="content__container">
                <header class="page__header">
                    <div class="page__headline">
                        <nav aria-label="Breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb__item"><a href="{{ route('benteng.dashboard') }}">Beranda</a></li>
                                @yield('remah')
                            </ol>
                        </nav>
                        <h1 class="page__title">@yield('judul')</h1>
                        @hasSection('keterangan')
                            <p class="page__description">@yield('keterangan')</p>
                        @endif
                    </div>
                    @hasSection('aksi-halaman')
                        <div class="page__action w-full md:w-auto">@yield('aksi-halaman')</div>
                    @endif
                </header>

                <div class="page__body">
                    @yield('konten')
                </div>

                <p class="text-xs text-muted-foreground">BENTENG {{ date('Y') }}. Sistem pertahanan jaringan satuan, berjalan di server milik satuan sendiri.</p>
            </div>
        </div>
    </main>
</div>

{{-- Modal konfirmasi tindakan. Isinya diisi resources/js/app.js dari atribut data-* pada form. --}}
<button type="button" id="pembuka-modal" data-stisla-dialog-trigger="modal-konfirmasi" hidden></button>
<div class="dialog" id="modal-konfirmasi" data-stisla-dialog data-state="closed" role="alertdialog" aria-modal="true" aria-labelledby="modal-judul" aria-describedby="modal-pesan" aria-hidden="true" tabindex="-1">
    <div class="dialog__backdrop" data-stisla-dialog-dismiss></div>
    <div class="dialog__panel">
        <div class="dialog__content">
            <button type="button" class="dialog__close" data-stisla-dialog-dismiss aria-label="Tutup"><x-ikon nama="silang" class="size-4" /></button>
            <div class="dialog__body pt-6 text-center">
                <span data-ikon-varian="bahaya" class="icon-box icon-box--danger icon-box--circle icon-box--lg mb-3"><x-ikon nama="kunci" /></span>
                <span data-ikon-varian="utama" class="icon-box icon-box--success icon-box--circle icon-box--lg mb-3"><x-ikon nama="centang" /></span>
                <span data-ikon-varian="info" class="icon-box icon-box--primary icon-box--circle icon-box--lg mb-3"><x-ikon nama="buka-kunci" /></span>
                <h3 class="dialog__title mb-1" id="modal-judul" data-modal-judul></h3>
                <p class="text-muted-foreground" id="modal-pesan" data-modal-pesan></p>
                <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 rounded-lg bg-surface-2 p-3 text-start text-sm" data-modal-rincian></dl>
                <p class="mt-3 text-xs text-muted-foreground">Tindakan ini tercatat di jejak audit atas nama {{ $user->name }}.</p>
            </div>
            <div class="dialog__footer justify-center">
                <button type="button" class="button button--ghost button--neutral" data-stisla-dialog-dismiss>Batal</button>
                <button type="button" class="button button--danger" data-modal-ya="bahaya"></button>
                <button type="button" class="button button--success" data-modal-ya="utama"></button>
                <button type="button" class="button button--primary" data-modal-ya="info"></button>
            </div>
        </div>
    </div>
</div>

{{-- Pesan setelah tindakan, ditampilkan resources/js/app.js sebagai toast Stisla. --}}
@if (session('pesan'))
    <span hidden data-flash data-varian="success" data-judul="Berhasil" data-pesan="{{ session('pesan') }}"></span>
@endif
@if (session('galat'))
    <span hidden data-flash data-varian="danger" data-judul="Tidak dapat diproses" data-pesan="{{ session('galat') }}"></span>
@endif

<div id="tip" role="tooltip" hidden class="pointer-events-none fixed z-[1100] rounded-md bg-overlay px-3 py-2 text-xs whitespace-nowrap text-overlay-foreground shadow-lg">
    <strong class="block text-sm font-semibold" data-tip-nilai></strong><span data-tip-label></span>
</div>
</body>
</html>
