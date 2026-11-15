# benteng-starter

Skrip yang dipasang di sensor OpenWrt, skrip pemasangan sekali jalan, dan alat uji lab.

## Memasang

Setelah sensor bisa di-ping (lihat [panduan sensor](../benteng-server/docs/PANDUAN-SENSOR.md)
langkah 2A atau 2B), jalankan dari folder repo:

```powershell
# Windows
powershell -ExecutionPolicy Bypass -File benteng-starter\pasang-sensor.ps1 -Jalur vm
```

```sh
# Linux atau macOS
bash benteng-starter/pasang-sensor.sh vm
```

Ganti `vm` dengan `router` untuk router OpenWrt sungguhan.

## Isi folder

| File | Fungsi | Dipasang di sensor sebagai |
| --- | --- | --- |
| `pasang-sensor.ps1`, `pasang-sensor.sh` | Pemasangan sekali jalan dari laptop/PC server (Windows / Linux, macOS) | - |
| `pasang.sh` | Dijalankan di sensor oleh skrip di atas: menyalin skrip, mengisi token dan alamat server, menyalakan layanan, memeriksa hasil | - |
| `sensor/benteng-collect` (+ `.init`) | Menyalin baris query DNS dari log dnsmasq ke buffer | `/usr/bin/benteng-collect`, `/etc/init.d/benteng-collect` |
| `sensor/benteng-agent` | Mengirim log DNS dan daftar DHCP ke server tiap menit (cron) | `/usr/bin/benteng-agent` |
| `sensor/benteng-banner` | Banner saat login SSH | `/etc/banner` |
| `responden/benteng-responder` (+ `.init`) | Menarik perintah karantina dan daftar blokir dari server tiap 5 detik | `/usr/bin/benteng-responder`, `/etc/init.d/benteng-responder` |
| `responden/benteng-nft.conf` | Tabel nftables karantina, terpisah dari firewall bawaan | `/etc/benteng/benteng-nft.conf` |
| `responden/benteng-portal.nft` | Mengalihkan HTTP perangkat karantina ke halaman pemberitahuan | `/etc/benteng/benteng-portal.nft` |
| `responden/www-benteng/index.html` | Halaman "Perangkat dikarantina" (uhttpd port 8081) | `/www-benteng/index.html` |
| `uji/benteng-trafik-uji.py` | Alat uji di perangkat uji: meniru beacon, domain acak, atau trafik normal. Hanya menanyakan nama domain, bukan malware. | - |
| `server/` | Arsip versi awal server, tidak dipakai lagi | - |
