# Intégration Kafka - Keycloak

## Architecture

```
┌─────────────┐    ┌─────────────────┐    ┌───────────────────────────┐
│  Keycloak   │───▶│  Kafka          │───▶│  article-service-consumer │
│  (Auth)     │    │  (Events Bus)   │    │  (Background Worker)      │
└─────────────┘    └─────────────────┘    └───────────────────────────┘
     │                                                  │
     │ Events:                                          │ Stocke dans
     │ - REGISTER                                       ▼
     │ - UPDATE_PROFILE                        ┌───────────────┐
     │ - DELETE_ACCOUNT                        │  UserInfo     │
     │                                         │  (PostgreSQL) │
     │                                         └───────────────┘
     │                                                  │
     │                                                  │ Enrichit
     │                                                  ▼
     │                                         ┌───────────────────────┐
     └────────────────────────────────────────▶│  article-service      │
                      JWT Token                │  GET /articles        │
                                               │  → Articles + Owner   │
                                               └───────────────────────┘
```

## Principe

Quand un utilisateur s'inscrit ou modifie son profil sur Keycloak :
1. Keycloak publie un event sur Kafka (topic: `keycloak-events`)
2. Le consumer `article-service-consumer` reçoit l'event
3. Il crée/met à jour/supprime l'entrée dans la table `UserInfo`
4. Quand on fait `GET /articles`, les infos du propriétaire sont incluses

---

## Fichiers créés/modifiés

### Keycloak

| Fichier | Description |
|---------|-------------|
| `keycloak/providers/keycloak-kafka-1.1.5-jar-with-dependencies.jar` | Plugin Kafka pour Keycloak |
| `keycloak/realm-export.json` | Events activés + listener "kafka" |

### Article-service

| Fichier | Description |
|---------|-------------|
| `src/Entity/UserInfo.php` | Entité pour stocker les infos utilisateur |
| `src/Repository/UserInfoRepository.php` | Repository Doctrine |
| `src/Kafka/KafkaConsumer.php` | Wrapper rdkafka |
| `src/MessageHandler/KeycloakEventHandler.php` | Traite les events (REGISTER, UPDATE, DELETE) |
| `src/Command/ConsumeKeycloakEventsCommand.php` | Commande consumer Kafka |
| `src/Command/SyncKeycloakUsersCommand.php` | Sync des users existants |
| `src/State/ArticleWithOwnerProvider.php` | Enrichit GET /articles avec owner |
| `src/Entity/Article.php` | Ajout du champ `owner` |
| `config/services.yaml` | Config Kafka et Keycloak |

### Docker

| Fichier | Description |
|---------|-------------|
| `docker-compose.yml` | Ajout consumer + config Keycloak Kafka |
| `docker-compose.override.yml` | Config dev pour le consumer |

---

## Variables d'environnement

Ajouter dans `.env` :

```env
# Keycloak Admin (pour sync des users existants)
KEYCLOAK_URL=http://keycloak:8080
KEYCLOAK_REALM=collector_realms
KEYCLOAK_ADMIN=admin
KEYCLOAK_ADMIN_PASSWORD=admin
```

---

## Installation

### 1. Rebuild et lancer les conteneurs

```bash
docker compose up -d --build
```

### 2. Créer la migration pour UserInfo

```bash
docker compose exec article-service php bin/console make:migration
```

### 3. Exécuter les migrations

```bash
docker compose exec article-service php bin/console doctrine:migrations:migrate
```

### 4. Synchroniser les utilisateurs existants

```bash
docker compose exec article-service php bin/console app:sync-keycloak-users
```

Cette commande :
- Se connecte à l'API admin de Keycloak
- Récupère tous les utilisateurs existants
- Les insère dans la table `UserInfo`

### 5. Vérifier que le consumer fonctionne

```bash
docker compose logs -f article-service-consumer
```

Tu devrais voir :
```
Keycloak Events Consumer
Connecting to Kafka: kafka:29092
Topic: keycloak-events
Consumer started. Waiting for messages...
```

---

## Commandes disponibles

### Consumer Kafka (tourne en continu)
```bash
php bin/console app:consume-keycloak-events
```

### Sync des utilisateurs existants (one-shot)
```bash
php bin/console app:sync-keycloak-users
```

---

## Structure des conteneurs

```
article-service           → API REST (php-fpm + nginx)
article-service-consumer  → Consumer Kafka (même image, commande différente)
```

Avantages de cette séparation :
- Responsabilités claires (API vs background worker)
- Scaling indépendant
- Si le consumer crash, l'API continue
- Logs séparés

---

## Résultat API

`GET /api/articles` retourne maintenant :

```json
{
  "id": 1,
  "title": "Mon article",
  "description": "Description...",
  "price": 99.99,
  "mainPhotoUrl": "https://...",
  "ownerId": "abc-123-uuid",
  "owner": {
    "id": "abc-123-uuid",
    "email": "john@example.com",
    "firstName": "John",
    "lastName": "Doe",
    "fullName": "John Doe",
    "avatarUrl": "https://..."
  },
  "status": "DRAFT",
  "createdAt": "2025-12-08T10:00:00+00:00"
}
```

---

## Avatar utilisateur

L'avatar est stocké comme attribut custom dans Keycloak :

1. Keycloak Admin → Users → [User] → Attributes
2. Ajouter : `avatarUrl` = `https://example.com/avatar.jpg`

L'event `UPDATE_PROFILE` enverra cet attribut et le consumer le stockera.

---

## Troubleshooting

### Le consumer ne reçoit pas les events

1. Vérifier que Keycloak a bien le plugin :
```bash
docker compose exec keycloak ls /opt/keycloak/providers/
```

2. Vérifier que l'event listener "kafka" est activé dans Keycloak :
   - Admin Console → Realm Settings → Events → Event Listeners

3. Vérifier les logs Kafka :
```bash
docker compose logs kafka
```

### Erreur "Unable to write in cache"

```bash
docker compose down -v article-service-consumer
docker compose up -d article-service-consumer
```

### Sync des users échoue

Vérifier que les variables Keycloak sont bien définies :
```bash
docker compose exec article-service env | grep KEYCLOAK
```

---

## Kafka UI

### Accès

- **URL** : http://localhost:8090
- **Cluster** : `local`

### Configuration docker-compose.yml

```yaml
kafka-ui:
  image: provectuslabs/kafka-ui:latest
  container_name: kafka-ui
  depends_on:
    kafka: { condition: service_healthy }
  networks: [app-network]
  ports:
    - "8090:8080"
  environment:
    KAFKA_CLUSTERS_0_NAME: local
    KAFKA_CLUSTERS_0_BOOTSTRAPSERVERS: kafka:29092  # Port INTERNE Docker
```

> **Important** : Utiliser `kafka:29092` (listener INTERNAL) et non `kafka:9092` (listener PLAINTEXT pour localhost).

### Fonctionnalités

| Feature | Description |
|---------|-------------|
| **Topics** | Voir tous les topics, créer/supprimer, voir les partitions |
| **Messages** | Lire les messages dans un topic (debug) |
| **Consumer Groups** | Voir le lag des consumers, offset courant |
| **Brokers** | État du cluster, métriques |
| **Produce** | Envoyer des messages de test |

### Utilité pour ce projet

- Vérifier que les events Keycloak arrivent dans `keycloak-events`
- Voir si `article-service-consumer` consomme (lag = 0)
- Tester manuellement en produisant des messages

---

## Format des messages Kafka

### Topic : `keycloak-events`

#### Event REGISTER (inscription)

```json
{
  "type": "REGISTER",
  "realmId": "collector_realms",
  "clientId": "article-client",
  "userId": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "ipAddress": "192.168.1.100",
  "details": {
    "username": "john.doe",
    "email": "john.doe@example.com",
    "first_name": "John",
    "last_name": "Doe"
  },
  "time": 1733667890123
}
```

#### Event UPDATE_PROFILE (modification profil)

```json
{
  "type": "UPDATE_PROFILE",
  "realmId": "collector_realms",
  "clientId": "article-client",
  "userId": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "details": {
    "previous_email": "john.doe@example.com",
    "updated_email": "john.new@example.com",
    "previous_first_name": "John",
    "updated_first_name": "Johnny"
  },
  "time": 1733668000000
}
```

#### Event DELETE_ACCOUNT (suppression)

```json
{
  "type": "DELETE_ACCOUNT",
  "realmId": "collector_realms",
  "userId": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "time": 1733669000000
}
```

#### Event LOGIN

```json
{
  "type": "LOGIN",
  "realmId": "collector_realms",
  "clientId": "article-client",
  "userId": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "ipAddress": "192.168.1.100",
  "details": {
    "auth_method": "openid-connect",
    "username": "john.doe"
  },
  "time": 1733670000000
}
```

#### Event LOGOUT

```json
{
  "type": "LOGOUT",
  "realmId": "collector_realms",
  "userId": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "time": 1733671000000
}
```

### Topic : `keycloak-admin-events`

#### Création utilisateur par admin

```json
{
  "type": "CREATE",
  "realmId": "collector_realms",
  "resourceType": "USER",
  "resourcePath": "users/f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "representation": "{\"username\":\"john.doe\",\"email\":\"john.doe@example.com\",\"firstName\":\"John\",\"lastName\":\"Doe\"}",
  "operationType": "CREATE",
  "authDetails": {
    "realmId": "master",
    "clientId": "admin-cli",
    "userId": "admin-uuid"
  },
  "time": 1733667890456
}
```

---

## Tester manuellement via Kafka UI

### Simuler une inscription

1. Ouvrir http://localhost:8090
2. Aller dans **Topics** → `keycloak-events`
3. Cliquer sur **Produce Message**
4. **Key** : `test-user-12345`
5. **Value** :

```json
{
  "type": "REGISTER",
  "realmId": "collector_realms",
  "clientId": "article-client",
  "userId": "test-user-12345",
  "details": {
    "username": "test.user",
    "email": "test@example.com",
    "first_name": "Test",
    "last_name": "User"
  },
  "time": 1733667890000
}
```

6. Cliquer sur **Produce Message**
7. Vérifier les logs : `docker compose logs -f article-service-consumer`
8. L'utilisateur doit être créé dans `article-db`

### Simuler une mise à jour

```json
{
  "type": "UPDATE_PROFILE",
  "realmId": "collector_realms",
  "userId": "test-user-12345",
  "details": {
    "updated_email": "test.updated@example.com",
    "updated_first_name": "TestUpdated"
  },
  "time": 1733668000000
}
```

### Simuler une suppression

```json
{
  "type": "DELETE_ACCOUNT",
  "realmId": "collector_realms",
  "userId": "test-user-12345",
  "time": 1733669000000
}
```

---

## Intérêt de Kafka pour Keycloak

### Avantages

| Avantage | Explication |
|----------|-------------|
| **Découplage** | Keycloak publie sans connaître les consumers |
| **Sync utilisateurs** | Création automatique des profils locaux à l'inscription |
| **Audit/Logs** | Historique complet des événements d'authentification |
| **Scalabilité** | Plusieurs services peuvent consommer indépendamment |
| **Résilience** | Messages persistés si un consumer est down |
| **Event-driven** | Architecture réactive, pas de polling |

### Cas d'usage

- **Multi-services** : User-service, article-service, notification-service peuvent tous réagir à une inscription
- **Analytics** : Stocker les logins pour statistiques
- **Notifications** : Envoyer un email de bienvenue à l'inscription
- **Sécurité** : Détecter des patterns de login suspects

### Quand NE PAS utiliser Kafka

- Petit projet mono-service (overhead inutile)
- Pas besoin de réplication des users entre services
- Latence critique (quelques ms de délai)

---

## Plugin Keycloak-Kafka

### JAR installé

```
keycloak/providers/keycloak-kafka-1.1.5-jar-with-dependencies.jar
```

### Source

https://github.com/SnuK87/keycloak-kafka

### Configuration Keycloak (docker-compose.yml)

```yaml
keycloak:
  environment:
    KAFKA_TOPIC: keycloak-events
    KAFKA_ADMIN_TOPIC: keycloak-admin-events
    KAFKA_BOOTSTRAP_SERVERS: kafka:29092
    KAFKA_CLIENT_ID: keycloak
    KAFKA_EVENTS: REGISTER,UPDATE_PROFILE,DELETE_ACCOUNT,LOGIN,LOGOUT
```

### Activer l'Event Listener

1. Keycloak Admin Console → **Realm Settings** → **Events**
2. **Event Listeners** → Ajouter `kafka`
3. Sauvegarder
