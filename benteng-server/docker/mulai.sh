#!/bin/sh
# Dijalankan setiap container BENTENG mulai: siapkan data, cache, penjadwal, lalu Apache.
set -e
cd /var/www/html

DATA_DIR="$(dirname "${DB_DATABASE:-/data/database.sqlite}")"
mkdir -p "$DATA_DIR"

# APP_KEY dibuat sekali lalu disimpan di penyimpanan permanen, agar sesi login tidak hilang tiap deploy.
if [ -z "$APP_KEY" ]; then
    if [ ! -s "$DATA_DIR/app_key" ]; then
        php -r 'echo "base64:".base64_encode(random_bytes(32));' > "$DATA_DIR/app_key"
        echo "APP_KEY baru dibuat di $DATA_DIR/app_key"
    fi
    APP_KEY="$(cat "$DATA_DIR/app_key")"
    export APP_KEY
fi

# Token sensor: pada mode demo tidak ada sensor sungguhan, jadi cukup token acak yang stabil.
if [ -z "$BENTENG_SENSOR_TOKEN" ]; then
    if [ ! -s "$DATA_DIR/sensor_token" ]; then
        php -r 'echo bin2hex(random_bytes(16));' > "$DATA_DIR/sensor_token"
    fi
    BENTENG_SENSOR_TOKEN="$(cat "$DATA_DIR/sensor_token")"
    export BENTENG_SENSOR_TOKEN
fi

[ -f "${DB_DATABASE:-/data/database.sqlite}" ] || touch "${DB_DATABASE:-/data/database.sqlite}"

php artisan migrate --force

# Mode demo: data simulasi dan akun demo dibuat ulang setiap container mulai.
if [ "$BENTENG_MODE_DEMO" = "true" ]; then
    php artisan benteng:siapkan-demo
fi

php artisan optimize

chown -R www-data:www-data storage bootstrap/cache "$DATA_DIR"

# Penjadwal Laravel (detektor tiap menit, data demo diperbarui tiap jam), berjalan sebagai www-data.
su -s /bin/sh www-data -c "php artisan schedule:work" > /dev/null 2>&1 &

exec apache2-foreground
