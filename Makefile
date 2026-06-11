.DEFAULT_GOAL := help

install:
	docker compose up -d --build

sh:
	docker compose exec php sh

cache:
	docker compose exec php php bin/console cache:clear

fixtures_load:
    docker compose exec php php bin/console hautelook:fixtures:load --no-interaction

cache_warmup:
	docker compose exec php php bin/console cache:warmup

fixtures:
	docker compose exec php php bin/console doctrine:fixtures:load

fixtures_force:
	docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

migration:
	docker compose exec php php bin/console make:migration

migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate

migrate_force:
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

db_create:
	docker compose exec php php bin/console doctrine:database:create

db_drop:
	docker compose exec php php bin/console doctrine:database:drop --force

db_reset:
	docker compose exec php php bin/console doctrine:database:drop --force --if-exists && \
	docker compose exec php php bin/console doctrine:database:create && \
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction && \
	docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

composer_install:
	docker compose exec php composer install

composer_update:
	docker compose exec php composer update

test:
	docker compose exec php php bin/phpunit

lint:
	docker compose exec php php bin/console lint:twig templates
	docker compose exec php php bin/console lint:yaml config

cc:
	docker compose exec php php bin/console cache:clear

logs:
	docker compose logs -f --tail=100 php

up start:
	docker compose up -d && \
	echo "==> Les services ont été démarrés avec succès" && \
	echo "==> Application : http://localhost:8089" && \
	echo "==> Base de données : http://localhost:8088" && \
	echo "==> Mailpit : http://localhost:8025"

down stop:
	docker compose down

restart: down up

help:
	@echo "Makefile commands:"
	@echo "  install           - Build et démarrage des conteneurs"
	@echo "  sh                - Shell dans le conteneur PHP"
	@echo "  up                - Démarrer les conteneurs"
	@echo "  down              - Arrêter les conteneurs"
	@echo "  restart           - Redémarrer les conteneurs"
	@echo "  cache             - Vider le cache Symfony"
	@echo "  cache_warmup      - Réchauffer le cache Symfony"
	@echo "  fixtures          - Charger les fixtures"
	@echo "  fixtures_force    - Charger les fixtures sans confirmation"
	@echo "  migration         - Générer une migration"
	@echo "  migrate           - Exécuter les migrations"
	@echo "  migrate_force     - Exécuter les migrations sans confirmation"
	@echo "  db_create         - Créer la base de données"
	@echo "  db_drop           - Supprimer la base de données"
	@echo "  db_reset          - Recréer la BDD + migrations + fixtures"
	@echo "  composer_install  - Installer les dépendances Composer"
	@echo "  composer_update   - Mettre à jour les dépendances Composer"
	@echo "  test              - Lancer les tests PHPUnit"
	@echo "  lint              - Vérifier Twig et YAML"
	@echo "  logs              - Afficher les logs PHP"
	@echo "  help              - Afficher cette aide"
