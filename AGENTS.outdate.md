# AGENTS.md — Plateforme de location (type Airbnb)

Instructions pour les agents IA travaillant sur ce dépôt. Répondre en **français**. Ne pas ajouter de commentaires dans le code PHP/Twig/JS sauf nécessité métier non évidente.

## Contexte projet

Application web de réservation de logements entre particuliers et professionnels (modèle Airbnb). Référence fonctionnelle : `Cahier des charges.md`. Schéma cible BDD : `PlantText.txt`.

### Utilisateurs et rôles (cahier des charges)

| Rôle | Capacités principales |
|------|------------------------|
| **Voyageur** | Recherche, réservation, paiement, avis, messagerie |
| **Hôte** | Annonces, calendrier, tarifs, réservations, revenus |
| **Administrateur** | Modération, utilisateurs, litiges, reporting |
| **Super Admin** | Accès complet plateforme |

Rôles Symfony à aligner sur le métier : `ROLE_USER`, `ROLE_HOST`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` (adapter `config/packages/security.yaml` et `role_hierarchy` au fur et à mesure).

### MVP (priorité développement)

Inclus : authentification, annonces, recherche, réservation, Stripe, messagerie, avis, dashboards hôte/admin.

Exclu phase 1 : IA avancée, expériences, tarification dynamique, fidélité, apps natives.

---

## Stack technique

| Composant | Version / choix |
|-----------|-----------------|
| PHP | **8.4** (`>=8.4` dans `composer.json`, image `php:8.4-fpm-alpine`) |
| Symfony | **8.0.\*** |
| API | **API Platform 4.2** (REST, stateless par défaut) |
| ORM | Doctrine ORM 3 + Migrations |
| BDD | **PostgreSQL 16** (Alpine via Docker) |
| Front | Twig, Asset Mapper, Stimulus, UX Turbo |
| CSS | TailwindCSS (prévu cahier des charges — à intégrer si absent) |
| Cache / temps réel | Redis, Mercure (prévus — services commentés dans `compose.yaml`) |
| Files d’attente | Symfony Messenger (`doctrine://default`, worker `messenger-worker`) |
| Email dev | Mailpit (SMTP 1025, UI 8025) |
| Tests | PHPUnit 12 |

---

## Environnement Docker

```bash
make install   # premier lancement (build + up)
make up        # démarrer les services
make down      # arrêter
make sh        # shell dans le conteneur PHP
make cache     # cache:clear
make logs      # logs PHP
```

| Service | URL / port |
|---------|------------|
| Application | http://localhost:8089 |
| Adminer (BDD) | http://localhost:8088 |
| Mailpit | http://localhost:8025 |
| PostgreSQL | `localhost:5439` |

**Toujours exécuter les commandes Symfony/Composer/PHPUnit dans le conteneur `php`**, sauf si l’utilisateur travaille hors Docker :

```bash
docker compose exec php php bin/console <commande>
docker compose exec php composer require <package>
docker compose exec php php bin/phpunit
```

Variables d’environnement clés (voir `compose.yaml`, `.env.example`) : `DATABASE_URL`, `MESSENGER_TRANSPORT_DSN`, `MAILER_DSN`, `APP_SECRET`.

---

## Structure du code

```
src/
  Entity/          # Entités Doctrine (mapping attributs PHP 8)
  Repository/      # Repositories Doctrine uniquement (requêtes DB)
  Controller/      # Contrôleurs fins (HTTP → service)
  ApiResource/     # DTOs / ressources API Platform si séparation entité/API
  Service/         # Logique métier
  Message/         # Messages Messenger
  MessageHandler/  # Handlers async
  EventSubscriber/ # Écouteurs domaine
  Security/        # Voters, authenticators
  Form/            # Form types Symfony
  Validator/       # Contraintes custom
config/
  packages/        # Config bundles
  routes/          # Routes additionnelles
templates/         # Twig (hériter de layout/base)
assets/            # JS/CSS via Asset Mapper
tests/             # PHPUnit
migrations/        # Doctrine migrations
```

Namespace racine : `App\`. Autoload PSR-4 dans `composer.json`.

---

## Bonnes pratiques Symfony (obligatoires)

### Architecture

- **Contrôleurs fins** : pas de logique métier ; déléguer aux services injectés.
- **Services stateless** : une responsabilité par service ; interfaces pour les dépendances externes (Stripe, maps, mail).
- **Entités = modèle persistence** : pas de dépendance HTTP dans les entités.
- **DTO / Input** pour API Platform et formulaires complexes ; ne pas exposer toute l’entité si champs sensibles.
- **Repository** : requêtes Doctrine uniquement ; pas de logique métier lourde.
- **Messenger** : emails, notifications, traitements longs → messages async (`#[AsMessageHandler]`, routing dans `config/packages/messenger.yaml`).
- **Events** : `EventSubscriber` pour effets de bord transverses (audit, notifications).

### Configuration et DI

- Paramètres applicatifs dans `config/services.yaml` (`parameters:`) ou variables d’env — **jamais de secrets en dur**.
- Services auto-enregistrés via `App\:` + `autowire` / `autoconfigure`.
- Préférer les **attributs PHP** (`#[Route]`, `#[IsGranted]`, `#[ORM\...]`, `#[ApiResource]`) aux YAML quand le bundle le supporte.

### Doctrine

- Migrations pour tout changement de schéma : `doctrine:migrations:diff` puis `migrate`.
- Relations explicites (`inversedBy` / `mappedBy`), `cascade` et `orphanRemoval` seulement si justifiés.
- Types : `DateTimeImmutable` pour les dates métier ; UUID si alignement avec `PlantText.txt`.
- Index et contraintes uniques sur colonnes recherchées (email, slugs).

### Sécurité

- Hacher les mots de passe via `UserPasswordHasherInterface` — champ `passwordHash`, jamais en clair.
- **CSRF** activé sur les formulaires web (`enable_csrf: true`).
- **Voters** pour autorisation fine (propriétaire d’annonce, réservation, etc.).
- API : JWT ou session selon client ; API Platform `security` / `securityPostDenormalize` sur les opérations sensibles.
- Valider et assainir toutes les entrées (`Validator`, types stricts PHP).
- `access_control` et `#[IsGranted]` cohérents avec les rôles métier.

### API Platform

- Déclarer `#[ApiResource]` sur entités ou DTO dédiés.
- Operations explicites (`Get`, `Post`, `Patch`, `Delete`) avec `security` par opération.
- Pagination, filtres (`#[ApiFilter]`) et serialization groups (`#[Groups]`) pour limiter les champs exposés.
- Codes HTTP et exceptions domaine via `ProblemException` / `HttpException` appropriées.
- Documenter les breaking changes d’API (version dans `api_platform.yaml`).

### Formulaires et validation

- Constraints sur entités/DTO (`#[Assert\...]`) + validation métier dans services si cross-champs.
- `FormType` dédiés ; pas de `$request->request->get()` brut hors formulaires.

### Twig et front

- Templates dans `templates/` ; blocs réutilisables, héritage `extends`.
- Assets via **Asset Mapper** (`importmap.php`), Stimulus pour le JS interactif.
- Turbo pour navigation partielle si pertinent ; éviter le JS inline massif.

### Performance et qualité

- Requêtes N+1 : `JOIN` / `addSelect` ou fetch joins dans repositories.
- Cache Symfony pour config/routes en prod ; HTTP cache headers API Platform déjà configurés.
- Logs via Monolog ; niveaux adaptés (`error` métier, pas de dump en prod).
- Tests : tests fonctionnels pour parcours critiques (auth, réservation) ; PHPUnit dans `tests/`.

### Conventions de code

- `declare(strict_types=1);` en tête des nouveaux fichiers PHP.
- Types de retour et paramètres typés ; propriétés `private` + getters/setters ou **constructor promotion** pour DTOs.
- Nommage anglais pour le code (classes, méthodes), français acceptable pour labels UI et messages utilisateur.
- Pas de commit de `.env`, secrets, ou `var/` / `vendor/`.

---

## Domaines métier (ordre d’implémentation suggéré)

1. **Users & auth** — inscription, login, OAuth (phase 2), 2FA, profils, documents identité
2. **Listings** — annonces, médias, calendrier, tarifs
3. **Search** — filtres, carte (Google Maps / Mapbox)
4. **Bookings** — disponibilité, tarification, politiques d’annulation
5. **Payments** — Stripe (MVP), commissions, remboursements
6. **Messaging** — chat (Mercure pour temps réel)
7. **Reviews** — notes, modération
8. **Notifications** — email (Mailer), push/SMS (Notifier)
9. **Admin** — back-office, reporting, litiges

Chaque feature : entité(s) + migration + repository + service + (controller ou API Resource) + tests ciblés.

---

## Intégrations externes (cahier des charges)

Stripe (MVP), Google Maps, SendGrid/Twilio/Firebase (notifications), OAuth Google/Facebook/Apple. Clés via variables d’environnement ; wrappers service injectables.

---

## Git et livrables

- Ne créer de **commit** ou **push** que sur demande explicite de l’utilisateur.
- Messages de commit en français ou anglais, courts, orientés « pourquoi ».
- Documenter les endpoints API et les décisions d’architecture dans le README ou docs dédiées si demandé.

---

## Pièges connus

- Le schéma `PlantText.txt` utilise des **UUID** ; les entités actuelles (`User`, `Document`) utilisent encore des `int` — migrer progressivement vers le modèle cible.
- `security.yaml` contient des rôles hérités d’un autre projet (`ROLE_PRESTATAIRE`, etc.) — à remplacer par les rôles voyageur/hôte/admin du cahier des charges.
- Redis et Mercure sont commentés dans Docker : activer avant cache distribué et temps réel.
- Commandes `composer` / `bin/console` : privilégier le conteneur `php` pour cohérence avec l’environnement du projet.
