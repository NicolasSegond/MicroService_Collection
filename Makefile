# Makefile - Marketplace Microservices

.PHONY: help dev dev-down dev-logs dev-ps dev-clean k8s-start k8s-stop k8s-delete k8s-deploy k8s-setup k8s-status k8s-logs k8s-forward k8s-up k8s-restart build-images

CYAN := \033[36m
GREEN := \033[32m
YELLOW := \033[33m
RESET := \033[0m

help: ## Affiche cette aide
	@echo "$(CYAN)Marketplace Microservices$(RESET)"
	@echo ""
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo "$(GREEN)  MINIKUBE - Comment lancer ?$(RESET)"
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo ""
	@echo "  $(YELLOW)1ère fois (ou après k8s-delete):$(RESET)"
	@echo "     $(CYAN)make k8s-up$(RESET)        Crée le cluster + déploie + données de test"
	@echo "     $(CYAN)make k8s-forward$(RESET)   Ouvre les ports (garder le terminal ouvert)"
	@echo ""
	@echo "  $(YELLOW)Redémarrage (après reboot PC/Docker):$(RESET)"
	@echo "     $(CYAN)make k8s-restart$(RESET)   Redémarre le cluster existant (conserve les données)"
	@echo "     $(CYAN)make k8s-forward$(RESET)   Ouvre les ports"
	@echo ""
	@echo "  $(YELLOW)Arrêter / Supprimer:$(RESET)"
	@echo "     $(CYAN)make k8s-stop$(RESET)      Arrête (conserve les données)"
	@echo "     $(CYAN)make k8s-delete$(RESET)    Supprime tout (données perdues)"
	@echo ""
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo "$(GREEN)  DOCKER COMPOSE (Dev local)$(RESET)"
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo ""
	@echo "     $(CYAN)make dev$(RESET)           Démarre l'environnement dev"
	@echo "     $(CYAN)make dev-down$(RESET)      Arrête l'environnement"
	@echo "     $(CYAN)make dev-logs$(RESET)      Logs (make dev-logs s=article-service)"
	@echo "     $(CYAN)make dev-clean$(RESET)     Supprime volumes et données"
	@echo ""
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo "$(GREEN)  MINIKUBE - Commandes avancées$(RESET)"
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo ""
	@echo "     $(CYAN)make k8s-status$(RESET)    État des pods"
	@echo "     $(CYAN)make k8s-logs$(RESET)      Logs (make k8s-logs p=article-service)"
	@echo "     $(CYAN)make build-images$(RESET)  Reconstruit les images Docker"
	@echo "     $(CYAN)make k8s-deploy$(RESET)    Redéploie sur K8s"
	@echo "     $(CYAN)make k8s-setup$(RESET)     Réinitialise les données de test"
	@echo ""

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

k8s-start: ## Crée un nouveau cluster Minikube (utilisé par k8s-up)
	@echo "$(YELLOW)Création du cluster Minikube...$(RESET)"
	minikube start --cpus=4 --memory=8192 --driver=docker
	minikube addons enable ingress
	minikube addons enable metrics-server
	@echo "$(GREEN)Cluster créé !$(RESET)"

k8s-restart: ## Redémarre Minikube (conserve les données)
	@echo "$(YELLOW)Redémarrage de Minikube...$(RESET)"
	minikube start
	@echo ""
	@echo "$(GREEN)Cluster redémarré !$(RESET)"
	@echo "Lancez maintenant: $(CYAN)make k8s-forward$(RESET)"

k8s-stop: ## Arrête Minikube (conserve les données)
	minikube stop
	@echo "$(GREEN)Cluster arrêté.$(RESET) Pour relancer: $(CYAN)make k8s-restart$(RESET)"

k8s-delete: ## Supprime Minikube (ATTENTION: données perdues)
	minikube delete
	@echo "$(YELLOW)Cluster supprimé.$(RESET) Pour recréer: $(CYAN)make k8s-up$(RESET)"

build-images: ## Construit les images Docker
	@eval $$(minikube docker-env) && \
	docker build -t article-service:prod --target prod ./article-service && \
	docker build -t frontend:prod --target prod ./frontend && \
	docker build -t keycloak:local ./keycloak

k8s-secrets: ## Crée les secrets depuis .env
	@kubectl create namespace marketplace --dry-run=client -o yaml | kubectl apply -f -
	@kubectl create secret generic app-secrets --from-env-file=.env -n marketplace --dry-run=client -o yaml | kubectl apply -f -
	@echo "✓ Secrets créés"

k8s-deploy: k8s-secrets ## Déploie sur K8s
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
