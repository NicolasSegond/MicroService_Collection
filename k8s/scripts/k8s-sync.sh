#!/bin/bash

# Sync kubeconfig WSL2 → Windows pour OpenLens
# Copie les certificats et génère un kubeconfig avec les chemins Windows
# Utilise localhost forwarding WSL2 (pas de remplacement d'IP)

GREEN='\033[32m'
RED='\033[31m'
CYAN='\033[36m'
RESET='\033[0m'

WIN_USER="nicol"
WIN_KUBE_DIR="/mnt/c/Users/${WIN_USER}/.kube"
WIN_MINIKUBE_DIR="${WIN_KUBE_DIR}/minikube"
WIN_KUBE_PATH="C:/Users/${WIN_USER}/.kube/minikube"
LINUX_USER=$(whoami)

mkdir -p "$WIN_MINIKUBE_DIR"

# Copie des certificats
cp "$HOME/.minikube/ca.crt" "$WIN_MINIKUBE_DIR/" 2>/dev/null
cp "$HOME/.minikube/profiles/minikube/client.crt" "$WIN_MINIKUBE_DIR/" 2>/dev/null
cp "$HOME/.minikube/profiles/minikube/client.key" "$WIN_MINIKUBE_DIR/" 2>/dev/null

# Génération du kubeconfig avec chemins Windows
# On garde 127.0.0.1 car WSL2 localhost forwarding permet à Windows
# d'accéder directement aux ports écoutant sur localhost dans WSL2
kubectl config view --raw | sed \
  -e "s|/home/${LINUX_USER}/.minikube/ca.crt|${WIN_KUBE_PATH}/ca.crt|g" \
  -e "s|/home/${LINUX_USER}/.minikube/profiles/minikube/client.crt|${WIN_KUBE_PATH}/client.crt|g" \
  -e "s|/home/${LINUX_USER}/.minikube/profiles/minikube/client.key|${WIN_KUBE_PATH}/client.key|g" \
  > "${WIN_KUBE_DIR}/config"

echo -e "${GREEN}  Kubeconfig synced (via localhost forwarding)${RESET}"
echo -e "  ${CYAN}Config:${RESET} C:\\Users\\${WIN_USER}\\.kube\\config"
