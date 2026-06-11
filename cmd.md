# Commandes du projet

> Toutes les commandes sont à exécuter depuis le conteneur Docker :
> `docker exec -it <container> sh` puis dans `/var/www/html`

---

## Rôles utilisateur

```bash
# Assigner un rôle
php bin/console app:user:role <email> <role>

# Retirer un rôle
php bin/console app:user:role <email> <role> --remove
```

Rôles disponibles : `ROLE_USER`, `ROLE_ADMIN`, `ROLE_CHEH`

```bash
# Exemples
php bin/console app:user:role alice@example.com ROLE_ADMIN
php bin/console app:user:role bob@example.com ROLE_CHEH
php bin/console app:user:role alice@example.com ROLE_ADMIN --remove
```

---

## Base de données

```bash
# Créer la base de données
php bin/console doctrine:database:create

# Générer une migration après modification d'une entité
php bin/console make:migration

# Appliquer les migrations
php bin/console doctrine:migrations:migrate

# Charger les fixtures (données de test)
php bin/console doctrine:fixtures:load

# Charger les fixtures sans confirmation
php bin/console doctrine:fixtures:load --no-interaction
```

---

## Génération de code (make)

```bash
# Créer une entité
php bin/console make:entity

# Créer un controller
php bin/console make:controller NomController

# Créer un formulaire
php bin/console make:form NomFormType

# Créer une commande console
php bin/console make:command app:nom:commande

# Créer une fixture
php bin/console make:fixtures NomFixtures
```

---

## Sécurité

```bash
# Voir les routes et leurs accès
php bin/console debug:router

# Voir la config du firewall
php bin/console debug:firewall
```

---

## Cache

```bash
# Vider le cache
php bin/console cache:clear

# Vider le cache en production
php bin/console cache:clear --env=prod
```

---

## Debug

```bash
# Lister toutes les commandes disponibles
php bin/console list

# Lister les commandes du projet
php bin/console list app

# Voir les services disponibles
php bin/console debug:container

# Voir toutes les routes
php bin/console debug:router

# Voir la config Twig
php bin/console debug:twig
```
