# Commandes du projet

> Toutes les commandes sont à exécuter depuis le conteneur Docker :
> `docker exec -it <container> sh` puis dans `/var/www/html`

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
php bin/console doctrine:fixtures:load --no-interaction

# Voir le statut des migrations
php bin/console doctrine:migrations:status
```

---

## Génération de code (make)

```bash
# Créer une entité (ou ajouter des champs à une existante)
php bin/console make:entity NomEntity

# Créer un controller
php bin/console make:controller Front/NomController

# Créer un formulaire
php bin/console make:form NomType

# Créer un message Messenger
php bin/console make:message NomMessage

# Créer un voter de sécurité
php bin/console make:voter NomVoter

# Créer une commande console
php bin/console make:command app:nom:commande

# Créer une fixture
php bin/console make:fixtures NomFixtures
```

---

## Messenger (emails asynchrones)

```bash
# Consommer la file de messages (emails)
php bin/console messenger:consume async

# Consommer avec logs verbeux
php bin/console messenger:consume async -vv

# Voir les messages en échec
php bin/console messenger:failed:show

# Rejouer les messages en échec
php bin/console messenger:failed:retry

# Purger les messages en échec
php bin/console messenger:failed:remove --all
```

> Mailpit disponible sur http://localhost:8025 pour voir les emails envoyés.

---

## Sécurité

```bash
# Voir les routes et leurs accès
php bin/console debug:router

# Voir la config du firewall
php bin/console debug:firewall

# Voir les voters actifs
php bin/console debug:container --tag=security.voter
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

# Voir les événements Doctrine
php bin/console debug:event-dispatcher doctrine
```

---

## Docker

```bash
# Lancer les conteneurs
docker compose up -d

# Rebuild + lancer
docker compose up --build -d php

# Voir les logs PHP
docker compose logs -f php

# Entrer dans le conteneur PHP
docker compose exec php sh

# Entrer dans PostgreSQL
docker compose exec database psql -U app -d app
```
