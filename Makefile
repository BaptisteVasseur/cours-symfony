.DEFAULT_GOAL := help

install:
	docker compose up -d --build

sh:
	docker compose exec -it php sh

cache:
	docker compose exec -it php php bin/console cache:clear

migrate:
	docker compose exec -it php php bin/console doctrine:migrations:migrate --no-interaction

fixtures:
	docker compose exec -it php php bin/console doctrine:fixtures:load --no-interaction

init: migrate fixtures
	@echo "==> Base de données initialisée (migrations + fixtures)."

worker:
	docker compose exec -it php php bin/console messenger:consume async -vv

maintenance:
	docker compose exec php php bin/console app:reservation:expire-pending --no-interaction
	docker compose exec php php bin/console app:reservation:complete-past --no-interaction
	docker compose exec php php bin/console app:ical:sync --no-interaction

test:
	docker compose exec php php bin/phpunit

test-init:
	docker compose exec database psql -U app -d app -tc "SELECT 1 FROM pg_database WHERE datname = 'app_test'" | findstr 1 >nul || docker compose exec database psql -U app -d app -c "CREATE DATABASE app_test;"
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction --env=test
	docker compose exec php php bin/console doctrine:fixtures:load --no-interaction --env=test

logs:
	docker compose logs -f --tail=100 php

up start:
	docker compose up -d && \
    echo "==> Les services ont été démarrés avec succès" && \
    echo "==> Vous pouvez accéder à l'application : http://localhost:8089" && \
    echo "==> Vous pouvez accéder à l'interface de la BDD : http://localhost:8088" && \
    echo "==> Vous pouvez accéder à l'interface de mailpit : http://localhost:8025"

down stop:
	docker compose down

restart: down up

help:
	@echo "Makefile commands:"
	@echo "  install  - Premier lancement : build + démarrage de tout"
	@echo "  sh       - Execute a shell inside the PHP container"
	@echo "  up       - Start the Docker containers"
	@echo "  down     - Stop and remove the Docker containers"
	@echo "  restart  - Restart the Docker containers"
	@echo "  cache    - Clear the Symfony cache"
	@echo "  migrate  - Exécuter les migrations Doctrine"
	@echo "  fixtures - Charger les fixtures (purge la BDD)"
	@echo "  init     - Migrations + fixtures (initialisation BDD)"
	@echo "  worker   - Consommer la file Messenger (emails async)"
	@echo "  maintenance - Expirer pending, compléter séjours, sync iCal"
	@echo "  test     - Lancer PHPUnit"
	@echo "  test-init - Préparer la BDD de test (app_test)"
	@echo "  logs     - Follow the logs of the PHP container"
	@echo "  help     - Show this help message"
