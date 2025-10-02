#!/bin/sh
set -e

if [ ! -f .env ]; then
  echo "==> Aucun .env trouvé, copie de .env.example..."
  cp .env.example .env
  SECRET=$(php -r "echo bin2hex(random_bytes(16));")
  sed -i "s/APP_SECRET=.*/APP_SECRET=${SECRET}/" .env
  echo "==> APP_SECRET généré automatiquement."
fi

echo "==> Création des dossiers var/..."
mkdir -p var/cache var/log

echo "==> Installation des dépendances Composer..."
composer install --no-interaction --prefer-dist --no-scripts

echo "==> Génération des autoloaders..."
composer dump-autoload --no-interaction

echo "==> Warm-up du cache Symfony..."
php bin/console cache:warmup

echo "==> Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "==> Démarrage du serveur PHP sur le port 8000..."
exec php -S 0.0.0.0:8000 -t public
