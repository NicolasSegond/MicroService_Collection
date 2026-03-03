# Makefile - Marketplace Microservices

SHELL := /bin/bash

.PHONY: help dev dev-down dev-logs dev-clean k8s-up k8s-update k8s-restart k8s-stop k8s-delete k8s-status k8s-logs k8s-forward build-images \
        demo-crash demo-scale monitoring-install traffic

CYAN := \033[36m
GREEN := \033[32m
YELLOW := \033[33m
RED := \033[31m
BOLD := \033[1m
RESET := \033[0m

help: ## Affiche cette aide
	@echo -e "$(CYAN)Marketplace Microservices$(RESET)"
	@echo -e ""
	@echo -e "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo -e "$(GREEN)  DOCKER COMPOSE$(RESET)"
	@echo -e "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo -e ""
	@echo -e "     $(CYAN)make dev$(RESET)           Démarre avec hot-reload"
	@echo -e "     $(CYAN)make dev-down$(RESET)      Arrête"
	@echo -e "     $(CYAN)make dev-logs$(RESET)      Logs (make dev-logs s=article-service)"
	@echo -e "     $(CYAN)make dev-clean$(RESET)     Supprime volumes"
	@echo -e ""
	@echo -e "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo -e "$(GREEN)  MINIKUBE$(RESET)"
	@echo -e "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo -e ""
	@echo -e "     $(CYAN)make k8s-up$(RESET)        Crée cluster + déploie"
	@echo -e "     $(CYAN)make k8s-update$(RESET)    Rebuild + redéploie (après modif code/assets)"
	@echo -e "     $(CYAN)make k8s-restart$(RESET)   Redémarre le cluster"
	@echo -e "     $(CYAN)make k8s-forward$(RESET)   Ouvre les ports"
	@echo -e "     $(CYAN)make k8s-stop$(RESET)      Arrête"
	@echo -e "     $(CYAN)make k8s-delete$(RESET)    Supprime tout"
	@echo -e "     $(CYAN)make k8s-status$(RESET)    État des pods"
	@echo -e "     $(CYAN)make k8s-logs$(RESET)      Logs (make k8s-logs p=article-service)"
	@echo -e ""
	@echo -e "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo -e "$(GREEN)  DEMOS K8S$(RESET)"
	@echo -e "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo -e ""
	@echo -e "     $(CYAN)make demo-crash$(RESET)    Auto-healing (kill + watch rebuild)"
	@echo -e "     $(CYAN)make demo-scale$(RESET)    Scalabilité (make demo-scale r=3)"
	@echo -e ""
	@echo -e "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo -e "$(GREEN)  MONITORING$(RESET)"
	@echo -e "$(GREEN)══════════════════════════════════════════════════════════════$(RESET)"
	@echo -e ""
	@echo -e "     $(CYAN)make monitoring-install$(RESET)  Prometheus + Grafana + AlertManager"
	@echo -e "     $(CYAN)make traffic$(RESET)             Trafic mixte + chaos pour Grafana"
	@echo -e "                              (make traffic d=120 e=15 r=5 c=40)"
	@echo -e ""
	@echo -e "     Accès (après k8s-forward):"
	@echo -e "       Grafana:      http://localhost:3001 (admin/admin)"
	@echo -e "       Prometheus:   http://localhost:9090"
	@echo -e "       AlertManager: http://localhost:9093"
	@echo -e ""

# ============================================
# DOCKER COMPOSE
# ============================================

dev:
	docker compose up -d --build
	@echo -e "$(GREEN)Prêt !$(RESET) http://localhost:3000"

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
	@docker build -t article-service:latest --target minikube ./article-service && \
	docker build -t frontend:latest --target prod ./frontend && \
	docker build -t keycloak:latest ./keycloak && \
	minikube image load article-service:latest && \
	minikube image load frontend:latest && \
	minikube image load keycloak:latest

k8s-up:
	@echo -e ""
	@echo -e "$(BOLD)$(CYAN)═══ INSTALLATION KUBERNETES ═══$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)[1/5]$(RESET) Création du cluster..."
	@minikube start --cpus=2 --memory=4192 --driver=docker --container-runtime=containerd
	@minikube addons enable ingress >/dev/null 2>&1
	@minikube addons enable metrics-server >/dev/null 2>&1
	@echo -e "$(GREEN)  ✓ Cluster créé$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)[2/5]$(RESET) Build des images..."
	@$(MAKE) --no-print-directory build-images
	@echo -e "$(GREEN)  ✓ Images OK$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)[3/5]$(RESET) Déploiement application..."
	@kubectl create namespace marketplace --dry-run=client -o yaml | kubectl apply -f -
	@kubectl create secret generic app-secrets --from-env-file=.env -n marketplace --dry-run=client -o yaml | kubectl apply -f -
	@kubectl apply -k k8s/ >/dev/null
	@echo -e "$(GREEN)  ✓ Application déployée$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)[4/5]$(RESET) Installation monitoring..."
	@$(MAKE) --no-print-directory monitoring-install
	@echo -e ""
	@echo -e "$(YELLOW)[5/5]$(RESET) Attente des services (parallèle)..."
	@( kubectl wait --for=condition=ready pod -l app=keycloak -n marketplace --timeout=120s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Keycloak$(RESET)" || echo -e "$(RED)  ✗ Keycloak$(RESET)" ) & \
	( kubectl wait --for=condition=ready pod -l app=article-service -n marketplace --timeout=120s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Article Service$(RESET)" || echo -e "$(RED)  ✗ Article Service$(RESET)" ) & \
	( kubectl wait --for=condition=ready pod -l app=frontend -n marketplace --timeout=60s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Frontend$(RESET)" || echo -e "$(RED)  ✗ Frontend$(RESET)" ) & \
	( kubectl wait --for=condition=ready pod -l app.kubernetes.io/name=prometheus -n monitoring --timeout=30s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Prometheus$(RESET)" || echo -e "$(RED)  ✗ Prometheus$(RESET)" ) & \
	( kubectl wait --for=condition=ready pod -l app.kubernetes.io/name=grafana -n monitoring --timeout=30s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Grafana$(RESET)" || echo -e "$(RED)  ✗ Grafana$(RESET)" ) & \
	( kubectl wait --for=condition=ready pod -l app.kubernetes.io/name=alertmanager -n monitoring --timeout=30s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ AlertManager$(RESET)" || echo -e "$(RED)  ✗ AlertManager$(RESET)" ) & \
	wait
	@echo -e ""
	@echo -e "$(BOLD)$(GREEN)═══ PRÊT ! ═══$(RESET)"
	@echo -e "Lancez: $(CYAN)make k8s-forward$(RESET)"

k8s-restart:
	@echo -e ""
	@echo -e "$(BOLD)$(CYAN)═══ REDÉMARRAGE KUBERNETES ═══$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)[1/2]$(RESET) Démarrage de Minikube..."
	@minikube start
	@echo -e "$(GREEN)  ✓ Cluster démarré$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)[2/2]$(RESET) Attente des pods..."
	@kubectl wait --for=condition=ready pod -l app=keycloak -n marketplace --timeout=300s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Keycloak$(RESET)" || echo -e "$(RED)  ✗ Keycloak$(RESET)"
	@kubectl wait --for=condition=ready pod -l app=article-service -n marketplace --timeout=180s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Article Service$(RESET)" || echo -e "$(RED)  ✗ Article Service$(RESET)"
	@kubectl wait --for=condition=ready pod -l app=frontend -n marketplace --timeout=180s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Frontend$(RESET)" || echo -e "$(RED)  ✗ Frontend$(RESET)"
	@echo -e ""
	@echo -e "$(BOLD)$(GREEN)═══ PRÊT ! ═══$(RESET)"
	@echo -e "Lancez: $(CYAN)make k8s-forward$(RESET)"

k8s-update:
	@echo -e ""
	@echo -e "$(BOLD)$(CYAN)═══ MISE À JOUR KUBERNETES ═══$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)[1/3]$(RESET) Rebuild des images..."
	@$(MAKE) --no-print-directory build-images
	@echo -e "$(GREEN)  ✓ Images OK$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)[2/3]$(RESET) Redéploiement..."
	@kubectl rollout restart deployment -n marketplace
	@echo -e "$(GREEN)  ✓ Déployé$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)[3/3]$(RESET) Attente des pods..."
	@kubectl rollout status deployment/article-service -n marketplace --timeout=180s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Article Service$(RESET)" || echo -e "$(RED)  ✗ Article Service$(RESET)"
	@kubectl rollout status deployment/frontend -n marketplace --timeout=180s >/dev/null 2>&1 && echo -e "$(GREEN)  ✓ Frontend$(RESET)" || echo -e "$(RED)  ✗ Frontend$(RESET)"
	@echo -e ""
	@echo -e "$(BOLD)$(GREEN)═══ MIS À JOUR ! ═══$(RESET)"

k8s-stop:
	minikube stop

k8s-delete:
	minikube delete

k8s-forward:
	@chmod +x ./k8s/scripts/k8s-forward.sh && ./k8s/scripts/k8s-forward.sh

k8s-status:
	@kubectl get pods -n marketplace

k8s-logs:
	@if [ -z "$(p)" ]; then kubectl get pods -n marketplace; else kubectl logs -f -n marketplace -l app=$(p); fi

# ============================================
# DEMOS KUBERNETES
# ============================================

demo-crash: ## Démo auto-healing : kill le pod avec trafic intense
	@echo -e ""
	@echo -e "$(BOLD)$(CYAN)═══ DEMO AUTO-HEALING ═══$(RESET)"
	@echo -e ""
	@echo -e "$(YELLOW)1. Lancement du trafic (30 sec en arrière-plan)...$(RESET)"
	@TRAFFIC_LOG=$$(mktemp /tmp/traffic-XXXXXX.log); \
	(for i in $$(seq 1 300); do curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/api/articles; sleep 0.1; done) > $$TRAFFIC_LOG 2>&1 & \
	TRAFFIC_PID=$$!; \
	trap "kill $$TRAFFIC_PID 2>/dev/null; rm -f $$TRAFFIC_LOG; exit" INT TERM EXIT; \
	sleep 1; \
	kubectl get pods -n marketplace -l app=article-service; \
	echo -e ""; \
	echo -e "$(RED)2. CRASH du pod...$(RESET)"; \
	kubectl delete pods -n marketplace -l app=article-service --wait=false; \
	echo -e ""; \
	echo -e "$(YELLOW)3. Watch reconstruction (Ctrl+C pour arrêter) :$(RESET)"; \
	kubectl get pods -n marketplace -l app=article-service -w; \
	kill $$TRAFFIC_PID 2>/dev/null; \
	echo -e ""; \
	echo -e "$(BOLD)$(CYAN)═══ RÉSUMÉ TRAFIC ═══$(RESET)"; \
	echo -e "$(GREEN)  200 : $$(grep -c '^200$$' $$TRAFFIC_LOG 2>/dev/null || echo 0) requêtes OK$(RESET)"; \
	echo -e "$(RED)  000 : $$(grep -c '^000$$' $$TRAFFIC_LOG 2>/dev/null || echo 0) requêtes échouées (pendant le crash)$(RESET)"; \
	OTHERS=$$(grep -vcE '^(200|000)$$' $$TRAFFIC_LOG 2>/dev/null || echo 0); \
	[ "$$OTHERS" -gt 0 ] 2>/dev/null && echo -e "$(YELLOW)  Autres : $$OTHERS$(RESET)"; \
	rm -f $$TRAFFIC_LOG

demo-scale: ## Démo scalabilité : scale article-service (make demo-scale r=3)
	@echo -e ""
	@echo -e "$(BOLD)$(CYAN)═══ DEMO SCALABILITÉ ═══$(RESET)"
	@echo -e ""
	@REPLICAS=$${r:-3} && \
	echo -e "$(YELLOW)Scaling article-service à $$REPLICAS replicas...$(RESET)" && \
	kubectl scale deployment article-service -n marketplace --replicas=$$REPLICAS && \
	echo -e "" && \
	echo -e "$(YELLOW)Watch scaling (Ctrl+C pour quitter):$(RESET)" && \
	kubectl get pods -n marketplace -l app=article-service -w

# ============================================
# MONITORING (kube-prometheus-stack)
# ============================================

HELM := $(shell which helm 2>/dev/null || echo "$(HOME)/.local/bin/helm")

monitoring-install: ## Installe Prometheus + Grafana + AlertManager via Helm
	@$(HELM) repo add prometheus-community https://prometheus-community.github.io/helm-charts >/dev/null 2>&1 || true
	@$(HELM) repo update >/dev/null 2>&1
	@kubectl create namespace monitoring --dry-run=client -o yaml | kubectl apply -f -
	@$(HELM) upgrade --install prometheus-stack prometheus-community/kube-prometheus-stack \
		--namespace monitoring \
		--values k8s/prometheus-stack/values.yaml \
		--set grafana.adminPassword=admin \
		--wait --timeout 5m >/dev/null 2>&1
	@kubectl create configmap grafana-dashboard-microservices \
		--from-file=microservices.json=k8s/grafana-dashboards/microservices_dashboard.json \
		--namespace monitoring --dry-run=client -o yaml | kubectl apply -f - >/dev/null 2>&1
	@kubectl label configmap grafana-dashboard-microservices grafana_dashboard=1 --namespace monitoring --overwrite >/dev/null 2>&1
	@DISCORD_WEBHOOK=$$(grep '^DISCORD_WEBHOOK=' .env 2>/dev/null | cut -d'=' -f2-); \
	if [ -n "$$DISCORD_WEBHOOK" ]; then \
		kubectl create secret generic discord-webhook \
			--from-literal=DISCORD_WEBHOOK=$$DISCORD_WEBHOOK \
			--namespace monitoring --dry-run=client -o yaml | kubectl apply -f - >/dev/null 2>&1 && \
		kubectl apply -f k8s/alertmanager-discord.yaml >/dev/null 2>&1 && \
		echo -e "$(GREEN)  ✓ Alertes Discord configurées$(RESET)"; \
	fi
	@echo -e "$(GREEN)  ✓ Prometheus + Grafana + AlertManager installés$(RESET)"

# ============================================
# TRAFFIC GENERATOR (pour dashboards Grafana)
# ============================================

traffic: ## Simule du trafic mixte + chaos (make traffic d=120 e=15 r=5 c=40)
	@chmod +x ./k8s/scripts/traffic.sh && d="$(d)" e="$(e)" r="$(r)" c="$(c)" ./k8s/scripts/traffic.sh

