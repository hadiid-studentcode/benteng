# BENTENG — Sensor Siber Tepi untuk Jaringan Satuan

> Proyek untuk **Kartika Cyber Hackathon 2026** (PUSSIBERAD × CODEPOLITAN),
> kategori **Penangkalan**. Deadline submission: **15 November 2026**.

BENTENG adalah sistem pertahanan jaringan untuk satuan kecil TNI AD yang tidak
punya personel keamanan siber khusus. Sensor berbasis **OpenWrt** dipasang di
tepi jaringan satuan untuk mendeteksi dan memblokir ancaman, lalu dikelola dari
satu **dashboard pusat**. Analis melihat insiden, membaca penjelasannya, dan
mengarantina perangkat dengan satu klik. Semua berjalan **on-premise**, tanpa
layanan cloud asing.

Analoginya: BENTENG adalah "satpam pintar" di gerbang jaringan satuan yang
menolak tamu berbahaya, mengenali gerak-gerik mencurigakan, mencatat siapa yang
masuk, dan mengisolasi perangkat yang terinfeksi.

---

## 1. Status proyek (per 25 Okt 2026)

**Sudah selesai dan teruji end-to-end di lab:**
- ✅ Sensor OpenWrt mencatat query DNS (dnsmasq) dan mengirim ke server tiap menit.
- ✅ Server Laravel menerima & menyimpan DNS ke SQLite (anti-dobel via `line_hash`).
- ✅ Detektor `php artisan benteng:deteksi` menemukan **beaconing** (T1071.004)
  dan **DGA** (T1568.002), dengan daftar izin agar NTP/update tidak salah tangkap.
- ✅ Dashboard `/dashboard` menampilkan metrik, insiden, dan tabel perangkat.
- ✅ Karantina: tombol di dashboard → sensor menjalankan `nftables` → koneksi
  perangkat putus. Lepas karantina juga jalan. Semua tercatat di `audit_logs`.

**Belum dikerjakan (roadmap, lihat bagian 9):**
- ⬜ Halaman karantina (captive portal) di sisi perangkat korban.
- ⬜ Penjelasan insiden berbahasa Indonesia dari template playbook (+ opsional LLM lokal).
- ⬜ Deteksi perangkat asing (MAC baru) & CCTV/IoT phone-home.
- ⬜ Materi submission: video demo, slide, proposal, repo GitHub rapi.

---

## 2. Arsitektur

```
┌─────────────── Jaringan satuan (lab: VirtualBox Internal "benteng-lan") ───────────────┐
│                                                                                        │
│   VM korban (Alpine)                 Sensor BENTENG (OpenWrt 24.10.8)                   │
│   192.168.1.142          ──query──▶  192.168.1.1 (br-lan / LAN)                         │
│   - benteng-trafik-uji.py            - dnsmasq (logqueries=1)                           │
│     (alat uji beacon+DGA)            - benteng-collect  (buffer log DNS)                │
│                                      - benteng-agent    (kirim ke server tiap menit)    │
│                                      - benteng-responder(karantina via nftables)        │
│                                      - WAN: NAT, mgmt: 192.168.56.20 (host-only)        │
└───────────────────────────────────────────────┬────────────────────────────────────────┘
                                                 │ HTTP (host-only 192.168.56.x)
                                                 ▼
                         Server pusat = laptop Windows 11 (FlyEnv)
                         http://192.168.56.1:8000  (php artisan serve --host=0.0.0.0)
                         - Laravel + SQLite (database/database.sqlite)
                         - API: /api/ingest/dns, /api/sensors/{s}/commands[/ack]
                         - Dashboard: /dashboard  (+ aksi karantina/false-positive/lepas)
                         - Detektor: php artisan benteng:deteksi
```

**Prinsip desain yang dipegang (penting saat menambah fitur):**
1. **Perilaku di atas konten** — deteksi pola koordinasi/keteraturan, bukan isi/opini.
2. **Setiap klaim ada buktinya** — insiden selalu punya kolom `bukti`.
3. **Human-in-the-loop** — AI/sistem memberi saran; karantina butuh klik analis.
4. **Siap berdaulat** — semua on-premise, tanpa API cloud asing.
5. **Jujur** — data lab ditandai simulasi; jangan mengklaim lebih dari yang ada.

---

## 3. Lingkungan lab (VirtualBox di Windows 11, RAM 8 GB)

| Mesin | OS | IP | Jaringan VirtualBox |
| --- | --- | --- | --- |
| `benteng-sensor` | OpenWrt 24.10.8 x86 (EFI) | LAN 192.168.1.1 · mgmt 192.168.56.20 | Adapter1 Internal `benteng-lan`, Adapter2 NAT, Adapter3 Host-only |
| `benteng-korban` | Alpine Linux | 192.168.1.142 (DHCP dari sensor) | Adapter1 Internal `benteng-lan` |
| Server | Windows 11 host | 192.168.56.1 (host-only) | — |

Catatan lab: VirtualBox NAT tidak punya IPv6, jadi sensor memakai
`filter_aaaa=1` di dnsmasq. Di lapangan (jaringan ber-IPv6), opsi ini tidak perlu.

Di lapangan, "sensor" = router OpenWrt fisik atau mini-PC x86; tidak terikat VM.

---

## 4. Tech stack

- **Sensor:** OpenWrt 24.10.8, dnsmasq, nftables, paket `conntrack`,
  skrip shell (ash/BusyBox) + procd init.
- **Server:** PHP 8.3 + Laravel (via FlyEnv di Windows), **SQLite**, Blade.
  Tidak pakai Docker/Python agar muat di 8 GB RAM.
- **Komunikasi sensor↔server:** HTTP + header token `X-Sensor-Token`.
  (Di lapangan idealnya lewat WireGuard; di lab via host-only.)
- **Link project (rencana submission):** repo GitHub + demo replay statis di
  GitHub Pages + video. Tanpa VPS.

---

## 5. Struktur repo

```
benteng/
├─ benteng-server/                    # proyek Laravel (server pusat)
│  ├─ app/Http/Controllers/
│  │  ├─ IngestController.php          # terima DNS dari sensor  (POST /api/ingest/dns)
│  │  ├─ CommandController.php         # perintah utk sensor      (GET/POST /api/sensors/..)
│  │  └─ DashboardController.php       # dashboard + aksi karantina/false-positive/lepas
│  ├─ app/Console/Commands/
│  │  └─ DeteksiCommand.php            # detektor  (php artisan benteng:deteksi)
│  ├─ config/benteng.php               # BENTENG_SENSOR_TOKEN
│  ├─ database/migrations/
│  │  ├─ ..._create_dns_events_table.php
│  │  ├─ ..._create_incidents_table.php
│  │  └─ ..._create_commands_table.php # commands + audit_logs
│  ├─ resources/views/benteng/dashboard.blade.php
│  └─ routes/{web.php, api.php}        # lihat bagian 7
│
├─ sensor/                            # file untuk OpenWrt (disalin ke sensor via scp)
│  ├─ benteng-collect                 # -> /usr/bin/benteng-collect     (buffer log DNS)
│  ├─ benteng-collect.init            # -> /etc/init.d/benteng-collect
│  ├─ benteng-agent                   # -> /usr/bin/benteng-agent       (kirim ke server, cron 1 menit)
│  ├─ benteng-responder               # -> /usr/bin/benteng-responder   (polling perintah 5 dtk)
│  ├─ benteng-responder.init          # -> /etc/init.d/benteng-responder
│  └─ benteng-nft.conf                # -> /etc/benteng/benteng-nft.conf (tabel nftables karantina)
│
├─ lab/
│  └─ benteng-trafik-uji.py           # alat uji di VM korban (beacon + DGA). BUKAN malware.
│
└─ docs/                              # (opsional) pindahkan LANGKAH-*.md ke sini
   ├─ LANGKAH-2-deteksi.md
   └─ LANGKAH-3-karantina.md
```

---

## 6. Alur data

**6a. Pengumpulan DNS**
1. VM korban meng-query domain → resolver = sensor (192.168.1.1).
2. dnsmasq menulis `query[...] ... from <ip>` ke log sistem.
3. `benteng-collect` (service) menyalin baris query ke `/tmp/benteng-dns.buf`.
4. `benteng-agent` (cron, tiap menit) memindahkan buffer ke `.send`, lalu
   mengirim **baris log mentah** (text/plain) ke `POST /api/ingest/dns` dengan token.
   - Sukses dinilai dari isi balasan (`"diterima"`), bukan exit code wget
     (php artisan serve kadang memutus koneksi lebih awal).
5. `IngestController@dns` mem-parse tiap baris (regex), simpan ke `dns_events`
   dengan `line_hash` unik → `insertOrIgnore` (anti-dobel saat kirim ulang).

**6b. Deteksi**
6. `php artisan benteng:deteksi` membaca `dns_events`, lalu menulis `incidents`
   (unik via kolom `sidik`, aman dijalankan berulang). Lihat bagian 8.

**6c. Respons (karantina)**
7. Analis klik **Karantina** di dashboard → `DashboardController@karantina`
   membuat baris di `commands` (aksi=`quarantine`, target_ip), ubah insiden
   jadi `dikarantina`, tulis `audit_logs`.
8. `benteng-responder` (loop 5 dtk) GET `/api/sensors/BTG-02/commands` →
   dapat baris teks `<id> quarantine <ip>` → `nft add element inet benteng
   karantina_v4 { <ip> }` → koneksi perangkat diputus (drop forward).
9. Responder ACK ke `/api/sensors/BTG-02/commands/{id}/ack?hasil=ok` →
   `CommandController@ack` tandai `commands.status=selesai` + `audit_logs`.
10. **Lepas** = aksi `unquarantine` → `nft delete element ...`.

---

## 7. Routes

`routes/api.php`:
```php
Route::post('/ingest/dns', [IngestController::class, 'dns']);
Route::get('/sensors/{sensor}/commands', [CommandController::class, 'ambil']);
Route::post('/sensors/{sensor}/commands/{id}/ack', [CommandController::class, 'ack']);
```

`routes/web.php`:
```php
Route::get('/dashboard', [DashboardController::class, 'index'])->name('benteng.dashboard');
Route::post('/dashboard/incidents/{id}/karantina', [DashboardController::class, 'karantina']);
Route::post('/dashboard/incidents/{id}/false-positive', [DashboardController::class, 'falsePositive']);
Route::post('/dashboard/incidents/{id}/lepas', [DashboardController::class, 'lepas']);
```

---

## 8. Skema database & logika deteksi

**Tabel:**
- `dns_events`: sensor, queried_at, qtype, domain, client_ip, **line_hash (unique)**.
- `incidents`: sensor, client_ip, jenis (`beaconing`|`dga`), tingkat
  (`kritis`|`tinggi`), skor (0–99), judul, attck, bukti, status
  (`baru`|`dikarantina`|`false_positive`), **sidik (unique)**.
- `commands`: sensor, aksi (`quarantine`|`unquarantine`), target_ip, incident_id,
  status (`pending`|`selesai`|`gagal`), hasil, diambil_pada, selesai_pada.
- `audit_logs`: pelaku (`analis`|`sensor:BTG-02`), aksi, target, incident_id, created_at.

**Deteksi (`DeteksiCommand.php`), ambang di konstanta kelas:**
- **Beaconing** — kelompokkan per (sensor, client, domain). Gabungkan query
  berdekatan (≤5 dtk, untuk pasangan A+AAAA). Ambil **median jeda**; hitung
  berapa jeda yang konsisten di ±30% median. Jika ≥6 jeda konsisten dan median
  ≥5 dtk → insiden. **Tahan terhadap jeda besar antar-sesi** (perangkat sempat mati).
- **DGA** — domain "acak" dideteksi dari ciri huruf label terpanjang: entropi
  Shannon ≥3.2, rasio konsonan ≥0.62, panjang ≥10. Jika ≥8 domain acak unik per
  perangkat → insiden.
- **Daftar izin** (`ALLOW_SUFFIX`): NTP, windowsupdate, repo distro, dsb. —
  tidak pernah jadi insiden walau berpola teratur. Tambah domain sah di sini
  kalau ada false positive.

---

## 9. Setup dari nol (ringkas)

**Server (Windows, FlyEnv):**
```powershell
composer create-project laravel/laravel benteng-server
cd benteng-server
php artisan install:api
# salin file controller/command/migrations/view, tambahkan routes (bagian 7)
# .env: DB_CONNECTION=sqlite  +  BENTENG_SENSOR_TOKEN=<token acak>
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

**Sensor (OpenWrt, via scp + ssh root@192.168.56.20):**
```sh
# paket
opkg update && opkg install conntrack wireguard-tools luci-proto-wireguard
# dnsmasq log + filter IPv6 (khusus lab)
uci set dhcp.@dnsmasq[0].logqueries='1'; uci set dhcp.@dnsmasq[0].filter_aaaa='1'
uci commit dhcp; /etc/init.d/dnsmasq restart
# skrip (benteng-collect, benteng-agent, benteng-responder, *.init, benteng-nft.conf)
# -> set token di benteng-agent & benteng-responder, chmod +x, sed -i 's/\r$//'
mkdir -p /etc/benteng   # tempat benteng-nft.conf
# services
/etc/init.d/benteng-collect enable;   /etc/init.d/benteng-collect start
/etc/init.d/benteng-responder enable; /etc/init.d/benteng-responder start
echo '* * * * * /usr/bin/benteng-agent' > /etc/crontabs/root
/etc/init.d/cron enable; /etc/init.d/cron restart
```

**VM korban (Alpine):** jalankan `python3 /root/benteng-trafik-uji.py --menit 6`.

**Deteksi:** `php artisan benteng:deteksi` (atau `--ulang=30` untuk demo).

> ⚠️ **Token sensor** harus sama di `benteng-agent`, `benteng-responder`, dan
> `.env` (`BENTENG_SENSOR_TOKEN`). Ganti token baru sebelum submission karena
> token lama sudah tersebar saat pengembangan.

---

## 10. Alur demo (untuk video)

1. Dashboard normal, lalu jalankan simulator di VM korban.
2. `php artisan benteng:deteksi` → 2 insiden kritis muncul (beaconing + DGA).
3. Split screen: terminal korban (kiri) + dashboard (kanan).
4. Di korban `ping 8.8.8.8` → jalan.
5. Klik **Karantina perangkat** → status jadi "dikarantina".
6. Di korban `ping 8.8.8.8` → **gagal**. (momen kunci)
7. Tutup: keputusan di tangan analis, semua tercatat di audit log.

---

## 11. Catatan etika & keamanan

- `benteng-trafik-uji.py` adalah **alat uji**, bukan malware: hanya menanyakan
  domain ke DNS (TLD `.example`/`.test`/`.invalid` yang dicadangkan RFC) dan
  "beacon" ke `c2-sim.example`. Tidak ada payload, perintah jarak jauh, atau
  penyembunyian.
- IP yang dikarantina berasal dari data insiden (IP sudah divalidasi saat
  ingest) dan divalidasi ulang sebelum masuk perintah `nft`.
- Tabel nftables `inet benteng` terpisah dari firewall bawaan (fw4), prioritas
  -1. Tidak mengubah aturan fw4.
- **Jangan commit `.env`** (sudah di `.gitignore` Laravel) atau token ke GitHub.
- Privasi: sistem memantau metadata DNS/perilaku, bukan isi komunikasi.
  Log sebaiknya retensi terbatas & akses berbasis peran (rencana lapangan).

---

## 12. Untuk AI assistant (Claude di VS Code)

- Server berjalan di Windows via FlyEnv; **bukan** Docker/Python. Jangan sarankan
  solusi yang butuh Python/VPS kecuali diminta.
- Bahasa UI, komentar kode, dan nama kolom memakai **Bahasa Indonesia**
  (mis. `aksi`, `tingkat`, `bukti`, `dikarantina`). Ikuti konvensi ini.
- Sensor pakai shell BusyBox (ash) — hindari bashism; uji mental terhadap
  keterbatasan BusyBox (mis. `wget` BusyBox, bukan GNU).
- Prioritas pengerjaan: **demo yang jalan > jumlah fitur**. Satu alur mulus
  (deteksi → dashboard → karantina) sudah jadi; jaga jangan sampai rusak saat
  menambah fitur.
- Deadline 15 Nov 2026. Fokus berikutnya: materi submission (video, slide,
  proposal, repo rapi), lalu fitur opsional di bagian 1 bila sempat.
