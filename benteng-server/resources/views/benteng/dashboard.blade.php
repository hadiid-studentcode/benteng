@use('App\Models\User')
@use('Illuminate\Support\Carbon')
@use('Illuminate\Support\Str')
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BENTENG - Dashboard Analis</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
  :root{
    --bg:#0E1311; --panel:#171D1A; --panel2:#1B2320; --garis:#27302B; --garis2:#222A26;
    --teks:#E6EBE7; --redup:#9AA59F; --terang:#C9D2CC;
    --hijau:#A9CF7A; --kritis:#F07A6A; --peringatan:#E5B454; --karantina:#7FB1E6;
    --seri:#76A055;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--teks);font-family:'IBM Plex Sans',system-ui,sans-serif}
  a{color:inherit}
  .mono{font-family:'IBM Plex Mono',monospace}
  .wrap{max-width:1240px;margin:0 auto;padding:20px 16px 48px;display:flex;flex-direction:column;gap:20px}
  header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid var(--garis);padding-bottom:16px}
  .merek{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
  .judulmerek{font-size:18px;font-weight:600;letter-spacing:.08em}
  .sub{font-size:12px;color:var(--redup)}
  .chip{font-size:11px;padding:3px 8px;border-radius:4px;background:var(--garis);color:var(--terang)}
  .akun{display:flex;flex-wrap:wrap;align-items:center;gap:14px}
  .btn-teks{font:inherit;font-size:13px;background:none;border:1px solid #3A453F;color:var(--terang);border-radius:6px;padding:6px 12px;cursor:pointer}
  .btn-teks:focus-visible{outline:2px solid var(--hijau);outline-offset:2px}
  #isi{display:flex;flex-direction:column;gap:20px}

  /* Indikator penyegaran langsung */
  .langsung{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--redup)}
  .langsung .led{background:var(--hijau);animation:denyut 2s ease-in-out infinite}
  .langsung.putus{color:var(--kritis)}
  .langsung.putus .led{background:var(--kritis);animation:none}
  @keyframes denyut{50%{opacity:.35}}

  .led{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--redup);flex:none}

  /* Pesan setelah aksi */
  .flash{border-radius:8px;padding:12px 16px;font-size:14px;border:1px solid}
  .flash.ok{background:#16261C;border-color:#2F5A3B;color:#CDEBD3}
  .flash.galat{background:#2A1715;border-color:#6A2C26;color:#FFCFC7}

  /* Status sensor & detektor */
  .sensorbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}
  .sensorlist{display:flex;flex-wrap:wrap;gap:10px}
  .sensor{display:flex;flex-wrap:wrap;align-items:center;gap:8px;background:var(--panel);border:1px solid var(--garis);border-radius:8px;padding:8px 12px;font-size:13px}
  .sensor.online .led{background:var(--hijau)}
  .sensor.offline{border-color:#6A2C26}
  .sensor.offline .led{background:var(--kritis)}
  .sensor.waspada{border-color:#6B5520}
  .sensor.waspada .led{background:var(--peringatan)}

  /* Metrik */
  .metrik{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}
  .kartu{background:var(--panel);border:1px solid var(--garis);border-radius:8px;padding:14px 16px;display:flex;flex-direction:column;gap:6px}
  .kartu .angka{font-size:28px;font-weight:600;white-space:nowrap}
  .kartu .angka small{font-size:14px;font-weight:500;color:var(--redup)}
  .kartu.bahaya{border-color:#6A2C26}
  .kartu.bahaya .angka{color:var(--kritis)}
  .kartu.karantina .angka{color:var(--karantina)}
  .kartu.waspada{border-color:#6B5520}

  h2.seksi{margin:0;font-size:13px;font-weight:600;letter-spacing:.06em;color:var(--terang)}
  .kepala{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:8px;margin-bottom:12px}
  .panel{background:var(--panel);border:1px solid var(--garis);border-radius:8px;padding:16px;min-width:0}

  /* Diagram topologi */
  .topologi-bungkus{overflow-x:auto}
  svg.topologi{display:block;width:100%;min-width:760px;height:auto}
  .topologi text{font-family:'IBM Plex Sans',system-ui,sans-serif}
  .topologi .mono{font-family:'IBM Plex Mono',monospace}
  .t-judul{font-size:14px;font-weight:600;fill:var(--teks)}
  .t-isi{font-size:13px;fill:var(--teks)}
  .t-kecil{font-size:11px;fill:var(--redup)}
  .halo{paint-order:stroke;stroke:var(--panel);stroke-width:5px;stroke-linejoin:round}
  .kotak{fill:var(--panel2);stroke:#33403A;stroke-width:1}
  .kotak.st-insiden,.kotak.putus{stroke:var(--kritis)}
  .kotak.st-dikarantina{stroke:var(--karantina)}
  .kotak.st-asing{stroke:var(--peringatan)}
  .kotak-internet{fill:#121815;stroke:#33403A;stroke-dasharray:3 3}
  .tautan{fill:none;stroke:#3F4C45;stroke-width:2}
  .tautan.aliran{stroke:var(--hijau);stroke-dasharray:6 8;animation:alir 1s linear infinite}
  .tautan.putus{stroke:var(--kritis);stroke-dasharray:4 6}
  @keyframes alir{to{stroke-dashoffset:-14}}
  .cabang{fill:none;stroke-width:2;stroke:#5E7A66}
  .cabang.st-insiden{stroke:var(--kritis)}
  .cabang.st-dikarantina{stroke:var(--karantina);stroke-dasharray:5 5}
  .cabang.st-asing{stroke:var(--peringatan);stroke-dasharray:2 4}
  .titik.st-normal{fill:var(--seri)}
  .titik.st-insiden{fill:var(--kritis)}
  .titik.st-dikarantina{fill:var(--karantina)}
  .titik.st-asing{fill:var(--peringatan)}
  .titik.online{fill:var(--hijau)}
  .titik.offline{fill:var(--kritis)}
  .lencana.st-normal{fill:#1F3A2A}
  .lencana.st-insiden{fill:#4A1F1C}
  .lencana.st-dikarantina{fill:#1E3147}
  .lencana.st-asing{fill:#4A3A12}
  .t-lencana{font-size:11px;font-weight:500}
  .t-lencana.st-normal{fill:#A8E0B0}
  .t-lencana.st-insiden{fill:#FFBDB3}
  .t-lencana.st-dikarantina{fill:#B5D4F3}
  .t-lencana.st-asing{fill:#F2D184}
  .tanda-putus circle{fill:var(--panel);stroke:var(--karantina);stroke-width:2}
  .tanda-putus path{stroke:var(--karantina);stroke-width:2;stroke-linecap:round}
  .ikon{fill:none;stroke:var(--hijau);stroke-width:2;stroke-linejoin:round;stroke-linecap:round}
  .node{cursor:default}
  .node:hover .kotak{fill:#202A26}

  /* Grafik aktivitas DNS: batang HTML agar tajam di semua lebar layar */
  .grafik{display:grid;grid-template-columns:auto 1fr;grid-template-rows:140px auto;column-gap:8px;row-gap:6px}
  .sumbu-y{position:relative;width:28px}
  .sumbu-y span,.sumbu-x span{position:absolute;font-size:11px;color:var(--redup);font-family:'IBM Plex Mono',monospace;font-variant-numeric:tabular-nums}
  .sumbu-y span{right:0;transform:translateY(50%)}
  .plot{position:relative;border-bottom:1px solid #3A453F}
  .plot .grid{position:absolute;left:0;right:0;border-top:1px solid var(--garis2)}
  .batang-batang{position:absolute;inset:0;display:flex;align-items:stretch}
  .slot{flex:1;display:flex;align-items:flex-end;justify-content:center;cursor:default}
  .batang{width:min(24px,calc(100% - 2px));background:var(--seri);border-radius:4px 4px 0 0}
  .slot:hover .batang{background:#8DB86A}
  .slot:hover{background:rgba(255,255,255,.03)}
  .sumbu-x{grid-column:2;position:relative;height:16px}
  .sumbu-x span{transform:translateX(-50%)}
  .grafik-kosong{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--redup)}
  #tip{position:fixed;z-index:10;pointer-events:none;background:#0B0F0D;border:1px solid #3A453F;border-radius:6px;padding:6px 10px;font-size:12px;display:none;white-space:nowrap}
  #tip strong{display:block;font-size:13px;color:var(--teks)}
  #tip span{color:var(--redup)}

  /* Insiden + jejak audit berdampingan di layar lebar */
  .kolom{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:20px;align-items:start}
  @media (max-width:980px){.kolom{grid-template-columns:minmax(0,1fr)}}

  .pill{display:inline-block;font-size:12px;font-weight:500;padding:3px 10px;border-radius:999px;white-space:nowrap}
  .pill.kritis{background:#4A1F1C;color:#FFBDB3}
  .pill.tinggi,.pill.asing{background:#4A3A12;color:#F2D184}
  .pill.aman{background:#1F3A2A;color:#A8E0B0}
  .pill.karantina{background:#1E3147;color:#B5D4F3}
  .pill.info{background:var(--garis);color:var(--terang)}

  .insiden{display:flex;flex-direction:column;gap:8px;padding:14px 0 14px 14px;border-top:1px solid var(--garis2);border-left:3px solid var(--garis);border-radius:0 6px 6px 0}
  .insiden + .insiden{margin-top:12px}
  .insiden.tingkat-kritis{border-left-color:var(--kritis)}
  .insiden.tingkat-tinggi{border-left-color:var(--peringatan)}
  .insiden.status-dikarantina{border-left-color:var(--karantina)}
  .insiden.status-false_positive{border-left-color:var(--garis);opacity:.7}
  .insiden.muncul{animation:muncul 4s ease-out}
  @keyframes muncul{from{background:rgba(240,122,106,.18)}to{background:transparent}}
  .insiden .baris{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
  .insiden .judul{font-size:16px;font-weight:600;overflow-wrap:anywhere}
  .tag{font-size:12px;padding:3px 8px;border-radius:4px;border:1px solid #3A453F;color:var(--terang);text-decoration:none}
  a.tag:hover{border-color:var(--redup)}
  .bukti{font-size:13px;color:var(--terang);line-height:1.5;overflow-wrap:anywhere}
  .label{font-size:11px;letter-spacing:.06em;color:var(--redup);text-transform:uppercase;margin-right:6px}

  /* Garis waktu query beacon: tiap garis = satu query ke domain C2 */
  .ketukan{position:relative;height:28px;background:#121815;border:1px solid var(--garis2);border-radius:4px}
  .ketukan span{position:absolute;top:4px;bottom:4px;width:2px;margin-left:-1px;background:var(--kritis);border-radius:1px}
  .ketukan-sumbu{display:flex;justify-content:space-between;gap:8px;font-size:11px}

  details.playbook{border:1px solid var(--garis2);border-radius:6px;background:#141A17}
  details.playbook summary{cursor:pointer;padding:10px 12px;font-size:13px;font-weight:500;color:var(--terang)}
  details.playbook summary:focus-visible{outline:2px solid var(--hijau);outline-offset:2px}
  details.playbook .isi-pb{padding:0 12px 12px;font-size:13px;line-height:1.6;color:var(--terang)}
  details.playbook p{margin:0 0 8px}
  details.playbook ol{margin:0 0 8px;padding-left:20px}

  /* Jalur perintah ke sensor: diminta -> diambil -> diterapkan */
  .jalur{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:6px 18px;font-size:13px}
  .jalur li{display:flex;align-items:center;gap:6px;color:var(--redup)}
  .jalur li.selesai{color:var(--terang)}
  .jalur li.selesai .led{background:var(--karantina)}
  .jalur li.menunggu .led{background:var(--peringatan);animation:denyut 1.2s ease-in-out infinite}
  .jalur li.gagal{color:var(--kritis)}
  .jalur li.gagal .led{background:var(--kritis)}
  .peringatan{margin:0;font-size:13px;color:var(--peringatan)}

  .aksi{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:2px}
  .btn{font-family:inherit;min-height:40px;padding:0 16px;border-radius:6px;font-size:14px;cursor:pointer;border:1px solid transparent}
  .btn:disabled{opacity:.6;cursor:progress}
  .btn-karantina{background:var(--hijau);color:#10170F;font-weight:600;border:0}
  .btn-fp{background:transparent;color:var(--teks);border:1px solid #3A453F}
  .btn-lepas{background:#18222D;color:#CFE2F5;border:1px solid #3D5A7A}
  .btn:focus-visible{outline:2px solid var(--hijau);outline-offset:2px}
  .catatan{font-size:12px;color:var(--redup)}
  form{display:inline}
  .kosong{border:1px dashed #3A453F;border-radius:8px;padding:24px 16px;color:var(--redup);font-size:14px}

  /* Jejak audit */
  .jejak{list-style:none;margin:0;padding:0;display:flex;flex-direction:column}
  .jejak li{display:grid;grid-template-columns:auto 1fr;column-gap:10px;padding:9px 0;border-top:1px solid var(--garis2);font-size:13px}
  .jejak li:first-child{border-top:0}
  .jejak .led{margin-top:5px}
  .jejak .oleh-analis .led{background:var(--hijau)}
  .jejak .oleh-sensor .led{background:var(--karantina)}
  .jejak .gagal .led{background:var(--kritis)}
  .jejak .gagal .apa{color:var(--kritis)}
  .jejak .apa{color:var(--teks)}

  table{width:100%;border-collapse:collapse;font-size:13px}
  th{text-align:left;color:var(--redup);font-weight:500;padding:10px 8px;border-bottom:1px solid var(--garis2);white-space:nowrap}
  td{padding:10px 8px;border-top:1px solid var(--garis2)}
  td.angka{font-variant-numeric:tabular-nums}
  tr.st-insiden td{background:rgba(240,122,106,.08)}
  tr.st-dikarantina td{background:rgba(127,177,230,.07)}
  tr.st-asing td{background:rgba(229,180,84,.07)}
  .tabelbungkus{overflow-x:auto}
  footer{color:var(--redup);font-size:12px;border-top:1px solid var(--garis);padding-top:12px}

  @media (prefers-reduced-motion:reduce){
    .langsung .led,.jalur li.menunggu .led,.tautan.aliran,.insiden.muncul{animation:none}
  }
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
      <span class="chip mono">LAB · DATA SIMULASI</span>
    </div>
    <div class="akun">
      <div id="langsung" class="langsung" role="status"><span class="led" aria-hidden="true"></span><span id="status-segar">Langsung · segar tiap 5 dtk</span></div>
      <span class="sub">{{ auth()->user()->name }} · {{ User::PERAN[auth()->user()->peran] ?? auth()->user()->peran }}</span>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn-teks">Keluar</button>
      </form>
    </div>
  </header>

  @if (session('pesan'))
    <div class="flash ok" role="status">{{ session('pesan') }}</div>
  @endif
  @if (session('galat'))
    <div class="flash galat" role="alert">{{ session('galat') }}</div>
  @endif

  <div id="isi">
  @fragment('isi')
    @php
      $relatif = fn ($t) => $t ? Carbon::parse($t)->locale('id')->diffForHumans() : 'belum pernah';
      // Durasi ringkas; angka saja yang masuk HTML sehingga aman dicetak mentah.
      $durasiHtml = function (?int $d): string {
          if ($d === null) {
              return '—';
          }
          if ($d < 60) {
              return $d.'<small> dtk</small>';
          }
          if ($d < 3600) {
              return intdiv($d, 60).'<small> mnt</small> '.($d % 60).'<small> dtk</small>';
          }

          return intdiv($d, 3600).'<small> jam</small> '.intdiv($d % 3600, 60).'<small> mnt</small>';
      };
      $durasi = fn (?int $d) => strip_tags($durasiHtml($d));
      $labelStatus = ['normal' => 'Normal', 'insiden' => 'Insiden', 'dikarantina' => 'Dikarantina', 'asing' => 'Belum dikenal'];
    @endphp
    <span hidden data-insiden-aktif="{{ $metrik['insidenAktif'] }}"></span>

    <section class="sensorbar" aria-label="Status sensor dan detektor">
      <div class="sensorlist">
        @forelse ($sensor as $s)
          <div class="sensor {{ $s['online'] ? 'online' : 'offline' }}">
            <span class="led" aria-hidden="true"></span>
            <span class="mono">{{ $s['nama'] }}</span>
            <span>{{ $s['online'] ? 'Online' : 'Tidak merespons' }}</span>
            <span class="sub">responder {{ $relatif($s['detak']) }} · data DNS {{ $relatif($s['dataTerakhir']) }}</span>
          </div>
        @empty
          <div class="sensor offline"><span class="led" aria-hidden="true"></span>Belum ada sensor yang terhubung.</div>
        @endforelse
        <div class="sensor {{ $detektor['jalan'] ? 'online' : 'waspada' }}">
          <span class="led" aria-hidden="true"></span>
          <span>Detektor {{ $detektor['jalan'] ? 'otomatis tiap menit' : 'tidak berjalan' }}</span>
          <span class="sub">
            @if ($detektor['jalan'])
              terakhir {{ $relatif($detektor['detak']) }}
            @else
              jalankan <span class="mono">php artisan schedule:work</span>
            @endif
          </span>
        </div>
      </div>
      <div class="sub mono">Diperbarui {{ $sekarang->locale('id')->translatedFormat('d M Y H:i:s') }} WIB</div>
    </section>

    <section class="metrik" aria-label="Ringkasan">
      <div class="kartu {{ $metrik['sensorOnline'] < $metrik['sensorTotal'] ? 'bahaya' : '' }}"><span class="sub">Sensor online</span><span class="angka">{{ $metrik['sensorOnline'] }}/{{ $metrik['sensorTotal'] }}</span><span class="sub">responder menarik perintah</span></div>
      <div class="kartu {{ $metrik['asing'] ? 'waspada' : '' }}"><span class="sub">Perangkat terpantau</span><span class="angka">{{ $metrik['perangkat'] }}</span><span class="sub">{{ $metrik['asing'] ? $metrik['asing'].' belum dikenal' : 'semua dikenal' }}</span></div>
      <div class="kartu {{ $metrik['insidenAktif'] ? 'bahaya' : '' }}"><span class="sub">Insiden aktif</span><span class="angka">{{ $metrik['insidenAktif'] }}</span><span class="sub">menunggu keputusan analis</span></div>
      <div class="kartu {{ $metrik['dikarantina'] ? 'karantina' : '' }}"><span class="sub">Perangkat dikarantina</span><span class="angka">{{ $metrik['dikarantina'] }}</span><span class="sub">diputus oleh sensor</span></div>
      <div class="kartu"><span class="sub">Waktu deteksi (median)</span><span class="angka">{!! $durasiHtml($metrik['waktuDeteksi']) !!}</span><span class="sub">aktivitas pertama → insiden</span></div>
      <div class="kartu"><span class="sub">Respons sensor (median)</span><span class="angka">{!! $durasiHtml($metrik['responsSensor']) !!}</span><span class="sub">klik analis → diterapkan</span></div>
    </section>

    {{-- Diagram topologi: server pusat -> sensor -> perangkat, warna mengikuti status --}}
    @php
      $SX = 330; $SW = 220; $DX = 690; $DW = 300; $DH = 50; $DG = 10;
      $BX = ($SX + $SW + $DX) / 2;
      $blokH = 164;
      $y = 20;
      $grup = [];
      foreach ($topologi as $t) {
          $baris = max(1, count($t['perangkat']) + ($t['lainnya'] ? 1 : 0));
          $devH = $baris * $DH + ($baris - 1) * $DG;
          $tinggi = max($blokH, $devH);
          $blokTop = $y + ($tinggi - $blokH) / 2;
          $grup[] = ['t' => $t, 'internetY' => $blokTop, 'sensorY' => $blokTop + 60, 'devTop' => $y + ($tinggi - $devH) / 2];
          $y += $tinggi + 40;
      }
      $isiBawah = $y - 40;
      $serverH = 112;
      $serverY = (20 + $isiBawah) / 2 - $serverH / 2;
      $serverMid = $serverY + $serverH / 2;
      $tinggiSvg = $isiBawah + 56;
      $serverInfo = config('benteng.server_info');
      $jumlahPerangkat = $topologi->sum(fn ($t) => count($t['perangkat']) + $t['lainnya']);
    @endphp
    <section class="panel" aria-label="Topologi jaringan">
      <div class="kepala">
        <h2 class="seksi">TOPOLOGI JARINGAN · LIVE</h2>
        <span class="sub">{{ $topologi->count() }} sensor · {{ $jumlahPerangkat }} perangkat · arahkan kursor ke perangkat untuk detail</span>
      </div>
      @if ($topologi->isEmpty())
        <div class="kosong">Belum ada sensor yang melapor. Diagram muncul setelah sensor mengirim data DNS atau DHCP.</div>
      @else
        <div class="topologi-bungkus">
          <svg class="topologi" viewBox="0 0 1000 {{ $tinggiSvg }}" role="img" aria-labelledby="topologi-judul topologi-desk">
            <title id="topologi-judul">Topologi jaringan BENTENG</title>
            <desc id="topologi-desk">Server pusat terhubung ke {{ $topologi->count() }} sensor. @foreach ($topologi as $t)Sensor {{ $t['nama'] }} {{ $t['online'] ? 'online' : 'tidak merespons' }} dengan {{ count($t['perangkat']) + $t['lainnya'] }} perangkat. @endforeach</desc>

            @foreach ($grup as $g)
              @php
                $t = $g['t'];
                $sy = $g['sensorY'];
                $sMid = $sy + 52;
                $tengahPerangkat = collect($t['perangkat'])->keys()->map(fn ($k) => $g['devTop'] + $k * ($DH + $DG) + $DH / 2);
                $trunkAtas = min($sMid, $tengahPerangkat->min() ?? $sMid);
                $trunkBawah = max($sMid, $tengahPerangkat->max() ?? $sMid);
                $detikDetak = $t['detak'] ? (int) $t['detak']->diffInSeconds($sekarang) : null;
              @endphp

              {{-- Server -> sensor --}}
              <path class="tautan {{ $t['online'] ? 'aliran' : 'putus' }}" d="M 206 {{ $serverMid }} C 268 {{ $serverMid }}, 268 {{ $sMid }}, {{ $SX }} {{ $sMid }}"/>
              <text class="t-kecil halo" x="268" y="{{ ($serverMid + $sMid) / 2 + 18 }}" text-anchor="middle">{{ $t['online'] ? 'HTTP + token' : 'terputus' }}</text>

              {{-- Internet -> sensor --}}
              <line class="tautan" x1="{{ $SX + $SW / 2 }}" y1="{{ $g['internetY'] + 34 }}" x2="{{ $SX + $SW / 2 }}" y2="{{ $sy }}"/>
              <rect class="kotak-internet" x="{{ $SX + 30 }}" y="{{ $g['internetY'] }}" width="160" height="34" rx="17"/>
              <text class="t-isi" x="{{ $SX + $SW / 2 }}" y="{{ $g['internetY'] + 22 }}" text-anchor="middle">Internet (WAN/NAT)</text>

              {{-- Sensor --}}
              <rect class="kotak {{ $t['online'] ? '' : 'putus' }}" x="{{ $SX }}" y="{{ $sy }}" width="{{ $SW }}" height="104" rx="10"/>
              <g transform="translate({{ $SX + 14 }} {{ $sy + 12 }}) scale(.8)" aria-hidden="true">
                <path class="ikon" d="M16 3 L27 7 V15 C27 22 22 27 16 29 C10 27 5 22 5 15 V7 Z"/>
                <path class="ikon" d="M11 16 L15 20 L21 12"/>
              </g>
              <text class="t-judul" x="{{ $SX + 50 }}" y="{{ $sy + 28 }}">Sensor {{ $t['nama'] }}</text>
              <text class="t-kecil" x="{{ $SX + 50 }}" y="{{ $sy + 45 }}">{{ $t['info']['model'] ?? 'Sensor OpenWrt' }}</text>
              <text class="t-kecil mono" x="{{ $SX + 16 }}" y="{{ $sy + 70 }}">LAN {{ $t['info']['lan'] ?? '-' }}</text>
              <circle class="titik {{ $t['online'] ? 'online' : 'offline' }}" cx="{{ $SX + 20 }}" cy="{{ $sy + 87 }}" r="4"/>
              <text class="t-kecil" x="{{ $SX + 30 }}" y="{{ $sy + 91 }}">
                @if ($t['online'])
                  Online · detak {{ $detikDetak }} dtk lalu
                @else
                  Tidak merespons{{ $detikDetak !== null ? ' · '.$durasi($detikDetak).' lalu' : '' }}
                @endif
              </text>

              {{-- Sensor -> perangkat --}}
              <path class="tautan" d="M {{ $SX + $SW }} {{ $sMid }} H {{ $BX }}"/>
              <line class="tautan" x1="{{ $BX }}" y1="{{ $trunkAtas }}" x2="{{ $BX }}" y2="{{ $trunkBawah }}"/>
              @foreach ($t['perangkat'] as $k => $p)
                @php
                  $dy = $g['devTop'] + $k * ($DH + $DG);
                  $dc = $dy + $DH / 2;
                  $st = 'st-'.$p->status;
                  $nama = $p->hostname ? Str::limit($p->hostname, 10, '…') : null;
                @endphp
                <path class="cabang {{ $st }}" d="M {{ $BX }} {{ $dc }} H {{ $DX }}"/>
                @if ($p->status === 'dikarantina')
                  @php $cx = ($BX + $DX) / 2; @endphp
                  <g class="tanda-putus" aria-hidden="true">
                    <circle cx="{{ $cx }}" cy="{{ $dc }}" r="9"/>
                    <path d="M {{ $cx - 4 }} {{ $dc - 4 }} L {{ $cx + 4 }} {{ $dc + 4 }} M {{ $cx + 4 }} {{ $dc - 4 }} L {{ $cx - 4 }} {{ $dc + 4 }}"/>
                  </g>
                @endif
                <g class="node" data-nilai="{{ $p->ip }} · {{ $labelStatus[$p->status] }}" data-label="{{ $p->hostname ?: 'tanpa nama host' }} · {{ $p->mac ?? 'MAC belum dilaporkan' }} · {{ number_format($p->jumlah, 0, ',', '.') }} query DNS">
                  <rect class="kotak {{ $st }}" x="{{ $DX }}" y="{{ $dy }}" width="{{ $DW }}" height="{{ $DH }}" rx="8"/>
                  <circle class="titik {{ $st }}" cx="{{ $DX + 16 }}" cy="{{ $dc }}" r="5"/>
                  <text class="t-isi mono" x="{{ $DX + 30 }}" y="{{ $dy + 21 }}">{{ $p->ip }}</text>
                  <text class="t-kecil" x="{{ $DX + 30 }}" y="{{ $dy + 38 }}">{{ $nama ?? 'tanpa nama' }} · {{ $p->mac ?? 'MAC ?' }}</text>
                  <rect class="lencana {{ $st }}" x="{{ $DX + $DW - 94 }}" y="{{ $dy + 15 }}" width="84" height="20" rx="10"/>
                  <text class="t-lencana {{ $st }}" x="{{ $DX + $DW - 52 }}" y="{{ $dy + 29 }}" text-anchor="middle">{{ $labelStatus[$p->status] }}</text>
                </g>
              @endforeach
              @if ($t['lainnya'])
                @php $dy = $g['devTop'] + count($t['perangkat']) * ($DH + $DG); @endphp
                <text class="t-kecil" x="{{ $DX + 16 }}" y="{{ $dy + 30 }}">+{{ $t['lainnya'] }} perangkat lain, lihat tabel di bawah</text>
              @endif
              @if (! count($t['perangkat']))
                <text class="t-kecil" x="{{ $DX + 16 }}" y="{{ $g['devTop'] + 30 }}">Belum ada perangkat terlihat</text>
              @endif
            @endforeach

            {{-- Server pusat --}}
            <rect class="kotak" x="16" y="{{ $serverY }}" width="190" height="{{ $serverH }}" rx="10"/>
            <g transform="translate(30 {{ $serverY + 14 }})" aria-hidden="true">
              <rect class="ikon" x="1" y="1" width="22" height="15" rx="2"/>
              <path class="ikon" d="M8 21 H16 M12 16 V21"/>
            </g>
            <text class="t-judul" x="62" y="{{ $serverY + 28 }}">{{ $serverInfo['nama'] }}</text>
            <text class="t-kecil" x="62" y="{{ $serverY + 45 }}">{{ $serverInfo['keterangan'] }}</text>
            <text class="t-kecil mono" x="30" y="{{ $serverY + 70 }}">{{ $serverInfo['alamat'] }}</text>
            <circle class="titik {{ $detektor['jalan'] ? 'online' : 'st-asing' }}" cx="34" cy="{{ $serverY + 89 }}" r="4"/>
            <text class="t-kecil" x="44" y="{{ $serverY + 93 }}">Detektor {{ $detektor['jalan'] ? 'otomatis' : 'tidak berjalan' }}</text>

            {{-- Legenda --}}
            @php $ly = $isiBawah + 32; @endphp
            <g aria-hidden="true">
              <circle class="titik st-normal" cx="22" cy="{{ $ly - 4 }}" r="5"/><text class="t-kecil" x="34" y="{{ $ly }}">Normal</text>
              <circle class="titik st-insiden" cx="112" cy="{{ $ly - 4 }}" r="5"/><text class="t-kecil" x="124" y="{{ $ly }}">Insiden aktif</text>
              <circle class="titik st-dikarantina" cx="232" cy="{{ $ly - 4 }}" r="5"/><text class="t-kecil" x="244" y="{{ $ly }}">Dikarantina (diputus sensor)</text>
              <circle class="titik st-asing" cx="452" cy="{{ $ly - 4 }}" r="5"/><text class="t-kecil" x="464" y="{{ $ly }}">Belum dikenal (perangkat asing)</text>
              <path class="tautan aliran" d="M 686 {{ $ly - 4 }} H 716"/><text class="t-kecil" x="724" y="{{ $ly }}">aliran data sensor ↔ server</text>
            </g>
          </svg>
        </div>
      @endif
    </section>

    @php
      $puncak = collect($aktivitas)->max('jumlah');
      $totalGrafik = collect($aktivitas)->sum('jumlah');
      $menitPuncak = collect($aktivitas)->firstWhere('jumlah', $puncak)['menit'] ?? null;
      // Batas atas sumbu dibulatkan ke 1/2/5 x 10^n supaya angka sumbu rapi.
      $skala = 1;
      if ($puncak > 0) {
          $pangkat = 10 ** floor(log10($puncak));
          foreach ([1, 2, 5, 10] as $k) {
              if ($k * $pangkat >= $puncak) { $skala = (int) ($k * $pangkat); break; }
          }
      }
      $jumlahMenit = count($aktivitas);
    @endphp
    <section class="panel" aria-label="Aktivitas DNS">
      <div class="kepala">
        <h2 class="seksi">AKTIVITAS DNS · QUERY PER MENIT, {{ $jumlahMenit }} MENIT TERAKHIR</h2>
        <span class="sub">
          @if ($totalGrafik)
            {{ number_format($totalGrafik, 0, ',', '.') }} query · puncak {{ $puncak }}/menit pukul {{ $menitPuncak }} ·
          @endif
          {{ number_format($metrik['query24'], 0, ',', '.') }} query dalam 24 jam
        </span>
      </div>
      <div class="grafik">
        <div class="sumbu-y" aria-hidden="true">
          <span style="bottom:100%">{{ $skala }}</span>
          @if ($skala % 2 === 0)<span style="bottom:50%">{{ $skala / 2 }}</span>@endif
          <span style="bottom:0">0</span>
        </div>
        <div class="plot" role="img" aria-label="Grafik batang query DNS per menit. {{ $totalGrafik ? "Total $totalGrafik query, puncak $puncak query pada $menitPuncak." : 'Tidak ada query.' }}">
          <div class="grid" style="top:0"></div>
          <div class="grid" style="top:50%"></div>
          <div class="batang-batang">
            @foreach ($aktivitas as $a)
              <div class="slot" data-nilai="{{ $a['jumlah'] }} query" data-label="pukul {{ $a['menit'] }} WIB">
                @if ($a['jumlah'])
                  <div class="batang" style="height:max(2px, {{ round($a['jumlah'] / $skala * 100, 2) }}%)"></div>
                @endif
              </div>
            @endforeach
          </div>
          @unless ($totalGrafik)
            <div class="grafik-kosong">Tidak ada query DNS dalam {{ $jumlahMenit }} menit terakhir.</div>
          @endunless
        </div>
        <div class="sumbu-x" aria-hidden="true">
          @foreach ($aktivitas as $n => $a)
            @if ($n % 15 === 0 || $n === $jumlahMenit - 1)
              <span style="left:{{ round(($n + 0.5) / $jumlahMenit * 100, 2) }}%">{{ $a['menit'] }}</span>
            @endif
          @endforeach
        </div>
      </div>
    </section>

    <div class="kolom">
      <section class="panel" aria-label="Insiden">
        <div class="kepala">
          <h2 class="seksi">INSIDEN</h2>
          <span class="sub">{{ $incidents->count() }} total · urut: aktif, dikarantina, selesai</span>
        </div>
        @forelse ($incidents as $i)
          @php
            $playbook = config('benteng.playbook.'.$i->jenis);
            // Kode teknik mengandung titik (T1071.004), jadi jangan pakai notasi titik config().
            $namaTeknik = config('benteng.attck')[$i->attck] ?? null;
            $asing = $i->jenis === 'perangkat_asing';
          @endphp
          <article class="insiden tingkat-{{ $i->tingkat }} status-{{ $i->status }}" data-id="{{ $i->id }}">
            <div class="baris">
              <span class="pill {{ $i->tingkat }}">{{ ucfirst($i->tingkat) }} · {{ $i->skor }}</span>
              <span class="mono sub">#{{ $i->id }} · {{ $i->sensor }} · {{ $i->client_ip }}</span>
              @if ($i->status === 'dikarantina')
                <span class="pill karantina">dikarantina</span>
              @elseif ($i->status === 'false_positive')
                <span class="pill info">{{ $asing ? 'perangkat sah' : 'false positive' }}</span>
              @endif
              <span class="sub">terdeteksi {{ Carbon::parse($i->created_at)->format('H:i') }} · {{ $relatif($i->created_at) }}</span>
            </div>
            <div class="judul">{{ $i->judul }}</div>
            <div class="baris">
              @if ($i->attck)
                <a class="tag mono" href="https://attack.mitre.org/techniques/{{ str_replace('.', '/', $i->attck) }}/" target="_blank" rel="noopener noreferrer">ATT&amp;CK {{ $i->attck }}@if ($namaTeknik) · {{ $namaTeknik }}@endif</a>
              @endif
              <span class="tag mono">{{ $asing ? 'PERANGKAT ASING' : strtoupper($i->jenis) }}</span>
            </div>
            <div class="bukti"><span class="label">Bukti</span>{{ $i->bukti }}</div>
            @if ($i->mulai_pada)
              <div class="sub">Aktivitas pertama {{ Carbon::parse($i->mulai_pada)->format('H:i:s') }} → terdeteksi {{ Carbon::parse($i->created_at)->format('H:i:s') }} ({{ $durasi(max(0, strtotime($i->created_at) - strtotime($i->mulai_pada))) }})</div>
            @endif

            @if (count($i->ketukan) >= 2)
              @php
                $t0 = $i->ketukan[0];
                $t1 = $i->ketukan[count($i->ketukan) - 1];
                $rentang = max(1, $t1 - $t0);
              @endphp
              <div>
                <div class="ketukan" role="img" aria-label="{{ count($i->ketukan) }} query ke {{ $i->domain }} antara {{ date('H:i:s', $t0) }} dan {{ date('H:i:s', $t1) }}, berjarak teratur.">
                  @foreach ($i->ketukan as $t)
                    <span style="left:{{ round(($t - $t0) / $rentang * 100, 2) }}%"></span>
                  @endforeach
                </div>
                <div class="ketukan-sumbu sub mono"><span>{{ date('H:i:s', $t0) }}</span><span>tiap garis = 1 query ke {{ $i->domain }}</span><span>{{ date('H:i:s', $t1) }}</span></div>
              </div>
            @endif

            @if ($playbook)
              <details class="playbook" data-kunci="pb-{{ $i->id }}">
                <summary>Apa artinya &amp; langkah yang disarankan</summary>
                <div class="isi-pb">
                  <p>{{ $playbook['arti'] }}</p>
                  <p><strong>Risiko:</strong> {{ $playbook['risiko'] }}</p>
                  <ol>
                    @foreach ($playbook['langkah'] as $langkah)
                      <li>{{ $langkah }}</li>
                    @endforeach
                  </ol>
                  <p class="catatan">Saran dari playbook BENTENG. Keputusan tetap di tangan analis.</p>
                </div>
              </details>
            @endif

            @if ($i->perintah && $i->status !== 'false_positive')
              @php
                $c = $i->perintah;
                $diminta = Carbon::parse($c->created_at);
                $diambil = $c->diambil_pada ? Carbon::parse($c->diambil_pada) : null;
                $selesai = $c->selesai_pada ? Carbon::parse($c->selesai_pada) : null;
                $umurDetik = (int) $diminta->diffInSeconds($sekarang);
                $tertahan = $c->status === 'pending' && $umurDetik > config('benteng.perintah_tertahan_detik');
                $tahapAkhir = match ($c->status) { 'selesai' => 'selesai', 'gagal' => 'gagal', default => 'menunggu' };
              @endphp
              <ol class="jalur" aria-label="Status perintah ke sensor">
                <li class="selesai"><span class="led" aria-hidden="true"></span>{{ $c->aksi === 'quarantine' ? 'Karantina' : 'Lepas karantina' }} diminta <span class="mono">{{ $diminta->format('H:i:s') }}</span></li>
                <li class="{{ $diambil ? 'selesai' : 'menunggu' }}"><span class="led" aria-hidden="true"></span>Diambil {{ $c->sensor }} <span class="mono">{{ $diambil?->format('H:i:s') ?? '…' }}</span></li>
                <li class="{{ $tahapAkhir }}"><span class="led" aria-hidden="true"></span>{{ $c->status === 'gagal' ? 'Gagal diterapkan' : 'Diterapkan' }} <span class="mono">{{ $selesai?->format('H:i:s') ?? '…' }}</span>
                  @if ($c->status === 'selesai' && $selesai)
                    <span class="sub">({{ $selesai->getTimestamp() - $diminta->getTimestamp() }} dtk dari klik)</span>
                  @endif
                </li>
              </ol>
              @if ($tertahan)
                <p class="peringatan">Sensor belum mengonfirmasi perintah selama {{ $umurDetik }} dtk. Periksa layanan benteng-responder di {{ $c->sensor }}.</p>
              @endif
            @endif

            <div class="aksi">
              @if (! $bisaBertindak && $i->status !== 'false_positive')
                <span class="catatan">Mode pengamat: hanya akun analis yang dapat mengambil tindakan.</span>
              @elseif ($i->status === 'baru')
                <form method="POST" action="{{ route('benteng.karantina', $i->id) }}" data-konfirmasi="Karantina {{ $i->client_ip }}? Sensor {{ $i->sensor }} akan memutus perangkat ini dari jaringan.">
                  @csrf
                  <button type="submit" class="btn btn-karantina">Karantina perangkat</button>
                </form>
                <form method="POST" action="{{ route('benteng.false-positive', $i->id) }}" @if ($asing) data-konfirmasi="Daftarkan {{ $i->client_ip }} sebagai perangkat sah satuan?" @endif>
                  @csrf
                  <button type="submit" class="btn btn-fp">{{ $asing ? 'Daftarkan perangkat sah' : 'Tandai false positive' }}</button>
                </form>
                <span class="catatan">Keputusan tercatat atas nama {{ auth()->user()->name }}</span>
              @elseif ($i->status === 'dikarantina')
                <form method="POST" action="{{ route('benteng.lepas', $i->id) }}" data-konfirmasi="Lepas karantina {{ $i->client_ip }}? Perangkat akan tersambung lagi ke jaringan.">
                  @csrf
                  <button type="submit" class="btn btn-lepas">Lepas karantina</button>
                </form>
                <span class="catatan">Karantina berlaku untuk semua insiden perangkat {{ $i->client_ip }}.</span>
              @elseif ($asing)
                <span class="catatan">Didaftarkan sebagai perangkat sah satuan oleh analis.</span>
              @else
                <span class="catatan">Ditandai false positive oleh analis. Pola yang sama tidak dilaporkan ulang.</span>
              @endif
            </div>
          </article>
        @empty
          <div class="kosong">Belum ada insiden. Jalankan simulator di VM korban; detektor berjalan otomatis tiap menit.</div>
        @endforelse
      </section>

      @php
        $kalimatAudit = [
            'minta karantina' => 'meminta karantina',
            'lepas karantina' => 'meminta lepas karantina',
            'tandai false positive' => 'menandai false positive',
            'daftarkan perangkat' => 'mendaftarkan perangkat sah',
            'quarantine ok' => 'menerapkan karantina',
            'quarantine gagal' => 'gagal menerapkan karantina',
            'unquarantine ok' => 'melepas karantina',
            'unquarantine gagal' => 'gagal melepas karantina',
        ];
      @endphp
      <section class="panel" aria-label="Jejak audit">
        <div class="kepala">
          <h2 class="seksi">JEJAK AUDIT</h2>
          <span class="sub">{{ $audit->count() }} terbaru</span>
        </div>
        @if ($audit->isEmpty())
          <div class="kosong">Belum ada tindakan tercatat.</div>
        @else
          <ol class="jejak">
            @foreach ($audit as $a)
              @php
                $dariSensor = str_starts_with($a->pelaku, 'sensor:');
                $gagal = str_ends_with($a->aksi, 'gagal');
              @endphp
              <li class="{{ $dariSensor ? 'oleh-sensor' : 'oleh-analis' }} {{ $gagal ? 'gagal' : '' }}">
                <span class="led" aria-hidden="true"></span>
                <div>
                  <div><strong>{{ $dariSensor ? 'Sensor '.substr($a->pelaku, 7) : ucfirst($a->pelaku) }}</strong> <span class="apa">{{ $kalimatAudit[$a->aksi] ?? $a->aksi }}</span></div>
                  <div class="sub mono">{{ Carbon::parse($a->created_at)->format('H:i:s') }} · {{ $a->target }}@if ($a->incident_id) · insiden #{{ $a->incident_id }}@endif</div>
                </div>
              </li>
            @endforeach
          </ol>
        @endif
      </section>
    </div>

    <section class="panel" aria-label="Perangkat terpantau">
      <div class="kepala">
        <h2 class="seksi">PERANGKAT TERPANTAU</h2>
        <span class="sub">dari log DNS dan daftar DHCP sensor · 20 teratas menurut jumlah query</span>
      </div>
      <div class="tabelbungkus">
        <table>
          <thead><tr><th>Perangkat (IP)</th><th>Nama host · MAC</th><th>Sensor</th><th>Jumlah query</th><th>Domain unik</th><th>Pertama terlihat</th><th>Terakhir terlihat</th><th>Status</th></tr></thead>
          <tbody>
          @forelse ($perangkat->take(20) as $p)
            <tr class="st-{{ $p->status }}">
              <td class="mono">{{ $p->ip }}</td>
              <td class="sub">{{ $p->hostname ?: 'tanpa nama' }} · <span class="mono">{{ $p->mac ?? 'belum dilaporkan' }}</span></td>
              <td>{{ $p->sensor }}</td>
              <td class="mono angka">{{ number_format($p->jumlah, 0, ',', '.') }}</td>
              <td class="mono angka">{{ number_format($p->domain_unik, 0, ',', '.') }}</td>
              <td class="mono sub">{{ Carbon::parse($p->awal)->locale('id')->translatedFormat('d M H:i') }}</td>
              <td class="mono sub">{{ Carbon::parse($p->akhir)->locale('id')->translatedFormat('d M H:i') }} · {{ $relatif($p->akhir) }}</td>
              <td>
                @if ($p->status === 'dikarantina')
                  <span class="pill karantina">Dikarantina</span>
                @elseif ($p->status === 'insiden')
                  <span class="pill kritis">Insiden</span>
                @elseif ($p->status === 'asing')
                  <span class="pill asing">Belum dikenal</span>
                @else
                  <span class="pill aman">Normal</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="sub">Belum ada data. Pastikan sensor mengirim ke /api/ingest/dns dan /api/ingest/dhcp.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </section>
  @endfragment
  </div>

  <footer>
    BENTENG · data lab simulasi · deteksi berbasis perilaku (beaconing, DGA, perangkat asing), dipetakan ke MITRE ATT&amp;CK. Keputusan respons tetap di tangan analis.
  </footer>

</div>

<div id="tip" role="tooltip"><strong></strong><span></span></div>

<script>
(() => {
  const JEDA_MS = 5000;
  const JUDUL = document.title;
  const isi = document.getElementById('isi');
  const langsung = document.getElementById('langsung');
  const statusSegar = document.getElementById('status-segar');
  let sedangMemuat = false;

  // Judul tab menampilkan jumlah insiden aktif, agar terlihat walau tab lain sedang dibuka.
  function perbaruiJudul() {
    const n = Number(isi.querySelector('[data-insiden-aktif]')?.dataset.insidenAktif || 0);
    document.title = n > 0 ? `(${n}) ${JUDUL}` : JUDUL;
  }

  // Segarkan hanya fragmen "isi" supaya halaman tidak berkedip dan posisi scroll tetap.
  async function segarkan() {
    if (sedangMemuat || document.hidden) return;
    // Jangan ganti isi saat analis sedang mengarahkan kursor atau fokus ke tombol aksi.
    if (isi.querySelector('.aksi:hover, .aksi :focus')) return;
    sedangMemuat = true;
    try {
      const res = await fetch(location.pathname, { headers: { 'X-Benteng-Sebagian': '1' }, cache: 'no-store' });
      // Sesi habis: server mengarahkan ke halaman login.
      if (res.redirected) { location.reload(); return; }
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const html = await res.text();
      const terbuka = [...isi.querySelectorAll('details[open][data-kunci]')].map((d) => d.dataset.kunci);
      const lama = new Set([...isi.querySelectorAll('article[data-id]')].map((a) => a.dataset.id));
      isi.innerHTML = html;
      terbuka.forEach((k) => isi.querySelector(`details[data-kunci="${CSS.escape(k)}"]`)?.setAttribute('open', ''));
      isi.querySelectorAll('article[data-id]').forEach((a) => { if (!lama.has(a.dataset.id)) a.classList.add('muncul'); });
      perbaruiJudul();
      langsung.classList.remove('putus');
      statusSegar.textContent = 'Langsung · segar tiap 5 dtk';
    } catch (e) {
      langsung.classList.add('putus');
      statusSegar.textContent = 'Koneksi ke server terputus · mencoba lagi';
    } finally {
      sedangMemuat = false;
    }
  }
  perbaruiJudul();
  setInterval(segarkan, JEDA_MS);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) segarkan(); });

  // Konfirmasi tindakan berisiko dan cegah klik ganda.
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.dataset.konfirmasi && !confirm(form.dataset.konfirmasi)) {
      e.preventDefault();
      return;
    }
    const tombol = form.querySelector('button[type=submit]');
    if (tombol) {
      tombol.disabled = true;
      tombol.textContent = 'Mengirim…';
    }
  });

  // Tooltip grafik dan topologi: satu elemen, didelegasikan agar tetap jalan setelah isi disegarkan.
  const tip = document.getElementById('tip');
  const [tipNilai, tipLabel] = tip.children;
  document.addEventListener('pointermove', (e) => {
    const sasaran = e.target.closest?.('[data-nilai]');
    if (!sasaran) { tip.style.display = 'none'; return; }
    tipNilai.textContent = sasaran.dataset.nilai;
    tipLabel.textContent = sasaran.dataset.label;
    tip.style.display = 'block';
    const x = Math.min(e.clientX + 12, window.innerWidth - tip.offsetWidth - 8);
    tip.style.left = x + 'px';
    tip.style.top = (e.clientY - tip.offsetHeight - 12) + 'px';
  });
  document.addEventListener('pointerleave', () => { tip.style.display = 'none'; });
})();
</script>
</body>
</html>
