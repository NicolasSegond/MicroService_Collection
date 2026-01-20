# Makefile - Marketplace Microservices
# Commandes pour Docker Compose (dev) et Minikube (démo)

.PHONY: help dev dev-down dev-logs dev-ps k8s-start k8s-stop k8s-deploy k8s-delete k8s-status k8s-logs k8s-dashboard k8s-demo k8s-full k8s-wait k8s-migrate k8s-seed k8s-init k8s-forward k8s-forward-stop build-images

# Couleurs pour l'affichage
CYAN := \033[36m
GREEN := \033[32m
YELLOW := \033[33m
RESET := \033[0m

help: ## Affiche cette aide
	@echo "$(CYAN)Marketplace Microservices - Commandes disponibles$(RESET)"
	@echo ""
	@echo "$(GREEN)=== Docker Compose (Développement) ===$(RESET)"
	@grep -E '^dev' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*##"}; {printf "  $(CYAN)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(GREEN)=== Minikube (Démo Kubernetes) ===$(RESET)"
	@grep -E '^k8s' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*##"}; {printf "  $(CYAN)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(GREEN)=== Build ===$(RESET)"
	@grep -E '^build' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*##"}; {printf "  $(CYAN)%-20s$(RESET) %s\n", $$1, $$2}'

# ============================================
# DOCKER COMPOSE (DÉVELOPPEMENT)
# ============================================

dev: ## Démarre l'environnement de développement (Docker Compose)
	@echo "$(GREEN)Démarrage de l'environnement de développement...$(RESET)"
	docker compose up -d --build
	@echo "$(GREEN)Environnement prêt !$(RESET)"
	@echo "  Frontend:    http://localhost:3000"
	@echo "  API:         http://localhost:8000"
	@echo "  Keycloak:    http://localhost:8080"
	@echo "  Grafana:     http://localhost:3001"

dev-down: ## Arrête l'environnement de développement
	@echo "$(YELLOW)Arrêt de l'environnement...$(RESET)"
	docker compose down

dev-logs: ## Affiche les logs (usage: make dev-logs ou make dev-logs s=article-service)
	@if [ -z "$(s)" ]; then \
		docker compose logs -f; \
	else \
		docker compose logs -f $(s); \
	fi

dev-ps: ## Affiche l'état des services
	docker compose ps

dev-clean: ## Supprime tous les volumes et données
	@echo "$(YELLOW)Suppression des volumes...$(RESET)"
	docker compose down -v --remove-orphans

# ============================================
# MINIKUBE (DÉMO KUBERNETES)
# ============================================

k8s-start: ## Démarre Minikube avec les ressources nécessaires
	@echo "$(GREEN)Démarrage de Minikube...$(RESET)"
	minikube start --cpus=4 --memory=8192 --driver=docker
	@echo "$(GREEN)Activation de l'addon Ingress...$(RESET)"
	minikube addons enable ingress
	minikube addons enable metrics-server
	@echo "$(GREEN)Minikube prêt !$(RESET)"

k8s-stop: ## Arrête Minikube
	@echo "$(YELLOW)Arrêt de Minikube...$(RESET)"
	minikube stop

k8s-delete: ## Supprime le cluster Minikube
	@echo "$(YELLOW)Suppression du cluster Minikube...$(RESET)"
	minikube delete

build-images: ## Construit les images Docker pour Minikube
	@echo "$(GREEN)Configuration de l'environnement Docker Minikube...$(RESET)"
	@eval $$(minikube docker-env) && \
	echo "$(GREEN)Construction de l'image article-service...$(RESET)" && \
	docker build -t article-service:prod --target prod ./article-service && \
	echo "$(GREEN)Construction de l'image frontend...$(RESET)" && \
	docker build -t frontend:prod --target prod ./frontend && \
	echo "$(GREEN)Images construites !$(RESET)"

k8s-deploy: ## Déploie l'application sur Minikube
	@echo "$(GREEN)Déploiement sur Kubernetes...$(RESET)"
	kubectl apply -k k8s/
	@echo "$(GREEN)Déploiement lancé ! Utilisez 'make k8s-status' pour suivre.$(RESET)"

k8s-wait: ## Attend que tous les pods soient prêts
	@echo "$(YELLOW)Attente des pods...$(RESET)"
	@kubectl wait --for=condition=ready pod -l app=keycloak-db -n marketplace --timeout=180s 2>/dev/null || true
	@kubectl wait --for=condition=ready pod -l app=keycloak -n marketplace --timeout=300s 2>/dev/null || true
	@kubectl wait --for=condition=ready pod -l app=article-db -n marketplace --timeout=180s 2>/dev/null || true
	@kubectl wait --for=condition=ready pod -l app=article-service -n marketplace --timeout=180s 2>/dev/null || true
	@kubectl wait --for=condition=ready pod -l app=frontend -n marketplace --timeout=180s 2>/dev/null || true
	@echo "$(GREEN)Tous les pods sont prêts !$(RESET)"

k8s-migrate: ## Exécute les migrations de base de données
	@echo "$(YELLOW)Exécution des migrations...$(RESET)"
	@kubectl exec -n marketplace deploy/article-service -c article-service -- php bin/console doctrine:migrations:migrate --no-interaction
	@kubectl exec -n marketplace deploy/article-service -c article-service -- php bin/console doctrine:schema:update --force
	@echo "$(GREEN)Migrations terminées !$(RESET)"

k8s-seed: ## Charge les données de test (fixtures)
	@echo "$(YELLOW)Chargement des données de test...$(RESET)"
	@chmod +x ./scripts/k8s-seed.sh && ./scripts/k8s-seed.sh

k8s-init: ## Initialise l'application (migrations + seed data)
	@echo "$(GREEN)Initialisation de l'application...$(RESET)"
	@chmod +x ./scripts/k8s-init.sh && ./scripts/k8s-init.sh

k8s-status: ## Affiche l'état des pods Kubernetes
	@echo "$(CYAN)=== Pods ===$(RESET)"
	kubectl get pods -n marketplace -o wide
	@echo ""
	@echo "$(CYAN)=== Services ===$(RESET)"
	kubectl get svc -n marketplace
	@echo ""
	@echo "$(CYAN)=== Ingress ===$(RESET)"
	kubectl get ingress -n marketplace

k8s-logs: ## Affiche les logs d'un pod (usage: make k8s-logs p=article-service)
	@if [ -z "$(p)" ]; then \
		echo "Usage: make k8s-logs p=<pod-name>"; \
		echo "Pods disponibles:"; \
		kubectl get pods -n marketplace --no-headers -o custom-columns=":metadata.name"; \
	else \
		kubectl logs -f -n marketplace -l app=$(p); \
	fi

k8s-dashboard: ## Ouvre le dashboard Kubernetes
	@echo "$(GREEN)Ouverture du dashboard Kubernetes...$(RESET)"
	minikube dashboard

k8s-tunnel: ## Crée un tunnel pour accéder aux services (run in separate terminal)
	@echo "$(GREEN)Création du tunnel Minikube...$(RESET)"
	@echo "Ajoutez ces lignes à /etc/hosts:"
	@echo "  $$(minikube ip) marketplace.local api.marketplace.local auth.marketplace.local"
	@echo "  $$(minikube ip) traefik.marketplace.local kafka.marketplace.local grafana.marketplace.local"
	@echo ""
	minikube tunnel

# ============================================
# DÉMO COMPLÈTE
# ============================================

k8s-demo: k8s-start build-images k8s-deploy ## Lance la démo complète (Minikube + build + deploy)
	@echo ""
	@echo "$(GREEN)=====================================$(RESET)"
	@echo "$(GREEN)   DÉPLOIEMENT LANCÉ !$(RESET)"
	@echo "$(GREEN)=====================================$(RESET)"
	@echo ""
	@echo "$(CYAN)Prochaines étapes:$(RESET)"
	@echo ""
	@echo "  1. Attendez que les pods soient prêts:"
	@echo "     $(GREEN)make k8s-wait$(RESET)"
	@echo ""
	@echo "  2. Initialisez l'application (migrations + données):"
	@echo "     $(GREEN)make k8s-init$(RESET)"
	@echo ""
	@echo "  3. Lancez les port-forwards:"
	@echo "     $(GREEN)make k8s-forward$(RESET)"
	@echo ""
	@echo "  4. Accédez à l'application:"
	@echo "     Frontend:  http://localhost:3000"
	@echo "     API:       http://localhost:8000"
	@echo "     Keycloak:  http://localhost:8080"
	@echo "     Grafana:   http://localhost:3001"
	@echo ""
	@echo "  $(CYAN)Identifiants de test:$(RESET)"
	@echo "     testuser / test123"
	@echo "     admin / admin123"

k8s-full: k8s-demo k8s-wait k8s-init ## Lance TOUT (démo + attente + init) - Commande complète
	@echo ""
	@echo "$(GREEN)=====================================$(RESET)"
	@echo "$(GREEN)   APPLICATION PRÊTE !$(RESET)"
	@echo "$(GREEN)=====================================$(RESET)"
	@echo ""
	@echo "Lancez: $(GREEN)make k8s-forward$(RESET)"
	@echo ""
	@echo "Puis accédez à: $(CYAN)http://localhost:3000$(RESET)"

k8s-self-healing-demo: ## Démo du self-healing (tue un pod et montre le restart)
	@echo "$(YELLOW)Suppression du pod article-service...$(RESET)"
	kubectl delete pod -n marketplace -l app=article-service
	@echo "$(GREEN)Observez le redémarrage automatique:$(RESET)"
	kubectl get pods -n marketplace -l app=article-service -w

k8s-scale-demo: ## Démo du scaling (scale frontend à 3 replicas)
	@echo "$(GREEN)Scaling du frontend à 3 replicas...$(RESET)"
	kubectl scale deployment frontend -n marketplace --replicas=3
	kubectl get pods -n marketplace -l app=frontend -w

k8s-forward: ## Lance tous les port-forwards (Ctrl+C pour arrêter)
	@./scripts/k8s-forward.sh

k8s-forward-stop: ## Arrête tous les port-forwards
	@echo "$(YELLOW)Arrêt des port-forwards...$(RESET)"
	@pkill -f "kubectl port-forward -n marketplace" 2>/dev/null || true
	@echo "$(GREEN)Port-forwards arrêtés.$(RESET)"
