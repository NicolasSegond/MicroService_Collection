# language: fr

Fonctionnalité: Création et affichage public d'un article
  En tant qu'utilisateur authentifié
  Je veux créer un article de vente avec une photo et le publier
  Afin qu'il soit visible dans le catalogue public et consultable via une page de détail accessible à tous

  Contexte:
    Etant donné que l'application est disponible
    Et que la base de données est opérationnelle

  # ─────────────────────────────────────────────
  # CA1 – Authentification obligatoire
  # ─────────────────────────────────────────────

  Scénario: Création d'un article sans authentification
    Etant donné un utilisateur non authentifié
    Quand il envoie une requête POST sur "/api/articles" avec un payload valide
    Alors la réponse a le statut 401
    Et la réponse contient un message indiquant que l'authentification est requise

  # ─────────────────────────────────────────────
  # CA2 – Création d'un article valide
  # ─────────────────────────────────────────────

  Scénario: Création d'un article avec tous les champs obligatoires
    Etant donné un utilisateur authentifié avec un token valide
    Quand il envoie une requête POST sur "/api/articles" avec les données suivantes :
      | title       | description              | price  | mainPhotoUrl          |
      | Vase Ming   | Vase de la dynastie Ming | 450.00 | https://cdn.../vase.jpg |
    Alors la réponse a le statut 201
    Et la réponse contient un champ "id"
    Et l'article est créé en base avec le statut "DRAFT"

  Scénario: Création d'un article sans photo
    Etant donné un utilisateur authentifié avec un token valide
    Quand il envoie une requête POST sur "/api/articles" sans le champ "mainPhotoUrl"
    Alors la réponse a le statut 400
    Et la réponse contient un message d'erreur mentionnant que la photo est obligatoire

  Scénario: Création d'un article avec un prix invalide
    Etant donné un utilisateur authentifié avec un token valide
    Quand il envoie une requête POST sur "/api/articles" avec un price de 0
    Alors la réponse a le statut 400
    Et la réponse contient un message d'erreur mentionnant que le prix doit être supérieur à 0

  # ─────────────────────────────────────────────
  # CA3 – Catalogue public
  # ─────────────────────────────────────────────

  Scénario: Un article publié apparaît dans le catalogue
    Etant donné un article en base avec le statut "PUBLISHED"
    Quand un visiteur non authentifié envoie une requête GET sur "/api/articles"
    Alors la réponse a le statut 200
    Et la liste contient cet article
    Et chaque article expose au minimum les champs "title", "price" et "mainPhotoUrl"

  Scénario: Un article en brouillon n'apparaît pas dans le catalogue
    Etant donné un article en base avec le statut "DRAFT"
    Quand un visiteur non authentifié envoie une requête GET sur "/api/articles"
    Alors la réponse a le statut 200
    Et la liste ne contient pas cet article

  # ─────────────────────────────────────────────
  # CA4 – Page de détail
  # ─────────────────────────────────────────────

  Scénario: Consultation du détail d'un article publié
    Etant donné un article en base avec le statut "PUBLISHED"
    Quand un visiteur non authentifié envoie une requête GET sur "/api/articles/{id}"
    Alors la réponse a le statut 200
    Et la réponse contient au minimum les champs "title", "description", "price" et "mainPhotoUrl"

  Scénario: Consultation du détail d'un article en brouillon
    Etant donné un article en base avec le statut "DRAFT"
    Quand un visiteur non authentifié envoie une requête GET sur "/api/articles/{id}"
    Alors la réponse a le statut 404