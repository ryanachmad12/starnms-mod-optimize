.DEFAULT_GOAL := help

COMPOSE := docker compose --env-file .env.docker

.PHONY: help build up down restart logs ps migrate backup shell worker-logs

help:
	@printf '%s\n' 'make build       Build application and supporting images'
	@printf '%s\n' 'make up          Start the Docker deployment'
	@printf '%s\n' 'make down        Stop the Docker deployment'
	@printf '%s\n' 'make restart     Restart all services'
	@printf '%s\n' 'make logs        Follow all service logs'
	@printf '%s\n' 'make ps          Show service state'
	@printf '%s\n' 'make migrate     Run Laravel migrations once'
	@printf '%s\n' 'make backup      Create and verify a PostgreSQL backup now'
	@printf '%s\n' 'make shell       Open a Laravel application shell'
	@printf '%s\n' 'make worker-logs Follow monitoring worker logs'

build:
	$(COMPOSE) build

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

logs:
	$(COMPOSE) logs -f --tail=100

ps:
	$(COMPOSE) ps

migrate:
	$(COMPOSE) run --rm app php artisan migrate --force

backup:
	$(COMPOSE) exec postgres-backup backup-postgres

shell:
	$(COMPOSE) exec app php artisan tinker

worker-logs:
	$(COMPOSE) logs -f --tail=100 worker
