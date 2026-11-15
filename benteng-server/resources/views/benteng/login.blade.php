<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - BENTENG</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/images/ikon.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth">
    <section class="auth__panel">
        <div class="auth__form">
            <div>
                <img src="{{ asset('assets/images/logo-gelap.png') }}" alt="BENTENG" class="mb-6 h-9 w-auto lg:hidden">
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
