.DEFAULT_GOAL := help

install:
	docker compose up -d --build

sh:
	docker compose exec -it php sh

cache:
	docker compose exec -it php php bin/console cache:clear

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
	@echo "  logs     - Follow the logs of the PHP container"
	@echo "  help     - Show this help message"
