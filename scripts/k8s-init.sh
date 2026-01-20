#!/bin/bash

# Couleurs
GREEN='\033[32m'
YELLOW='\033[33m'
CYAN='\033[36m'
RED='\033[31m'
RESET='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo -e "${CYAN}========================================${RESET}"
echo -e "${CYAN}   Initialisation Kubernetes${RESET}"
echo -e "${CYAN}========================================${RESET}"
echo ""

# 1. Attendre que tous les pods soient prêts
echo -e "${YELLOW}[1/4] Attente des pods...${RESET}"

echo -e "  Attente de Keycloak DB..."
kubectl wait --for=condition=ready pod -l app=keycloak-db -n marketplace --timeout=180s

echo -e "  Attente de Keycloak..."
kubectl wait --for=condition=ready pod -l app=keycloak -n marketplace --timeout=300s

echo -e "  Attente de Article DB..."
kubectl wait --for=condition=ready pod -l app=article-db -n marketplace --timeout=180s

echo -e "  Attente de Article Service..."
kubectl wait --for=condition=ready pod -l app=article-service -n marketplace --timeout=180s

echo -e "  Attente du Frontend..."
kubectl wait --for=condition=ready pod -l app=frontend -n marketplace --timeout=180s

echo -e "${GREEN}  Tous les pods sont prêts !${RESET}"
echo ""

# 2. Exécuter les migrations
echo -e "${YELLOW}[2/4] Exécution des migrations...${RESET}"
kubectl exec -n marketplace deploy/article-service -c article-service -- php bin/console doctrine:migrations:migrate --no-interaction 2>&1
echo -e "${GREEN}  Migrations terminées !${RESET}"
echo ""

# 3. Mise à jour du schéma (au cas où)
echo -e "${YELLOW}[3/4] Mise à jour du schéma...${RESET}"
kubectl exec -n marketplace deploy/article-service -c article-service -- php bin/console doctrine:schema:update --force 2>&1
echo -e "${GREEN}  Schéma à jour !${RESET}"
echo ""

# 4. Charger les données de test
echo -e "${YELLOW}[4/4] Chargement des données de test...${RESET}"
bash "$SCRIPT_DIR/k8s-seed.sh"

echo ""
echo -e "${GREEN}========================================${RESET}"
echo -e "${GREEN}   Initialisation terminée !${RESET}"
echo -e "${GREEN}========================================${RESET}"
echo ""
echo -e "Lancez maintenant: ${CYAN}make k8s-forward${RESET}"
echo ""
