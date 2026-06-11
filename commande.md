# Commandes pour lancer le projet en local

## Prérequis

- Docker Desktop installé et démarré
- Git
- Make (inclus sur Linux/Mac, sur Windows : installer via Chocolatey)

---

## Premier lancement (après avoir cloné le repo)

```bash
git clone https://github.com/massinissabencherif/cours-symfony.git
cd cours-symfony

# Copier le fichier d'environnement
cp .env .env.local

# Build et démarrage de tous les conteneurs
make install

# Entrer dans le conteneur PHP
make sh

# Dans le conteneur : installer les dépendances
composer install

# Créer la base de données et appliquer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# Charger les données de test (fixtures)
php bin/console doctrine:fixtures:load --no-interaction

# Vider le cache
php bin/console cache:clear

# Quitter le conteneur
exit
```

---

## URLs disponibles

| Service    | URL                        |
|------------|----------------------------|
| Application | http://localhost:8089      |
| Adminer (BDD) | http://localhost:8088    |
| Mailpit (emails) | http://localhost:8025 |
| PostgreSQL | port 5439                  |

---

## Comptes de test

| Rôle   | Email                    | Mot de passe |
|--------|--------------------------|--------------|
| Admin  | admin@airbnb-clone.fr    | password     |
| Hôte   | host@example.com         | password     |
| Voyageur | test@example.com       | password     |

---

## Commandes quotidiennes

```bash
# Démarrer les conteneurs
make up

# Arrêter les conteneurs
make down

# Redémarrer
make restart

# Vider le cache Symfony
make cache

# Voir les logs PHP en temps réel
make logs

# Ouvrir un shell dans le conteneur PHP
make sh
```

---

## Worker Messenger (emails async)

Le worker tourne dans un conteneur dédié `cours-symfony-messenger-worker-1`.

```bash
# Vérifier son état
docker ps | grep worker

# Le redémarrer si nécessaire (ex : après un cache:clear)
docker exec php php bin/console cache:warmup
docker restart cours-symfony-messenger-worker-1
```

---

## Remise à zéro complète de la base

```bash
make sh

php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction

exit
```
