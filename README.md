# 🏪 Marketplace - Architecture Microservices

> POC d'une Plateforme marketplace moderne construite avec une architecture microservices event-driven

[![Symfony](https://img.shields.io/badge/Symfony-7.3-000000?logo=symfony)](https://symfony.com/)
[![React](https://img.shields.io/badge/React-18-61DAFB?logo=react)](https://react.dev/)
[![Keycloak](https://img.shields.io/badge/Keycloak-23-4D4D4D?logo=keycloak)](https://www.keycloak.org/)
[![Kafka](https://img.shields.io/badge/Kafka-7.5-231F20?logo=apache-kafka)](https://kafka.apache.org/)
[![Kong](https://img.shields.io/badge/Kong-Gateway-003459?logo=kong)](https://konghq.com/)
[![CI/CD](https://img.shields.io/badge/CI%2FCD-GitHub_Actions-2088FF?logo=github-actions)](https://github.com/features/actions)

---

## 📋 Table des matières

- [🏗️ Architecture](#️-architecture)
- [🛠️ Stack Technique](#️-stack-technique)
- [📁 Structure du Projet](#-structure-du-projet)
- [🚀 Installation](#-installation)
- [🎮 Utilisation](#-utilisation)
- [🔌 Ports & Services](#-ports--services)

---

## 🏗️ Architecture

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
          │  Keycloak    │                                      │     Kong     │
          │    :8080     │◄────────────────────────────────────▶│  API Gateway │
          │              │          Token Introspection         │    :8000     │
          │ OAuth2/OIDC  │                                      │ OIDC Plugin  │
          │ Token Issuer │                                      │Token Validate│
          └──────────────┘                                      └──────┬───────┘
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

### 🔄 Flux d'authentification

1. **Client** accède au **Frontend React**
2. **Frontend** redirige vers **Keycloak** pour l'authentification
3. **Keycloak** retourne un **JWT Token** au Frontend
4. **Frontend** appelle les API via **Kong** avec le token Bearer
5. **Kong** valide le token avec **Keycloak** (introspection)
6. **Kong** route vers les **microservices** avec contexte utilisateur
7. **Microservices** traitent la requête et accèdent à leur propre DB
8. **Microservices** publient des événements dans **Kafka**

### 🎯 Principes clés

| Principe | Description |
|----------|-------------|
| **🔒 API Gateway** | Kong centralise l'authentification et le routage |
| **🗄️ Database per Service** | Chaque service a sa propre base de données isolée |
| **📨 Event-Driven** | Communication asynchrone via Kafka entre services |
| **🔐 OAuth2/OIDC** | Authentification centralisée avec Keycloak |
| **🚫 No Direct Access** | Les services ne s'appellent pas directement |

---

## 🛠️ Stack Technique

| Couche | Technologies |
|--------|-------------|
| **Frontend** | React 18 • Keycloak.js • React Router • Axios |
| **API Gateway** | Kong • Plugin OIDC |
| **Backend** | Symfony 7.3 • API Platform 4.2 • PHP 8.2+ |
| **Authentification** | Keycloak 23 (OAuth2/OIDC) |
| **Base de données** | PostgreSQL 15 |
| **Messagerie** | Apache Kafka 7.5 • Kafka UI |
| **Infrastructure** | Docker • Docker Compose |
| **CI/CD** | GitHub Actions • SonarCloud • ZAP Security |

---

## 📁 Structure du Projet

```
MicroService_Collection/
├── 📂  frontend/     # Application React
│   ├── src/
│   │   ├── KeycloakProvider.js  # Context d'authentification
│   │   └── pages/               # Composants de pages
│   └── Dockerfile
│
├── 📂 user-service/             # Microservice utilisateurs (Symfony)
│   ├── src/
│   │   ├── Entity/              # Entités Doctrine
│   │   ├── Repository/          # Repositories
│   │   ├── Controller/          # Contrôleurs API
│   │   └── ApiResource/         # Ressources API Platform
│   ├── config/                  # Configuration Symfony
│   ├── migrations/              # Migrations Doctrine
│   └── Dockerfile
│
├── 📂 article-service/          # Microservice articles (Symfony)
│   ├── src/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   ├── Controller/
│   │   ├── ApiResource/
│   │   └── Security/
│   ├── config/
│   ├── migrations/
│   └── Dockerfile
│
├── 📂 kong/                     # Configuration API Gateway
│   ├── Dockerfile               # Image Kong + plugin OIDC
│   └── kong.yml                 # Routes et plugins
│
├── 📂 keycloak/                 # Import automatique du realm
│
├── 📂 .github/workflows/        # CI/CD GitHub Actions
│
├── docker-compose.yml           # Orchestration des services
├── docker-compose.override.yml  # Surcharges développement
├── .env.example                 # Template des variables
└── .env.enc                     # Variables chiffrées
```

---

## 📦 Prérequis

- **Docker** 20.10+ & **Docker Compose** 2.0+
- **Git**

---

## 🚀 Installation

### 1. Cloner et configurer

```bash
git clone <repository-url>
cd MicroService_Collection
```

### 2. Configurer les variables d'environnement

Déchiffrer le fichier `.env.enc` :

```bash
openssl enc -aes-256-cbc -d -pbkdf2 -in .env.enc -out .env -pass pass:"VOTRE_CLE"
```

> 📝 Le fichier `.env.example` sert uniquement de référence pour les variables nécessaires.

### 3. Lancer les services

```bash
docker-compose up -d --build
```

### 4. Exécuter les migrations

```bash
docker exec -it user-service php bin/console doctrine:migrations:migrate --no-interaction
docker exec -it article-service php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Vérifier l'état

```bash
docker-compose ps
```

> ⏳ Keycloak peut prendre 2-3 minutes pour démarrer complètement

---

## 🎮 Utilisation

### 🌐 Accès aux services

| Service | URL | Description |
|---------|-----|-------------|
| **Frontend** | http://localhost:3000 | Interface utilisateur |
| **Keycloak** | http://localhost:8080 | Console admin |
| **Kong Admin** | http://localhost:8001 | API d'administration |
| **Kafka UI** | http://localhost:8090 | Monitoring Kafka |

---

## 🔌 Ports & Services

| Service | Port | Description | Technologie |
|---------|------|-------------|-------------|
| **Frontend** | 3000 | Interface React | React 18 |
| **Keycloak** | 8080 | Authentification OAuth2/OIDC | Keycloak 23 |
| **Kong Proxy** | 8000 | API Gateway | Kong + OIDC |
| **Kong Admin** | 8001 | Admin API | Kong |
| **User Service** | 8081 | Gestion utilisateurs | Symfony 7.3 |
| **Article Service** | 8082 | Gestion articles | Symfony 7.3 |
| **Kafka UI** | 8090 | Monitoring Kafka | Kafka UI |
| **Kafka** | 9092 | Event Streaming | Apache Kafka 7.5 |
| **User DB** | 5432 | Base utilisateurs | PostgreSQL 15 |
| **Article DB** | 5433 | Base articles | PostgreSQL 15 |

---

## 🐛 Commandes utiles

```bash
# Voir les logs
docker-compose logs -f [service-name]

# Entrer dans un conteneur
docker exec -it [container-name] bash

# Redémarrer un service
docker-compose restart [service-name]

# Arrêter tous les services
docker-compose down

# Arrêter et supprimer les données
docker-compose down -v
```

---

## 📚 Documentation

- [Symfony](https://symfony.com/doc) • [API Platform](https://api-platform.com/docs) • [Keycloak](https://www.keycloak.org/documentation)
- [Kong](https://docs.konghq.com) • [Kafka](https://kafka.apache.org/documentation) • [React](https://react.dev)

---

<div align="center">

**Projet étudiant afin d'apprendre l'architecture Microservices**

</div>
