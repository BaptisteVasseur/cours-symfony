# Guide du projet Airbnb - Symfony

## Vue d'ensemble

Ce projet est une application de location de biens immobiliers (style Airbnb) développée avec **Symfony 8.0** et **PHP 8.4**. L'application permet aux utilisateurs de réserver des propriétés, de gérer leurs réservations, de laisser des avis, et inclut un système de gamification avec badges, défis et récompenses.

## Stack technique

- **Framework** : Symfony 8.0
- **PHP** : 8.4+
- **Base de données** : PostgreSQL 16
- **ORM** : Doctrine ORM 3.6
- **Messaging** : RabbitMQ (AMQP) pour les messages asynchrones
- **Cache** : Redis 7 (optionnel, configuré mais pas activé par défaut)
- **Frontend** : Stimulus, Turbo, Asset Mapper
- **Docker** : Configuration complète pour le développement

## Structure du projet

```
src/
├── Controller/          # Contrôleurs Symfony
├── Entity/              # Entités Doctrine (16 entités)
├── Repository/          # Repositories Doctrine
├── DataFixtures/        # Fixtures pour remplir la base de données
└── Kernel.php

config/
├── packages/            # Configuration des bundles Symfony
└── routes/              # Configuration des routes

docker/
└── nginx/              # Configuration Nginx

assets/                 # Assets frontend (Stimulus, CSS)
templates/              # Templates Twig
migrations/             # Migrations Doctrine
```

## Entités principales

### Utilisateurs et authentification
- **User** : Utilisateurs avec authentification Symfony (UserInterface)
- **GamificationUserStats** : Statistiques de gamification par utilisateur

### Propriétés
- **Property** : Propriétés à louer
- **PropertyPhoto** : Photos des propriétés
- **Amenity** : Équipements disponibles (WiFi, Piscine, etc.)
- **Availability** : Disponibilités et prix par date

### Réservations
- **Booking** : Réservations
- **Payment** : Paiements associés aux réservations
- **Review** : Avis entre hôtes et invités
- **Message** : Messages entre utilisateurs

### Gamification
- **Badge** : Badges disponibles
- **UserBadge** : Badges obtenus par les utilisateurs
- **Challenge** : Défis disponibles
- **UserChallenge** : Progression des utilisateurs dans les défis
- **Reward** : Récompenses disponibles
- **UserReward** : Récompenses obtenues par les utilisateurs

## Conventions de code

### Langue
- **Toutes les chaînes de caractères doivent être en français** (messages, statuts, descriptions, etc.)
- Les commentaires de code peuvent être en français ou en anglais

### Naming
- Entités : PascalCase (ex: `User`, `Property`)
- Propriétés : camelCase (ex: `firstName`, `checkInDate`)
- Méthodes : camelCase (ex: `getFullName()`, `setStatus()`)
- Tables : snake_case (ex: `property_photo`, `user_badge`)

### Statuts et valeurs
Les statuts utilisent des valeurs en français avec underscores :
- Réservations : `en_attente`, `confirmé`, `terminé`, `annulé`
- Propriétés : `actif`, `inactif`, `en_attente`
- Paiements : `en_attente`, `terminé`, `échoué`, `remboursé`
- Récompenses : `gagné`, `utilisé`, `expiré`

### Types de propriétés
- `Appartement`, `Maison`, `Villa`, `Copropriété`, `Studio`, `Loft`, `Cottage`, `Chalet`

## Base de données

- **SGBD** : PostgreSQL 16
- **Migrations** : Doctrine Migrations
- **Fixtures** : DoctrineFixturesBundle (fichier `AppFixtures.php`)
- **Schéma** : Voir `planttext-bdd.txt` pour le diagramme UML

### Commandes utiles
```bash
# Créer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Charger les fixtures
php bin/console doctrine:fixtures:load
```

## Docker

Le projet utilise Docker Compose avec les services suivants :

- **php** : Service PHP 8.4 avec extensions (port 8888)
- **nginx** : Serveur web (port 8000)
- **database** : PostgreSQL 16 (port 5432)
- **adminer** : Interface web pour PostgreSQL (port 8080)
- **rabbitmq** : Broker de messages (ports 5672, 15672)
- **redis** : Cache (port 6379)
- **mailer** : Mailpit pour le développement (ports 1025, 8025)
- **messenger-worker** : Worker pour consommer les messages RabbitMQ

### Commandes Docker
```bash
# Démarrer les services
docker compose up -d

# Arrêter les services
docker compose down

# Voir les logs
docker compose logs -f
```

## Messaging asynchrone

Le projet utilise **RabbitMQ** pour les messages asynchrones via Symfony Messenger :

- **Transport** : `amqp://guest:guest@rabbitmq:5672/%2f/messages`
- **Configuration** : `config/packages/messenger.yaml`
- **Worker** : Service Docker `messenger-worker` qui exécute `messenger:consume async`

Les messages suivants sont routés vers le transport asynchrone :
- `SendEmailMessage` (emails)
- `ChatMessage` (notifications)
- `SmsMessage` (SMS)

## Sécurité

- **Authentification** : Symfony Security avec UserInterface
- **Hachage des mots de passe** : UserPasswordHasherInterface (auto)
- **CSRF** : Protection activée
- **Fixtures** : Tous les utilisateurs ont le mot de passe `password123` (dev uniquement)

## Assets et frontend

- **Asset Mapper** : Gestion des assets JavaScript/CSS
- **Stimulus** : Framework JavaScript pour les contrôleurs
- **Turbo** : Navigation rapide sans rechargement de page
- **Pas de build step** : Assets servis directement

## Tests

- **Framework** : PHPUnit 12.5
- **Configuration** : `phpunit.dist.xml`
- **Base de données de test** : Suffixe `_test` ajouté automatiquement

## Développement

### Variables d'environnement
- `DATABASE_URL` : URL de connexion PostgreSQL
- `MESSENGER_TRANSPORT_DSN` : URL de connexion RabbitMQ
- `MAILER_DSN` : Configuration du mailer
- `REDIS_URL` : URL de connexion Redis (optionnel)

### Commandes utiles
```bash
# Lancer le serveur de développement
php -S localhost:8000 -t public

# Vider le cache
php bin/console cache:clear

# Créer une entité
php bin/console make:entity

# Créer un contrôleur
php bin/console make:controller
```

## Points d'attention

1. **Toutes les chaînes doivent être en français** dans les fixtures et le code métier
2. **Les dates** utilisent `DateTimeImmutable` partout
3. **Les relations Doctrine** sont bien configurées avec les bonnes inversions
4. **Les statuts** utilisent des valeurs en français avec underscores
5. **Le worker Messenger** doit tourner en continu pour traiter les messages asynchrones
6. **Les fixtures** créent des données cohérentes entre toutes les entités

## Architecture

- **MVC** : Modèle-Vue-Contrôleur classique Symfony
- **Repository Pattern** : Toutes les entités ont leur repository
- **Service Layer** : Logique métier dans les services (à créer si nécessaire)
- **Event-Driven** : Utilisation de Messenger pour les opérations asynchrones

## Prochaines étapes suggérées

1. Créer les contrôleurs pour les fonctionnalités principales
2. Implémenter les formulaires Symfony pour les réservations
3. Ajouter la validation des données
4. Créer les templates Twig pour l'interface utilisateur
5. Implémenter l'API REST si nécessaire
6. Ajouter les tests unitaires et fonctionnels
