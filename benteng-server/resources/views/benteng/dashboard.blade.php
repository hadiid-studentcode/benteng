<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{-- Muat ulang otomatis tiap 8 detik supaya terasa real-time saat demo. --}}
<meta http-equiv="refresh" content="8">
<title>BENTENG - Dashboard Analis</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
  :root{
    --bg:#0E1311; --panel:#171D1A; --garis:#27302B; --garis2:#222A26;
    --teks:#E6EBE7; --redup:#9AA59F; --terang:#C9D2CC;
    --hijau:#A9CF7A; --kritis:#F07A6A; --peringatan:#E5B454; --karantina:#7FB1E6;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--teks);font-family:'IBM Plex Sans',system-ui,sans-serif}
  .mono{font-family:'IBM Plex Mono',monospace}
  .wrap{max-width:1240px;margin:0 auto;padding:20px 16px 48px;display:flex;flex-direction:column;gap:20px}
  header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid var(--garis);padding-bottom:16px}
  .merek{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .judulmerek{font-size:18px;font-weight:600;letter-spacing:.08em}
  .sub{font-size:12px;color:var(--redup)}
  .chip{font-size:11px;padding:3px 8px;border-radius:4px;background:var(--garis);color:var(--terang)}
  .metrik{display:flex;flex-wrap:wrap;gap:12px}
  .kartu{flex:1 1 200px;background:var(--panel);border:1px solid var(--garis);border-radius:8px;padding:14px 16px;display:flex;flex-direction:column;gap:6px}
  .kartu .angka{font-size:28px;font-weight:600;font-family:'IBM Plex Mono',monospace}
  .kartu.bahaya .angka{color:var(--kritis)}
  h2.seksi{margin:0 0 4px;font-size:13px;font-weight:600;letter-spacing:.06em;color:var(--terang)}
  .panel{background:var(--panel);border:1px solid var(--garis);border-radius:8px;padding:16px}
  .pill{display:inline-block;font-size:12px;font-weight:500;padding:3px 10px;border-radius:999px;white-space:nowrap}
  .pill.kritis{background:#4A1F1C;color:#FFBDB3}
  .pill.tinggi{background:#4A3A12;color:#F2D184}
  .pill.aman{background:#1F3A2A;color:#A8E0B0}
  .pill.karantina{background:#1E3147;color:#B5D4F3}
  .pill.info{background:var(--garis);color:var(--terang)}
  .insiden{display:flex;flex-direction:column;gap:6px;padding:14px 0;border-top:1px solid var(--garis2)}
  .insiden:first-child{border-top:0}
  .insiden .baris{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
  .insiden .judul{font-size:16px;font-weight:600}
  .tag{font-size:12px;padding:3px 8px;border-radius:4px;border:1px solid #3A453F;color:var(--terang)}
  .bukti{font-size:13px;color:var(--terang);line-height:1.5}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th{text-align:left;color:var(--redup);font-weight:500;padding:10px 8px;border-bottom:1px solid var(--garis2)}
  td{padding:10px 8px;border-top:1px solid var(--garis2)}
  tr.bahaya td{background:rgba(240,122,106,.08)}
  .kosong{border:1px dashed #3A453F;border-radius:8px;padding:24px 16px;color:var(--redup);font-size:14px}
  .aksi{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:4px}
  .btn{font-family:inherit;min-height:40px;padding:0 16px;border-radius:6px;font-size:14px;cursor:pointer;border:1px solid transparent}
  .btn-karantina{background:var(--hijau);color:#10170F;font-weight:600;border:0}
  .btn-fp{background:transparent;color:var(--teks);border:1px solid #3A453F}
  .btn-lepas{background:#18222D;color:#CFE2F5;border:1px solid #3D5A7A}
  .btn:focus-visible{outline:2px solid var(--hijau);outline-offset:2px}
  .catatan{font-size:12px;color:var(--redup)}
  form{display:inline}
  .tabelbungkus{overflow-x:auto}
  .baris2{display:flex;flex-wrap:wrap;gap:20px}
  .baris2>.panel{flex:1 1 420px;min-width:0}
  footer{color:var(--redup);font-size:12px;border-top:1px solid var(--garis);padding-top:12px}
</style>
</head>
<body>
<div class="wrap">

  <header>
    <div class="merek">
      <svg width="30" height="30" viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M16 3 L27 7 V15 C27 22 22 27 16 29 C10 27 5 22 5 15 V7 Z" stroke="#A9CF7A" stroke-width="2" stroke-linejoin="round"/><path d="M11 16 L15 20 L21 12" stroke="#A9CF7A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <div>
        <div class="judulmerek">BENTENG</div>
        <div class="sub">Sensor siber tepi untuk jaringan satuan</div>
      </div>
      <span class="chip mono">LAB</span>
    </div>
    <div class="sub mono">Diperbarui otomatis · {{ now()->timezone('Asia/Jakarta')->format('d M Y H:i:s') }} WIB</div>
  </header>

  <section class="metrik" aria-label="Ringkasan">
    <div class="kartu"><span class="sub">Sensor aktif</span><span class="angka">{{ $metrik['sensor'] }}</span><span class="sub">mengirim data</span></div>
    <div class="kartu"><span class="sub">Perangkat terpantau</span><span class="angka">{{ $metrik['perangkat'] }}</span><span class="sub">terlihat oleh sensor</span></div>
    <div class="kartu"><span class="sub">Query DNS (24 jam)</span><span class="angka">{{ number_format($metrik['query24'], 0, ',', '.') }}</span><span class="sub">tercatat & dianalisis</span></div>
    <div class="kartu {{ $metrik['insidenAktif'] ? 'bahaya' : '' }}"><span class="sub">Insiden aktif</span><span class="angka">{{ $metrik['insidenAktif'] }}</span><span class="sub">menunggu tindakan</span></div>
  </section>

  <section class="panel" aria-label="Insiden">
    <h2 class="seksi">INSIDEN</h2>
    @forelse ($incidents as $i)
      <div class="insiden">
        <div class="baris">
          <span class="pill {{ $i->tingkat }}">{{ ucfirst($i->tingkat) }} · {{ $i->skor }}</span>
          <span class="mono sub">{{ $i->sensor }} · {{ $i->client_ip }}</span>
          @if ($i->status !== 'baru')
            <span class="pill {{ $i->status === 'dikarantina' ? 'karantina' : 'info' }}">{{ str_replace('_',' ',$i->status) }}</span>
          @endif
        </div>
        <div class="judul">{{ $i->judul }}</div>
        <div class="baris">
          @if ($i->attck)<span class="tag mono">ATT&amp;CK {{ $i->attck }}</span>@endif
          <span class="tag mono">{{ strtoupper($i->jenis) }}</span>
        </div>
        <div class="bukti">{{ $i->bukti }}</div>
        <div class="aksi">
          @if ($i->status === 'baru')
            <form method="POST" action="{{ url('/dashboard/incidents/'.$i->id.'/karantina') }}">
              @csrf
              <button type="submit" class="btn btn-karantina">Karantina perangkat</button>
            </form>
            <form method="POST" action="{{ url('/dashboard/incidents/'.$i->id.'/false-positive') }}">
              @csrf
              <button type="submit" class="btn btn-fp">Tandai false positive</button>
            </form>
            <span class="catatan">Keputusan akan tercatat di audit log</span>
          @elseif ($i->status === 'dikarantina')
            <span class="catatan">Perangkat {{ $i->client_ip }} sedang dikarantina oleh {{ $i->sensor }}.</span>
            <form method="POST" action="{{ url('/dashboard/incidents/'.$i->id.'/lepas') }}">
              @csrf
              <button type="submit" class="btn btn-lepas">Lepas karantina</button>
            </form>
          @else
            <span class="catatan">Ditandai false positive. Tujuan masuk daftar izin satuan.</span>
          @endif
        </div>
      </div>
    @empty
      <div class="kosong">Belum ada insiden. Jalankan simulator di VM korban, lalu <span class="mono">php artisan benteng:deteksi</span>.</div>
    @endforelse
  </section>

  <section class="panel" aria-label="Perangkat terpantau">
    <h2 class="seksi">PERANGKAT TERPANTAU</h2>
    <div class="tabelbungkus">
      <table>
        <thead><tr><th>Perangkat (IP)</th><th>Sensor</th><th>Jumlah query</th><th>Pertama terlihat</th><th>Terakhir terlihat</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($perangkat as $p)
          <tr class="{{ in_array($p->client_ip, $ipBermasalah) ? 'bahaya' : '' }}">
            <td class="mono">{{ $p->client_ip }}</td>
            <td>{{ $p->sensor }}</td>
            <td class="mono">{{ number_format($p->jumlah, 0, ',', '.') }}</td>
            <td class="mono sub">{{ \Illuminate\Support\Str::of($p->awal)->substr(0,16) }}</td>
            <td class="mono sub">{{ \Illuminate\Support\Str::of($p->akhir)->substr(0,16) }}</td>
            <td>
              @if (in_array($p->client_ip, $ipBermasalah))
                <span class="pill kritis">Insiden</span>
              @else
                <span class="pill aman">Normal</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="sub">Belum ada data. Pastikan sensor mengirim ke /api/ingest/dns.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <footer>
    BENTENG · data lab simulasi · deteksi berbasis perilaku (beaconing &amp; DGA), dipetakan ke MITRE ATT&amp;CK. Keputusan respons tetap di tangan analis.
  </footer>

</div>
</body>
</html>