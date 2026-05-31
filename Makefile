# CUK Concours — common dev tasks.
#
# The PHP app + node are on the host (WAMP); only Postgres runs in Docker.
# Use bash via Git Bash / WSL / MSYS for the make targets.

.DEFAULT_GOAL := help

DC := docker compose

.PHONY: help up down restart logs psql install \
        migrate fresh seed clear-cache modules-list \
        build dev watch \
        test stan fmt fmt-check rector \
        dusk dusk-prep dusk-fresh chromedriver

# ---------------------------------------------------------------------------
# Discoverability
# ---------------------------------------------------------------------------

help: ## Show this help.
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------------------
# Docker (postgres only)
# ---------------------------------------------------------------------------

up: ## Start the Postgres container.
	$(DC) up -d

down: ## Stop the Postgres container (volume preserved).
	$(DC) down

restart: down up ## Restart Postgres.

logs: ## Tail Postgres logs.
	$(DC) logs -f --tail=200 postgres

psql: ## Open a psql shell inside the container.
	$(DC) exec postgres psql -U cuk -d cuk

# ---------------------------------------------------------------------------
# Host PHP + Node
# ---------------------------------------------------------------------------

install: ## composer install + npm install (host).
	composer install
	npm install

migrate: ## Run pending migrations against cuk DB.
	php artisan migrate

fresh: ## Drop everything and re-migrate + seed.
	php artisan migrate:fresh --seed

seed: ## Run database seeders.
	php artisan db:seed

clear-cache: ## Clear all Laravel caches.
	php artisan optimize:clear

modules-list: ## List nWidart modules.
	php artisan module:list

build: ## Production JS/CSS bundle.
	npm run build

dev: ## Vite dev server (HMR).
	npm run dev

watch: ## Rebuild bundle on every source change.
	npm run watch

# ---------------------------------------------------------------------------
# Quality
# ---------------------------------------------------------------------------

test: ## Pest test suite.
	composer test

stan: ## Static analysis.
	composer stan

fmt: ## Apply Pint formatting.
	composer fmt

fmt-check: ## Verify Pint formatting (CI).
	composer lint

rector: ## Rector dry-run.
	composer rector

# ---------------------------------------------------------------------------
# Dusk (browser tests)
# ---------------------------------------------------------------------------

dusk-prep: ## Prepare the cuk_dusk database (one-shot before the first run).
	-$(DC) exec postgres psql -U cuk -d cuk -c "CREATE DATABASE cuk_dusk OWNER cuk;"
	php artisan migrate --env=dusk.local --force
	php artisan db:seed --env=dusk.local --force

dusk-fresh: ## Re-create the dusk DB from scratch then reseed.
	-$(DC) exec postgres psql -U cuk -d postgres -c "DROP DATABASE IF EXISTS cuk_dusk;"
	$(DC) exec postgres psql -U cuk -d cuk -c "CREATE DATABASE cuk_dusk OWNER cuk;"
	php artisan migrate --env=dusk.local --force
	php artisan db:seed --env=dusk.local --force

dusk: ## Run the Dusk browser tests (boots its own php artisan serve).
	php artisan dusk

chromedriver: ## Re-download ChromeDriver to match the installed Chrome.
	php artisan dusk:chrome-driver --detect
