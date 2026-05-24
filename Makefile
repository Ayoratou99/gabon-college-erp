# CUK Concours — common dev tasks
.DEFAULT_GOAL := help

DC := docker compose
APP := $(DC) exec app

.PHONY: help up down restart logs shell migrate fresh seed test stan fmt fmt-check rector cache-clear modules-list

help: ## Show this help.
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

up: ## Build and start the stack.
	$(DC) up -d --build

down: ## Stop and remove containers.
	$(DC) down

restart: down up ## Restart the stack.

logs: ## Tail container logs.
	$(DC) logs -f --tail=200

shell: ## Bash into the app container.
	$(APP) bash

migrate: ## Run pending migrations.
	$(APP) php artisan migrate

fresh: ## Drop everything and re-migrate + seed.
	$(APP) php artisan migrate:fresh --seed

seed: ## Run database seeders.
	$(APP) php artisan db:seed

test: ## Pest test suite.
	$(APP) composer test

test-unit: ## Pest, unit suite only.
	$(APP) composer test:unit

stan: ## Static analysis.
	$(APP) composer stan

fmt: ## Apply Pint formatting.
	$(APP) composer fmt

fmt-check: ## Verify Pint formatting (CI).
	$(APP) composer lint

rector: ## Rector dry-run.
	$(APP) composer rector

cache-clear: ## Clear all Laravel caches.
	$(APP) php artisan optimize:clear

modules-list: ## List nWidart modules.
	$(APP) php artisan module:list
