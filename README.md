# StayShare - Symfony

Projet Symfony de location de logements avec recherche, reservations, calendrier hote, notifications, emails et export iCal.

## Prerequis

- Docker et Docker Compose
- Make, optionnel mais recommande

Le projet tourne principalement via Docker. Les ports utilises sont :

- Application : http://localhost:8089
- Adminer : http://localhost:8088
- Mailpit : http://localhost:8025
- PostgreSQL local : `127.0.0.1:5439`

## Lancer le projet

Premier lancement :

```bash
make install
```

Ou sans Make :

```bash
docker compose up -d --build
```

Lancements suivants :

```bash
make up
```

Arreter le projet :

```bash
make down
```

Voir les logs PHP :

```bash
make logs
```

Entrer dans le conteneur PHP :

```bash
make sh
```

## Base de donnees

Les commandes Symfony se lancent dans le conteneur PHP :

```bash
docker compose exec php php bin/console doctrine:migrations:migrate
```

Si besoin de verifier les migrations sans appliquer :

```bash
docker compose exec php php bin/console doctrine:migrations:status
```

## Fixtures

Pour charger les donnees de test :

```bash
docker compose exec php php bin/console doctrine:fixtures:load
```

Attention : cette commande vide les tables avant de recharger les donnees.

Comptes de test :

```text
Hote      : hote@stayshare.test
Voyageur  : voyageur@stayshare.test
Admin     : admin@stayshare.test
Mot de passe : Password123!
```

## Emails et worker

Les emails sont envoyes en asynchrone avec Messenger. Le service `messenger-worker` est deja declare dans Docker Compose.

Verifier que le worker tourne :

```bash
docker compose ps
```

Lancer ou relancer les services, worker inclus :

```bash
docker compose up -d
```

Lancer le worker manuellement si besoin :

```bash
docker compose exec php php bin/console messenger:consume async -vv
```

Voir les messages en attente :

```bash
docker compose exec php php bin/console messenger:stats
```

Les emails arrivent dans Mailpit : http://localhost:8025

## Commandes utiles

Vider le cache Symfony :

```bash
make cache
```

Lister les routes :

```bash
docker compose exec php php bin/console debug:router
```

Verifier les templates Twig :

```bash
docker compose exec php php bin/console lint:twig templates
```

Verifier la syntaxe PHP d'un fichier :

```bash
php -l src/Controller/LogementController.php
```

## Infos generales

- La recherche de logements filtre par destination, dates et nombre de voyageurs.
- Le voyageur peut voir les disponibilites sur la fiche logement avant de reserver.
- L'hote peut gerer son calendrier, bloquer des dates et rouvrir des disponibilites.
- Les reservations confirmees alimentent l'export iCal.
- Les notifications internes sont visibles dans l'application, les emails passent par Messenger et Mailpit.
