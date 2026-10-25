<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BENTENG - Masuk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
  :root{
    --bg:#0E1311; --panel:#171D1A; --garis:#27302B; --garis2:#222A26;
    --teks:#E6EBE7; --redup:#9AA59F; --terang:#C9D2CC;
    --hijau:#A9CF7A; --kritis:#F07A6A;
  }
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;background:var(--bg);color:var(--teks);font-family:'IBM Plex Sans',system-ui,sans-serif}
  .kotak{width:100%;max-width:380px;background:var(--panel);border:1px solid var(--garis);border-radius:10px;padding:28px 24px;display:flex;flex-direction:column;gap:18px}
  .merek{display:flex;align-items:center;gap:12px}
  .judulmerek{font-size:18px;font-weight:600;letter-spacing:.08em}
  .sub{font-size:12px;color:var(--redup)}
  .mono{font-family:'IBM Plex Mono',monospace}
  form{display:flex;flex-direction:column;gap:14px}
  label{display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--terang)}
  input[type=email],input[type=password]{font:inherit;font-size:15px;color:var(--teks);background:#0F1412;border:1px solid #3A453F;border-radius:6px;padding:10px 12px;min-height:42px}
  input:focus-visible{outline:2px solid var(--hijau);outline-offset:1px;border-color:transparent}
  .ingat{flex-direction:row;align-items:center;gap:8px;color:var(--redup)}
  .galat{font-size:13px;color:#FFCFC7;background:#2A1715;border:1px solid #6A2C26;border-radius:6px;padding:10px 12px}
  button{font:inherit;font-size:15px;font-weight:600;min-height:44px;border:0;border-radius:6px;background:var(--hijau);color:#10170F;cursor:pointer}
  button:focus-visible{outline:2px solid var(--hijau);outline-offset:2px}
</style>
</head>
<body>
  <main class="kotak">
    <div class="merek">
      <svg width="30" height="30" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M16 3 L27 7 V15 C27 22 22 27 16 29 C10 27 5 22 5 15 V7 Z" stroke="#A9CF7A" stroke-width="2" stroke-linejoin="round"/><path d="M11 16 L15 20 L21 12" stroke="#A9CF7A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <div>
        <div class="judulmerek">BENTENG</div>
        <div class="sub">Masuk ke dashboard analis</div>
      </div>
    </div>

    @if ($errors->any())
      <div class="galat" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
      @csrf
      <label>Email
        <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
      </label>
      <label>Kata sandi
        <input type="password" name="password" required autocomplete="current-password">
      </label>
      <label class="ingat"><input type="checkbox" name="ingat" value="1"> Ingat saya di perangkat ini</label>
      <button type="submit">Masuk</button>
    </form>

    <div class="sub">Akun dibuat oleh admin satuan dengan <span class="mono">php artisan benteng:pengguna</span>. Setiap tindakan tercatat atas nama akun Anda.</div>
  </main>
</body>
</html>
