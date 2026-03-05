#!/bin/bash

# Couleurs
GREEN='\033[32m'
YELLOW='\033[33m'
CYAN='\033[36m'
RESET='\033[0m'

# Arrêter les anciens port-forwards
pkill -f "kubectl port-forward -n marketplace" 2>/dev/null
pkill -f "kubectl port-forward -n monitoring" 2>/dev/null

echo -e "${GREEN}Lancement des port-forwards...${RESET}"

# Application (namespace: marketplace)
kubectl port-forward -n marketplace svc/frontend 3000:3000 &
kubectl port-forward -n marketplace svc/traefik 8000:80 8001:8080 &
kubectl port-forward -n marketplace svc/article-service 8082:8000 &
kubectl port-forward -n marketplace svc/article-db 5432:5432 &
kubectl port-forward -n marketplace svc/keycloak 8080:8080 &
kubectl port-forward -n marketplace svc/kafka-ui 8090:8080 &

# Monitoring (namespace: monitoring)
kubectl port-forward -n monitoring svc/prometheus-stack-grafana 3001:80 &
kubectl port-forward -n monitoring svc/prometheus-prometheus 9090:9090 &
kubectl port-forward -n monitoring svc/prometheus-alertmanager 9093:9093 &

sleep 2

echo ""
echo -e "${GREEN}========================================${RESET}"
echo -e "${GREEN}   Port-forwards actifs !${RESET}"
echo -e "${GREEN}========================================${RESET}"
echo ""
echo -e "  ${CYAN}Frontend:${RESET}       http://localhost:3000"
echo -e "  ${CYAN}API Gateway:${RESET}    http://localhost:8000"
echo -e "  ${CYAN}Traefik Dash:${RESET}   http://localhost:8001/dashboard/"
echo -e "  ${CYAN}API Docs:${RESET}       http://localhost:8082/api"
echo -e "  ${CYAN}Article DB:${RESET}     localhost:5432 (PostgreSQL)"
echo -e "  ${CYAN}Keycloak:${RESET}       http://localhost:8080"
echo -e "  ${CYAN}Kafka UI:${RESET}       http://localhost:8090"
echo ""
echo -e "${GREEN}── Monitoring ──${RESET}"
echo -e "  ${CYAN}Grafana:${RESET}        http://localhost:3001 (admin/admin)"
echo -e "  ${CYAN}Prometheus:${RESET}     http://localhost:9090"
echo -e "  ${CYAN}AlertManager:${RESET}   http://localhost:9093"
echo ""
echo -e "${YELLOW}Appuyez sur Ctrl+C pour tout arrêter${RESET}"
echo ""

# Attendre Ctrl+C
wait
