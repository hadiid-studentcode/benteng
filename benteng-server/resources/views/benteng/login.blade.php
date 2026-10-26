<!doctype html>
<html lang="id" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - BENTENG</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<div class="flex min-h-full">
    <div class="relative hidden w-[44%] flex-col justify-between bg-sidebar p-12 text-slate-300 lg:flex">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-lg bg-brand-600 text-white">
                <svg class="size-6" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M16 3 L27 7 V15 C27 22 22 27 16 29 C10 27 5 22 5 15 V7 Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M11 16 L15 20 L21 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span class="text-lg font-bold tracking-wider text-white">BENTENG</span>
        </div>
        <div class="max-w-md">
            <h1 class="text-3xl leading-tight font-bold text-white">Pertahanan jaringan untuk satuan yang tidak punya tim siber sendiri.</h1>
            <p class="mt-4 leading-7 text-slate-400">Sensor di tepi jaringan mengenali perangkat yang terinfeksi, lalu analis memutuskan tindakan dari satu dashboard. Semua data tetap di server satuan.</p>
        </div>
        <p class="text-xs text-slate-500">Kartika Cyber Hackathon 2026, kategori Penangkalan.</p>
    </div>

    <div class="flex flex-1 items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="flex items-center gap-3 lg:hidden">
                <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-white">
                    <svg class="size-5" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M16 3 L27 7 V15 C27 22 22 27 16 29 C10 27 5 22 5 15 V7 Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M11 16 L15 20 L21 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="font-bold tracking-wider text-slate-900">BENTENG</span>
            </div>

            <h2 class="mt-8 text-2xl font-bold text-slate-900 lg:mt-0">Masuk ke dashboard</h2>
            <p class="mt-2 text-sm text-slate-500">Gunakan akun yang diberikan admin satuan.</p>

            @if ($errors->any())
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                        class="mt-1.5 block w-full rounded-md border-0 px-3 py-2.5 text-slate-900 shadow-xs ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-brand-600">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Kata sandi</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                        class="mt-1.5 block w-full rounded-md border-0 px-3 py-2.5 text-slate-900 shadow-xs ring-1 ring-slate-300 ring-inset focus:ring-2 focus:ring-brand-600">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="ingat" value="1" class="size-4 rounded border-slate-300 accent-brand-600">
                    Ingat saya di perangkat ini
                </label>
                <x-tombol type="submit" varian="utama" class="w-full py-2.5">Masuk</x-tombol>
            </form>

            <p class="mt-8 text-xs leading-5 text-slate-500">Setiap tindakan di dashboard tercatat atas nama akun Anda. Belum punya akun? Hubungi admin satuan.</p>
        </div>
    </div>
</div>
</body>
</html>
