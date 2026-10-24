# BENTENG starter: sensor → server (alur data DNS pertama)

Tujuan langkah ini: setiap query DNS dari VM korban tercatat di sensor, dikirim otomatis tiap menit ke server Laravel di Windows, lalu tersimpan di database.

```
VM korban ──query DNS──▶ sensor (OpenWrt) ──JSON tiap 60 detik──▶ Laravel di Windows (192.168.56.1:8000) ──▶ SQLite
```

Isi paket:

| File | Dipasang di |
| --- | --- |
| `sensor/benteng-collect` | sensor: `/usr/bin/benteng-collect` |
| `sensor/benteng-collect.init` | sensor: `/etc/init.d/benteng-collect` |
| `sensor/benteng-agent` | sensor: `/usr/bin/benteng-agent` |
| `server/app/Http/Controllers/IngestController.php` | proyek Laravel, path yang sama |
| `server/config/benteng.php` | proyek Laravel, path yang sama |
| `server/database/migrations/2026_10_24_000001_create_dns_events_table.php` | proyek Laravel, path yang sama |
| `server/routes/api-tambahan.php` | isinya ditempel ke `routes/api.php` |

---

## A. Server di Windows

1. Buka FlyEnv, pastikan **PHP 8.3** (atau lebih baru) dan **Composer** sudah terpasang dan aktif.
2. Buka PowerShell di folder kerjamu, lalu buat proyek:
   ```powershell
   composer create-project laravel/laravel benteng-server
   cd benteng-server
   php artisan install:api
   ```
   Kalau `install:api` bertanya soal migrasi, jawab `yes`.
3. Salin isi folder `server/` dari paket ini ke dalam `benteng-server/`, dengan path yang sama.
4. Buka `routes/api.php`, tambahkan dua baris dari `server/routes/api-tambahan.php`:
   ```php
   use App\Http\Controllers\IngestController;
   Route::post('/ingest/dns', [IngestController::class, 'dns']);
   ```
5. Buat token acak:
   ```powershell
   php -r "echo bin2hex(random_bytes(16));"
   ```
   Salin hasilnya. Buka `.env`, pastikan ada `DB_CONNECTION=sqlite`, lalu tambahkan baris:
   ```
   BENTENG_SENSOR_TOKEN=hasil-token-tadi
   ```
6. Jalankan migrasi dan server:
   ```powershell
   php artisan migrate
   php artisan serve --host=0.0.0.0 --port=8000
   ```
   Kalau Windows Firewall bertanya, izinkan **php.exe** (centang juga jaringan Public kalau ada pilihannya, karena adapter Host-only sering dianggap Public).

## B. Sensor (OpenWrt)

Pakai SSH dari PowerShell supaya bisa copy-paste. Jalankan dari folder paket ini (yang berisi folder `sensor`):

```powershell
scp -O sensor\benteng-collect root@192.168.56.20:/usr/bin/benteng-collect
scp -O sensor\benteng-agent root@192.168.56.20:/usr/bin/benteng-agent
scp -O sensor\benteng-collect.init root@192.168.56.20:/etc/init.d/benteng-collect
ssh root@192.168.56.20
```

Di dalam sesi SSH (ganti `TOKEN-KAMU` dengan token dari langkah A5):

```sh
sed -i 's/\r$//' /usr/bin/benteng-collect /usr/bin/benteng-agent /etc/init.d/benteng-collect
chmod +x /usr/bin/benteng-collect /usr/bin/benteng-agent /etc/init.d/benteng-collect
sed -i 's/GANTI-DENGAN-TOKEN-ACAK/TOKEN-KAMU/' /usr/bin/benteng-agent

/etc/init.d/benteng-collect enable
/etc/init.d/benteng-collect start

echo '* * * * * /usr/bin/benteng-agent' >> /etc/crontabs/root
/etc/init.d/cron enable
/etc/init.d/cron restart
```

Baris `sed -i 's/\r$//'` membuang akhir baris gaya Windows, kalau file sempat dibuka dan disimpan di Windows.

## C. Uji alur end-to-end

1. Di sensor, cek server bisa dijangkau:
   ```sh
   wget -qO- http://192.168.56.1:8000/up | head -3
   ```
2. Di VM korban, buat lalu lintas:
   ```sh
   wget -qO /dev/null http://example.com
   ```
3. Tunggu sekitar 1 menit, lalu di sensor:
   ```sh
   logread | grep benteng-agent | tail -3
   ```
   Harus muncul `terkirim ke server`.
4. Di Windows (folder `benteng-server`):
   ```powershell
   php artisan tinker --execute="dump(DB::table('dns_events')->latest('id')->limit(5)->get());"
   ```
   Harus muncul baris dengan `domain: example.com` dan `client_ip: 192.168.1.142`.

## Kalau gagal

| Gejala | Penyebab paling mungkin |
| --- | --- |
| Langkah C1 gagal / timeout | Windows Firewall memblokir port 8000, atau `php artisan serve` tidak memakai `--host=0.0.0.0` |
| Log `gagal kirim` | Server mati, alamat salah, atau token di sensor dan `.env` berbeda (server membalas 401) |
| Tidak ada log `benteng-agent` sama sekali | Cron belum jalan: cek `cat /etc/crontabs/root` dan `/etc/init.d/cron restart` |
| `/tmp/benteng-dns.buf` tidak pernah terisi | Collector mati: `/etc/init.d/benteng-collect restart`, dan pastikan `logqueries` dnsmasq masih aktif |
