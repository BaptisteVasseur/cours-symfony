# Appli Symfony Clone Airbnb

## Etapes suivies pour le développement

1. Lire le cahier des charges
2. En fonction du cahier des charges, générer un schéma de BDD avec PlantUML (plant text)
3. Créer un projet Symfony de 0 soit via Composer, soit via Symfony CLI, soit via un repo + image Docker
4. Créer les entités Doctrine en fonction du schéma de BDD
5. Générer les migrations et les exécuter pour créer la BDD
6. Créer des fixtures (php ou YAML) pour peupler la BDD avec des données de test
7. Générer un premier controller + générer un layout user (+ d'autres layout ? admin ? hôte ? autre ?)
8. Découper le template (avec de l'héritage + sous-templates/sous-composants)
9. Créer les controllers et 4 pages pour : 
   - Page d'accueil (listing des annonces) -> Repository
   - Page d'historique des reservations -> Repository
   - Page de détail d'une annonce -> Entité
   - Page de confirmation de reservation -> Entité


10. Faire l'authentification 
11. Bloquer l'accés à certaines pages en fonction des roles 
    - Page détail d'un logement : que ceux qui sont connectés
    - Pages admin : que ceux qui ont le role admin
12. Afficher dynamiquement un bouton pour se connecter si on est pas connecté
    + Un bouton pour se déconnecter si on est connecté
    + Un bouton 'interface admin' si on a le role admin
13. Afficher les réservations du user connecté sur la page d'historique des résas
14. Créer un CRUD pour ajouter des propriétés, des users, des résas + adapter les forms types pour retirer ce qui est pas utile + faire faire le design des cruds à l'IA
15. Ajouter les contraintes de validation sur les entités
16. Intro API Platform et normalisation/dénormalisation

<!-- Voir les Events ? Faire de l'Asynchrone ? Ajouter des commandes personnalisées ? Faire de services pour séparer le code ? Voir l'envoie de mail ? Faire des appels API avec HTTP Client ? Système de Traductions ? -->

## Étapes d'initialisation détaillées

### 1. Configuration des entités Doctrine, migrations et fixtures

```bash
# Installer les dépendances nécessaires
composer require symfony/orm-pack
composer require --dev symfony/maker-bundle orm-fixtures

# Configurer la connexion BDD dans .env (DATABASE_URL)
# Créer la base de données
php bin/console doctrine:database:create

# Générer une entité
php bin/console make:entity

# Générer la migration à partir des entités
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Créer une classe de fixtures
php bin/console make:fixtures

# Charger les fixtures en base
php bin/console doctrine:fixtures:load
```

### 2. Mise en place des contrôleurs, vues Twig et formulaires

```bash
# Installer Twig et le système de formulaires
composer require twig
composer require symfony/form symfony/validator

# Générer un contrôleur (crée aussi le template Twig associé)
php bin/console make:controller

# Générer un FormType pour une entité
php bin/console make:form

# Générer un CRUD complet (contrôleur + templates + formulaire)
php bin/console make:crud
```

### 3. Gestion de l'authentification et des autorisations par rôles

```bash
# Installer le composant de sécurité
composer require symfony/security-bundle

# Créer l'entité User
php bin/console make:user

# Générer le système d'authentification (formulaire de login)
php bin/console make:auth

# Créer la commande d'enregistrement / page d'inscription
php bin/console make:registration-form
```

- Définir les `access_control` et la hiérarchie des rôles dans `config/packages/security.yaml`.
- Restreindre l'accès dans les contrôleurs avec `#[IsGranted('ROLE_ADMIN')]` ou `$this->denyAccessUnlessGranted(...)`.
- Afficher conditionnellement les éléments dans Twig avec `{% if is_granted('ROLE_ADMIN') %}`.

### 4. Maîtrise des Repositories et requêtes personnalisées

- Chaque entité dispose d'un Repository généré automatiquement (`src/Repository/`).
- Écrire des méthodes personnalisées via le **QueryBuilder** (`createQueryBuilder`) ou en **DQL**.
- Injecter le Repository dans un contrôleur (autowiring) pour récupérer les données.
- Utiliser les méthodes intégrées : `find()`, `findOneBy()`, `findBy()`, `findAll()`.
