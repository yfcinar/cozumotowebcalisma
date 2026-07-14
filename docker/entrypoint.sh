#!/bin/sh
set -e

cd /var/www/html

# Render gibi platformlar PORT ortam değişkeni ile port atar; Apache'yi ona göre ayarla.
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# SQLite kullanılıyorsa ve veritabanı yoksa şemayı kur + başlangıç verilerini yükle.
if [ "${DB_DRIVER:-sqlite}" = "sqlite" ] && [ ! -f "${DB_SQLITE_PATH:-database/cozumoto.sqlite}" ]; then
    echo ">> Veritabanı bulunamadı, migrate + seed çalıştırılıyor..."
    php database/migrate.php
    php database/seed.php
    chown www-data:www-data "${DB_SQLITE_PATH:-database/cozumoto.sqlite}"
fi

exec "$@"
