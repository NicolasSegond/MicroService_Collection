# Déploiement Kubernetes (Minikube)

Guide complet pour déployer l'application sur Minikube.

## Prérequis

- [Minikube](https://minikube.sigs.k8s.io/docs/start/) installé
- [kubectl](https://kubernetes.io/docs/tasks/tools/) installé
- Docker installé et en cours d'exécution
- Au moins 8 Go de RAM disponible

## Démarrage rapide

### Option 1: Commande tout-en-un (recommandé)

```bash
make k8s-full
```

Cette commande fait tout automatiquement:
1. Démarre Minikube
2. Construit les images Docker
3. Déploie sur Kubernetes
4. Attend que les pods soient prêts
5. Exécute les migrations
6. Charge les données de test

Ensuite, lancez les port-forwards:
```bash
make k8s-forward
```

### Option 2: Étape par étape

```bash
# 1. Démarrer Minikube et déployer
make k8s-demo

# 2. Attendre que les pods soient prêts
make k8s-wait

# 3. Initialiser (migrations + données de test)
make k8s-init

# 4. Lancer les port-forwards
make k8s-forward
```

## Accès à l'application

Une fois les port-forwards actifs:

| Service | URL | Description |
|---------|-----|-------------|
| Frontend | http://localhost:3000 | Application React |
| API | http://localhost:8000/api | API REST (Swagger UI) |
| Keycloak | http://localhost:8080 | Administration auth |
| Grafana | http://localhost:3001 | Monitoring |
| Kafka UI | http://localhost:8090 | Interface Kafka |

## Identifiants de test

| Utilisateur | Mot de passe | Rôle |
|-------------|--------------|------|
| testuser | test123 | USER |
| admin | admin123 | ADMIN |

**Keycloak Admin:**
- URL: http://localhost:8080/admin
- User: admin
- Password: admin

## Commandes utiles

### Gestion du cluster

```bash
make k8s-status       # État des pods
make k8s-logs p=article-service  # Logs d'un service
make k8s-dashboard    # Dashboard Kubernetes
make k8s-stop         # Arrêter Minikube
make k8s-delete       # Supprimer le cluster
```

### Données

```bash
make k8s-migrate      # Exécuter les migrations uniquement
make k8s-seed         # Recharger les données de test
make k8s-init         # Migrations + données de test
```

### Port-forwards

```bash
make k8s-forward      # Lancer tous les port-forwards
make k8s-forward-stop # Arrêter les port-forwards
```

### Démonstrations

```bash
make k8s-self-healing-demo  # Démo du self-healing (kill un pod)
make k8s-scale-demo         # Démo du scaling (3 replicas frontend)
```

## Troubleshooting

### Les pods ne démarrent pas

```bash
# Vérifier l'état des pods
kubectl get pods -n marketplace

# Voir les logs d'un pod
kubectl logs -n marketplace <pod-name>

# Décrire un pod pour voir les erreurs
kubectl describe pod -n marketplace <pod-name>
```

### Erreur "connection refused" sur localhost

Vérifiez que les port-forwards sont actifs:
```bash
make k8s-forward
```

### Keycloak ne démarre pas

Keycloak nécessite que sa base de données soit prête. Attendez avec:
```bash
make k8s-wait
```

### Les articles n'apparaissent pas

Vérifiez que les données de test sont chargées:
```bash
make k8s-seed
```

### Réinitialiser complètement

```bash
make k8s-delete   # Supprimer le cluster
make k8s-full     # Tout recréer
```

## Architecture des pods

```
marketplace (namespace)
├── frontend (Deployment)
├── article-service (Deployment)
├── article-service-consumer (Deployment)
├── article-db (StatefulSet - PostgreSQL)
├── keycloak (Deployment)
├── keycloak-db (StatefulSet - PostgreSQL)
├── kafka (StatefulSet)
├── zookeeper (StatefulSet)
├── traefik (Deployment)
├── oathkeeper (Deployment)
├── prometheus (Deployment)
├── grafana (Deployment)
└── kafka-ui (Deployment)
```

## Ressources requises

- **CPU**: 4 cores minimum
- **RAM**: 8 Go minimum
- **Disque**: ~10 Go pour les images et volumes
