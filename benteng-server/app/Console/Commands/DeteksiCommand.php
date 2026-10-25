<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Detektor BENTENG: membaca dns_events, mencari pola beaconing dan DGA,
 * lalu menulis temuan ke tabel incidents.
 *
 * Jalankan sekali:   php artisan benteng:deteksi
 * Jalankan berulang: php artisan benteng:deteksi --ulang=30
 */
class DeteksiCommand extends Command
{
    protected $signature = 'benteng:deteksi {--ulang=0 : ulangi tiap N detik (0 = sekali)}';

    protected $description = 'Deteksi beaconing dan DGA dari data DNS sensor';

    // Domain yang wajar berpola teratur; jangan dijadikan insiden.
    private const ALLOW_SUFFIX = [
        '.pool.ntp.org', 'ntp.org', '.windowsupdate.com', '.alpinelinux.org',
        '.ubuntu.com', '.debian.org', '.archlinux.org', 'openwrt.org',
        '.cloudflare.com', '.google.com', '.gstatic.com',
    ];

    // Query ke domain sama dalam sekian detik dianggap satu (mis. pasangan A+AAAA
    // dari getaddrinfo, atau percobaan ulang), supaya jeda beacon tidak berselang-seling.
    private const BEACON_RAPAT_DETIK = 5;
    // Minimal berapa jeda yang konsisten di sekitar median sampai disebut beaconing.
    private const BEACON_MIN_TERATUR = 6;
    // Sebuah jeda disebut "teratur" bila dalam +-30% dari jeda median.
    private const BEACON_TOLERANSI = 0.30;
    // Minimal median jeda (detik); di bawah ini dianggap semburan, bukan beacon.
    private const BEACON_MIN_MEDIAN = 5;
    private const DGA_MIN_ENTROPI = 3.2;
    private const DGA_MIN_KONSONAN = 0.62;
    private const DGA_MIN_DOMAIN = 8;

    public function handle(): int
    {
        $ulang = (float) $this->option('ulang');

        do {
            $baru = $this->analisis();
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
        $perClientDomain = [];   // "sensor|client|domain" => [timestamp detik]
        $dgaPerClient = [];      // "sensor|client" => [domain => true]

        DB::table('dns_events')
            ->select('sensor', 'client_ip', 'domain', 'qtype', 'queried_at')
            ->orderBy('queried_at')
            ->chunk(5000, function ($rows) use (&$perClientDomain, &$dgaPerClient) {
                foreach ($rows as $r) {
                    if ($r->qtype === 'PTR' || $this->diAllowlist($r->domain)) {
                        continue;
                    }
                    $ts = strtotime(substr($r->queried_at, 0, 19));
                    $perClientDomain[$r->sensor.'|'.$r->client_ip.'|'.$r->domain][] = $ts;

                    if ($this->tampakDga($r->domain)) {
                        $dgaPerClient[$r->sensor.'|'.$r->client_ip][$r->domain] = true;
                    }
                }
            });

        $baru = 0;

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
            $bukti = $pola['teratur']." jeda konsisten sekitar ".round($pola['median'])." dtk "
                ."ke $domain dari $client (".$pola['total']." jeda, "
                .round($pola['frac'] * 100)."% konsisten)";

            $baru += $this->simpan([
                'sensor' => $sensor, 'client_ip' => $client, 'jenis' => 'beaconing',
                'tingkat' => $skor >= 80 ? 'kritis' : 'tinggi', 'skor' => $skor,
                'judul' => "Beaconing ke $domain dari $client",
                'attck' => 'T1071.004', 'bukti' => $bukti,
                'sidik' => "beacon|$sensor|$client|$domain",
            ]);
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

            $baru += $this->simpan([
                'sensor' => $sensor, 'client_ip' => $client, 'jenis' => 'dga',
                'tingkat' => $skor >= 80 ? 'kritis' : 'tinggi', 'skor' => $skor,
                'judul' => "Query domain acak (DGA) dari $client",
                'attck' => 'T1568.002', 'bukti' => $bukti,
                'sidik' => "dga|$sensor|$client",
            ]);
        }

        return $baru;
    }

    private function simpan(array $ins): int
    {
        $ins['status'] = 'baru';
        $ins['created_at'] = now();
        $ins['updated_at'] = now();

        return DB::table('incidents')->insertOrIgnore($ins);
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

    private function labelTerpanjang(string $domain): string
    {
        $labels = array_filter(explode('.', $domain), fn ($l) => $l !== '');
        if (! $labels) {
            return '';
        }
        $labels = array_values($labels);
        // Buang TLD (label terakhir), lalu ambil label terpanjang.
        $kandidat = count($labels) > 1 ? array_slice($labels, 0, -1) : $labels;
        usort($kandidat, fn ($a, $b) => strlen($b) - strlen($a));

        return $kandidat[0];
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
        $label = $this->labelTerpanjang($domain);
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
     * @return array{teratur: int, total: int, median: float, frac: float}|null
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

        return [
            'teratur' => $teratur,
            'total' => count($jeda),
            'median' => $median,
            'frac' => $teratur / count($jeda),
        ];
    }
}