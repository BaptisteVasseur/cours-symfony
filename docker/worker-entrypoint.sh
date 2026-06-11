#!/bin/sh
set -e

echo "==> Attente que les dépendances Composer soient disponibles..."
until [ -f /var/www/html/vendor/autoload.php ]; do
  echo "==> vendor/autoload.php absent, nouvelle tentative dans 3s..."
  sleep 3
done

echo "==> Warm-up du cache Symfony..."
php bin/console cache:warmup

echo "==> Démarrage du worker Messenger..."
exec php bin/console messenger:consume async -vv --time-limit=3600
