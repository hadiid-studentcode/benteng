# Panduan memasang BENTENG dengan sensor sungguhan

Panduan ini memasang BENTENG lengkap: server dashboard, sensor OpenWrt, dan perangkat uji,
sampai karantina dan blokir domain bisa dicoba. Ada dua jalur:

| | **Jalur A: Lab VirtualBox** | **Jalur B: Router OpenWrt** |
| --- | --- | --- |
| Cocok untuk | Mencoba di satu laptop tanpa perangkat tambahan | Memasang di jaringan sungguhan |
| Yang dibutuhkan | Laptop Windows dengan VirtualBox 7, RAM 8 GB | Router yang didukung OpenWrt 24.10 (atau mini-PC x86), dan laptop/PC untuk server |
| Alamat sensor dari server | `192.168.56.20` (jaringan host-only) | `192.168.1.1` (LAN router) |
| Alamat server dari sensor | `192.168.56.1` | IP server di LAN router, mis. `192.168.1.100` |
| Perangkat uji | VM korban (Alpine Linux) | Laptop/PC Linux yang tersambung ke LAN router |
| Langkah | 2A → skrip (atau 1, 3, 4, 5) → 6A → 7 | 2B → skrip (atau 1, 3, 4, 5) → 6B → 7 |
| Perkiraan waktu | 45–60 menit | 45–60 menit, belum termasuk memasang OpenWrt |

Jalur A adalah jalur yang dipakai dan diuji tim. Jalur B memakai skrip dan langkah yang
sama; bedanya hanya pada alamat dan cara menyiapkan perangkatnya.

> **Cara cepat.** Setelah langkah 2A atau 2B selesai (sensor bisa di-ping), langkah 1, 3, 4,
> dan 5 bisa dikerjakan sekaligus oleh satu skrip, dari folder repo:
>
> - Windows: `powershell -ExecutionPolicy Bypass -File benteng-starter\pasang-sensor.ps1 -Jalur vm`
> - Linux atau macOS: `bash benteng-starter/pasang-sensor.sh vm`
>
> Ganti `vm` dengan `router` untuk Jalur B. Skrip menyiapkan server, menyalakan dashboard dan
> detektor, memasang skrip BENTENG di sensor, lalu memeriksa hasilnya. Setelah itu lanjut ke
> langkah 6. Langkah manual di bawah tetap berguna untuk memahami isi skrip atau memperbaiki
> masalah.

Belum punya waktu untuk lab? Coba dulu [mode demo](../../README.md#cara-1-mode-demo) tanpa sensor.

Setiap blok perintah diberi label tempat menjalankannya:

| Label | Tempat |
| --- | --- |
| **PowerShell** | Terminal di laptop/PC server, dari folder repo `benteng` |
| **Konsol VM** | Jendela VirtualBox VM tersebut (ketik manual, tidak bisa tempel) |
| **SSH sensor** | Sesi `ssh root@<alamat sensor>` dari PowerShell (bisa tempel) |
| **Perangkat uji** | Terminal VM korban atau laptop uji |

## Gambaran jaringan

Jalur A, semua di satu laptop:

```
VM korban (Alpine)          Sensor BTG-02 (OpenWrt 24.10.8)                 Laptop Windows
192.168.1.x (DHCP) ─LAN─▶  eth0  192.168.1.1    (Adapter 1: Internal "benteng-lan")
                           eth1  DHCP dari NAT  (Adapter 2: NAT, jalan ke internet)
                           eth2  192.168.56.20  (Adapter 3: Host-only) ──▶ 192.168.56.1:8000 (server)
```

Jalur B, perangkat sungguhan:

```
Modem/internet ──▶ port WAN router OpenWrt (sensor BTG-02, 192.168.1.1)
                   port LAN ──▶ server BENTENG (mis. 192.168.1.100:8000)
                   port LAN / Wi-Fi ──▶ laptop uji dan perangkat lain di jaringan
```

---

## 1. Server BENTENG

Server dijalankan di laptop/PC yang terhubung ke sensor: laptop yang sama dengan VirtualBox
(Jalur A) atau laptop/PC yang dicolok ke port LAN router (Jalur B).

### 1.1 Pasang

Butuh PHP 8.4.1 atau lebih baru (teruji di 8.5) dengan ekstensi `pdo_sqlite`, Composer,
dan Node.js. Cek dulu versi PHP:

```powershell
php -v
```

Dengan PHP 8.3 atau lebih lama, semua perintah `php artisan` gagal dengan pesan
*Composer detected issues in your platform*.

**PowerShell**:

```powershell
cd benteng-server
composer install
npm install; npm run build
copy .env.example .env
php artisan key:generate
New-Item database\database.sqlite
php artisan migrate
```

Jangan pakai `composer setup` untuk jalur ini, karena perintah itu menyalakan mode demo
dan mengisi data simulasi.

### 1.2 Token sensor dan mode

Buat token acak:

```powershell
php -r "echo bin2hex(random_bytes(16));"
```

Di `.env`, isi dua baris ini. Token yang sama dipakai lagi di langkah 5.2.

```
BENTENG_SENSOR_TOKEN=<token tadi>
BENTENG_MODE_DEMO=false
```

### 1.3 Akun

```powershell
php artisan benteng:pengguna analis@satuan.mil --nama="Nama Analis"
php artisan benteng:pengguna pimpinan@satuan.mil --nama="Nama Pimpinan" --peran=pengamat
```

Kata sandi acak ditampilkan sekali; simpan baik-baik. Analis bisa bertindak, pengamat
hanya melihat.

### 1.4 Jalankan

Buka dua terminal di folder `benteng-server`:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

```powershell
php artisan schedule:work
```

Terminal pertama menjalankan dashboard, terminal kedua menjalankan detektor tiap menit.
Kalau Windows Firewall bertanya, izinkan `php.exe` untuk jaringan **Private dan Public**
(adapter host-only VirtualBox dianggap Public).

Dashboard: `http://localhost:8000`.

---

## 2A. Sensor di VirtualBox (Jalur A)

Pakai OpenWrt **24.10.8**, versi yang sudah diuji dengan skrip BENTENG. Jangan pakai seri
25.x karena pengelola paketnya berbeda (`apk`, bukan `opkg`).

### 2A.1 Unduh image

Unduh `openwrt-24.10.8-x86-64-generic-ext4-combined-efi.img.gz` dari
<https://downloads.openwrt.org/releases/24.10.8/targets/x86/64/> ke folder Downloads, lalu
ekstrak menjadi `.img` dengan 7-Zip, atau lewat Git Bash:

```sh
cd ~/Downloads
gzip -dk openwrt-24.10.8-x86-64-generic-ext4-combined-efi.img.gz
```

Pesan `trailing garbage ignored` itu normal; file `.img` tetap benar.

### 2A.2 Buat VM dan atur jaringan

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
& $VBox modifyvm benteng-sensor --nic1 intnet --intnet1 benteng-lan --nic2 nat --nic3 hostonly --hostonlyadapter3 "VirtualBox Host-Only Ethernet Adapter"
```

Urutan adapter **harus** seperti di atas, karena OpenWrt x86 memakai `eth0` sebagai LAN dan
`eth1` sebagai WAN. Nama `benteng-lan` juga harus sama persis dengan yang dipakai VM korban.

Pastikan laptop memakai alamat `192.168.56.1` di adapter host-only:

```powershell
& $VBox list hostonlyifs | Select-String "IPAddress"
```

Kalau alamatnya lain, perbaiki dengan
`& $VBox hostonlyif ipconfig "VirtualBox Host-Only Ethernet Adapter" --ip 192.168.56.1 --netmask 255.255.255.0`.

### 2A.3 Konfigurasi awal di konsol VM

Tahap ini satu-satunya yang harus diketik manual; setelah ini semuanya lewat SSH.

1. Start VM `benteng-sensor`. Tunggu sampai muncul `Please press Enter to activate this console`, lalu tekan Enter.
2. Buat kata sandi root (**Konsol VM**): `passwd`
3. Buat antarmuka manajemen di `eth2` agar laptop bisa SSH ke sensor (**Konsol VM**):

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

   Kalau keyboard VM mengetik simbol yang salah, atur layout keyboard Windows ke *US* sementara.
4. Dari **PowerShell**: `ping 192.168.56.20` harus dibalas.

Alamat sensor untuk langkah berikutnya: **`192.168.56.20`**. Alamat server: **`http://192.168.56.1:8000`**.

---

## 2B. Sensor di router OpenWrt (Jalur B)

### 2B.1 Pasang OpenWrt

Cari router Anda di <https://firmware-selector.openwrt.org/>, pilih versi **24.10.x**, lalu
ikuti petunjuk pemasangan di halaman wiki router tersebut. Pemasangan firmware berbeda untuk
tiap merek, dan salah langkah bisa merusak router; baca petunjuknya sampai habis.

Mini-PC x86 juga bisa: tulis image yang sama dengan Jalur A ke disk mini-PC.

### 2B.2 Sambungkan

1. Colok modem/internet ke port **WAN** router, dan laptop/PC server ke port **LAN**.
2. Laptop server mendapat alamat `192.168.1.x` dari router. Cek dengan `ipconfig`.
3. Buat kata sandi root: `ssh root@192.168.1.1`, lalu `passwd`.

### 2B.3 Kunci alamat server

Sensor mengirim data ke alamat server, jadi alamat itu tidak boleh berubah. **SSH sensor**,
ganti MAC dengan MAC adapter laptop server (`ipconfig /all`, baris *Physical Address*):

```sh
uci add dhcp host
uci set dhcp.@host[-1].name='server-benteng'
uci set dhcp.@host[-1].mac='AA:BB:CC:DD:EE:FF'
uci set dhcp.@host[-1].ip='192.168.1.100'
uci commit dhcp
/etc/init.d/dnsmasq restart
```

Cabut-colok kabel LAN laptop server, lalu pastikan alamatnya menjadi `192.168.1.100`.

Alamat sensor untuk langkah berikutnya: **`192.168.1.1`**. Alamat server: **`http://192.168.1.100:8000`**.

> Laptop server juga terlihat sebagai perangkat di dashboard. Jangan mengarantina perangkat
> ini, karena koneksi sensor ke server ikut terputus.

---

## 3. Akses SSH tanpa kata sandi

**PowerShell**. Isi `$Sensor` dengan alamat sensor dari langkah 2A atau 2B:

```powershell
$Sensor = "192.168.56.20"   # Jalur B: "192.168.1.1"

ssh-keygen -R $Sensor                 # hapus sidik kunci lama bila pernah tersambung
ssh-keygen -t ed25519                 # sekali saja; tekan Enter untuk semua pertanyaan
scp -O $env:USERPROFILE\.ssh\id_ed25519.pub "root@${Sensor}:/etc/dropbear/authorized_keys"
ssh root@$Sensor "chmod 600 /etc/dropbear/authorized_keys"
ssh root@$Sensor                      # sekarang tanpa kata sandi
```

Opsi `-O` wajib untuk `scp` ke OpenWrt karena OpenWrt tidak punya `sftp-server`.

---

## 4. Konfigurasi dasar sensor

Semua blok di bagian ini dijalankan di **SSH sensor**.

### 4.1 Nama host dan zona waktu WIB

Server membaca waktu di log sensor sebagai WIB. Bila zona waktu sensor salah, semua waktu di
dashboard bergeser 7 jam.

```sh
uci set system.@system[0].hostname='benteng-sensor'
uci set system.@system[0].zonename='Asia/Jakarta'
uci set system.@system[0].timezone='WIB-7'
uci commit system
/etc/init.d/system reload
date
```

### 4.2 Internet dan paket

```sh
ping -c 3 downloads.openwrt.org
opkg update
opkg install conntrack
```

`conntrack` opsional, berguna untuk memeriksa koneksi perangkat (`conntrack -L`).

### 4.3 Pencatatan query DNS

```sh
uci set dhcp.@dnsmasq[0].logqueries='1'
uci commit dhcp
/etc/init.d/dnsmasq restart
```

**Khusus Jalur A**, NAT VirtualBox tidak punya IPv6, jadi tambahkan juga:

```sh
uci set dhcp.@dnsmasq[0].filter_aaaa='1'
uci commit dhcp
/etc/init.d/dnsmasq restart
```

Pastikan *software flow offloading* di firewall tetap mati (bawaan OpenWrt). Offloading
melewati aturan firewall, sehingga koneksi lama perangkat yang dikarantina bisa lolos.

---

## 5. Memasang skrip BENTENG

### 5.1 Salin file

**PowerShell**, dari folder `benteng-starter`:

```powershell
cd benteng-starter
ssh root@$Sensor "mkdir -p /etc/benteng /www-benteng"
scp -O sensor\benteng-collect sensor\benteng-agent responden\benteng-responder "root@${Sensor}:/usr/bin/"
scp -O sensor\benteng-collect.init "root@${Sensor}:/etc/init.d/benteng-collect"
scp -O responden\benteng-responder.init "root@${Sensor}:/etc/init.d/benteng-responder"
scp -O responden\benteng-nft.conf responden\benteng-portal.nft "root@${Sensor}:/etc/benteng/"
scp -O responden\www-benteng\index.html "root@${Sensor}:/www-benteng/"
scp -O sensor\benteng-banner "root@${Sensor}:/etc/banner"
```

### 5.2 Isi token dan alamat server

**SSH sensor**. Ganti dua nilai di baris pertama: token dari langkah 1.2, dan alamat server
dari langkah 2A atau 2B.

```sh
TOKEN='TOKEN-DARI-ENV'; SERVER='http://192.168.56.1:8000'

cd /usr/bin
sed -i 's/\r$//' benteng-collect benteng-agent benteng-responder \
    /etc/init.d/benteng-collect /etc/init.d/benteng-responder \
    /etc/benteng/benteng-nft.conf /etc/benteng/benteng-portal.nft
sed -i "s/GANTI-DENGAN-TOKEN-ACAK/$TOKEN/" benteng-agent benteng-responder
sed -i "s#http://192.168.56.1:8000#$SERVER#" benteng-agent benteng-responder
chmod +x benteng-collect benteng-agent benteng-responder /etc/init.d/benteng-collect /etc/init.d/benteng-responder

grep -c "$TOKEN" benteng-agent benteng-responder
grep -h "$SERVER" benteng-agent benteng-responder
```

`grep -c` harus menghasilkan `1` untuk kedua file, dan `grep -h` menampilkan alamat server
Anda. Lalu uji token ke server:

```sh
H=$(wget -O /dev/null --header="X-Sensor-Token: $TOKEN" "$SERVER/api/sensors/BTG-02/karantina" 2>&1)
case "$H" in *"Writing to"*) echo "token cocok" ;; *401*) echo "TOKEN SALAH" ;; *) echo "GAGAL: $H" ;; esac
```

- `token cocok`: lanjut ke 5.3.
- `TOKEN SALAH`: token di sensor berbeda dengan `.env`. Ulangi `sed` token dengan nilai yang benar.
- `GAGAL`: server belum berjalan di `0.0.0.0:8000`, alamat server salah, atau firewall Windows memblokir `php.exe`.

> `wget` di OpenWrt adalah `uclient-fetch`. `php artisan serve` sering menutup koneksi tepat
> setelah membalas, sehingga kode keluarnya bukan 0 walaupun data sudah diterima. Karena itu
> uji di atas membaca pesan `Writing to`, bukan kode keluar.

### 5.3 Halaman karantina (port 8081)

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

Hasil yang benar: `<title>Perangkat dikarantina - BENTENG</title>`.

### 5.4 Nyalakan layanan

```sh
/etc/init.d/benteng-collect enable
/etc/init.d/benteng-collect start
/etc/init.d/benteng-responder enable
/etc/init.d/benteng-responder start
echo '* * * * * /usr/bin/benteng-agent' > /etc/crontabs/root
/etc/init.d/cron enable
/etc/init.d/cron restart

nft list table inet benteng
```

Tabel harus berisi `set karantina_v4`, `chain forward`, dan `chain prerouting`. Dalam satu
menit, menu **Sensor** di dashboard menampilkan BTG-02 *Online*.

---

## 6A. VM korban (Jalur A)

VM korban adalah Alpine Linux kecil yang memakai sensor sebagai router. Sensor harus sudah
menyala sebelum langkah ini, karena korban mendapat alamat dan internet dari sensor.

### 6A.1 Buat VM

Unduh ISO **Alpine "Virtual" x86_64** dari <https://alpinelinux.org/downloads/>, lalu
**PowerShell** (sesuaikan nama file ISO):

```powershell
$Iso = "$env:USERPROFILE\Downloads\alpine-virt-x86_64.iso"
$Dir = "$env:USERPROFILE\VirtualBox VMs\benteng-korban"

& $VBox createvm --name benteng-korban --ostype Linux_64 --register
& $VBox modifyvm benteng-korban --memory 256 --cpus 1 --nic1 intnet --intnet1 benteng-lan
& $VBox createmedium disk --filename "$Dir\benteng-korban.vdi" --size 2048
& $VBox storagectl benteng-korban --name SATA --add sata --controller IntelAhci
& $VBox storageattach benteng-korban --storagectl SATA --port 0 --device 0 --type hdd --medium "$Dir\benteng-korban.vdi"
& $VBox storageattach benteng-korban --storagectl SATA --port 1 --device 0 --type dvddrive --medium $Iso
& $VBox startvm benteng-korban
```

### 6A.2 Pasang Alpine

Di **Konsol VM** `benteng-korban`, login sebagai `root` (tanpa kata sandi), lalu jalankan
`setup-alpine` dan jawab:

| Pertanyaan | Jawaban |
| --- | --- |
| Keyboard layout | `us`, lalu `us` |
| Hostname | `benteng-korban` |
| Interface | `eth0`, lalu `dhcp` |
| Root password | kata sandi pilihan Anda |
| Timezone | `Asia/Jakarta` |
| Proxy | `none` |
| Mirror | `f` (pilih yang tercepat) |
| User / SSH server | lewati, atau `none` |
| Disk | `sda`, lalu mode `sys`, lalu `y` |

Setelah selesai, matikan VM (`poweroff`), lepaskan ISO dari **PowerShell**, lalu nyalakan lagi:

```powershell
& $VBox storageattach benteng-korban --storagectl SATA --port 1 --device 0 --type dvddrive --medium emptydrive
& $VBox startvm benteng-korban
```

Login lagi, lalu pasang Python dan cek jaringan (**Perangkat uji**):

```sh
apk add python3
ip addr show eth0 | grep 'inet '
nslookup example.com 192.168.1.1
```

Alamat harus `192.168.1.x` dan `nslookup` menjawab.

### 6A.3 Salin alat uji

Salin lewat web server sensor. **PowerShell**, dari folder `benteng-starter`:

```powershell
scp -O uji\benteng-trafik-uji.py "root@${Sensor}:/www/"
```

**Perangkat uji**:

```sh
wget -O /root/benteng-trafik-uji.py http://192.168.1.1/benteng-trafik-uji.py
```

Lalu hapus salinannya dari sensor: `ssh root@$Sensor "rm /www/benteng-trafik-uji.py"`.

---

## 6B. Perangkat uji di jaringan router (Jalur B)

Pakai laptop/PC **Linux** (atau Raspberry Pi) yang tersambung ke LAN atau Wi-Fi router, dengan
Python 3. Windows dan macOS menyimpan jawaban DNS di cache, sehingga query yang diulang tiap
30 detik bisa tidak sampai ke sensor dan pola beacon tidak terlihat.

1. Pastikan perangkat mendapat alamat `192.168.1.x` dan DNS-nya `192.168.1.1` (bawaan DHCP router).
2. Salin `benteng-starter/uji/benteng-trafik-uji.py` dari repo ke perangkat itu.

Alat uji ini bukan malware: ia hanya menanyakan nama domain berakhiran `.example`, `.test`,
atau `.invalid` yang dicadangkan untuk pengujian, tanpa membuka koneksi atau mengirim data.

---

## 7. Uji akhir

Urutan ini juga urutan demo.

1. **Sensor online.** Buka dashboard, login sebagai analis, buka menu **Sensor**: BTG-02 *Online*.
2. **Data mengalir.** Satu sampai dua menit kemudian, menu **Perangkat** menampilkan perangkat
   uji lengkap dengan MAC dan nama host. Laporan DHCP pertama menjadi daftar perangkat sah,
   jadi statusnya *Normal*.
3. **Deteksi otomatis.** Di **Perangkat uji**:

   ```sh
   python3 benteng-trafik-uji.py --menit 6
   ```

   Insiden domain acak (DGA) muncul sendiri dalam 1–3 menit. Insiden beaconing butuh sekitar
   5–6 menit, karena detektor perlu melihat minimal 9 jeda yang teratur.
4. **Karantina.** Di perangkat uji `ping -c 3 8.8.8.8` berhasil. Di dashboard klik
   **Karantina** → **Ya, karantina**. Dalam beberapa detik:
   - `ping -c 3 8.8.8.8` di perangkat uji gagal;
   - `wget -qO- http://neverssl.com | grep -o '<title>.*</title>'` menampilkan judul halaman karantina;
   - di **SSH sensor**, `nft list set inet benteng karantina_v4` berisi IP perangkat uji.
5. **Tahan reboot.** Saat perangkat masih dikarantina, jalankan `reboot` di **SSH sensor**.
   Setelah sensor menyala, IP perangkat uji kembali ada di `karantina_v4` (dipulihkan dari server).
6. **Blokir domain.** Buka detail insiden **Beaconing ke c2-sim.example**, klik **Blokir domain**
   → **Ya, blokir domain**. Dalam beberapa detik, menu **Domain diblokir** menampilkan status
   *Aktif*. Bukti di **SSH sensor**: `logread | grep c2-sim | tail -3` berisi
   `config c2-sim.example is NXDOMAIN`, artinya jawaban berasal dari daftar blokir BENTENG.
7. **Lepas.** Klik **Lepas karantina** dan **Lepas blokir**. Ping perangkat uji berhasil lagi.
8. **Uji alarm palsu** (opsional, 60 menit): `python3 benteng-trafik-uji.py --mode normal --menit 60`.
   Dashboard seharusnya tidak menampilkan insiden baru.

Mengulang dari awal untuk latihan atau rekaman: `php artisan benteng:reset-demo` di folder
`benteng-server`. Data DNS, insiden, audit, dan daftar perangkat dihapus; akun tetap ada.
Langkah ini juga diperlukan bila pola yang sama pernah ditandai **Bukan ancaman**, karena pola
itu tidak dilaporkan ulang.

---

## 8. Masalah yang sering muncul

| Gejala | Penyebab dan solusi |
| --- | --- |
| `php artisan` gagal: *Composer detected issues* | PHP lebih lama dari 8.4.1. Ganti versi PHP. |
| `ping 192.168.56.20` tidak dibalas (Jalur A) | Urutan adapter salah atau langkah 2A.3 belum jalan. Cek `ip -br addr` di konsol VM; `eth2` harus 192.168.56.20. |
| SSH: `REMOTE HOST IDENTIFICATION HAS CHANGED` | `ssh-keygen -R <alamat sensor>`, lalu coba lagi. |
| `scp: ... sftp-server: not found` | Tambahkan opsi `-O` pada `scp`. |
| Sensor *Tidak merespons*, tapi dashboard terbuka normal | Jalur A: IP host-only laptop bukan lagi 192.168.56.1 (lihat 2A.2). Jalur B: IP server berubah (lihat 2B.3). Cek juga `logread -e benteng` di sensor. |
| Uji token `GAGAL` | Server belum berjalan di `0.0.0.0:8000`, alamat server di skrip salah, atau firewall Windows memblokir `php.exe`. |
| Menu Perangkat kosong | Cron sensor belum jalan (`/etc/init.d/cron restart`) atau perangkat uji belum melakukan query. Cek `ls -la /tmp/benteng-dns.*` dan `cat /tmp/dhcp.leases` di sensor. |
| Waktu di dashboard meleset 7 jam | Zona waktu sensor belum WIB (langkah 4.1). |
| VM korban tidak dapat IP | Nama internal network korban dan sensor tidak sama persis (`benteng-lan`), atau sensor belum menyala. |
| Dashboard: *Detektor tidak berjalan* | `php artisan schedule:work` belum dijalankan. |
| Grafik DNS naik tapi insiden tidak muncul | Pola itu pernah ditandai *Bukan ancaman*. Jalankan `php artisan benteng:reset-demo`. Di Jalur B, pastikan perangkat uji memakai Linux (lihat 6B). |
| Halaman karantina tidak muncul, ping tetap gagal | Pengalihan HTTP tidak aktif. Jalankan `nft -f /etc/benteng/benteng-portal.nft` untuk melihat pesan galatnya, dan pastikan paket `kmod-nft-nat` terpasang. Situs HTTPS memang tidak bisa dialihkan. |

---

## 9. Menyimpan hasil

Setelah semua langkah 7 berhasil:

1. **Cadangan konfigurasi sensor** (kedua jalur). Daftarkan file BENTENG sekali saja di **SSH sensor**:

   ```sh
   for f in /usr/bin/benteng-collect /usr/bin/benteng-agent /usr/bin/benteng-responder \
            /etc/init.d/benteng-collect /etc/init.d/benteng-responder /etc/benteng/ /www-benteng/; do
       grep -qxF "$f" /etc/sysupgrade.conf || echo "$f" >> /etc/sysupgrade.conf
   done
   ```

   Lalu buat cadangan dan simpan di laptop (**PowerShell**):

   ```powershell
   ssh root@$Sensor "sysupgrade -b /tmp/benteng-sensor-backup.tar.gz"
   scp -O "root@${Sensor}:/tmp/benteng-sensor-backup.tar.gz" $env:USERPROFILE\Documents\
   ```

   Memulihkan: salin file itu ke `/tmp/` sensor, jalankan
   `sysupgrade -r /tmp/benteng-sensor-backup.tar.gz && reboot`, lalu ulangi perintah
   `enable`/`start` di langkah 5.4.

2. **Snapshot dan ekspor VM** (Jalur A):

   ```powershell
   & $VBox snapshot benteng-sensor take "siap-demo" --description "Sensor BENTENG lengkap dan teruji"
   & $VBox export benteng-sensor benteng-korban -o "$env:USERPROFILE\Documents\benteng-lab.ova"
   ```

   File `.ova` bisa diimpor di laptop lain lewat **File → Import Appliance**.
