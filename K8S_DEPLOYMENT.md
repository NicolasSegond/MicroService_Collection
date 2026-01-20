# Déploiement Kubernetes (Minikube)

## Prérequis

- Minikube
- kubectl
- Docker
- 8 Go RAM minimum

## Démarrage rapide

```bash
# 1. Tout lancer (start + build + deploy + init)
make k8s-up

# 2. Port-forwards (dans un autre terminal)
make k8s-forward
```

Accès: **http://localhost:3000**

Login: **testuser / test123**

## Commandes

| Commande | Description |
|----------|-------------|
| `make k8s-up` | **Lance tout** (recommandé) |
| `make k8s-start` | Démarre Minikube |
| `make build-images` | Construit les images |
| `make k8s-deploy` | Déploie sur K8s |
| `make k8s-setup` | Migrations + données test |
| `make k8s-forward` | Port-forwards localhost |
| `make k8s-status` | État des pods |
| `make k8s-logs p=X` | Logs d'un service |
| `make k8s-stop` | Arrête Minikube |
| `make k8s-delete` | Supprime le cluster |

## URLs

| Service | URL |
|---------|-----|
| Frontend | http://localhost:3000 |
| API | http://localhost:8000/api |
| Keycloak | http://localhost:8080 |
| Grafana | http://localhost:3001 |
| Kafka UI | http://localhost:8090 |

## Identifiants

- **testuser** / test123
- **admin** / admin123
- **Keycloak admin**: admin / admin

## Troubleshooting

```bash
# Voir l'état des pods
make k8s-status

# Logs d'un service
make k8s-logs p=article-service

# Réinitialiser les données
make k8s-setup

# Reset complet
make k8s-delete && make k8s-up
```
