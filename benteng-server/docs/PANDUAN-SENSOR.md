# Panduan membangun ulang sensor BENTENG (VirtualBox + OpenWrt)

Panduan ini membangun VM `benteng-sensor` dari nol sampai siap demo: VM OpenWrt,
jaringan, akses SSH, skrip BENTENG, halaman karantina, server Laravel, dan uji akhir.
Perkiraan waktu: 30–45 menit.

Setiap blok perintah diberi label tempat menjalankannya:

| Label | Tempat |
| --- | --- |
| **PowerShell** | Windows, folder proyek |
| **Konsol VM** | Jendela VirtualBox `benteng-sensor` (ketik manual, tidak bisa tempel) |
| **SSH sensor** | Sesi `ssh root@192.168.56.20` dari PowerShell (bisa tempel) |
| **VM korban** | Jendela VirtualBox `benteng-korban` |

## Gambaran jaringan

```
VM korban (Alpine)            Sensor BTG-02 (OpenWrt 24.10.8)              Laptop Windows
192.168.1.x (DHCP)  ──LAN──▶  eth0  192.168.1.1   (Adapter 1: Internal "benteng-lan")
                              eth1  DHCP dari NAT (Adapter 2: NAT, jalan ke internet)
                              eth2  192.168.56.20 (Adapter 3: Host-only) ──▶ 192.168.56.1:8000 (Laravel)
```

Urutan adapter **harus** seperti di atas, karena OpenWrt x86 memakai eth0 sebagai LAN
dan eth1 sebagai WAN secara bawaan.

---

## A. Membuat VM

Pakai OpenWrt **24.10.8** (versi yang sudah diuji dengan skrip BENTENG). Jangan pakai
seri 25.x karena pengelola paketnya berbeda (`apk`, bukan `opkg`).

### A1. Unduh dan ekstrak image

Unduh `openwrt-24.10.8-x86-64-generic-ext4-combined-efi.img.gz` dari
<https://downloads.openwrt.org/releases/24.10.8/targets/x86/64/> ke folder Downloads.

Ekstrak menjadi `.img` dengan 7-Zip, atau lewat Git Bash:

```sh
cd ~/Downloads
gzip -dk openwrt-24.10.8-x86-64-generic-ext4-combined-efi.img.gz
```

Pesan `trailing garbage ignored` itu normal; file `.img` tetap benar.

### A2. Buat VM dan disk

**PowerShell**:

```powershell
$VBox = "C:\Program Files\Oracle\VirtualBox\VBoxManage.exe"
$Img  = "$env:USERPROFILE\Downloads\openwrt-24.10.8-x86-64-generic-ext4-combined-efi.img"
$Dir  = "$env:USERPROFILE\VirtualBox VMs\benteng-sensor"

& $VBox createvm --name benteng-sensor --ostype Linux_64 --register
& $VBox modifyvm benteng-sensor --memory 512 --cpus 1 --firmware efi --rtcuseutc on
& $VBox convertfromraw $Img "$Dir\benteng-sensor.vdi" --format VDI
& $VBox storagectl benteng-sensor --name SATA --add sata --controller IntelAhci
& $VBox storageattach benteng-sensor --storagectl SATA --port 0 --device 0 --type hdd --medium "$Dir\benteng-sensor.vdi"
```

Kalau lebih suka lewat GUI: **New** → nama `benteng-sensor`, Type *Linux*, Version
*Other Linux (64-bit)*, RAM 512 MB, centang **Enable EFI**, pilih *Use an existing
virtual hard disk* lalu pilih VDI hasil `convertfromraw` di atas.

### A3. Atur tiga adapter jaringan

VM harus mati dan jendela Settings VM harus tertutup. **PowerShell**:

```powershell
& $VBox modifyvm benteng-sensor --nic1 intnet --intnet1 benteng-lan --nic2 nat --nic3 hostonly --hostonlyadapter3 "VirtualBox Host-Only Ethernet Adapter"
& $VBox showvminfo benteng-sensor --machinereadable | Select-String '^(nic[1-3]|intnet1|hostonlyadapter3)='
```

Hasil yang benar:

```
nic1="intnet"
intnet1="benteng-lan"
nic2="nat"
nic3="hostonly"
hostonlyadapter3="VirtualBox Host-Only Ethernet Adapter"
```

Lewat GUI: Settings → Network → Adapter 1 *Internal Network* nama `benteng-lan`;
Adapter 2 *NAT*; Adapter 3 *Host-only Adapter* "VirtualBox Host-Only Ethernet Adapter".
Nama `benteng-lan` harus sama persis dengan yang dipakai VM korban.

---

## B. Konfigurasi awal di konsol VM

Tahap ini satu-satunya yang harus diketik manual. Setelah ini semuanya lewat SSH.

1. Start VM `benteng-sensor`. Tunggu sampai muncul
   `Please press Enter to activate this console`, lalu tekan Enter.
2. Buat kata sandi root (**Konsol VM**):

   ```sh
   passwd
   ```

3. Cek nama antarmuka. Harus ada `eth0`, `eth1`, `eth2`:

   ```sh
   ls /sys/class/net
   ```

4. Buat antarmuka manajemen di `eth2` agar laptop bisa SSH ke sensor (**Konsol VM**):

   ```sh
   uci set network.mgmt=interface
   uci set network.mgmt.proto=static
   uci set network.mgmt.device=eth2
   uci set network.mgmt.ipaddr=192.168.56.20
   uci set network.mgmt.netmask=255.255.255.0
   uci add_list firewall.@zone[0].network=mgmt
   uci commit
   /etc/init.d/network restart
   /etc/init.d/firewall restart
   ```

   `@zone[0]` adalah zona `lan` bawaan, jadi SSH dan LuCI dari laptop diizinkan.
   Kalau keyboard VM mengetik simbol yang salah, atur layout keyboard Windows ke *US* sementara.

5. Cek alamat (**Konsol VM**):

   ```sh
   ip addr show br-lan | grep 'inet '
   ip addr show eth1 | grep 'inet '
   ip addr show eth2 | grep 'inet '
   ```

   Hasilnya berturut-turut `192.168.1.1/24`, `10.0.2.15/24`, dan `192.168.56.20/24`.

6. Dari **PowerShell**: `ping 192.168.56.20` harus dibalas.

---

## C. Akses SSH tanpa kata sandi

**PowerShell**:

```powershell
# Sidik kunci sensor lama masih tersimpan; hapus agar SSH tidak menolak VM baru.
ssh-keygen -R 192.168.56.20

# Buat kunci SSH laptop (sekali saja; tekan Enter untuk semua pertanyaan).
ssh-keygen -t ed25519

# Pasang kunci ke sensor. Jawab "yes" lalu masukkan kata sandi root dari langkah B2.
scp -O $env:USERPROFILE\.ssh\id_ed25519.pub root@192.168.56.20:/etc/dropbear/authorized_keys
ssh root@192.168.56.20 "chmod 600 /etc/dropbear/authorized_keys"

# Coba masuk. Sekarang tidak perlu kata sandi.
ssh root@192.168.56.20
```

Opsi `-O` wajib untuk `scp` ke OpenWrt karena OpenWrt tidak punya `sftp-server`.

---

## D. Konfigurasi dasar sensor

Semua blok di bagian ini dijalankan di **SSH sensor**.

### D1. Nama host dan zona waktu WIB

Server membaca waktu di log sensor sebagai WIB. Jika zona waktu sensor salah,
semua waktu di dashboard bergeser 7 jam.

```sh
uci set system.@system[0].hostname='benteng-sensor'
uci set system.@system[0].zonename='Asia/Jakarta'
uci set system.@system[0].timezone='WIB-7'
uci commit system
/etc/init.d/system reload
date
```

`date` harus menunjukkan jam WIB yang sama dengan jam laptop.

### D2. Internet dan paket

```sh
ping -c 3 downloads.openwrt.org
opkg update
opkg install conntrack
```

`conntrack` opsional, berguna untuk memeriksa koneksi perangkat saat demo (`conntrack -L`).

### D3. Pencatatan query DNS

```sh
uci set dhcp.@dnsmasq[0].logqueries='1'
uci set dhcp.@dnsmasq[0].filter_aaaa='1'
uci commit dhcp
/etc/init.d/dnsmasq restart
logread | grep -c 'query\['
```

`filter_aaaa` khusus lab, karena NAT VirtualBox tidak punya IPv6. Angka terakhir boleh 0
dulu; akan naik setelah VM korban melakukan query.

---

## E. Server Laravel di Windows

### E1. PHP 8.5

Di FlyEnv, pastikan PHP aktif versi **8.5** (minimal 8.4). Dengan PHP 8.3 semua
perintah `php artisan` gagal dengan pesan *Composer detected issues in your platform*.

```powershell
php -v
```

### E2. Token sensor baru

Token lama sudah tercatat di riwayat git, jadi ini saat yang tepat untuk menggantinya.

```powershell
cd C:\Users\Hadiid\Documents\project\hackthon\benteng\benteng-server
php -r "echo bin2hex(random_bytes(16));"
```

Salin hasilnya, lalu di `.env` ubah baris `BENTENG_SENSOR_TOKEN=<token baru>`.
Token yang sama dipakai di langkah F2.

### E3. Jalankan server dan detektor

Buka dua terminal di folder `benteng-server`:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

```powershell
php artisan schedule:work
```

Kalau Windows Firewall bertanya, izinkan `php.exe` untuk jaringan **Private dan Public**
(adapter host-only dianggap Public).

Uji dari **SSH sensor**:

```sh
H=$(wget -O /dev/null http://192.168.56.1:8000/up 2>&1)
case "$H" in *"Writing to"*) echo "server terhubung" ;; *) echo "GAGAL: $H" ;; esac
```

Kalau keluar `server terhubung`, sensor sudah bisa menghubungi server.

> `wget` di OpenWrt adalah `uclient-fetch`. `php artisan serve` sering menutup koneksi
> tepat setelah membalas, sehingga muncul `Connection reset prematurely` dan kode keluar
> bukan 0 walaupun data sudah diterima. Karena itu uji di panduan ini membaca pesan
> `Writing to`, bukan kode keluar. Skrip BENTENG sendiri sudah tahan terhadap hal ini.

---

## F. Memasang skrip BENTENG

### F1. Salin file dari laptop

**PowerShell** (folder `benteng-starter`):

```powershell
cd C:\Users\Hadiid\Documents\project\hackthon\benteng\benteng-starter
ssh root@192.168.56.20 "mkdir -p /etc/benteng /www-benteng"
scp -O sensor\benteng-collect sensor\benteng-agent responden\benteng-responder root@192.168.56.20:/usr/bin/
scp -O sensor\benteng-collect.init root@192.168.56.20:/etc/init.d/benteng-collect
scp -O responden\benteng-responder.init root@192.168.56.20:/etc/init.d/benteng-responder
scp -O responden\benteng-nft.conf responden\benteng-portal.nft root@192.168.56.20:/etc/benteng/
scp -O responden\www-benteng\index.html root@192.168.56.20:/www-benteng/
```

### F2. Rapikan file dan isi token

**SSH sensor**. Ganti `TOKEN-DARI-ENV` dengan token dari langkah E2:

```sh
TOKEN='TOKEN-DARI-ENV'

cd /usr/bin
sed -i 's/\r$//' benteng-collect benteng-agent benteng-responder \
    /etc/init.d/benteng-collect /etc/init.d/benteng-responder \
    /etc/benteng/benteng-nft.conf /etc/benteng/benteng-portal.nft
sed -i "s/GANTI-DENGAN-TOKEN-ACAK/$TOKEN/" benteng-agent benteng-responder
chmod +x benteng-collect benteng-agent benteng-responder /etc/init.d/benteng-collect /etc/init.d/benteng-responder

grep -c "$TOKEN" benteng-agent benteng-responder
```

Hasil `grep` harus `benteng-agent:1` dan `benteng-responder:1`.

Uji token ke server:

```sh
H=$(wget -O /dev/null --header="X-Sensor-Token: $TOKEN" http://192.168.56.1:8000/api/sensors/BTG-02/karantina 2>&1)
case "$H" in *"Writing to"*) echo "token cocok" ;; *401*) echo "TOKEN SALAH" ;; *) echo "GAGAL: $H" ;; esac
```

`token cocok` berarti siap lanjut. `TOKEN SALAH` (HTTP 401) berarti token di sensor
berbeda dengan `.env`: ulangi `sed` di atas dengan token yang benar.

### F3. Halaman karantina (uhttpd port 8081)

**SSH sensor**:

```sh
uci -q delete uhttpd.benteng
uci set uhttpd.benteng=uhttpd
uci add_list uhttpd.benteng.listen_http='0.0.0.0:8081'
uci set uhttpd.benteng.home='/www-benteng'
uci set uhttpd.benteng.error_page='/index.html'
uci set uhttpd.benteng.no_dirlists='1'
uci commit uhttpd
/etc/init.d/uhttpd restart
wget -qO- http://127.0.0.1:8081/ | grep -o '<title>.*</title>'
```

Hasil yang benar: `<title>Perangkat dikarantina - BENTENG</title>`. Di browser, alamat
apa pun di port 8081 juga menampilkan halaman ini.

### F4. Nyalakan layanan dan jadwal

**SSH sensor**:

```sh
/etc/init.d/benteng-collect enable
/etc/init.d/benteng-collect start
/etc/init.d/benteng-responder enable
/etc/init.d/benteng-responder start
echo '* * * * * /usr/bin/benteng-agent' > /etc/crontabs/root
/etc/init.d/cron enable
/etc/init.d/cron restart
```

Cek tabel firewall BENTENG:

```sh
nft list table inet benteng
logread -e benteng | tail -5
```

Tabel harus berisi `set karantina_v4`, `chain forward`, dan `chain prerouting`. Kalau
`chain prerouting` tidak ada dan log berisi "halaman karantina tidak aktif", karantina
tetap berfungsi; hanya halaman pemberitahuannya yang tidak muncul (lihat bagian I).

### F5. Banner login BENTENG

**PowerShell** (folder `benteng-starter`):

```powershell
ssh root@192.168.56.20 "cp /etc/banner /etc/banner.asli"
scp -O sensor\benteng-banner root@192.168.56.20:/etc/banner
```

---

## G. VM korban

1. Pastikan Adapter 1 VM `benteng-korban` adalah *Internal Network* `benteng-lan`.
2. Start (atau restart) VM korban agar mendapat alamat baru dari sensor. Di **VM korban**:

   ```sh
   rc-service networking restart
   ip addr show eth0 | grep 'inet '
   ping -c 2 192.168.1.1
   nslookup example.com 192.168.1.1
   ```

   Alamat harus `192.168.1.x`, ping ke sensor dibalas, dan `nslookup` menjawab.
3. Simulator ada di `/root/benteng-trafik-uji.py`. Kalau hilang, salin lewat web server sensor:

   ```powershell
   # PowerShell, folder benteng-starter
   scp -O uji\benteng-trafik-uji.py root@192.168.56.20:/www/
   ```

   ```sh
   # VM korban
   wget -O /root/benteng-trafik-uji.py http://192.168.1.1/benteng-trafik-uji.py
   ```

   Setelah itu hapus salinannya dari sensor: `ssh root@192.168.56.20 "rm /www/benteng-trafik-uji.py"`.

---

## H. Uji akhir (urutan demo)

1. **Sensor online.** Buka `http://localhost:8000`, login sebagai analis, buka menu
   **Sensor**: BTG-02 harus *Online*.
2. **Data mengalir.** Satu sampai dua menit setelah langkah G, menu **Perangkat**
   menampilkan IP korban lengkap dengan MAC dan nama host. Laporan DHCP pertama menjadi
   daftar perangkat sah, jadi statusnya *Normal*.
3. **Deteksi otomatis.** Di **VM korban**:

   ```sh
   python3 /root/benteng-trafik-uji.py --menit 6
   ```

   Dalam 1–3 menit, insiden beaconing dan DGA muncul sendiri di dashboard.

   > Pola yang pernah ditandai **Bukan ancaman** tidak dilaporkan ulang untuk perangkat
   > yang sama. Kalau grafik DNS naik tetapi insiden tidak muncul, kosongkan data lab dulu
   > di **PowerShell** (`benteng-server`): `php artisan benteng:reset-demo`, lalu jalankan
   > simulator lagi. Akun tidak ikut terhapus.
4. **Karantina.** Di VM korban `ping -c 3 8.8.8.8` (berhasil). Di dashboard klik
   **Karantina** → **Ya, karantina**. Dalam kurang dari 5 detik:
   - `ping -c 3 8.8.8.8` di korban gagal,
   - `wget -qO- http://neverssl.com | grep -o '<title>.*</title>'` menampilkan judul halaman karantina,
   - di **SSH sensor**, `nft list set inet benteng karantina_v4` berisi IP korban.
5. **Tahan reboot.** Saat korban masih dikarantina, jalankan `reboot` di **SSH sensor**.
   Setelah sensor menyala lagi, `nft list set inet benteng karantina_v4` kembali berisi
   IP korban (dipulihkan otomatis dari server).
6. **Lepas.** Klik **Lepas karantina**. Ping korban berhasil lagi.
7. **Uji alarm palsu** (opsional, 60 menit): `python3 /root/benteng-trafik-uji.py --mode normal --menit 60`.
   Dashboard seharusnya tidak menampilkan insiden baru.

---

## I. Masalah yang sering muncul

| Gejala | Penyebab dan solusi |
| --- | --- |
| `ping 192.168.56.20` tidak dibalas | Urutan adapter salah atau langkah B4 belum jalan. Cek `ip -br addr` di konsol VM; `eth2` harus 192.168.56.20. |
| SSH: `REMOTE HOST IDENTIFICATION HAS CHANGED` | Jalankan `ssh-keygen -R 192.168.56.20` lalu coba lagi. |
| `scp: ... sftp-server: not found` | Tambahkan opsi `-O` pada `scp`. |
| Sensor *Tidak merespons*, log sensor `gagal kirim`, tapi dashboard terbuka normal di `127.0.0.1` | IP laptop di adapter host-only bukan lagi 192.168.56.1. Cek dengan `VBoxManage list hostonlyifs`, perbaiki dengan `VBoxManage hostonlyif ipconfig "VirtualBox Host-Only Ethernet Adapter" --ip 192.168.56.1 --netmask 255.255.255.0`. |
| Sensor *Tidak merespons* di dashboard | Server belum jalan di `0.0.0.0:8000`, firewall Windows memblokir `php.exe`, atau token beda (uji langkah F2). Lihat `logread -e benteng`. |
| Menu Perangkat kosong | Cron belum jalan (`/etc/init.d/cron restart`) atau korban belum melakukan query. Cek `ls -la /tmp/benteng-dns.*` dan `cat /tmp/dhcp.leases`. |
| Waktu di dashboard meleset 7 jam | Zona waktu sensor belum WIB (langkah D1). |
| Korban tidak dapat IP | Nama internal network korban dan sensor tidak sama persis (`benteng-lan`). |
| `php artisan` gagal: *Composer detected issues* | PHP di FlyEnv bukan 8.5 (langkah E1). |
| Dashboard: *Detektor tidak berjalan* | `php artisan schedule:work` belum dijalankan. |
| Halaman karantina tidak muncul, ping tetap gagal | Pengalihan HTTP tidak aktif. Cek `nft -f /etc/benteng/benteng-portal.nft` untuk melihat pesan galatnya; pastikan paket `kmod-nft-nat` terpasang (`opkg list-installed \| grep nft-nat`). Situs HTTPS memang tidak bisa dialihkan. |

---

## J. Supaya tidak terulang

Setelah semua langkah H berhasil:

1. **Snapshot VM** (**PowerShell**):

   ```powershell
   & $VBox snapshot benteng-sensor take "siap-demo" --description "Sensor BENTENG lengkap dan teruji"
   ```

2. **Cadangan konfigurasi sensor.** Secara bawaan, `sysupgrade -b` hanya menyimpan isi
   `/etc/config`, jadi daftarkan dulu file BENTENG (sekali saja) di **SSH sensor**:

   ```sh
   for f in /usr/bin/benteng-collect /usr/bin/benteng-agent /usr/bin/benteng-responder \
            /etc/init.d/benteng-collect /etc/init.d/benteng-responder /etc/benteng/ /www-benteng/; do
       grep -qxF "$f" /etc/sysupgrade.conf || echo "$f" >> /etc/sysupgrade.conf
   done
   ```

   Lalu buat cadangan dan simpan di laptop (**PowerShell**):

   ```powershell
   ssh root@192.168.56.20 "sysupgrade -b /tmp/benteng-sensor-backup.tar.gz"
   scp -O root@192.168.56.20:/tmp/benteng-sensor-backup.tar.gz $env:USERPROFILE\Documents\
   ```

   Memulihkan di VM baru (setelah langkah A–C): salin file itu ke `/tmp/` sensor, jalankan
   `sysupgrade -r /tmp/benteng-sensor-backup.tar.gz && reboot`, lalu ulangi perintah
   `enable`/`start` di langkah F4 dan `opkg install conntrack` bila perlu.

3. **Ekspor VM ke satu file** untuk dibawa ke laptop lain atau saat presentasi:

   ```powershell
   & $VBox export benteng-sensor -o "$env:USERPROFILE\Documents\benteng-sensor.ova"
   ```
