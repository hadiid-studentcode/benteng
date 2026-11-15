#!/bin/sh
# Memasang dan mengonfigurasi BENTENG di sensor OpenWrt dalam sekali jalan.
# Dipanggil oleh pasang-sensor.ps1 setelah semua file disalin ke folder yang sama dengan skrip ini:
#   sh pasang.sh <token> <alamat-server> [vm]
# Contoh: sh pasang.sh 3f9a...c1 http://192.168.56.1:8000 vm
# Aman dijalankan ulang: file BENTENG ditimpa dengan versi baru, pengaturan lain tidak disentuh.

TOKEN="$1"
SERVER="$2"
JALUR="$3"
D=$(dirname "$0")

if [ -z "$TOKEN" ] || [ -z "$SERVER" ]; then
	echo "Pakai: sh pasang.sh <token> <alamat-server> [vm]"
	exit 1
fi
if ! echo "$TOKEN" | grep -Eq '^[A-Za-z0-9]+$'; then
	echo "Token hanya boleh berisi huruf dan angka."
	exit 1
fi

langkah() { echo; echo "== $1"; }

langkah "Menyalin skrip BENTENG"
mkdir -p /etc/benteng /www-benteng
for f in benteng-collect benteng-agent benteng-responder; do
	tr -d '\r' < "$D/$f" > "/usr/bin/$f"
	chmod +x "/usr/bin/$f"
done
tr -d '\r' < "$D/benteng-collect.init" > /etc/init.d/benteng-collect
tr -d '\r' < "$D/benteng-responder.init" > /etc/init.d/benteng-responder
chmod +x /etc/init.d/benteng-collect /etc/init.d/benteng-responder
tr -d '\r' < "$D/benteng-nft.conf" > /etc/benteng/benteng-nft.conf
tr -d '\r' < "$D/benteng-portal.nft" > /etc/benteng/benteng-portal.nft
cp "$D/index.html" /www-benteng/index.html
[ -f /etc/banner.asli ] || cp /etc/banner /etc/banner.asli
tr -d '\r' < "$D/benteng-banner" > /etc/banner

sed -i "s/GANTI-DENGAN-TOKEN-ACAK/$TOKEN/" /usr/bin/benteng-agent /usr/bin/benteng-responder
sed -i "s#http://192.168.56.1:8000#$SERVER#" /usr/bin/benteng-agent /usr/bin/benteng-responder
echo "Server: $SERVER"

langkah "Zona waktu WIB dan pencatatan DNS"
uci set system.@system[0].hostname='benteng-sensor'
uci set system.@system[0].zonename='Asia/Jakarta'
uci set system.@system[0].timezone='WIB-7'
uci commit system
/etc/init.d/system reload
uci set dhcp.@dnsmasq[0].logqueries='1'
if [ "$JALUR" = "vm" ]; then
	# NAT VirtualBox tidak punya IPv6.
	uci set dhcp.@dnsmasq[0].filter_aaaa='1'
fi
uci commit dhcp
/etc/init.d/dnsmasq restart >/dev/null 2>&1
date

langkah "Halaman karantina di port 8081"
uci -q delete uhttpd.benteng
uci set uhttpd.benteng=uhttpd
uci add_list uhttpd.benteng.listen_http='0.0.0.0:8081'
uci set uhttpd.benteng.home='/www-benteng'
uci set uhttpd.benteng.error_page='/index.html'
uci set uhttpd.benteng.no_dirlists='1'
uci commit uhttpd
/etc/init.d/uhttpd restart

langkah "Menyalakan layanan"
/etc/init.d/benteng-collect enable
/etc/init.d/benteng-collect restart
/etc/init.d/benteng-responder enable
/etc/init.d/benteng-responder restart
touch /etc/crontabs/root
grep -qF /usr/bin/benteng-agent /etc/crontabs/root || echo '* * * * * /usr/bin/benteng-agent' >> /etc/crontabs/root
/etc/init.d/cron enable
/etc/init.d/cron restart

# File BENTENG ikut tersimpan saat cadangan konfigurasi dibuat (sysupgrade -b).
for f in /usr/bin/benteng-collect /usr/bin/benteng-agent /usr/bin/benteng-responder \
	/etc/init.d/benteng-collect /etc/init.d/benteng-responder /etc/benteng/ /www-benteng/; do
	grep -qxF "$f" /etc/sysupgrade.conf || echo "$f" >> /etc/sysupgrade.conf
done

langkah "Memeriksa hasil"
GAGAL=0
H=$(wget -O /dev/null --header="X-Sensor-Token: $TOKEN" "$SERVER/api/sensors/BTG-02/karantina" 2>&1)
case "$H" in
	*"Writing to"*) echo "[OK] Server terhubung dan token cocok" ;;
	*401*) echo "[GAGAL] Token di sensor berbeda dengan BENTENG_SENSOR_TOKEN di .env"; GAGAL=1 ;;
	*) echo "[GAGAL] Server tidak bisa dihubungi di $SERVER: $H"; GAGAL=1 ;;
esac
sleep 2
if nft list table inet benteng >/dev/null 2>&1; then
	echo "[OK] Aturan karantina (nftables) aktif"
else
	echo "[GAGAL] Tabel nftables inet benteng tidak ada. Lihat: logread -e benteng"
	GAGAL=1
fi
if wget -qO- http://127.0.0.1:8081/ 2>/dev/null | grep -q 'BENTENG'; then
	echo "[OK] Halaman karantina tersedia"
else
	echo "[PERINGATAN] Halaman karantina belum bisa dibuka. Karantina tetap berfungsi."
fi
echo "Data DNS dan DHCP pertama terkirim dalam 1 menit."

rm -rf "$D"
exit $GAGAL
