# Marketplace - Architecture Microservices

> POC d'une Plateforme marketplace moderne construite avec une architecture microservices event-driven

[![Symfony](https://img.shields.io/badge/Symfony-7.3-000000?logo=symfony)](https://symfony.com/)
[![React](https://img.shields.io/badge/React-19-61DAFB?logo=react)](https://react.dev/)
[![Keycloak](https://img.shields.io/badge/Keycloak-23-4D4D4D?logo=keycloak)](https://www.keycloak.org/)
[![Kafka](https://img.shields.io/badge/Kafka-7.5-231F20?logo=apache-kafka)](https://kafka.apache.org/)
[![Traefik](https://img.shields.io/badge/Traefik-3.2-24A1C1?logo=traefik-proxy)](https://traefik.io/)
[![CI/CD](https://img.shields.io/badge/CI%2FCD-GitHub_Actions-2088FF?logo=github-actions)](https://github.com/features/actions)

---

## Table des matières

- [Architecture](#-architecture)
- [Stack Technique](#-stack-technique)
- [Structure du Projet](#-structure-du-projet)
- [Installation](#-installation)
- [Utilisation](#-utilisation)
- [Ports & Services](#-ports--services)

---

## Architecture

```
                                       ┌─────────────┐
                                       │   Client    │
                                       │   Browser   │
                                       └──────┬──────┘
                                              │
                                       HTTPS (Port 3000)
                                              │
                                              ▼
                                  ┌───────────────────────┐
                                  │  Frontend React :3000 │
                                  │   (Keycloak.js)       │
                                  └──────────┬────────────┘
                                             │
                   ┌─────────────────────────┴─────────────────────────┐
                   │                                                   │
          OAuth2 Login/Token                                       API Calls
           (Port 8080)                                            (Port 8000)
                   │                                                   │
                   ▼                                                   ▼
          ┌──────────────┐                                      ┌──────────────┐
          │  Keycloak    │                                      │   Traefik    │
          │    :8080     │◄────────────────────────────────────▶│  API Gateway │
          │              │          Token Introspection         │    :8000     │
          │ OAuth2/OIDC  │          (via Oathkeeper)            ├──────────────┤
          │ Token Issuer │                                      │  Oathkeeper  │
          └──────────────┘                                      │  (Decision)  │
                                                                └──────┬───────┘
                                                                       │
                                                                       │
                                    ┌──────────────────────────────────┴──────────────────────────────────┐
                                    │                                                                     │
                               HTTP Routing                                                         HTTP Routing
                             (JWT in Header)                                                      (JWT in Header)
                                    │                                                                     │
                                    ▼                                                                     ▼
                           ┌─────────────────┐                                                  ┌─────────────────┐
                           │  User Service   │                                                  │ Article Service │
                           │     :8081       │                                                  │      :8082      │
                           │  Symfony 7.3    │◄────────────────────────────────────────────────▶│  Symfony 7.3    │
                           │  API Platform   │               Kafka Events Stream                │  API Platform   │
                           └────────┬────────┘                                                  └────────┬────────┘
                                    │                                                                    │
                             SQL (Port 5432)                                                        SQL (Port 5433)
                                    │                                                                    │
                                    ▼                                                                    ▼
                           ┌─────────────────┐                                                  ┌─────────────────┐
                           │  PostgreSQL     │                                                  │  PostgreSQL     │
                           │   user_db       │                                                  │  article_db     │
                           │    :5432        │                                                  │    :5433        │
                           └────────┬────────┘                                                  └────────┬────────┘
                                    │                                                                    │
                                    │ Publish Events                                       Publish Events│
                                    │                                                                    │
                                    └────────────────────────────────┬───────────────────────────────────┘
                                                                     │
                                                              Kafka Protocol
                                                                (Port 9092)
                                                                     │
                                                                     ▼
                                                          ┌─────────────────────┐
                                                          │   Apache Kafka      │
                                                          │      :9092          │
                                                          │  Event Streaming    │
                                                          └──────────┬──────────┘
                                                                     │
                                                              HTTP (Port 8090)
                                                                     │
                                                                     ▼
                                                          ┌─────────────────────┐
                                                          │    Kafka UI :8090   │
                                                          │  Monitoring & Logs  │
                                                          └─────────────────────┘
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

| Principe | Description |
|----------|-------------|
| **API Gateway** | Traefik centralise le routage, Oathkeeper gere l'authentification |
| **Database per Service** | Chaque service a sa propre base de donnees isolee |
| **Event-Driven** | Communication asynchrone via Kafka entre services |
| **OAuth2/OIDC** | Authentification centralisee avec Keycloak |
| **Token Introspection** | Validation des tokens en temps reel via Keycloak |
| **No Direct Access** | Les services ne s'appellent pas directement |

---

## Stack Technique

| Couche | Technologies |
|--------|-------------|
| **Frontend** | React 19, Keycloak.js, React Router, Vite 7 |
| **API Gateway** | Traefik 3.2, Ory Oathkeeper |
| **Backend** | Symfony 7.3, API Platform 4.2, PHP 8.2+ |
| **Authentification** | Keycloak 23 (OAuth2/OIDC), Token Introspection |
| **Base de donnees** | PostgreSQL 15 |
| **Messagerie** | Apache Kafka 7.5, Kafka UI |
| **Infrastructure** | Docker, Docker Compose |
| **CI/CD** | GitHub Actions, SonarCloud, ZAP Security |

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
├── user-service/                # Microservice utilisateurs (Symfony)
│   ├── src/
│   │   ├── Entity/              # Entites Doctrine
│   │   ├── Repository/          # Repositories
│   │   ├── Controller/          # Controleurs API
│   │   └── ApiResource/         # Ressources API Platform
│   ├── config/                  # Configuration Symfony
│   ├── migrations/              # Migrations Doctrine
│   └── Dockerfile
│
├── article-service/             # Microservice articles (Symfony)
│   ├── src/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Controller/
│   │   ├── ApiResource/
│   │   └── Security/            # JwtAuthenticator
│   ├── config/
│   ├── migrations/
│   └── Dockerfile
│
├── traefik/                     # Configuration API Gateway
│   ├── traefik.yml              # Configuration statique
│   └── dynamic.yml              # Middlewares, routers & services
│
├── oathkeeper/                  # Authentification OIDC
│   ├── config.yaml              # Configuration introspection
│   └── rules.yaml               # Regles d'acces par route
│
├── keycloak/                    # Import automatique du realm
│   └── realm-export.json
│
├── .github/workflows/           # CI/CD GitHub Actions
│
├── docker-compose.yml           # Orchestration des services
├── docker-compose.override.yml  # Surcharges developpement
└── .env.example                 # Template des variables
```

---

## Prerequis

- **Docker** 20.10+ & **Docker Compose** 2.0+
- **Git**

---

## Installation

### 1. Cloner et configurer

```bash
git clone <repository-url>
cd MicroService_Collection
```

### 2. Décrypter les variables d'environnement

```bash
openssl enc -aes-256-cbc -d -pbkdf2 -in .env.enc -out .env -pass pass:"VOTRE_CLE_SECRETE"
```

> Contactez un membre de l'équipe pour obtenir la clé d'encryption.

### 3. Lancer les services

```bash
docker compose up -d --build
```

### 4. Executer les migrations

```bash
docker exec -it user-service php bin/console doctrine:migrations:migrate --no-interaction
docker exec -it article-service php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Verifier l'etat

```bash
docker compose ps
```

> Keycloak peut prendre 2-3 minutes pour demarrer completement

---

## Utilisation

### Acces aux services

| Service | URL | Description |
|---------|-----|-------------|
| **Frontend** | http://localhost:3000 | Interface utilisateur |
| **Keycloak** | http://localhost:8080 | Console admin |
| **Traefik Dashboard** | http://localhost:8001 | Monitoring Traefik |
| **Kafka UI** | http://localhost:8090 | Monitoring Kafka |

## Ports & Services

| Service | Port | Description | Technologie |
|---------|------|-------------|-------------|
| **Frontend** | 3000 | Interface React | React 19 |
| **Keycloak** | 8080 | Authentification OAuth2/OIDC | Keycloak 23 |
| **Traefik Proxy** | 8000 | API Gateway | Traefik 3.2 |
| **Traefik Dashboard** | 8001 | Monitoring | Traefik |
| **User Service** | 8081 | Gestion utilisateurs | Symfony 7.3 |
| **Article Service** | 8082 | Gestion articles | Symfony 7.3 |
| **Kafka UI** | 8090 | Monitoring Kafka | Kafka UI |
| **Kafka** | 9092 | Event Streaming | Apache Kafka 7.5 |

---

## Commandes utiles

### Frontend (depuis `frontend/`)

```bash
# Développement
npm run dev              # Lancer le serveur Vite (port 3000)

# Tests
npm run test             # Lancer les tests Vitest
npm run test:ui          # Tests avec interface UI
npm run test:coverage    # Tests avec couverture

# Build & Lint
npm run build            # Build de production
npm run lint             # Lancer ESLint
```

### Docker

```bash
# Voir les logs
docker compose logs -f [service-name]

# Logs Traefik
docker compose logs -f traefik

# Logs Oathkeeper (auth)
docker compose logs -f oathkeeper

# Entrer dans un conteneur
docker exec -it [container-name] bash

# Redemarrer un service
docker compose restart [service-name]

# Arreter tous les services
docker compose down

# Arreter et supprimer les donnees
docker compose down -v
```

### Encryption du fichier .env

Le fichier `.env` contient des secrets et ne doit pas être versionné en clair. Utilisez ces commandes pour gérer l'encryption :

```bash
# Encrypter le fichier .env (génère .env.enc)
openssl enc -aes-256-cbc -pbkdf2 -in .env -out .env.enc -pass pass:"VOTRE_CLE_SECRETE"

# Décrypter le fichier .env.enc
openssl enc -aes-256-cbc -d -pbkdf2 -in .env.enc -out .env -pass pass:"VOTRE_CLE_SECRETE"
```

> **Note** : La clé d'encryption doit être stockée de manière sécurisée (ex: GitHub Secrets sous `ENCRYPTION_KEY`).

---

## Documentation

- [Symfony](https://symfony.com/doc) | [API Platform](https://api-platform.com/docs) | [Keycloak](https://www.keycloak.org/documentation)
- [Traefik](https://doc.traefik.io/traefik/) | [Ory Oathkeeper](https://www.ory.sh/docs/oathkeeper) | [Kafka](https://kafka.apache.org/documentation)

---

<div align="center">

**Projet etudiant afin d'apprendre l'architecture Microservices**

</div>
