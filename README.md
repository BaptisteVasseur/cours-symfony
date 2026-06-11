# Airbnb Clone — Symfony

Plateforme de location saisonnière (Symfony 7, PostgreSQL, Docker).

---

## Prérequis

- [Docker](https://docs.docker.com/get-docker/) + Docker Compose
- Make

---

## Installation & premier lancement

```bash
# 1. Cloner le dépôt
git clone <url-du-repo>
cd cours-symfony

# 2. Copier le fichier d'environnement
cp .env .env.local
# (optionnel) éditer .env.local pour surcharger des variables

# 3. Build + démarrage (BDD, PHP, Mailpit, Messenger worker)
make install
```

L'entrypoint Docker exécute automatiquement :
1. `composer install`
2. `cache:warmup`
3. `doctrine:migrations:migrate`

---

## Démarrage rapide (après le premier lancement)

```bash
make up       # démarre tous les conteneurs
make down     # arrête tout
make restart  # redémarre
make logs     # suit les logs PHP en temps réel
make sh       # ouvre un shell dans le conteneur PHP
make cache    # vide le cache Symfony
```

---

## Accès aux services

| Service          | URL                           | Identifiants            |
|------------------|-------------------------------|-------------------------|
| Application web  | http://localhost:8089         | —                       |
| Adminer (BDD)    | http://localhost:8088         | user: madox / pwd: esgi |
| Mailpit (emails) | http://localhost:8025         | —                       |
| PostgreSQL       | localhost:5439                | madox / esgi / airbnb   |

---

## Charger les données de démonstration (fixtures)

```bash
make sh
# dans le conteneur :
php bin/console doctrine:fixtures:load --no-interaction
```

Comptes créés :

| Email                  | Mot de passe | Rôle      |
|------------------------|--------------|-----------|
| admin@airbnb.com       | password     | Admin     |
| alice@host.com         | password     | Hôte      |
| bob@host.com           | password     | Hôte      |
| carol@traveler.com     | password     | Voyageur  |
| david@traveler.com     | password     | Voyageur  |

---

## Migrations

```bash
make sh
# dans le conteneur :

# Appliquer toutes les migrations en attente
php bin/console doctrine:migrations:migrate --no-interaction

# Générer une migration après modification d'une entité
php bin/console doctrine:migrations:diff

# Voir l'état des migrations
php bin/console doctrine:migrations:status
```

---

## Contrôle d'accès par rôle

| Page / Route              | Accès requis  |
|---------------------------|---------------|
| Accueil (`/`)             | Public        |
| Liste des logements       | Public        |
| Détail d'un logement      | `ROLE_USER`   |
| Réserver un logement      | `ROLE_USER`   |
| Mes réservations          | `ROLE_USER`   |
| Ajouter / éditer logement | `ROLE_HOST`   |
| Dashboard admin (`/admin`)| `ROLE_ADMIN`  |
| CRUD admin                | `ROLE_ADMIN`  |

Hiérarchie des rôles : `ROLE_ADMIN` > `ROLE_HOST` > `ROLE_USER`

---

## Routes principales

```bash
# Afficher toutes les routes
php bin/console debug:router

# Filtrer par préfixe
php bin/console debug:router | grep admin
php bin/console debug:router | grep booking
```

| Route                     | Méthode | URL                              |
|---------------------------|---------|----------------------------------|
| `app_home`                | GET     | `/`                              |
| `app_login`               | GET/POST| `/login`                         |
| `app_logout`              | ANY     | `/logout`                        |
| `app_register`            | GET/POST| `/register`                      |
| `app_property_index`      | GET     | `/properties`                    |
| `app_property_show`       | GET     | `/properties/{id}`               |
| `app_property_new`        | GET/POST| `/properties/new`                |
| `app_property_edit`       | GET/POST| `/properties/{id}/edit`          |
| `app_booking_index`       | GET     | `/bookings` (historique)         |
| `app_booking_new`         | GET/POST| `/bookings/new/{id}`             |
| `app_booking_show`        | GET     | `/bookings/{id}`                 |
| `admin_dashboard`         | GET     | `/admin`                         |
| `admin_bookings`          | GET     | `/admin/bookings`                |
| `admin_booking_status`    | POST    | `/admin/bookings/{id}/status`    |
| `admin_booking_delete`    | POST    | `/admin/bookings/{id}/delete`    |
| `admin_users`             | GET     | `/admin/users`                   |
| `admin_user_edit`         | GET/POST| `/admin/users/{id}/edit`         |
| `admin_user_delete`       | POST    | `/admin/users/{id}/delete`       |
| `admin_properties`        | GET     | `/admin/properties`              |
| `admin_property_edit`     | GET/POST| `/admin/properties/{id}/edit`    |
| `admin_property_delete`   | POST    | `/admin/properties/{id}/delete`  |

---

## Lancer les tests

```bash
make sh
# dans le conteneur :

# Créer la base de données de test
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Lancer tous les tests
php bin/phpunit

# Un test spécifique
php bin/phpunit tests/NomDuTest.php

# Filtrer par méthode
php bin/phpunit --filter nomDeLaMethode
```

---

## Commandes Symfony utiles

```bash
# Vérifier la configuration des services
php bin/console debug:container

# Vérifier la configuration de sécurité
php bin/console debug:config security

# Créer un utilisateur via commande (si disponible)
php bin/console app:create-user

# Voir les événements disponibles
php bin/console debug:event-dispatcher

# Valider le mapping Doctrine
php bin/console doctrine:schema:validate
```

---

## Architecture

```
src/
├── Controller/
│   ├── AdminController.php      # CRUD admin (users, properties, bookings)
│   ├── BookingController.php    # Réservations voyageur
│   ├── HomeController.php       # Accueil
│   ├── PropertyController.php   # Logements
│   └── SecurityController.php  # Login / register / logout
├── Entity/
│   ├── Booking.php              # Réservation (avec contraintes Assert)
│   ├── Conversation.php
│   ├── Message.php
│   ├── Payment.php
│   ├── Property.php             # Logement (avec contraintes Assert)
│   ├── PropertyImage.php
│   ├── Review.php
│   ├── User.php                 # Utilisateur (avec contraintes Assert)
│   └── UserPreference.php
├── Enum/
│   ├── BookingStatus.php        # PENDING, CONFIRMED, CANCELLED, COMPLETED
│   ├── PropertyStatus.php       # DRAFT, PUBLISHED, ARCHIVED
│   └── UserRole.php             # TRAVELER, HOST, ADMIN
├── Form/
│   ├── AdminUserType.php        # Formulaire édition user (admin)
│   ├── BookingType.php
│   ├── PropertyType.php
│   └── RegisterType.php
└── Repository/
templates/
├── admin/                       # Dashboard, bookings, users, properties + forms
├── booking/                     # Historique, détail, confirmation
├── layout/                      # base.html.twig, header, footer
├── property/                    # Liste, détail, new, edit
└── security/                    # Login, register
migrations/
tests/
docker/entrypoint.sh
```

---

## Variables d'environnement

| Variable                    | Valeur Docker par défaut                               |
|-----------------------------|--------------------------------------------------------|
| `DATABASE_URL`              | `postgresql://madox:esgi@database:5432/airbnb`         |
| `MESSENGER_TRANSPORT_DSN`   | `doctrine://default`                                   |
| `MAILER_DSN`                | `smtp://mailer:1025`                                   |
| `APP_ENV`                   | `dev`                                                  |
| `APP_SECRET`                | généré automatiquement au premier lancement            |
