# Marketplace - Architecture Microservices

> POC d'une Plateforme marketplace moderne construite avec une architecture microservices event-driven

[![Symfony](https://img.shields.io/badge/Symfony-7.3-000000?logo=symfony)](https://symfony.com/)
[![React](https://img.shields.io/badge/React-19-61DAFB?logo=react)](https://react.dev/)
[![Keycloak](https://img.shields.io/badge/Keycloak-23-4D4D4D?logo=keycloak)](https://www.keycloak.org/)
[![Kafka](https://img.shields.io/badge/Kafka-7.5-231F20?logo=apache-kafka)](https://kafka.apache.org/)
[![Traefik](https://img.shields.io/badge/Traefik-3.2-24A1C1?logo=traefik-proxy)](https://traefik.io/)
[![Kubernetes](https://img.shields.io/badge/Kubernetes-Minikube-326CE5?logo=kubernetes)](https://minikube.sigs.k8s.io/)
[![CI/CD](https://img.shields.io/badge/CI%2FCD-GitHub_Actions-2088FF?logo=github-actions)](https://github.com/features/actions)

---

## Table des matieres

- [Architecture](#architecture)
- [Stack Technique](#stack-technique)
- [Structure du Projet](#structure-du-projet)
- [Installation](#installation)
- [Commandes Makefile](#commandes-makefile)
- [Acces aux services](#acces-aux-services)
- [Article Service - Details](#article-service---details)
- [Monitoring & Observability](#monitoring--observability)
- [CI/CD](#cicd)

---

## Architecture

```
                                 ┌─────────────┐
                                 │   Client    │
                                 └──────┬──────┘
                                        │
                                   Port 3000
                                        │
                                        ▼
                            ┌───────────────────────┐
                            │  Frontend React :3000 │
                            │     (Keycloak.js)     │
                            └───────────┬───────────┘
                                        │
              ┌─────────────────────────┴─────────────────────────┐
              │                                                   │
         OAuth2 Login                                        API Calls
          Port 8080                                          Port 8000
              │                                                   │
              ▼                                                   ▼
     ┌─────────────────┐                                 ┌─────────────────┐
     │    Keycloak     │                                 │     Traefik     │
     │     :8080       │◄───────────────────────────────▶│   API Gateway   │
     │                 │       Token Introspection       │     :8000       │
     │  OAuth2/OIDC    │         (via Oathkeeper)        ├─────────────────┤
     │  Token Issuer   │                                 │   Oathkeeper    │
     └────────┬────────┘                                 └────────┬────────┘
              │                                                   │
              │                                              JWT Header
              │                                                   │
              │          ┌────────────────────────────────────────┼────────┐
              │          │         Article Service :8082          ▼        │
              │          │  ┌─────────────────────┐  ┌─────────────────┐   │
              │          │  │     API REST        │  │  Kafka Consumer │   │
              │          │  │   (API Platform)    │  │ (Keycloak Sync) │   │
              │          │  │                     │  │                 │   │
              │          │  │  - GET /articles    │  │  - REGISTER     │   │
              │          │  │  - POST /articles   │  │  - UPDATE       │   │
              │          │  │  - PATCH /articles  │  │  - DELETE       │   │
              │          │  │  - POST /upload     │  │                 │   │
              │          │  └──────────┬──────────┘  └────────┬────────┘   │
              │          │             │                      │            │
              │          │             └──────────┬───────────┘            │
              │          │                        ▼                        │
              │          │               ┌─────────────────┐               │
              │          │               │   PostgreSQL    │               │
              │          │               │   article-db    │               │
              │          │               │                 │               │
              │          │               │  - Article      │               │
              │          │               │  - UserInfo     │               │
              │          │               └─────────────────┘               │
              │          └─────────────────────────▲───────────────────────┘
              │                                    │
              │   Keycloak Events                  │
              │   (REGISTER, UPDATE, DELETE)       │
              │                                    │
              │          ┌─────────────────────────┴───┐
              └─────────▶│        Apache Kafka         │
                         │           :9092             │
                         └─────────────────────────────┘
```

### Flux d'authentification

1. **Client** accede au **Frontend React**
2. **Frontend** redirige vers **Keycloak** pour l'authentification
3. **Keycloak** retourne un **JWT Token** au Frontend
4. **Frontend** appelle les API via **Traefik** avec le token Bearer
5. **Traefik** delegue la validation a **Oathkeeper** (forward auth)
6. **Oathkeeper** valide le token avec **Keycloak** (introspection)
7. **Traefik** route vers les **microservices** avec le contexte utilisateur
8. **Microservices** traitent la requete et accedent a leur propre DB
9. **Microservices** publient des evenements dans **Kafka**

### Principes cles

| Principe                 | Description                                                       |
|--------------------------|-------------------------------------------------------------------|
| **API Gateway**          | Traefik centralise le routage, Oathkeeper gere l'authentification |
| **Database per Service** | Chaque service a sa propre base de donnees isolee                 |
| **Event-Driven**         | Communication asynchrone via Kafka entre services                 |
| **OAuth2/OIDC**          | Authentification centralisee avec Keycloak                        |
| **Token Introspection**  | Validation des tokens en temps reel via Keycloak                  |
| **No Direct Access**     | Les services ne s'appellent pas directement                       |

---

## Stack Technique

| Couche               | Technologies                                       |
|----------------------|----------------------------------------------------|
| **Frontend**         | React 19, Keycloak.js, React Router, Vite 7        |
| **API Gateway**      | Traefik 3.2, Ory Oathkeeper                        |
| **Backend**          | Symfony 7.3, API Platform 4.2, PHP 8.3, PHPUnit 12 |
| **Authentification** | Keycloak 23 (OAuth2/OIDC), Token Introspection     |
| **Base de donnees**  | PostgreSQL 15                                      |
| **Messagerie**       | Apache Kafka 7.5                                   |
| **Infrastructure**   | Kubernetes (Minikube)                              |
| **Monitoring**       | Prometheus, Grafana                                |
| **CI/CD**            | GitHub Actions, SonarCloud, ZAP Security           |

---

## Structure du Projet

```
MicroService_Collection/
├── frontend/                    # Application React
│   ├── src/
│   │   ├── KeycloakProvider.jsx # Context d'authentification
│   │   └── pages/               # Composants de pages
│   └── Dockerfile
│
├── article-service/             # Microservice articles (Symfony)
│   ├── src/
│   │   ├── Entity/              # Article, UserInfo
│   │   ├── Repository/
│   │   ├── Controller/          # UploadController
│   │   ├── Command/             # Kafka consumer, Keycloak sync
│   │   ├── Doctrine/            # PublishedArticleExtension
│   │   ├── MessageHandler/      # KeycloakEventHandler
│   │   ├── State/               # Processors & Providers
│   │   └── Security/            # JwtAuthenticator
│   ├── tests/
│   │   ├── Unit/                # Tests unitaires
│   │   └── Integration/         # Tests API Platform
│   ├── config/
│   ├── migrations/
│   └── Dockerfile
│
├── gateway/                     # Configuration API Gateway
│   ├── traefik/
│   │   ├── traefik.yml          # Configuration statique
│   │   └── dynamic.yml          # Middlewares, routers & services
│   └── oathkeeper/
│       ├── config.yaml          # Configuration introspection
│       └── rules.yaml           # Regles d'acces par route
│
├── keycloak/                    # Configuration Keycloak
│   ├── Dockerfile
│   └── realm-export.json        # Import automatique du realm
│
├── k8s/                         # Manifests Kubernetes
│   ├── kustomization.yaml       # Configuration Kustomize
│   ├── namespace.yaml           # Namespace marketplace
│   ├── services.yaml            # Services applicatifs
│   ├── infrastructure.yaml      # DB, Kafka, Keycloak
│   ├── gateway.yaml             # Traefik & Oathkeeper
│   ├── auth.yaml                # Configuration auth
│   ├── monitoring.yaml          # Prometheus & Grafana
│   ├── scripts/                 # Scripts utilitaires
│   │   └── k8s-forward.sh       # Port forwarding
│   └── grafana-dashboards/      # Dashboards pre-configures
│
├── .github/
│   ├── workflows/               # CI/CD GitHub Actions
│   └── perf/                    # Tests de performance JMeter
│
├── Makefile                     # Commandes automatisees
└── .env.example                 # Template des variables
```

---

## Prerequis

- **Minikube** 1.30+
- **kubectl**
- **Docker** 20.10+
- **Git**

---

## Installation

### 1. Cloner et configurer

```bash
git clone <repository-url>
cd MicroService_Collection
```

### 2. Decrypter les variables d'environnement

```bash
openssl enc -aes-256-cbc -d -pbkdf2 -in .env.enc -out .env -pass pass:"VOTRE_CLE_SECRETE"
```

> Contactez un membre de l'equipe pour obtenir la cle d'encryption.

### 3. Demarrer Minikube

```bash
make k8s-start
```

Cette commande :

- Demarre Minikube avec les ressources adequates
- Configure le registry Docker local
- Deploie tous les services via Kustomize

### 4. Configurer le port forwarding

```bash
make k8s-forward
```

### 5. Verifier l'etat des pods

```bash
make k8s-status
```

---

## Commandes Makefile

### Kubernetes

| Commande                    | Description                                |
|-----------------------------|--------------------------------------------|
| `make k8s-start`            | Demarrer Minikube et deployer les services |
| `make k8s-stop`             | Arreter Minikube                           |
| `make k8s-delete`           | Supprimer le cluster Minikube              |
| `make k8s-forward`          | Configurer le port forwarding              |
| `make k8s-status`           | Afficher l'etat des pods                   |
| `make k8s-logs p=<service>` | Voir les logs d'un service                 |

### Developpement

| Commande    | Description           |
|-------------|-----------------------|
| `make test` | Lancer tous les tests |
| `make lint` | Lancer les linters    |

### Frontend (depuis `frontend/`)

```bash
npm run dev              # Serveur de developpement (port 3000)
npm run test             # Tests Vitest
npm run test:coverage    # Tests avec couverture
npm run build            # Build de production
npm run lint             # ESLint
```

---

## Acces aux services

| Service               | URL                   | Description           |
|-----------------------|-----------------------|-----------------------|
| **Frontend**          | http://localhost:3000 | Interface utilisateur |
| **API Gateway**       | http://localhost:8000 | Point d'entree API    |
| **Keycloak**          | http://localhost:8080 | Console admin         |
| **Traefik Dashboard** | http://localhost:8001 | Monitoring Traefik    |
| **Grafana**           | http://localhost:3001 | Dashboards            |
| **Prometheus**        | http://localhost:9090 | Metriques             |

---

## Article Service - Details

### Endpoints API

#### Public

| Methode | Endpoint             | Description                                  |
|---------|----------------------|----------------------------------------------|
| GET     | `/api/articles`      | Liste des articles publies (pagine, 10/page) |
| GET     | `/api/articles/{id}` | Detail d'un article avec infos proprietaire  |

#### Authentifie

| Methode | Endpoint             | Role      | Description                     |
|---------|----------------------|-----------|---------------------------------|
| POST    | `/api/articles`      | ROLE_USER | Creer un article (statut DRAFT) |
| PATCH   | `/api/articles/{id}` | Owner     | Modifier un article             |

#### Administration

| Methode | Endpoint              | Description                               |
|---------|-----------------------|-------------------------------------------|
| GET     | `/api/admin/articles` | Articles en DRAFT uniquement (moderation) |

#### Upload

| Methode | Endpoint            | Description                                    |
|---------|---------------------|------------------------------------------------|
| POST    | `/api/media/upload` | Upload d'image (JPG, PNG, GIF, WEBP - max 5MB) |

### Filtrage des Articles (PublishedArticleExtension)

Le service utilise une extension Doctrine pour filtrer automatiquement les articles :

| Contexte                         | Non-authentifie | Authentifie           | Admin |
|----------------------------------|-----------------|-----------------------|-------|
| Collection `/api/articles`       | PUBLISHED       | PUBLISHED             | Tous  |
| Collection `/api/admin/articles` | DRAFT           | DRAFT                 | DRAFT |
| Item `/api/articles/{id}`        | PUBLISHED       | PUBLISHED + ses DRAFT | Tous  |

### Synchronisation Keycloak

Le service maintient un cache local des utilisateurs (`UserInfo`) via Kafka :

```bash
# Consumer temps reel (evenements Keycloak)
php bin/console app:consume-keycloak-events

# Synchronisation initiale depuis l'API admin
php bin/console app:sync-keycloak-users
```

**Evenements traites :**

- `REGISTER` : Creation utilisateur (ignore si existant)
- `UPDATE_PROFILE` : Mise a jour profil (ignore si inexistant)
- `DELETE_ACCOUNT` : Suppression utilisateur

### Tests

```bash
# Setup base de test
cd article-service && composer test:setup

# Tous les tests
./vendor/bin/phpunit

# Avec couverture
./vendor/bin/phpunit --coverage-html coverage/
```

**Couverture :**

- Tests unitaires : Command, Controller, Entity, MessageHandler, Security, State
- Tests d'integration : API Articles (CRUD, filtrage, authentification)

---

## Monitoring & Observability

Le monitoring est deploye via Kubernetes. Les manifests se trouvent dans `k8s/monitoring.yaml`.

### Services

| Service        | Description                               |
|----------------|-------------------------------------------|
| **Grafana**    | Visualisation des metriques et dashboards |
| **Prometheus** | Collecte et stockage des metriques        |

### Configuration

- **Dashboards Grafana** : `k8s/grafana-dashboards/`
- **Manifests Kubernetes** : `k8s/monitoring.yaml`

---

## Encryption du fichier .env

Le fichier `.env` contient des secrets et ne doit pas etre versionne en clair.

```bash
# Encrypter
openssl enc -aes-256-cbc -pbkdf2 -in .env -out .env.enc -pass pass:"VOTRE_CLE_SECRETE"

# Decrypter
openssl enc -aes-256-cbc -d -pbkdf2 -in .env.enc -out .env -pass pass:"VOTRE_CLE_SECRETE"
```

> La cle d'encryption est stockee dans GitHub Secrets sous `ENCRYPTION_KEY`.

---

## CI/CD

La pipeline CI/CD utilise Docker Compose pour les tests d'integration. Les workflows se trouvent dans
`.github/workflows/`.

| Job            | Description                |
|----------------|----------------------------|
| **backend**    | Tests PHPUnit + couverture |
| **frontend**   | Tests Vitest + ESLint      |
| **sonarcloud** | Analyse de qualite         |
| **perf_tests** | Tests de charge JMeter     |
| **zap-local**  | Scan de securite OWASP ZAP |

---

## Documentation

- [Symfony](https://symfony.com/doc) | [API Platform](https://api-platform.com/docs) | [Keycloak](https://www.keycloak.org/documentation)
- [Traefik](https://doc.traefik.io/traefik/) | [Ory Oathkeeper](https://www.ory.sh/docs/oathkeeper) | [Kafka](https://kafka.apache.org/documentation)
- [Minikube](https://minikube.sigs.k8s.io/docs/) | [Kubernetes](https://kubernetes.io/docs/)

---

<div align="center">

**Projet etudiant afin d'apprendre l'architecture Microservices**

</div>
