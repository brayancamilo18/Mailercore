.PHONY: up dev down sh test pint stan migrate fresh

up:
	docker compose up -d --build

dev:
	docker compose --profile dev up -d --build

down:
	docker compose down

sh:
	docker compose exec app bash

test:
	docker compose exec app php artisan test

pint:
	docker compose exec app ./vendor/bin/pint

stan:
	docker compose exec app ./vendor/bin/phpstan analyse

migrate:
	docker compose exec app php artisan migrate

fresh:
	docker compose exec app php artisan migrate:fresh --seed
