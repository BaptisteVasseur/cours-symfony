#!/bin/sh

set -e

cd /var/www/html

if [ ! -f .env ]; then
  echo "==> Aucun .env trouve, copie de .env.example..."
  cp .env.example .env
  SECRET=$(php -r "echo bin2hex(random_bytes(16));")
  sed -i "s/APP_SECRET=.*/APP_SECRET=${SECRET}/" .env
  echo "==> APP_SECRET genere automatiquement."
fi

echo "==> Creation des dossiers var/..."
mkdir -p var/cache var/log

if [ ! -f vendor/autoload.php ]; then
  echo "==> Installation des dependances Composer..."
  composer install --no-interaction --prefer-dist --no-scripts

  echo "==> Generation des autoloaders..."
  composer dump-autoload --no-interaction
fi

echo "==> Warm-up du cache Symfony..."
php bin/console cache:warmup

echo "==> Execution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

echo "==> Demarrage du serveur PHP sur le port 8000..."
exec php -S 0.0.0.0:8000 -t public
