.PHONY: help start stop install migrate fixtures worker ical-sync

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

start: ## Démarre les containers
	docker compose up -d

stop: ## Arrête les containers
	docker compose stop

install: ## Installe les dépendances et prépare la BDD
	docker compose exec php composer install
	make migrate
	make fixtures

migrate: ## Applique les migrations
	docker compose exec php php bin/console doctrine:migrations:migrate -n

make-migration: ## Crée une nouvelle migration
	docker compose exec php php bin/console make:migration

fixtures: ## Recharge toutes les données de test
	docker compose exec php php bin/console doctrine:fixtures:load -n

worker: ## Lance le worker pour l'envoi d'emails (Messenger)
	docker compose exec php php bin/console messenger:consume async -vv

ical-sync: ## Synchronise les flux iCal externes
	docker compose exec php php bin/console app:ical:sync

cache: ## Vide le cache
	docker compose exec php php bin/console cache:clear
