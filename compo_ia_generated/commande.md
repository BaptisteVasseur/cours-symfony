# Lancer le projet en local

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installé et démarré
- Git

---

## 1. Cloner le dépôt

```bash
git clone <url-du-repo>
cd cours-symfony
```

---

## 2. Premier lancement (build + démarrage)

```bash
make install
```

Cette commande :
- Build l'image Docker PHP
- Démarre tous les conteneurs (PHP, PostgreSQL, Adminer, Mailpit, Messenger worker)
- L'entrypoint exécute automatiquement : `composer install`, `importmap:install`, `cache:warmup`, les migrations

> **Attendre ~30–60 secondes** que la base de données soit prête et que le serveur PHP démarre.

---

## 3. Charger les données de test (fixtures)

```bash
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

Cela crée ~26 propriétés, des utilisateurs, des réservations et des avis de démo.

---

## 4. Accéder à l'application

| Service | URL |
|---|---|
| Application web | http://localhost:8089 |
| Interface BDD (Adminer) | http://localhost:8088 |
| Emails (Mailpit) | http://localhost:8025 |

---

## Comptes de test

| Rôle | Email | Mot de passe |
|---|---|---|
| Admin | `admin@airbnb-clone.fr` | `password` |
| Utilisateur hôte | `test@example.com` | `password` |

---

## Commandes utiles au quotidien

```bash
# Démarrer les conteneurs (après le premier install)
make up

# Arrêter les conteneurs
make down

# Ouvrir un shell dans le conteneur PHP
make sh

# Vider le cache Symfony
make cache

# Suivre les logs du conteneur PHP
make logs
```

---

## Si le messenger worker s'arrête

Le worker Symfony Messenger s'arrête automatiquement toutes les heures (time-limit=3600). Pour le relancer :

```bash
docker compose restart messenger-worker
```

---

## Relancer depuis zéro (reset complet de la BDD)

```bash
docker compose exec php php bin/console doctrine:schema:drop --force
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```
