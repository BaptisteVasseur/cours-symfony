.DEFAULT_GOAL := help

install:
	docker compose up -d --build

sh:
	docker compose exec -it php sh

cache:
	docker compose exec -it php php bin/console cache:clear

fixtures:
	docker compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "==> Fixtures chargées. Comptes de démo (mot de passe pour tous : password) :"
	@echo "    - Super admin   : admin@airbnb-clone.fr"
	@echo "    - Admin / modé  : moderation@airbnb-clone.fr"
	@echo "    - Hôte          : jeanmarc.dupont@email.com"
	@echo "    - Voyageur      : sophie.chen@email.com"
	@echo "    - Hôte + Admin  : test@example.com"

fixtures-fresh:
	docker compose exec -T php php bin/console doctrine:database:drop --force --if-exists
	docker compose exec -T php php bin/console doctrine:database:create --if-not-exists
	docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction
	$(MAKE) fixtures

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
	@echo "  cache           - Clear the Symfony cache"
	@echo "  fixtures        - Recharge les fixtures (purge la base puis recharge)"
	@echo "  fixtures-fresh  - Drop + recreate DB + migrate + fixtures (reset complet)"
	@echo "  logs            - Follow the logs of the PHP container"
	@echo "  help            - Show this help message"
