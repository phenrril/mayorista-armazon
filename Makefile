.PHONY: help up down restart logs clean rebuild shell-db shell-web

help: ## Mostrar esta ayuda
	@echo "Comandos disponibles:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Levantar los contenedores
	docker-compose up -d
	@echo "✓ Sistema levantado en http://localhost:8000"

down: ## Detener los contenedores
	docker-compose down
	@echo "✓ Sistema detenido"

restart: ## Reiniciar los contenedores
	docker-compose restart
	@echo "✓ Sistema reiniciado"

logs: ## Ver logs de los contenedores
	docker-compose logs -f

logs-db: ## Ver logs de la base de datos
	docker-compose logs -f db

logs-web: ## Ver logs del servidor web
	docker-compose logs -f web

clean: ## Detener y eliminar todo (datos incluidos)
	docker-compose down -v
	@echo "✓ Todo eliminado. Ejecuta 'make up' para reiniciar."

rebuild: ## Reconstruir las imágenes
	docker-compose build
	docker-compose up -d
	@echo "✓ Imágenes reconstruidas"

shell-db: ## Abrir shell de la base de datos
	docker exec -it optica_db bash

shell-web: ## Abrir shell del contenedor web
	docker exec -it optica_web bash

mysql: ## Conectarse a MySQL
	docker exec -it optica_db mysql -u c2880275_ventas -pwego76FIfe c2880275_ventas

status: ## Ver estado de los contenedores
	docker-compose ps

