<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Detektor BENTENG: membaca dns_events dan perangkat, mencari pola beaconing,
 * DGA, dan perangkat asing, lalu menulis temuan ke tabel incidents.
 *
 * Jalankan sekali:   php artisan benteng:deteksi
 * Jalankan berulang: php artisan benteng:deteksi --ulang=30
 * Otomatis:          php artisan schedule:work   (tiap menit, lihat routes/console.php)
 * Uji kualitas:      php artisan benteng:deteksi --evaluasi
 */
class DeteksiCommand extends Command
{
    protected $signature = 'benteng:deteksi
        {--ulang=0 : ulangi tiap N detik (0 = sekali)}
        {--evaluasi : uji detektor dengan skenario sintetis tanpa menyentuh database}
        {--seed=2026 : seed awal data acak untuk --evaluasi}
        {--percobaan=1 : jumlah percobaan --evaluasi dengan seed berurutan}';

    protected $description = 'Deteksi beaconing, DGA, dan perangkat asing dari data sensor';

    /**
     * Kunci cache waktu terakhir detektor berjalan (ditampilkan di dashboard).
     */
    public const KUNCI_DETAK = 'benteng:detak:detektor';

    // Domain yang wajar berpola teratur; jangan dijadikan insiden.
    private const ALLOW_SUFFIX = [
        '.pool.ntp.org', 'ntp.org', '.windowsupdate.com', '.alpinelinux.org',
        '.ubuntu.com', '.debian.org', '.archlinux.org', 'openwrt.org',
        '.cloudflare.com', '.google.com', '.gstatic.com',
    ];

    // Label tingkat dua yang dipakai registri, mis. kemhan.go.id, tniad.mil.id, detik.net.id.
    private const SLD_UMUM = [
        'ac', 'co', 'go', 'mil', 'or', 'sch', 'web', 'my', 'biz', 'net',
        'com', 'org', 'gov', 'edu', 'desa', 'ponpes',
    ];

    // Query ke domain sama dalam sekian detik dianggap satu (mis. pasangan A+AAAA
    // dari getaddrinfo, atau percobaan ulang), supaya jeda beacon tidak berselang-seling.
    private const BEACON_RAPAT_DETIK = 5;

    // Minimal berapa jeda yang konsisten di sekitar median sampai disebut beaconing.
    private const BEACON_MIN_TERATUR = 6;

    // Pada akses acak (proses Poisson) hanya ~21% jeda jatuh di +-30% median; dipakai 25% agar konservatif.
    private const BEACON_PELUANG_ACAK = 0.25;

    // Pola disebut beacon bila peluang muncul dari akses acak di bawah 1 banding 100.000 (uji binomial).
    // Ambang porsi tetap (mis. 50%) terbukti salah tangkap pada sampel kecil, lihat --evaluasi.
    private const BEACON_ALPHA = 1e-5;

    // Sebuah jeda disebut "teratur" bila dalam +-30% dari jeda median.
    private const BEACON_TOLERANSI = 0.30;

    // Minimal median jeda (detik); di bawah ini dianggap semburan, bukan beacon.
    private const BEACON_MIN_MEDIAN = 5;

    private const DGA_MIN_ENTROPI = 3.2;

    private const DGA_MIN_KONSONAN = 0.62;

    private const DGA_MIN_DOMAIN = 8;

    public function handle(): int
    {
        if ($this->option('evaluasi')) {
            return $this->evaluasi();
        }

        $ulang = (float) $this->option('ulang');

        do {
            $baru = $this->analisis();
            Cache::forever(self::KUNCI_DETAK, now()->toDateTimeString());
            $total = DB::table('incidents')->count();
            $this->line('['.now()->format('H:i:s')."] insiden baru: $baru, total: $total");

            if ($ulang > 0) {
                sleep((int) $ulang);
            }
        } while ($ulang > 0);

        return self::SUCCESS;
    }

    private function analisis(): int
    {
        $rows = DB::table('dns_events')
            ->select('id', 'sensor', 'client_ip', 'domain', 'qtype', 'queried_at')
            ->lazyById(5000);

        $baru = 0;
        foreach ([...$this->temukan($rows), ...$this->perangkatAsing()] as $temuan) {
            $baru += $this->simpan($temuan);
        }

        return $baru;
    }

    /**
     * Inti deteksi DNS. Tidak menyentuh database, sehingga dipakai juga oleh --evaluasi.
     *
     * @param  iterable<object{sensor: string, client_ip: string, domain: string, qtype: string, queried_at: string}>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function temukan(iterable $rows): array
    {
        $perClientDomain = [];   // "sensor|client|domain" => [timestamp detik]
        $dgaPerClient = [];      // "sensor|client" => [domain => timestamp pertama]

        foreach ($rows as $r) {
            if ($r->qtype === 'PTR' || $this->diAllowlist($r->domain)) {
                continue;
            }
            $ts = strtotime(substr($r->queried_at, 0, 19));
            $perClientDomain[$r->sensor.'|'.$r->client_ip.'|'.$r->domain][] = $ts;

            if ($this->tampakDga($r->domain)) {
                $kunci = $r->sensor.'|'.$r->client_ip;
                $dgaPerClient[$kunci][$r->domain] = min($ts, $dgaPerClient[$kunci][$r->domain] ?? PHP_INT_MAX);
            }
        }

        $temuan = [];

        // --- Beaconing ---
        foreach ($perClientDomain as $key => $waktu) {
            sort($waktu);
            $waktu = $this->rapatkan($waktu, self::BEACON_RAPAT_DETIK);
            $pola = $this->polaBeacon($waktu);
            if ($pola === null) {
                continue;
            }
            [$sensor, $client, $domain] = explode('|', $key, 3);
            $skor = min(99, 70 + $pola['teratur'] + (int) ($pola['frac'] * 20));
            $bukti = $pola['teratur'].' jeda konsisten sekitar '.round($pola['median']).' dtk '
                ."ke $domain dari $client (".$pola['total'].' jeda, '
                .round($pola['frac'] * 100).'% konsisten; peluang muncul dari akses acak '
                .$this->formatPeluang($pola['peluang']).')';

            $temuan[] = [
                'sensor' => $sensor, 'client_ip' => $client, 'jenis' => 'beaconing',
                'tingkat' => $skor >= 80 ? 'kritis' : 'tinggi', 'skor' => $skor,
                'judul' => "Beaconing ke $domain dari $client",
                'attck' => 'T1071.004', 'bukti' => $bukti,
                'mulai_pada' => date('Y-m-d H:i:s', $waktu[0]),
                'sidik' => "beacon|$sensor|$client|$domain",
            ];
        }

        // --- DGA ---
        foreach ($dgaPerClient as $key => $domains) {
            if (count($domains) < self::DGA_MIN_DOMAIN) {
                continue;
            }
            [$sensor, $client] = explode('|', $key, 2);
            $skor = min(95, 60 + count($domains));
            $contoh = implode(', ', array_slice(array_keys($domains), 0, 3));
            $bukti = count($domains)." domain acak unik dari $client, contoh: $contoh";

            $temuan[] = [
                'sensor' => $sensor, 'client_ip' => $client, 'jenis' => 'dga',
                'tingkat' => $skor >= 80 ? 'kritis' : 'tinggi', 'skor' => $skor,
                'judul' => "Query domain acak (DGA) dari $client",
                'attck' => 'T1568.002', 'bukti' => $bukti,
                'mulai_pada' => date('Y-m-d H:i:s', min($domains)),
                'sidik' => "dga|$sensor|$client",
            ];
        }

        return $temuan;
    }

    /**
     * Perangkat dari DHCP yang belum didaftarkan sebagai perangkat sah satuan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function perangkatAsing(): array
    {
        return DB::table('perangkat')
            ->where('dikenal', false)
            ->get()
            ->map(fn ($p) => [
                'sensor' => $p->sensor, 'client_ip' => $p->ip, 'jenis' => 'perangkat_asing',
                'tingkat' => 'tinggi', 'skor' => 75,
                'judul' => 'Perangkat tak dikenal bergabung: '.($p->hostname ?: 'tanpa nama')." ({$p->mac})",
                'attck' => 'T1200',
                'bukti' => "MAC {$p->mac} mendapat IP {$p->ip} dari DHCP sensor {$p->sensor} pada "
                    .substr($p->pertama_terlihat, 0, 19).', hostname: '.($p->hostname ?: '-')
                    .'. Belum terdaftar sebagai perangkat satuan.',
                'mulai_pada' => $p->pertama_terlihat,
                'sidik' => "asing|{$p->sensor}|{$p->mac}",
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $ins
     */
    private function simpan(array $ins): int
    {
        $ins['status'] = 'baru';
        $ins['created_at'] = now();
        $ins['updated_at'] = now();

        $baru = DB::table('incidents')->insertOrIgnore($ins);

        // Insiden lama (sebelum kolom mulai_pada ada) dilengkapi waktu bukti pertamanya.
        if (! $baru && $ins['mulai_pada']) {
            DB::table('incidents')
                ->where('sidik', $ins['sidik'])
                ->whereNull('mulai_pada')
                ->update(['mulai_pada' => $ins['mulai_pada']]);
        }

        return $baru;
    }

    private function diAllowlist(string $domain): bool
    {
        foreach (self::ALLOW_SUFFIX as $s) {
            if ($domain === ltrim($s, '.') || str_ends_with($domain, $s)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Label nama yang didaftarkan pemiliknya: "cloudfront" untuk
     * d1nt6ywwhy4kxl.cloudfront.net, "kemhan" untuk www.kemhan.go.id.
     * Subdomain acak milik CDN sah tidak ikut dinilai sebagai DGA.
     */
    private function labelTerdaftar(string $domain): string
    {
        $labels = array_values(array_filter(explode('.', $domain), fn ($l) => $l !== ''));
        $n = count($labels);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $labels[0];
        }
        if ($n >= 3 && strlen($labels[$n - 1]) === 2 && in_array($labels[$n - 2], self::SLD_UMUM, true)) {
            return $labels[$n - 3];
        }

        return $labels[$n - 2];
    }

    private function entropiShannon(string $teks): float
    {
        $n = strlen($teks);
        if ($n === 0) {
            return 0.0;
        }
        $frek = count_chars($teks, 1);
        $h = 0.0;
        foreach ($frek as $c) {
            $p = $c / $n;
            $h -= $p * log($p, 2);
        }

        return $h;
    }

    private function rasioKonsonan(string $teks): float
    {
        $huruf = preg_replace('/[^a-z]/', '', strtolower($teks));
        $n = strlen($huruf);
        if ($n === 0) {
            return 0.0;
        }
        $konsonan = strlen(preg_replace('/[aeiou]/', '', $huruf));

        return $konsonan / $n;
    }

    private function tampakDga(string $domain): bool
    {
        $label = $this->labelTerdaftar($domain);
        if (strlen($label) < 10) {
            return false;
        }

        return $this->entropiShannon($label) >= self::DGA_MIN_ENTROPI
            && $this->rasioKonsonan($label) >= self::DGA_MIN_KONSONAN;
    }

    /**
     * Gabungkan timestamp (sudah urut) yang berjarak <= $jendela detik menjadi satu.
     *
     * @param  array<int>  $urut
     * @return array<int>
     */
    private function rapatkan(array $urut, int $jendela): array
    {
        $hasil = [];
        foreach ($urut as $t) {
            if (! $hasil || $t - end($hasil) > $jendela) {
                $hasil[] = $t;
            }
        }

        return $hasil;
    }

    /**
     * Cari pola beacon yang tahan terhadap jeda besar antar-sesi: ambil jeda
     * median, lalu hitung berapa banyak jeda yang konsisten di sekitarnya.
     * Jeda besar (mis. saat perangkat mati) jadi pencilan dan diabaikan.
     *
     * @param  array<int>  $waktuUrut
     * @return array{teratur: int, total: int, median: float, frac: float, peluang: float}|null
     */
    private function polaBeacon(array $waktuUrut): ?array
    {
        $jeda = [];
        for ($i = 1; $i < count($waktuUrut); $i++) {
            $d = $waktuUrut[$i] - $waktuUrut[$i - 1];
            if ($d > 0) {
                $jeda[] = $d;
            }
        }
        if (count($jeda) < self::BEACON_MIN_TERATUR) {
            return null;
        }

        $urut = $jeda;
        sort($urut);
        $n = count($urut);
        $median = $n % 2 ? $urut[intdiv($n, 2)] : ($urut[$n / 2 - 1] + $urut[$n / 2]) / 2;
        if ($median < self::BEACON_MIN_MEDIAN) {
            return null;
        }

        $bawah = $median * (1 - self::BEACON_TOLERANSI);
        $atas = $median * (1 + self::BEACON_TOLERANSI);
        $teratur = 0;
        foreach ($jeda as $j) {
            if ($j >= $bawah && $j <= $atas) {
                $teratur++;
            }
        }
        if ($teratur < self::BEACON_MIN_TERATUR) {
            return null;
        }
        $peluang = $this->ekorBinomial(count($jeda), $teratur, self::BEACON_PELUANG_ACAK);
        if ($peluang > self::BEACON_ALPHA) {
            return null;
        }

        return [
            'teratur' => $teratur,
            'total' => count($jeda),
            'median' => $median,
            'frac' => $teratur / count($jeda),
            'peluang' => $peluang,
        ];
    }

    /**
     * Peluang paling sedikit $k dari $n jeda konsisten bila tiap jeda hanya
     * kebetulan konsisten dengan peluang $p. Dihitung di ruang log agar tidak underflow.
     */
    private function ekorBinomial(int $n, int $k, float $p): float
    {
        $logP = log($p);
        $logQ = log(1 - $p);
        $logPmf = $n * $logQ; // k = 0
        $ekor = 0.0;
        for ($i = 1; $i <= $n; $i++) {
            $logPmf += log(($n - $i + 1) / $i) + $logP - $logQ;
            if ($i >= $k) {
                $ekor += exp($logPmf);
            }
        }

        return min(1.0, $ekor);
    }

    private function formatPeluang(float $peluang): string
    {
        if ($peluang < 1e-9) {
            return 'kurang dari 1 banding 1 miliar';
        }

        return '1 banding '.number_format(1 / $peluang, 0, ',', '.');
    }

    /**
     * Jalankan detektor terhadap skenario sintetis yang hasilnya sudah diketahui,
     * lalu laporkan deteksi benar, alarm palsu, dan keterbatasan yang diketahui.
     */
    private function evaluasi(): int
    {
        $seedAwal = (int) $this->option('seed');
        $percobaan = max(1, (int) $this->option('percobaan'));

        $rekap = [];
        $totalQuery = 0;

        for ($k = 0; $k < $percobaan; $k++) {
            mt_srand($seedAwal + $k);

            foreach ($this->skenarioEvaluasi() as $i => $s) {
                $totalQuery += count($s['data']);
                $temuan = $this->temukan($s['data']);
                $hasil = collect($temuan)->pluck('jenis')->unique()->sort()->values()->all();
                $harapan = $s['harapan'];
                sort($harapan);
                $sesuai = $hasil === $harapan;

                if ($this->output->isVerbose()) {
                    foreach ($temuan as $t) {
                        $this->line('  [seed '.($seedAwal + $k)."] [{$s['nama']}] {$t['bukti']}");
                    }
                }

                $rekap[$i] ??= ['kelompok' => $s['kelompok'], 'nama' => $s['nama'], 'harapan' => $harapan,
                    'catatan' => $s['catatan'], 'sesuai' => 0, 'meleset' => []];
                if ($sesuai) {
                    $rekap[$i]['sesuai']++;
                } else {
                    $rekap[$i]['meleset'][] = $seedAwal + $k;
                }
            }
        }

        $this->table(
            ['Kelompok', 'Skenario', 'Diharapkan', 'Sesuai', 'Catatan'],
            array_map(fn ($r) => [
                $r['kelompok'],
                $r['nama'],
                $r['harapan'] ? implode(', ', $r['harapan']) : 'tidak ada insiden',
                "{$r['sesuai']}/$percobaan".($r['meleset'] ? ' (meleset seed '.implode(',', array_slice($r['meleset'], 0, 5)).')' : ''),
                $r['catatan'],
            ], $rekap),
        );

        $perKelompok = collect($rekap)->groupBy('kelompok')->map(fn ($g) => [$g->sum('sesuai'), $g->count() * $percobaan]);
        [$benar, $serangan] = $perKelompok['serangan'];
        [$bersih, $normal] = $perKelompok['normal'];
        $this->line(sprintf('Tingkat deteksi serangan : %d/%d (%.1f%%)', $benar, $serangan, $benar / $serangan * 100));
        $this->line(sprintf('Alarm palsu trafik normal: %d/%d (%.1f%%)', $normal - $bersih, $normal, ($normal - $bersih) / $normal * 100));
        $this->line(sprintf('Keterbatasan sesuai dokumentasi: %d/%d', ...$perKelompok['keterbatasan']));
        $this->line("Total query sintetis: $totalQuery dari $percobaan percobaan (seed $seedAwal..".($seedAwal + $percobaan - 1).')');

        return collect($rekap)->every(fn ($r) => $r['sesuai'] === $percobaan) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Skenario 60 menit per perangkat. "harapan" adalah jenis insiden yang seharusnya muncul.
     *
     * @return array<int, array{kelompok: string, nama: string, harapan: array<int, string>, catatan: string, data: array<int, object>}>
     */
    private function skenarioEvaluasi(): array
    {
        $t0 = strtotime('2026-01-01 08:00:00');

        $webNormal = [
            'www.youtube.com', 'i.ytimg.com', 'r3---sn-npoe7nez.googlevideo.com', 'www.detik.com',
            'cdn.detik.net.id', 'www.kompas.com', 'asset.kompas.com', 'www.tokopedia.com',
            'images.tokopedia.net', 'www.bca.co.id', 'www.kemhan.go.id', 'tniad.mil.id',
            'www.instagram.com', 'scontent.cdninstagram.com', 'web.whatsapp.com', 'mmg.whatsapp.net',
            'login.microsoftonline.com', 'outlook.office365.com', 'api.github.com', 'www.linkedin.com',
            'x.com', 'abs.twimg.com', 'shopee.co.id', 'cf.shopee.co.id', 'www.google.com',
            'fonts.gstatic.com', 'id.wikipedia.org', 'upload.wikimedia.org', 'www.tiktok.com',
            'p16-sign-sg.tiktokcdn.com', 'www.liputan6.com', 'www.cnnindonesia.com',
        ];
        $cdnAcak = [];
        for ($i = 0; $i < 30; $i++) {
            $cdnAcak[] = 'd'.$this->acakHuruf(13, 'abcdefghijklmnopqrstuvwxyz0123456789').'.cloudfront.net';
            $cdnAcak[] = 'e'.mt_rand(1000, 9999).'.'.$this->acakHuruf(5, 'abcdefghijklmnopqrstuvwxyz0123456789').'.akamaiedge.net';
        }
        $kamus = ['merah', 'bunga', 'rumah', 'kuda', 'langit', 'sungai', 'batu', 'angin', 'kopi', 'garam', 'besi', 'awan'];

        return [
            ['kelompok' => 'serangan', 'nama' => 'Beacon C2 tetap 30 dtk (alat uji lab)', 'harapan' => ['beaconing'],
                'catatan' => 'Pola Cobalt Strike tanpa jitter',
                'data' => $this->berkala('10.0.0.11', 'c2-sim.example', $t0, 30, 0.02, 60)],
            ['kelompok' => 'serangan', 'nama' => 'Beacon C2 jitter +-20%', 'harapan' => ['beaconing'],
                'catatan' => 'Malware menyamarkan keteraturan',
                'data' => $this->berkala('10.0.0.12', 'update-cek.example', $t0, 45, 0.20, 60)],
            ['kelompok' => 'serangan', 'nama' => 'Beacon C2 jitter +-50%', 'harapan' => ['beaconing'],
                'catatan' => 'Jitter besar; sesekali butuh data >60 menit untuk yakin',
                'data' => $this->berkala('10.0.0.13', 'cdn-sync.example', $t0, 40, 0.50, 60)],
            ['kelompok' => 'serangan', 'nama' => 'Beacon lambat tiap 5 menit', 'harapan' => ['beaconing'],
                'catatan' => 'Hanya 12 query per jam',
                'data' => $this->berkala('10.0.0.14', 'telemetri.example', $t0, 300, 0.10, 60)],
            ['kelompok' => 'serangan', 'nama' => 'DGA 40 domain acak', 'harapan' => ['dga'],
                'catatan' => 'Malware mencari server C2 yang berpindah',
                'data' => $this->dga('10.0.0.15', $t0, 40)],
            ['kelompok' => 'serangan', 'nama' => 'CCTV/IoT melapor ke cloud vendor tiap 60 dtk', 'harapan' => ['beaconing'],
                'catatan' => 'Phone-home perangkat IoT patut ditinjau analis',
                'data' => $this->berkala('10.0.0.16', 'p2p.cam-vendor.example', $t0, 60, 0.05, 60)],

            ['kelompok' => 'normal', 'nama' => 'Penjelajahan web 3 pengguna, 60 menit', 'harapan' => [],
                'catatan' => 'Waktu akses acak seperti manusia',
                'data' => [
                    ...$this->acak('10.0.1.21', $webNormal, $t0, 60, 4),
                    ...$this->acak('10.0.1.22', $webNormal, $t0, 60, 6),
                    ...$this->acak('10.0.1.23', $webNormal, $t0, 60, 3),
                ]],
            ['kelompok' => 'normal', 'nama' => 'NTP dan pembaruan OS', 'harapan' => [],
                'catatan' => 'Teratur tapi masuk daftar izin',
                'data' => [
                    ...$this->berkala('10.0.1.24', '0.pool.ntp.org', $t0, 64, 0.0, 60),
                    ...$this->berkala('10.0.1.24', 'dl-cdn.alpinelinux.org', $t0, 300, 0.05, 60),
                ]],
            ['kelompok' => 'normal', 'nama' => 'CDN dengan subdomain acak (60 nama)', 'harapan' => [],
                'catatan' => 'Nama acak di subdomain CDN sah, bukan DGA',
                'data' => $this->acak('10.0.1.25', $cdnAcak, $t0, 60, 3)],

            ['kelompok' => 'keterbatasan', 'nama' => 'Heartbeat aplikasi sah tiap 5 menit', 'harapan' => ['beaconing'],
                'catatan' => 'Alarm palsu yang diketahui: analis tandai false positive, tidak dilaporkan ulang',
                'data' => $this->berkala('10.0.2.31', 'push.aplikasi-chat.example', $t0, 300, 0.02, 60)],
            ['kelompok' => 'keterbatasan', 'nama' => 'DGA berbasis kamus (kata bermakna)', 'harapan' => [],
                'catatan' => 'Lolos deteksi; perlu model n-gram/ML ke depan',
                'data' => $this->dgaKamus('10.0.2.32', $kamus, $t0, 40)],
        ];
    }

    /**
     * Query berkala (beacon). Tiap ketukan = query A lalu AAAA, seperti getaddrinfo.
     *
     * @return array<int, object>
     */
    private function berkala(string $client, string $domain, int $mulai, int $jeda, float $jitter, int $menit): array
    {
        $data = [];
        $selesai = $mulai + $menit * 60;
        for ($t = $mulai; $t < $selesai; $t += (int) round($jeda * (1 + $this->acakAntara(-$jitter, $jitter)))) {
            $data[] = $this->query($client, $domain, $t, 'A');
            $data[] = $this->query($client, $domain, $t + 1, 'AAAA');
        }

        return $data;
    }

    /**
     * Akses acak bergaya manusia (proses Poisson), domain populer lebih sering dipilih.
     *
     * @param  array<int, string>  $domains
     * @return array<int, object>
     */
    private function acak(string $client, array $domains, int $mulai, int $menit, float $perMenit): array
    {
        $bobot = array_map(fn ($i) => 1 / ($i + 1), array_keys($domains));
        $totalBobot = array_sum($bobot);

        $data = [];
        $t = $mulai;
        $selesai = $mulai + $menit * 60;
        while (true) {
            $t += (int) max(1, round(-log(max(1e-9, mt_rand() / mt_getrandmax())) * 60 / $perMenit));
            if ($t >= $selesai) {
                break;
            }
            $pilih = $this->acakAntara(0, $totalBobot);
            $dipilih = $domains[0];
            foreach ($domains as $i => $domain) {
                $pilih -= $bobot[$i];
                if ($pilih <= 0) {
                    $dipilih = $domain;
                    break;
                }
            }
            $data[] = $this->query($client, $dipilih, $t, 'A');
        }

        return $data;
    }

    /**
     * @return array<int, object>
     */
    private function dga(string $client, int $mulai, int $jumlah): array
    {
        $tld = ['com', 'net', 'org', 'info'];
        $data = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $nama = $this->acakHuruf(mt_rand(12, 20), 'abcdefghijklmnopqrstuvwxyz').'.'.$tld[mt_rand(0, 3)];
            $data[] = $this->query($client, $nama, $mulai + $i * 2, 'A');
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $kamus
     * @return array<int, object>
     */
    private function dgaKamus(string $client, array $kamus, int $mulai, int $jumlah): array
    {
        $data = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $nama = $kamus[mt_rand(0, count($kamus) - 1)].$kamus[mt_rand(0, count($kamus) - 1)].$kamus[mt_rand(0, count($kamus) - 1)].'.com';
            $data[] = $this->query($client, $nama, $mulai + $i * 2, 'A');
        }

        return $data;
    }

    private function query(string $client, string $domain, int $ts, string $qtype): object
    {
        return (object) [
            'sensor' => 'EVAL', 'client_ip' => $client, 'domain' => $domain,
            'qtype' => $qtype, 'queried_at' => date('Y-m-d H:i:s', $ts),
        ];
    }

    private function acakAntara(float $bawah, float $atas): float
    {
        return $bawah + (mt_rand() / mt_getrandmax()) * ($atas - $bawah);
    }

    private function acakHuruf(int $panjang, string $huruf): string
    {
        $hasil = '';
        for ($i = 0; $i < $panjang; $i++) {
            $hasil .= $huruf[mt_rand(0, strlen($huruf) - 1)];
        }

        return $hasil;
    }
}
