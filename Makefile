# =============================================================================
# proj-base — Makefile
# Docker DX shortcuts — mirrors composer scripts for containerized workflow
# =============================================================================

.PHONY: help up down dev build rebuild setup test lint shell tinker migrate \
        module logs fresh status ps artisan composer npm seed rollback

# Default target
.DEFAULT_GOAL := help

# ---------------------------------------------------------------------------
# Colors
# ---------------------------------------------------------------------------
BLUE   := \033[0;34m
GREEN  := \033[0;32m
YELLOW := \033[0;33m
RED    := \033[0;31m
NC     := \033[0m # No Color

# ---------------------------------------------------------------------------
# Variables
# ---------------------------------------------------------------------------
DC        := docker compose
EXEC      := $(DC) exec app
EXEC_IT   := $(DC) exec -it app
QUEUE     := $(DC) exec queue

# ---------------------------------------------------------------------------
# Help
# ---------------------------------------------------------------------------
help: ## Show this help
	@echo ""
	@echo "$(BLUE)proj-base$(NC) — Docker Commands"
	@echo "─────────────────────────────────────────"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-15s$(NC) %s\n", $$1, $$2}'
	@echo ""

# ---------------------------------------------------------------------------
# Container Lifecycle
# ---------------------------------------------------------------------------
up: ## Start all services (background)
	$(DC) up -d

down: ## Stop all services
	$(DC) down

dev: ## Start all services + Vite HMR (development)
	$(DC) --profile dev up -d

build: ## Build Docker images
	$(DC) build

rebuild: ## Rebuild Docker images (no cache)
	$(DC) build --no-cache

ps: ## Show running containers
	$(DC) ps

status: ## Show container status and health
	$(DC) ps -a --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

logs: ## Tail logs from all services (use s=app to filter)
ifdef s
	$(DC) logs -f $(s)
else
	$(DC) logs -f
endif

# ---------------------------------------------------------------------------
# Setup & Initialization
# ---------------------------------------------------------------------------
setup: build up ## Full project setup (build + start + install + migrate)
	$(EXEC) composer install --no-interaction
	$(EXEC) php artisan key:generate --no-interaction
	$(EXEC) php artisan migrate --force --no-interaction
	$(DC) run --rm vite npm ci --ignore-scripts
	$(DC) run --rm vite npm run build
	@echo ""
	@echo "$(GREEN)✅ Setup complete!$(NC)"
	@echo "   App:  http://localhost:$${APP_PORT:-80}"
	@echo "   Run $(YELLOW)make dev$(NC) to start developing."

# ---------------------------------------------------------------------------
# Artisan & Composer & NPM
# ---------------------------------------------------------------------------
artisan: ## Run artisan command (use c="migrate:status")
	$(EXEC) php artisan $(c)

composer: ## Run composer command (use c="require package/name")
	$(EXEC) composer $(c)

npm: ## Run npm command (use c="install package")
	$(DC) run --rm vite npm $(c)

# ---------------------------------------------------------------------------
# Database
# ---------------------------------------------------------------------------
migrate: ## Run database migrations
	$(EXEC) php artisan migrate --force --no-interaction

seed: ## Run database seeders
	$(EXEC) php artisan db:seed --force --no-interaction

rollback: ## Rollback the last migration batch
	$(EXEC) php artisan migrate:rollback

fresh: ## Destroy everything and rebuild from scratch
	$(DC) down -v --remove-orphans
	$(DC) build --no-cache
	$(DC) up -d
	$(EXEC) composer install --no-interaction
	$(EXEC) php artisan key:generate --no-interaction
	$(EXEC) php artisan migrate --force --no-interaction
	@echo "$(GREEN)✅ Fresh environment ready.$(NC)"

# ---------------------------------------------------------------------------
# Testing & Quality
# ---------------------------------------------------------------------------
test: ## Run PHPUnit tests
	$(EXEC) php artisan config:clear --no-interaction
	$(EXEC) php artisan test

lint: ## Run PHP Pint linter
	$(EXEC) ./vendor/bin/pint

analyse: ## Run PHPStan/Larastan static analysis
	$(EXEC) ./vendor/bin/phpstan analyse --memory-limit=1G

# ---------------------------------------------------------------------------
# Shell Access
# ---------------------------------------------------------------------------
shell: ## Open a shell in the app container
	$(EXEC_IT) sh

tinker: ## Open Laravel Tinker REPL
	$(EXEC_IT) php artisan tinker

# ---------------------------------------------------------------------------
# Module Management
# ---------------------------------------------------------------------------
module: ## Create a new module (interactive wizard)
	$(EXEC_IT) php artisan make:module

# ---------------------------------------------------------------------------
# Queue
# ---------------------------------------------------------------------------
queue-restart: ## Restart the queue worker
	$(DC) restart queue

queue-failed: ## List failed jobs
	$(EXEC) php artisan queue:failed

queue-retry: ## Retry all failed jobs
	$(EXEC) php artisan queue:retry all
