.DEFAULT_GOAL := help

sh:
	docker compose exec -it php sh
	# Execute a bash shell inside the PHP container

up:
	docker compose up -d
	# Start the Docker containers in detached mode

down:
	docker compose down
	# Stop and remove the Docker containers

restart: down up
	# Restart the Docker containers

cache:
	docker compose exec -it php php bin/console cache:clear
	# Clear the Symfony cache inside the PHP container

logs:
	docker compose logs php -f --tail=100
	# Follow the logs of php container, showing the last 100 lines

help:
	@echo "Makefile commands:"
	@echo "  sh       - Execute a shell inside the PHP container"
	@echo "  up       - Start the Docker containers"
	@echo "  down     - Stop and remove the Docker containers"
	@echo "  restart  - Restart the Docker containers"
	@echo "  cache    - Clear the Symfony cache"
	@echo "  logs     - Follow the logs of the PHP container"
	@echo "  help     - Show this help message"
	# Display help information about the Makefile commands
