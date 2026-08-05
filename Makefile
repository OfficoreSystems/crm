DC   := docker compose
EXEC := $(DC) exec -T php

# Bewusst ohne grep/awk/printf und ohne Anfuehrungszeichen in den Rezepten:
# GNU Make nimmt unter Windows cmd.exe, wenn keine sh im PATH liegt. Dort gibt
# es kein grep, und Anfuehrungszeichen wuerden mit ausgegeben statt entfernt.
# Alles hier laeuft deshalb sowohl unter sh als auch unter cmd.exe.

.DEFAULT_GOAL := help
.PHONY: help up down build sh install migrate seed test stan arch ci fresh logs reset

help:
	@echo ------------------------------------------------------------------
	@echo Setup
	@echo   make fresh ..... Alles von null: hochfahren, installieren, migrieren, seeden
	@echo   make install ... composer install im Container
	@echo   make migrate ... Migrationen anwenden
	@echo   make seed ...... Beispielkontakte anlegen
	@echo ------------------------------------------------------------------
	@echo Container
	@echo   make up ........ Container starten, wartet auf die Datenbank
	@echo   make down ...... Container stoppen
	@echo   make build ..... Images neu bauen
	@echo   make sh ........ Shell im PHP-Container
	@echo   make logs ...... Logs folgen
	@echo ------------------------------------------------------------------
	@echo Gates
	@echo   make test ...... PHPUnit ueber alle Modul-Suites
	@echo   make stan ...... PHPStan Level 8
	@echo   make arch ...... Deptrac: Modulgrenzen und Schichtung
	@echo   make ci ........ Alle Gates in CI-Reihenfolge
	@echo ------------------------------------------------------------------
	@echo Reset
	@echo   make reset ..... Container UND Datenbank-Volume loeschen. Daten sind danach weg.
	@echo ------------------------------------------------------------------

# ---------------------------------------------------------------- Container

up:
	$(DC) up -d --wait

down:
	$(DC) down

build:
	$(DC) build

sh:
	$(DC) exec php sh

logs:
	$(DC) logs -f

# ------------------------------------------------------------------- Setup

install:
	$(EXEC) composer install

migrate:
	$(EXEC) bin/console doctrine:migrations:migrate -n

seed:
	$(EXEC) bin/console contact:seed

fresh:
	$(DC) up -d --build --wait
	$(EXEC) composer install
	$(EXEC) bin/console doctrine:migrations:migrate -n
	$(EXEC) bin/console contact:seed
	@echo ------------------------------------------------------------------
	@echo   App .......... http://localhost:8080/contacts
	@echo   Profiler ..... http://localhost:8080/_profiler
	@echo   Mailpit ...... http://localhost:8025
	@echo   Postgres ..... 127.0.0.1:5432 db=crm user=app pass=app
	@echo ------------------------------------------------------------------

# ------------------------------------------------------------------- Gates

test:
	$(EXEC) bin/console doctrine:database:create --if-not-exists --env=test
	$(EXEC) bin/console doctrine:migrations:migrate -n --env=test
	$(EXEC) vendor/bin/phpunit

stan:
	$(EXEC) bin/console cache:warmup --env=dev
	$(EXEC) vendor/bin/phpstan analyse --memory-limit=1G

arch:
	$(EXEC) vendor/bin/deptrac analyse --report-uncovered --fail-on-uncovered

ci:
	$(EXEC) composer validate --strict
	$(MAKE) stan
	$(MAKE) arch
	$(MAKE) test

# ------------------------------------------------------------------- Reset

reset:
	$(DC) down -v
