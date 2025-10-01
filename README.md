# airbnb - Plateforme de location de logements

## 📋 Présentation

airbnb est une plateforme web de location de logements entre particuliers, développée avec Symfony pour servir de support pédagogique à l'apprentissage du framework.

## 🚀 Installation et configuration

### Prérequis

- PHP 8.2+ avec extensions (pdo_pgsql, mbstring, zip, gd, intl)
- Composer
- Symfony CLI
- PostgreSQL 15+
- Git

### Installation recommandée

**Pour suivre le cours, nous recommandons de :**
1. **Fork ce repository** pour garder une trace de votre progression
2. **Ou créer un nouveau repository** vide pour partir de zéro

### 1. Installation de Symfony CLI

(https://symfony.com/download)[https://symfony.com/download]

### 2. Création du projet Symfony

```bash
# Créer un nouveau projet Symfony
symfony new airbnb --version="7.1.*"
cd airbnb
```

### 3. Installation des dépendances

```bash
# Installer les dépendances de base
composer require orm
composer require debug
composer require alice
# ...
composer require symfony/maker-bundle --dev
composer require symfony/security-bundle
composer require symfony/form
composer require symfony/validator
composer require symfony/twig-bundle
composer require symfony/asset
composer require symfony/mailer
composer require symfony/http-client
composer require symfony/doctrine-fixtures-bundle --dev
composer require symfony/test-pack --dev
```

### 4. Services Docker (optionnel mais recommandé)

```bash
# Démarrer PostgreSQL et MailHog avec Docker Compose
docker compose up -d postgres mailhog

# Vérifier que les services sont démarrés
docker compose ps
```

### 5. Configuration de l'environnement

```bash
# Configurer la base de données dans .env.local
DATABASE_URL="postgresql://airbnb:airbnb@localhost:5432/airbnb?serverVersion=15&charset=utf8"
MAILER_DSN=smtp://localhost:1025
```

### 6. Initialisation de la base de données

```bash
# Créer la base de données
symfony console doctrine:database:create

# Vérifier la connexion
symfony console doctrine:database:create --if-not-exists
```

### 7. Démarrage du serveur de développement

```bash
# Démarrer le serveur Symfony
symfony serve -d

# Ou avec le serveur PHP intégré
symfony server:start
```

### 8. Accès aux services

- **Application Symfony** : https://127.0.0.1:8000
- **Interface MailHog** : http://localhost:8025
- **Base de données PostgreSQL** : localhost:5432

## 🐳 Services Docker (Docker Compose v2)

### Services disponibles

- **PostgreSQL** - Base de données principale
- **MailHog** - Serveur SMTP de test pour les emails

### Ports utilisés

- `5432` - PostgreSQL
- `8025` - Interface MailHog (web)
- `1025` - SMTP MailHog

### Commandes Docker Compose

```bash
# Démarrer les services
docker compose up -d

# Arrêter les services
docker compose down

# Voir les logs
docker compose logs -f

# Redémarrer un service
docker compose restart postgres
```

### Symfony

```bash
# Créer une entité
php bin/console make:entity

# Créer un contrôleur
php bin/console make:controller

# Créer un formulaire
php bin/console make:form

# Créer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Vider le cache
php bin/console cache:clear

# Voir les routes
php bin/console debug:router
```

## 📖 Documentation

- [Documentation Symfony](https://symfony.com/doc/current/)
- [Documentation Doctrine](https://www.doctrine-project.org/projects/orm.html)
- [Documentation Twig](https://twig.symfony.com/doc/)
