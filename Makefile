# Tareas de la extensión: pruebas y revisión del código. Ejecutar desde la raíz del repositorio.
# Uso: make <tarea> [MODULE=moduloN]. Ejemplos: make test, make test-php MODULE=modulo2, make lint.
# Las pruebas usan los scripts de tests/extension/ (ver README, "Pruebas").

SHELL := bash
RUST_DIR := rust/extension-service
# Módulo a probar (opcional): filtra las pruebas de Rust por nombre y elige la carpeta de PHP
MODULE ?=

.PHONY: help test test-unit test-integration test-rust test-rust-unit test-rust-integration \
	test-php test-php-unit test-php-integration lint fmt up build-service seed-demo

help: ## Muestra las tareas disponibles
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-24s %s\n", $$1, $$2}'

test: test-rust test-php ## Todas las pruebas (Rust y PHP)

test-unit: test-rust-unit test-php-unit ## Solo las unitarias (Rust sin Docker; PHP necesita el entorno)

test-integration: test-rust-integration test-php-integration ## Solo las de integración (necesitan Docker)

test-rust: ## Todas las pruebas de Rust
	bash tests/extension/test-rust.sh all $(MODULE)

test-rust-unit: ## Pruebas unitarias de Rust (no necesitan Docker)
	bash tests/extension/test-rust.sh unit $(MODULE)

test-rust-integration: ## Pruebas de integración de Rust (MySQL de Docker)
	bash tests/extension/test-rust.sh integration $(MODULE)

test-php: ## Todas las pruebas PHP (dentro del contenedor de Providence)
	bash tests/extension/test-php.sh all $(MODULE)

test-php-unit: ## Pruebas unitarias PHP
	bash tests/extension/test-php.sh unit $(MODULE)

test-php-integration: ## Pruebas de integración PHP
	bash tests/extension/test-php.sh integration $(MODULE)

lint: ## Revisa el formato y los avisos de Rust (falla si hay algo para corregir)
	cd $(RUST_DIR) && cargo fmt --check && cargo clippy --all-targets --locked -- -D warnings

fmt: ## Formatea el código de Rust
	cd $(RUST_DIR) && cargo fmt

up: ## Levanta el entorno de Docker
	docker compose up -d --build

build-service: ## Reconstruye y reinicia solo el servicio Rust
	docker compose up -d --build extension-service

seed-demo: ## Carga registros y subtipos de ejemplo en el Providence de desarrollo (módulo 2)
	docker compose exec -u www-data providence php app/plugins/modulo2/tests/support/seed_demo_data.php --confirm
