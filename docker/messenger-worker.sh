#!/bin/sh
set -e

cd /var/www/html

until [ -f vendor/autoload_runtime.php ]; do
  sleep 2
done

until php bin/console about >/dev/null 2>&1; do
  sleep 2
done

while true; do
  php bin/console messenger:consume async -vv --time-limit=3600
  sleep 2
done
