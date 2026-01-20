# Makefile - Marketplace Microservices

.PHONY: help dev dev-down dev-logs dev-ps dev-clean k8s-start k8s-stop k8s-delete k8s-deploy k8s-setup k8s-status k8s-logs k8s-forward k8s-up build-images

CYAN := \033[36m
GREEN := \033[32m
YELLOW := \033[33m
RESET := \033[0m

help: ## Affiche cette aide
	@echo "$(CYAN)Marketplace Microservices$(RESET)"
	@echo ""
	@echo "$(GREEN)=== Docker Compose (Dev) ===$(RESET)"
	@grep -E '^dev' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*##"}; {printf "  $(CYAN)%-15s$(RESET) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(GREEN)=== Minikube (K8s) ===$(RESET)"
	@grep -E '^k8s|^build' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*##"}; {printf "  $(CYAN)%-15s$(RESET) %s\n", $$1, $$2}'

# ============================================
# DOCKER COMPOSE (DEV)
# ============================================

dev: ## Démarre l'environnement dev
	docker compose up -d --build
	@echo "$(GREEN)Prêt !$(RESET) http://localhost:3000"

dev-down: ## Arrête l'environnement
	docker compose down

dev-logs: ## Logs (make dev-logs s=article-service)
	@if [ -z "$(s)" ]; then docker compose logs -f; else docker compose logs -f $(s); fi

dev-ps: ## État des services
	docker compose ps

dev-clean: ## Supprime volumes et données
	docker compose down -v --remove-orphans

# ============================================
# MINIKUBE (K8S)
# ============================================

k8s-start: ## Démarre Minikube
	minikube start --cpus=4 --memory=8192 --driver=docker
	minikube addons enable ingress
	minikube addons enable metrics-server

k8s-stop: ## Arrête Minikube
	minikube stop

k8s-delete: ## Supprime Minikube
	minikube delete

build-images: ## Construit les images Docker
	@eval $$(minikube docker-env) && \
	docker build -t article-service:prod --target prod ./article-service && \
	docker build -t frontend:prod --target prod ./frontend

k8s-deploy: ## Déploie sur Kubernetes
	kubectl apply -k k8s/

k8s-setup: ## Initialise (migrations + données)
	@chmod +x ./scripts/k8s-setup.sh && ./scripts/k8s-setup.sh

k8s-status: ## État des pods
	@kubectl get pods -n marketplace

k8s-logs: ## Logs (make k8s-logs p=article-service)
	@if [ -z "$(p)" ]; then kubectl get pods -n marketplace; else kubectl logs -f -n marketplace -l app=$(p); fi

k8s-forward: ## Port-forwards (localhost)
	@chmod +x ./scripts/k8s-forward.sh && ./scripts/k8s-forward.sh

# ============================================
# COMMANDES PRINCIPALES
# ============================================

k8s-up: k8s-start build-images k8s-deploy k8s-setup ## Lance TOUT (start + build + deploy + setup)
	@echo ""
	@echo "$(GREEN)=== APPLICATION PRÊTE ===$(RESET)"
	@echo ""
	@echo "Lancez: $(CYAN)make k8s-forward$(RESET)"
	@echo "Puis:   $(CYAN)http://localhost:3000$(RESET)"
	@echo ""
	@echo "Login:  $(CYAN)testuser / test123$(RESET)"
