# Makefile
.PHONY: help migrate rollback reset refresh status create-migration shell db-shell

help:
	@echo "OneShot Migration Commands"
	@echo "============================"
	@echo "make migrate          - Run all pending migrations"
	@echo "make rollback         - Rollback the last migration"
	@echo "make rollback n=3     - Rollback 3 migrations"
	@echo "make reset            - Rollback all migrations"
	@echo "make refresh          - Reset and re-run all migrations"
	@echo "make status           - Show migration status"
	@echo "make create-migration name=add_users - Create a new migration"
	@echo "make shell            - Open a shell in the web container"
	@echo "make db-shell         - Open a PostgreSQL shell"

migrate:
	docker-compose exec web php migrations/migrate.php migrate

rollback:
	docker-compose exec web php migrations/migrate.php rollback $(n)

reset:
	docker-compose exec web php migrations/migrate.php reset

refresh:
	docker-compose exec web php migrations/migrate.php refresh

status:
	docker-compose exec web php migrations/migrate.php status

create-migration:
	docker-compose exec web php migrations/migrate.php create $(name)

shell:
	docker-compose exec web bash

db-shell:
	docker-compose exec db psql -U oneshot_user -d oneshot
