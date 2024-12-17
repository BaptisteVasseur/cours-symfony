.PHONY: bddReset
bddReset:
	php bin/console doctrine:database:drop --force --if-exists
	php bin/console doctrine:database:create
	php bin/console doctrine:migrations:migrate --no-interaction
	#php bin/console hautelook:fixtures:load --no-interaction
	php bin/console doctrine:fixtures:load --no-interaction

start:
	docker compose up -d
	symfony serve -d
