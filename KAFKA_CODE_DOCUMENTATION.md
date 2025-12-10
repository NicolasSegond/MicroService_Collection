# Documentation du Code - Intégration Kafka/Keycloak

## Vue d'ensemble du flux

```
┌──────────────┐     ┌─────────┐     ┌────────────────────┐     ┌──────────────┐
│   Keycloak   │────▶│  Kafka  │────▶│ ConsumeKeycloak... │────▶│ EventHandler │
│  (REGISTER)  │     │ (topic) │     │    (Command)       │     │   (Logic)    │
└──────────────┘     └─────────┘     └────────────────────┘     └──────┬───────┘
                                                                       │
                                                                       ▼
┌──────────────┐     ┌─────────────────────┐     ┌──────────────────────────────┐
│   Article    │◀────│ ArticleWithOwner... │◀────│        UserInfo              │
│   (API)      │     │    (Provider)       │     │      (Database)              │
└──────────────┘     └─────────────────────┘     └──────────────────────────────┘
```

---

## 1. KafkaConsumer.php

**Chemin** : `src/Kafka/KafkaConsumer.php`

**Rôle** : Wrapper autour de la librairie `rdkafka` pour simplifier la consommation de messages.

### Méthodes

#### `__construct(string $brokers, string $groupId)`
```php
$conf = new Conf();
$conf->set('metadata.broker.list', $brokers);  // Liste des serveurs Kafka
$conf->set('group.id', $groupId);               // ID du groupe de consumers
$conf->set('auto.offset.reset', 'earliest');    // Commencer au début si pas d'offset
$conf->set('enable.auto.commit', 'true');       // Commit automatique des offsets
$conf->set('allow.auto.create.topics', 'true'); // Créer le topic s'il n'existe pas
```

**Paramètres** :
- `$brokers` : Adresse Kafka (ex: `kafka:29092`)
- `$groupId` : Identifiant du groupe consumer (ex: `article-service-consumer`)

**Note sur `group.id`** : Kafka utilise ce concept pour permettre le load-balancing. Si tu as 3 instances du consumer avec le même `group.id`, chaque message sera traité par UNE SEULE instance.

#### `subscribe(array $topics): void`
```php
$this->consumer->subscribe($topics);
```
S'abonne à un ou plusieurs topics Kafka.

#### `consume(int $timeoutMs = 1000): ?Message`
```php
$message = $this->consumer->consume($timeoutMs);

if ($message->err === RD_KAFKA_RESP_ERR_NO_ERROR) {
    return $message;  // Message valide
}

// Erreurs non-fatales : retourne null
if ($message->err === RD_KAFKA_RESP_ERR__PARTITION_EOF ||
    $message->err === RD_KAFKA_RESP_ERR__TIMED_OUT ||
    $message->err === RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART) {
    return null;
}

throw new \RuntimeException(...);  // Erreur fatale
```

**Gestion des erreurs** :
| Code erreur | Signification | Action |
|-------------|---------------|--------|
| `RD_KAFKA_RESP_ERR_NO_ERROR` | Message reçu | Retourne le message |
| `RD_KAFKA_RESP_ERR__PARTITION_EOF` | Fin de partition, pas de nouveaux messages | Retourne null |
| `RD_KAFKA_RESP_ERR__TIMED_OUT` | Timeout, rien reçu | Retourne null |
| `RD_KAFKA_RESP_ERR_UNKNOWN_TOPIC_OR_PART` | Topic n'existe pas encore | Retourne null |
| Autres | Erreur fatale | Lance une exception |

#### `close(): void`
Ferme proprement la connexion Kafka.

### Optimisation possible
- **Batch processing** : Actuellement on traite message par message. Pour de gros volumes, on pourrait consommer plusieurs messages puis les traiter en batch.

---

## 2. ConsumeKeycloakEventsCommand.php

**Chemin** : `src/Command/ConsumeKeycloakEventsCommand.php`

**Rôle** : Commande Symfony qui tourne en continu pour consommer les events Kafka.

### Attributs de classe

```php
#[AsCommand(
    name: 'app:consume-keycloak-events',
    description: 'Consume Keycloak events from Kafka'
)]
```
Permet d'exécuter via : `php bin/console app:consume-keycloak-events`

### Méthodes

#### `__construct(...)`
```php
public function __construct(
    private KeycloakEventHandler $eventHandler,  // Logique de traitement
    private LoggerInterface $logger,              // Logs
    private string $kafkaBrokers,                 // Injecté depuis services.yaml
    private string $kafkaTopic                    // Injecté depuis services.yaml
)
```

**Injection de dépendances** : Les paramètres `$kafkaBrokers` et `$kafkaTopic` sont injectés via `services.yaml` :
```yaml
App\Command\ConsumeKeycloakEventsCommand:
    arguments:
        $kafkaBrokers: '%kafka.brokers%'
        $kafkaTopic: '%kafka.topic.keycloak%'
```

#### `configure(): void`
```php
$this->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Consumer timeout in ms', 1000);
```
Option `--timeout` pour configurer le polling (défaut: 1000ms).

#### `execute(...): int`
```php
// Gestion du shutdown gracieux (CTRL+C)
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, fn() => $this->shouldStop = true);
    pcntl_signal(SIGINT, fn() => $this->shouldStop = true);
}
```

**Boucle principale** :
```php
while (!$this->shouldStop) {
    pcntl_signal_dispatch();          // Vérifier les signaux (CTRL+C)
    $message = $consumer->consume();  // Attendre un message
    if ($message === null) continue;  // Pas de message, reboucler
    $this->processMessage($message->payload, $io);
}
```

**Pourquoi `pcntl_signal_dispatch()` ?** : PHP ne traite pas les signaux automatiquement. Cette fonction vérifie si un signal (SIGTERM, SIGINT) a été reçu et exécute le callback associé.

#### `processMessage(string $payload, SymfonyStyle $io): void`
```php
$event = json_decode($payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $this->logger->warning('Invalid JSON');
    return;  // Ignorer les messages malformés
}

$this->eventHandler->handle($event);
```

**Séparation des responsabilités** : Cette méthode ne fait que :
1. Décoder le JSON
2. Logger l'event
3. Déléguer à `KeycloakEventHandler`

### Optimisations possibles
- **Retry mechanism** : Si `eventHandler->handle()` échoue, le message est perdu. On pourrait implémenter un Dead Letter Queue (DLQ).
- **Batch flush** : Actuellement chaque event fait un `flush()`. On pourrait accumuler et flush toutes les X secondes.

---

## 3. KeycloakEventHandler.php

**Chemin** : `src/MessageHandler/KeycloakEventHandler.php`

**Rôle** : Logique métier pour traiter les events Keycloak (REGISTER, UPDATE_PROFILE, DELETE_ACCOUNT).

### Méthodes

#### `handle(array $event): void`
```php
match ($type) {
    'REGISTER' => $this->handleRegister($event),
    'UPDATE_PROFILE' => $this->handleUpdateProfile($event),
    'DELETE_ACCOUNT' => $this->handleDeleteAccount($event),
    default => $this->logger->debug("Ignoring event type: {$type}")
};
```

**Structure d'un event Keycloak** (depuis Kafka) :
```json
{
  "id": "uuid-event",
  "time": 1765197837129,
  "type": "REGISTER",
  "realmId": "uuid-realm",
  "clientId": "collector_front",
  "userId": "uuid-user",
  "sessionId": null,
  "ipAddress": "172.26.0.1",
  "error": null,
  "details": {
    "email": "user@example.com",
    "username": "user@example.com",
    "first_name": "John",
    "last_name": "Doe"
  }
}
```

#### `handleRegister(array $event): void`
```php
$existingUser = $this->userInfoRepository->find($userId);
if ($existingUser) {
    // Utilisateur existe déjà (idempotence)
    $this->updateUserInfo($existingUser, ...);
    return;
}

$userInfo = new UserInfo($userId, $email);
$this->entityManager->persist($userInfo);
$this->entityManager->flush();
```

**Idempotence** : Si l'event est reçu 2 fois (Kafka at-least-once delivery), on ne crée pas de doublon.

#### `handleUpdateProfile(array $event): void`
```php
$userInfo = $this->userInfoRepository->find($userId);
if (!$userInfo) {
    // Cas rare : UPDATE sans REGISTER préalable
    $this->handleRegister($event);
    return;
}
```

**Clés possibles dans details** :
- `updated_email`, `email`
- `updated_first_name`, `firstName`, `first_name`
- `updated_last_name`, `lastName`, `last_name`

Le code gère les deux formats car Keycloak peut varier selon la version.

#### `handleDeleteAccount(array $event): void`
```php
$this->entityManager->remove($userInfo);
$this->entityManager->flush();
```

Simple suppression de l'utilisateur.

#### `updateUserInfo(...): void`
```php
if ($email) $userInfo->setEmail($email);
if ($firstName !== null) $userInfo->setFirstName($firstName);
// ...
$this->entityManager->flush();
```

**Note** : On vérifie `!== null` car une chaîne vide `""` est une valeur valide (l'utilisateur veut effacer son prénom).

### Optimisations possibles
- **Bulk insert** : Si beaucoup de REGISTER arrivent en même temps, on pourrait battre les inserts.
- **Event versioning** : Stocker la version de l'event pour gérer les mises à jour concurrentes.

---

## 4. UserInfo.php (Entity)

**Chemin** : `src/Entity/UserInfo.php`

**Rôle** : Entité Doctrine représentant un utilisateur synchronisé depuis Keycloak.

### Annotations/Attributs

```php
#[ORM\Entity(repositoryClass: UserInfoRepository::class)]
class UserInfo
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private string $id;  // UUID Keycloak (pas auto-généré)
```

**Pourquoi pas d'auto-increment ?** : L'ID est l'UUID Keycloak. Cela permet de faire le lien direct avec Keycloak sans mapping supplémentaire.

### Propriétés

| Propriété | Type | Description |
|-----------|------|-------------|
| `$id` | string | UUID Keycloak (clé primaire) |
| `$email` | string | Email de l'utilisateur |
| `$firstName` | ?string | Prénom (nullable) |
| `$lastName` | ?string | Nom (nullable) |
| `$avatarUrl` | ?string | URL de l'avatar (nullable) |
| `$createdAt` | DateTimeImmutable | Date de création |
| `$updatedAt` | DateTimeImmutable | Date de mise à jour |

### Méthodes notables

#### `getFullName(): string`
```php
return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? '')) ?: $this->email;
```
Retourne "Prénom Nom" ou l'email si les deux sont vides.

#### Setters avec `updatedAt`
```php
public function setEmail(string $email): static
{
    $this->email = $email;
    $this->updatedAt = new \DateTimeImmutable();  // Auto-update
    return $this;
}
```

Chaque modification met à jour `updatedAt` automatiquement.

### Optimisations possibles
- **Index sur email** : Si tu cherches souvent par email, ajouter `#[ORM\Index]`
- **Soft delete** : Au lieu de supprimer, ajouter un champ `deletedAt` pour garder l'historique

---

## 5. UserInfoRepository.php

**Chemin** : `src/Repository/UserInfoRepository.php`

**Rôle** : Repository Doctrine standard pour `UserInfo`.

### Méthodes

#### `save(UserInfo $entity, bool $flush = false): void`
```php
$this->getEntityManager()->persist($entity);
if ($flush) {
    $this->getEntityManager()->flush();
}
```

**Paramètre `$flush`** : Permet de contrôler quand la transaction est envoyée à la BDD. Utile pour le batching.

#### `remove(UserInfo $entity, bool $flush = false): void`
Même logique pour la suppression.

### Note
Ces méthodes ne sont pas utilisées actuellement (on utilise directement `EntityManager`). Elles pourraient être utilisées pour centraliser la logique.

---

## 6. ArticleWithOwnerProvider.php

**Chemin** : `src/State/ArticleWithOwnerProvider.php`

**Rôle** : State Provider API Platform pour enrichir les articles avec les infos du propriétaire.

### Concept API Platform

Dans API Platform, un **Provider** est responsable de récupérer les données. Ce provider "décore" les providers par défaut pour ajouter les infos utilisateur.

### Méthodes

#### `provide(...): object|array|null`
```php
if ($operation instanceof GetCollection) {
    $articles = $this->collectionProvider->provide(...);
    return $this->enrichArticles($articles);
}

$article = $this->itemProvider->provide(...);
if ($article instanceof Article) {
    $this->enrichArticle($article);
}
return $article;
```

**Deux cas** :
1. `GET /articles` → Collection → `enrichArticles()`
2. `GET /articles/{id}` → Item → `enrichArticle()`

#### `enrichArticles(iterable $articles): iterable`
```php
// 1. Collecter tous les ownerIds
foreach ($articles as $article) {
    if ($article->getOwnerId()) {
        $ownerIds[] = $article->getOwnerId();
    }
}

// 2. Une seule requête SQL pour tous les users
$users = $this->userInfoRepository->findBy(['id' => array_unique($ownerIds)]);

// 3. Créer un map pour lookup O(1)
$usersMap = [];
foreach ($users as $user) {
    $usersMap[$user->getId()] = $this->formatUserInfo($user);
}

// 4. Enrichir chaque article
foreach ($articleList as $article) {
    if (isset($usersMap[$article->getOwnerId()])) {
        $article->setOwner($usersMap[$ownerId]);
    }
}
```

**Optimisation N+1** : Au lieu de faire 1 requête par article, on fait UNE SEULE requête pour tous les owners. C'est le pattern "Eager Loading".

#### `enrichArticle(Article $article): void`
```php
$userInfo = $this->userInfoRepository->find($ownerId);
if ($userInfo) {
    $article->setOwner($this->formatUserInfo($userInfo));
}
```

Pour un seul article, une simple requête suffit.

#### `formatUserInfo(UserInfo $user): array`
```php
return [
    'id' => $user->getId(),
    'email' => $user->getEmail(),
    'firstName' => $user->getFirstName(),
    'lastName' => $user->getLastName(),
    'fullName' => $user->getFullName(),
    'avatarUrl' => $user->getAvatarUrl(),
];
```

Transforme l'entité en tableau pour la sérialisation JSON.

### Optimisations possibles
- **Cache** : Mettre en cache les UserInfo fréquemment accédés (Redis/Memcached)
- **Projection SQL** : Ne sélectionner que les colonnes nécessaires au lieu de l'entité complète

---

## 7. SyncKeycloakUsersCommand.php

**Chemin** : `src/Command/SyncKeycloakUsersCommand.php`

**Rôle** : Commande one-shot pour synchroniser les utilisateurs Keycloak existants.

**Quand l'utiliser** : Lors de la mise en place initiale, pour importer les utilisateurs créés avant l'activation de Kafka.

### Méthodes

#### `getAdminToken(): ?string`
```php
$response = $this->httpClient->request('POST',
    "{$this->keycloakUrl}/realms/master/protocol/openid-connect/token",
    ['body' => [
        'grant_type' => 'password',
        'client_id' => 'admin-cli',
        'username' => $this->keycloakAdminUser,
        'password' => $this->keycloakAdminPassword,
    ]]
);
```

**Authentification admin** : Utilise le client `admin-cli` (présent par défaut dans Keycloak) avec les credentials admin.

#### `fetchUsers(string $token): array`
```php
$response = $this->httpClient->request('GET',
    "{$this->keycloakUrl}/admin/realms/{$this->keycloakRealm}/users",
    [
        'headers' => ['Authorization' => "Bearer {$token}"],
        'query' => ['max' => 1000],  // Limite
    ]
);
```

**Limite de 1000** : Pour éviter les timeouts. Si tu as plus de 1000 users, il faudrait paginer.

#### `execute(...): int`
```php
foreach ($users as $user) {
    $existingUser = $this->userInfoRepository->find($userId);

    if ($existingUser) {
        // Update
    } else {
        // Create
    }
}

$this->entityManager->flush();  // Un seul flush à la fin
```

**Batch flush** : Contrairement au consumer qui flush après chaque event, ici on accumule et flush à la fin (plus performant pour l'import initial).

### Optimisations possibles
- **Pagination** : Gérer plus de 1000 users avec `first` et `max` parameters
- **Parallélisation** : Utiliser des promises pour les requêtes HTTP
- **Progress bar** : Ajouter `$io->progressStart()` pour les gros imports

---

## 8. services.yaml

**Chemin** : `config/services.yaml`

**Rôle** : Configuration des services et injection de dépendances.

### Parameters

```yaml
parameters:
    kafka.brokers: '%env(KAFKA_BROKERS)%'
    kafka.topic.keycloak: '%env(default:kafka.topic.keycloak.default:KAFKA_TOPIC_KEYCLOAK)%'
    kafka.topic.keycloak.default: 'keycloak-events'
    keycloak.realm.default: 'collector_realms'
```

**Syntaxe `env(default:...)`** : Si la variable d'environnement n'existe pas, utilise la valeur par défaut.

### Services

```yaml
App\Command\ConsumeKeycloakEventsCommand:
    arguments:
        $kafkaBrokers: '%kafka.brokers%'
        $kafkaTopic: '%kafka.topic.keycloak%'

App\Command\SyncKeycloakUsersCommand:
    arguments:
        $keycloakUrl: '%env(KEYCLOAK_URL)%'
        $keycloakRealm: '%env(default:keycloak.realm.default:KEYCLOAK_REALM)%'
        $keycloakAdminUser: '%env(KEYCLOAK_ADMIN)%'
        $keycloakAdminPassword: '%env(KEYCLOAK_ADMIN_PASSWORD)%'
```

**Injection explicite** : Ces arguments ne peuvent pas être auto-wirés (ce sont des strings), donc on les déclare explicitement.

---

## Résumé des Optimisations Possibles

| Fichier | Optimisation | Priorité | Complexité |
|---------|--------------|----------|------------|
| KafkaConsumer | Batch consume | Moyenne | Moyenne |
| ConsumeCommand | Dead Letter Queue | Haute | Haute |
| ConsumeCommand | Batch flush | Moyenne | Faible |
| EventHandler | Bulk insert | Basse | Moyenne |
| UserInfo | Index sur email | Basse | Faible |
| UserInfo | Soft delete | Basse | Faible |
| ArticleProvider | Cache Redis | Haute | Moyenne |
| SyncCommand | Pagination | Moyenne | Faible |

### Priorités recommandées

1. **Cache Redis pour ArticleProvider** - Impact immédiat sur les perfs API
2. **Dead Letter Queue** - Fiabilité des events (pas de perte)
3. **Pagination SyncCommand** - Si tu as beaucoup d'users
