#!/usr/bin/env bash
# Pasang BENTENG dengan sensor sungguhan dalam sekali jalan, untuk server Linux atau macOS.
# Versi Windows: pasang-sensor.ps1. Langkahnya sama.
#
# Menyiapkan server (dependensi, database, token, akun), menyalakan dashboard dan detektor
# di latar belakang, lalu memasang skrip BENTENG di sensor OpenWrt lewat SSH dan memeriksa hasilnya.
#
# Syarat: sensor sudah bisa di-ping (docs/PANDUAN-SENSOR.md langkah 2A atau 2B),
# PHP 8.4.1+ dengan pdo_sqlite, Composer, Node.js, dan OpenSSH.
#
# Pakai, dari folder repo:
#   bash benteng-starter/pasang-sensor.sh vm
#   bash benteng-starter/pasang-sensor.sh router
#   bash benteng-starter/pasang-sensor.sh router 192.168.1.1 http://192.168.1.100:8000
#
# Aman dijalankan ulang: .env, database, dan akun yang sudah ada tidak ditimpa.

set -u

JALUR="${1:-vm}"
SENSOR="${2:-}"
SERVER="${3:-}"
EMAIL_ANALIS="${EMAIL_ANALIS:-analis@satuan.mil}"

STARTER="$(cd "$(dirname "$0")" && pwd)"
APP="$(cd "$STARTER/../benteng-server" && pwd)"

langkah() { printf '\n\033[36m== %s\033[0m\n' "$1"; }
berhenti() { printf '\033[31m[GAGAL] %s\033[0m\n' "$1"; exit 1; }

baca_env() { grep -E "^$1=" "$APP/.env" | head -n1 | cut -d= -f2- | tr -d '\r'; }
tulis_env() {
	if grep -qE "^$1=" "$APP/.env"; then
		php -r '$f=$argv[1]; $k=$argv[2]; $v=$argv[3]; file_put_contents($f, preg_replace("/^".preg_quote($k,"/")."=.*$/m", "$k=$v", file_get_contents($f)));' "$APP/.env" "$1" "$2"
	else
		printf '\n%s=%s\n' "$1" "$2" >> "$APP/.env"
	fi
}

# --- Alamat sensor dan server --------------------------------------------------------

case "$JALUR" in
	vm)
		SENSOR="${SENSOR:-192.168.56.20}"
		SERVER="${SERVER:-http://192.168.56.1:8000}"
		;;
	router)
		SENSOR="${SENSOR:-192.168.1.1}"
		if [ -z "$SERVER" ]; then
			IP=$( (ip -4 -o addr show 2>/dev/null || ifconfig 2>/dev/null) | grep -oE '192\.168\.1\.[0-9]+' | grep -v '\.255$' | head -n1)
			[ -n "$IP" ] || berhenti "Komputer ini tidak punya alamat 192.168.1.x. Colok ke port LAN router, atau isi alamat server: bash $0 router 192.168.1.1 http://<ip>:8000"
			SERVER="http://$IP:8000"
		fi
		;;
	*) berhenti "Jalur harus 'vm' atau 'router'." ;;
esac
echo "Jalur: $JALUR   Sensor: $SENSOR   Server: $SERVER"

# --- 1. Server ------------------------------------------------------------------------

langkah "Memeriksa PHP"
command -v php >/dev/null || berhenti "PHP tidak ditemukan."
php -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' || berhenti "PHP $(php -r 'echo PHP_VERSION;') terlalu lama. Butuh 8.4.1 atau lebih baru."
php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);' || berhenti "Ekstensi pdo_sqlite belum aktif (Debian/Ubuntu: sudo apt install php-sqlite3)."
echo "PHP $(php -r 'echo PHP_VERSION;')"

cd "$APP" || exit 1

langkah "Menyiapkan server"
if [ ! -d vendor ]; then composer install --no-interaction || berhenti "composer install gagal."; fi
if [ ! -f public/build/manifest.json ]; then { npm install && npm run build; } || berhenti "npm run build gagal."; fi
if [ ! -f .env ]; then
	cp .env.example .env
	php artisan key:generate --no-interaction
fi
[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate --force --no-interaction || berhenti "Migrasi database gagal."

TOKEN="$(baca_env BENTENG_SENSOR_TOKEN)"
if [ -z "$TOKEN" ]; then
	TOKEN="$(php -r 'echo bin2hex(random_bytes(16));')"
	tulis_env BENTENG_SENSOR_TOKEN "$TOKEN"
	echo "Token sensor baru dibuat di .env"
fi
if [ "$(baca_env BENTENG_MODE_DEMO)" = "true" ]; then
	printf '\033[33m[PERINGATAN] Mode demo dimatikan. Data simulasi lama bisa dihapus dengan: php artisan benteng:reset-demo\033[0m\n'
fi
tulis_env BENTENG_MODE_DEMO false
php artisan config:clear >/dev/null

if [ "$(php artisan tinker --execute 'echo App\Models\User::count();' | tr -d '[:space:]')" = "0" ]; then
	langkah "Membuat akun analis $EMAIL_ANALIS"
	php artisan benteng:pengguna "$EMAIL_ANALIS" --nama="Analis Jaga"
	printf '\033[33mSimpan kata sandi di atas, karena tidak bisa ditampilkan lagi.\033[0m\n'
fi

langkah "Menyalakan dashboard dan detektor"
if php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);'; then
	echo "Port 8000 sudah dipakai; dianggap dashboard sudah berjalan."
else
	nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &
	echo "Dashboard berjalan (PID $!), log: benteng-server/storage/logs/serve.log"
	nohup php artisan schedule:work > storage/logs/detektor.log 2>&1 &
	echo "Detektor berjalan (PID $!), log: benteng-server/storage/logs/detektor.log"
	echo "Menghentikan keduanya nanti: pkill -f 'artisan (serve|schedule:work)'"
	sleep 4
fi

# --- 2. Akses SSH ke sensor -------------------------------------------------------------

langkah "Menghubungi sensor $SENSOR"
{ ping -c 2 "$SENSOR" || ping -n 2 "$SENSOR"; } >/dev/null 2>&1 || berhenti "Sensor $SENSOR tidak membalas ping. Ikuti langkah 2A atau 2B di docs/PANDUAN-SENSOR.md."

KUNCI="$HOME/.ssh/id_ed25519"
if [ ! -f "$KUNCI" ]; then
	mkdir -p "$HOME/.ssh"
	ssh-keygen -t ed25519 -f "$KUNCI" -N '' -q
fi
SSH_OPSI=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=8 -o LogLevel=ERROR)

if ! ssh "${SSH_OPSI[@]}" -o BatchMode=yes "root@$SENSOR" true 2>/dev/null; then
	ssh-keygen -R "$SENSOR" >/dev/null 2>&1
	echo "Memasang kunci SSH ke sensor. Masukkan kata sandi root sensor sekali."
	ssh "${SSH_OPSI[@]}" "root@$SENSOR" 'cat >> /etc/dropbear/authorized_keys && chmod 600 /etc/dropbear/authorized_keys' < "$KUNCI.pub"
	ssh "${SSH_OPSI[@]}" -o BatchMode=yes "root@$SENSOR" true 2>/dev/null || berhenti "Masuk SSH tanpa kata sandi belum berhasil. Periksa kata sandi root sensor."
fi
echo "SSH ke sensor siap."

# --- 3. Pasang skrip di sensor ---------------------------------------------------------

langkah "Menyalin file ke sensor"
cd "$STARTER" || exit 1
ssh "${SSH_OPSI[@]}" "root@$SENSOR" 'rm -rf /tmp/benteng-pasang && mkdir -p /tmp/benteng-pasang'
scp -O "${SSH_OPSI[@]}" pasang.sh sensor/benteng-collect sensor/benteng-collect.init sensor/benteng-agent sensor/benteng-banner \
	responden/benteng-responder responden/benteng-responder.init responden/benteng-nft.conf \
	responden/benteng-portal.nft responden/www-benteng/index.html "root@$SENSOR:/tmp/benteng-pasang/" \
	|| berhenti "Gagal menyalin file ke sensor."

langkah "Memasang BENTENG di sensor"
MODE=router
[ "$JALUR" = "vm" ] && MODE=vm
if ssh "${SSH_OPSI[@]}" "root@$SENSOR" "tr -d '\r' < /tmp/benteng-pasang/pasang.sh > /tmp/benteng-pasang/p.sh && sh /tmp/benteng-pasang/p.sh '$TOKEN' '$SERVER' $MODE"; then
	printf '\n\033[32mSelesai. BENTENG terpasang.\033[0m\n'
	echo "1. Buka http://localhost:8000 dan login sebagai analis."
	echo "2. Dalam 1-2 menit, menu Sensor menampilkan BTG-02 Online."
	echo "3. Jalankan alat uji di perangkat uji: python3 benteng-trafik-uji.py --menit 6"
	echo "   (lihat langkah 6 dan 7 di docs/PANDUAN-SENSOR.md)"
else
	printf '\n\033[31mSensor terpasang, tetapi ada pemeriksaan yang gagal. Lihat pesan [GAGAL] di atas\033[0m\n'
	echo 'dan tabel "Masalah yang sering muncul" di docs/PANDUAN-SENSOR.md.'
	echo "Penyebab paling sering: firewall server memblokir port 8000 (mis. sudo ufw allow 8000/tcp)."
	exit 1
fi
