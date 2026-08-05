DC   := docker compose
EXEC := $(DC) exec -T php

.DEFAULT_GOAL := help
.PHONY: help up down build sh install migrate seed test stan arch ci fresh logs reset

help: ## Diese Uebersicht
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-9s\033[0m %s\n", $$1, $$2}'

## ------------------------------------------------------------- Container

up: ## Container starten (wartet, bis die Datenbank gesund ist)
	$(DC) up -d --wait

down: ## Container stoppen
	$(DC) down

build: ## Images neu bauen
	$(DC) build

sh: ## Shell im PHP-Container
	$(DC) exec php sh

logs: ## Logs folgen
	$(DC) logs -f

## ---------------------------------------------------------------- Setup

install: ## composer install im Container
	$(EXEC) composer install

migrate: ## Migrationen anwenden
	$(EXEC) bin/console doctrine:migrations:migrate -n

seed: ## Beispielkontakte anlegen (tut nichts, wenn schon welche da sind)
	$(EXEC) bin/console contact:seed

fresh: ## Alles von null: hochfahren, installieren, migrieren, seeden
	$(DC) up -d --build --wait
	$(EXEC) composer install
	$(EXEC) bin/console doctrine:migrations:migrate -n
	$(EXEC) bin/console contact:seed
	@echo ""
	@echo "  App       http://localhost:8080/contacts"
	@echo "  Profiler  http://localhost:8080/_profiler"
	@echo "  Mailpit   http://localhost:8025"
	@echo "  Postgres  127.0.0.1:5432   db=crm  user=app  pass=app"
	@echo ""

## ---------------------------------------------------------------- Gates

test: ## PHPUnit ueber alle Modul-Suites
	$(EXEC) bin/console doctrine:database:create --if-not-exists --env=test
	$(EXEC) bin/console doctrine:migrations:migrate -n --env=test
	$(EXEC) vendor/bin/phpunit

stan: ## PHPStan Level 8
	$(EXEC) bin/console cache:warmup --env=dev
	$(EXEC) vendor/bin/phpstan analyse --memory-limit=1G

arch: ## Deptrac: Modulgrenzen und Schichtung
	$(EXEC) vendor/bin/deptrac analyse --report-uncovered --fail-on-uncovered

ci: ## Alle Gates in derselben Reihenfolge wie die CI
	$(EXEC) composer validate --strict
	$(MAKE) stan
	$(MAKE) arch
	$(MAKE) test

## ----------------------------------------------------------------- Reset

reset: ## Container UND Datenbank-Volume loeschen. Daten sind danach weg.
	$(DC) down -v
