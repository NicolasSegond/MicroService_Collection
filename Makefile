# Makefile - Marketplace Microservices

.PHONY: help dev dev-down dev-logs dev-clean k8s-up k8s-restart k8s-stop k8s-delete k8s-status k8s-logs k8s-forward build-images

CYAN := \033[36m
GREEN := \033[32m
YELLOW := \033[33m
RED := \033[31m
BOLD := \033[1m
RESET := \033[0m

help: ## Affiche cette aide
	@echo "$(CYAN)Marketplace Microservices$(RESET)"
	@echo ""
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo "$(GREEN)  DOCKER COMPOSE$(RESET)"
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo ""
	@echo "     $(CYAN)make dev$(RESET)           Démarre avec hot-reload"
	@echo "     $(CYAN)make dev-down$(RESET)      Arrête"
	@echo "     $(CYAN)make dev-logs$(RESET)      Logs (make dev-logs s=article-service)"
	@echo "     $(CYAN)make dev-clean$(RESET)     Supprime volumes"
	@echo ""
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo "$(GREEN)  MINIKUBE$(RESET)"
	@echo "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo ""
	@echo "     $(CYAN)make k8s-up$(RESET)        Crée cluster + déploie"
	@echo "     $(CYAN)make k8s-restart$(RESET)   Redémarre le cluster"
	@echo "     $(CYAN)make k8s-forward$(RESET)   Ouvre les ports"
	@echo "     $(CYAN)make k8s-stop$(RESET)      Arrête"
	@echo "     $(CYAN)make k8s-delete$(RESET)    Supprime tout"
	@echo "     $(CYAN)make k8s-status$(RESET)    État des pods"
	@echo "     $(CYAN)make k8s-logs$(RESET)      Logs (make k8s-logs p=article-service)"
	@echo ""

# ============================================
# DOCKER COMPOSE
# ============================================

dev:
	docker compose up -d --build
	@echo "$(GREEN)Prêt !$(RESET) http://localhost:3000"

dev-down:
	docker compose down

dev-logs:
	@if [ -z "$(s)" ]; then docker compose logs -f; else docker compose logs -f $(s); fi

dev-clean:
	docker compose down -v --remove-orphans

# ============================================
# MINIKUBE
# ============================================

build-images:
	@eval $$(minikube docker-env) && \
	docker build -t article-service --target minikube ./article-service && \
	docker build -t frontend --target prod ./frontend && \
	docker build -t keycloak ./keycloak

k8s-up:
	@echo ""
	@echo "$(BOLD)$(CYAN)═══ INSTALLATION KUBERNETES ═══$(RESET)"
	@echo ""
	@echo "$(YELLOW)[1/4]$(RESET) Création du cluster..."
	@minikube start --cpus=4 --memory=8192 --driver=docker
	@minikube addons enable ingress >/dev/null 2>&1
	@minikube addons enable metrics-server >/dev/null 2>&1
	@echo "$(GREEN)  ✓ Cluster créé$(RESET)"
	@echo ""
	@echo "$(YELLOW)[2/4]$(RESET) Build des images..."
	@$(MAKE) --no-print-directory build-images
	@echo "$(GREEN)  ✓ Images OK$(RESET)"
	@echo ""
	@echo "$(YELLOW)[3/4]$(RESET) Déploiement..."
	@kubectl create namespace marketplace --dry-run=client -o yaml | kubectl apply -f -
	@kubectl create secret generic app-secrets --from-env-file=.env -n marketplace --dry-run=client -o yaml | kubectl apply -f -
	@kubectl apply -k k8s/ >/dev/null
	@echo "$(GREEN)  ✓ Déployé$(RESET)"
	@echo ""
	@echo "$(YELLOW)[4/4]$(RESET) Attente des services..."
	@kubectl wait --for=condition=ready pod -l app=keycloak -n marketplace --timeout=300s >/dev/null 2>&1 && echo "$(GREEN)  ✓ Keycloak$(RESET)" || echo "$(RED)  ✗ Keycloak$(RESET)"
	@kubectl wait --for=condition=ready pod -l app=article-service -n marketplace --timeout=180s >/dev/null 2>&1 && echo "$(GREEN)  ✓ Article Service$(RESET)" || echo "$(RED)  ✗ Article Service$(RESET)"
	@kubectl wait --for=condition=ready pod -l app=frontend -n marketplace --timeout=180s >/dev/null 2>&1 && echo "$(GREEN)  ✓ Frontend$(RESET)" || echo "$(RED)  ✗ Frontend$(RESET)"
	@echo ""
	@echo "$(BOLD)$(GREEN)═══ PRÊT ! ═══$(RESET)"
	@echo "Lancez: $(CYAN)make k8s-forward$(RESET)"

k8s-restart:
	@echo ""
	@echo "$(BOLD)$(CYAN)═══ REDÉMARRAGE KUBERNETES ═══$(RESET)"
	@echo ""
	@echo "$(YELLOW)[1/2]$(RESET) Démarrage de Minikube..."
	@minikube start
	@echo "$(GREEN)  ✓ Cluster démarré$(RESET)"
	@echo ""
	@echo "$(YELLOW)[2/2]$(RESET) Attente des pods..."
	@kubectl wait --for=condition=ready pod -l app=keycloak -n marketplace --timeout=300s >/dev/null 2>&1 && echo "$(GREEN)  ✓ Keycloak$(RESET)" || echo "$(RED)  ✗ Keycloak$(RESET)"
	@kubectl wait --for=condition=ready pod -l app=article-service -n marketplace --timeout=180s >/dev/null 2>&1 && echo "$(GREEN)  ✓ Article Service$(RESET)" || echo "$(RED)  ✗ Article Service$(RESET)"
	@kubectl wait --for=condition=ready pod -l app=frontend -n marketplace --timeout=180s >/dev/null 2>&1 && echo "$(GREEN)  ✓ Frontend$(RESET)" || echo "$(RED)  ✗ Frontend$(RESET)"
	@echo ""
	@echo "$(BOLD)$(GREEN)═══ PRÊT ! ═══$(RESET)"
	@echo "Lancez: $(CYAN)make k8s-forward$(RESET)"

k8s-stop:
	minikube stop

k8s-delete:
	minikube delete

k8s-forward:
	@chmod +x ./scripts/k8s-forward.sh && ./scripts/k8s-forward.sh

k8s-status:
	@kubectl get pods -n marketplace

k8s-logs:
	@if [ -z "$(p)" ]; then kubectl get pods -n marketplace; else kubectl logs -f -n marketplace -l app=$(p); fi

