<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - BENTENG</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/ikon.png') }}">
    @include('benteng.partials.tema')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth">
    <section class="auth__panel">
        <x-tombol-tema class="auth__toggle" />
        <div class="auth__form">
            <div>
                <img src="{{ asset('assets/images/logo-gelap.png') }}" alt="BENTENG" class="mb-6 h-9 w-auto lg:hidden dark:hidden">
                <img src="{{ asset('assets/images/logo-terang.png') }}" alt="BENTENG" class="mb-6 hidden h-9 w-auto dark:block lg:dark:hidden">
                <h1 class="text-2xl">Masuk ke dashboard</h1>
                <p class="mt-1 text-muted-foreground">Gunakan akun yang diberikan admin satuan.</p>
            </div>

            @if ($errors->any())
                <div class="alert alert--danger" role="alert">
                    <x-ikon nama="info" />
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
                @csrf
                <div class="field">
                    <label for="email" class="field__label">Email</label>
                    <input id="email" type="email" name="email" class="input input--lg" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="field">
                    <label for="password" class="field__label">Kata sandi</label>
                    <input id="password" type="password" name="password" class="input input--lg" required autocomplete="current-password">
                </div>
                <div class="field__item">
                    <input class="checkbox" type="checkbox" id="ingat" name="ingat" value="1">
                    <label class="field__label" for="ingat">Ingat saya di perangkat ini</label>
                </div>
                <button type="submit" class="button button--primary button--block button--lg">Masuk</button>
            </form>

            @if (config('benteng.mode_demo'))
                <div class="rounded-lg border border-dashed border-border p-4" data-akun-demo>
                    <p class="text-sm font-semibold">Akun demo untuk juri</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Klik "Pakai akun ini", lalu tekan Masuk.</p>
                    <ul class="mt-3 flex flex-col gap-2">
                        @foreach (\App\Console\Commands\SiapkanDemoCommand::AKUN as $emailDemo => [$namaDemo, $peranDemo, $sandiDemo])
                            <li class="flex items-center justify-between gap-3 rounded-md bg-muted/50 px-3 py-2">
                                <div class="min-w-0 text-sm">
                                    <p class="font-medium">{{ ucfirst($peranDemo) }} <span class="text-xs font-normal text-muted-foreground">· {{ $peranDemo === 'analis' ? 'bisa bertindak' : 'hanya melihat' }}</span></p>
                                    <p class="font-mono text-xs break-all text-muted-foreground">{{ $emailDemo }} / {{ $sandiDemo }}</p>
                                </div>
                                <button type="button" class="button button--outline button--sm shrink-0" data-isi-email="{{ $emailDemo }}" data-isi-sandi="{{ $sandiDemo }}">Pakai akun ini</button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="text-center text-sm text-muted-foreground">Setiap tindakan di dashboard tercatat atas nama akun Anda.</p>
        </div>
    </section>

    <aside class="auth__aside">
        <a href="{{ route('login') }}" class="auth__brand">
            <img src="{{ asset('assets/images/logo-terang.png') }}" alt="BENTENG" class="h-11 w-auto">
        </a>
        <div class="auth__pitch">
            <h2 class="auth__pitch-title">Pertahanan jaringan untuk <span>satuan tanpa tim siber.</span></h2>
            <p class="auth__pitch-lede">Sensor di tepi jaringan mengenali perangkat yang terinfeksi, lalu analis memutuskan tindakan dari satu dashboard. Semua data tetap di server satuan.</p>

            <ul class="auth__points">
                <li class="auth__point">
                    <span class="auth__point-icon"><x-ikon nama="aktivitas" class="size-4" /></span>
                    <div><strong>Deteksi otomatis</strong><span>Beaconing, domain acak, dan perangkat asing diperiksa tiap menit.</span></div>
                </li>
                <li class="auth__point">
                    <span class="auth__point-icon"><x-ikon nama="pengguna" class="size-4" /></span>
                    <div><strong>Analis yang memutuskan</strong><span>Setiap insiden datang dengan bukti dan langkah penanganan.</span></div>
                </li>
                <li class="auth__point">
                    <span class="auth__point-icon"><x-ikon nama="kunci" class="size-4" /></span>
                    <div><strong>Respons dalam hitungan detik</strong><span>Karantina perangkat atau blokir domain langsung dari dashboard.</span></div>
                </li>
            </ul>
        </div>
    </aside>
</main>
</body>
</html>
