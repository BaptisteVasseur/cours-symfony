# 🗓️ Feuille de route - Post PHP 8.4

Ce document liste toutes les étapes pour finaliser le déploiement après installation de PHP 8.4.

## Phase 1: Installation PHP 8.4 (30 minutes)

### Windows (Local Development)

```powershell
# 1. Télécharger PHP 8.4
# https://windows.php.net/download/

# 2. Extraire dans C:\php84\
# Renommer le dossier selon version

# 3. Mettre à jour php.ini
# - Décommenter extensions nécessaires:
#   extension=pdo_mysql
#   extension=pdo_pgsql
#   extension=openssl
#   extension=curl
#   extension=gd

# 4. Tester la version
php -v
# Doit afficher: PHP 8.4.x

# 5. Vérifier le chemin dans PATH
php -r "echo PHP_EXECUTABLE;"
# Doit pointer vers C:\php84\php.exe
```

### Docker (Recommandé)

```dockerfile
# Dockerfile.dev - Remplacer la version

FROM php:8.4-fpm

# Installer les extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql
```

---

## Phase 2: Composer Update (10 minutes)

```bash
# Basculer sur PHP 8.4
# (Vérifier: php -v)

cd /path/to/cours-symfony

# Mettre à jour les dépendances
composer update

# ✅ Succès attendu: Toutes les dépendances Symfony 8.0 téléchargées
```

---

## Phase 3: Générer les migrations (5 minutes)

### Créer les migrations pour les 2 nouvelles entités

```bash
# Symfony analysera les entités et créera les migrations
php bin/console make:migration

# Vérifier les fichiers créés
ls -la migrations/

# Output attendu:
# Version20260611153000.php  ← Migration pour PropertyUnavailability + ICalToken
# Version20260610153000.php  ← Existant
```

### Réviser la migration (2 minutes)

```bash
# Ouvrir le fichier généré et vérifier
cat migrations/Version20260611153000.php

# À chercher:
# - CREATE TABLE property_unavailability
# - CREATE TABLE property_ical_token
# - INDEX sur property_id + dates
# - FOREIGN KEY vers property
```

**✅ Exemple de migration complète:**

```sql
-- Basé sur PropertyUnavailability entity
CREATE TABLE property_unavailability (
    id UUID NOT NULL,
    property_id UUID NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(50) NOT NULL,
    notes LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY(id),
    KEY IDX_7B9F6E64549213EC (property_id),
    CONSTRAINT FK_7B9F6E64549213EC FOREIGN KEY (property_id)
        REFERENCES property (id) ON DELETE CASCADE
);

CREATE TABLE property_ical_token (
    id UUID NOT NULL,
    property_id UUID NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    revoked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    last_accessed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY(id),
    UNIQUE KEY UNIQ_5E7D3D575F37A13B (token),
    KEY IDX_5E7D3D57549213EC (property_id),
    CONSTRAINT FK_5E7D3D57549213EC FOREIGN KEY (property_id)
        REFERENCES property (id) ON DELETE CASCADE
);
```

---

## Phase 4: Appliquer les migrations (5 minutes)

### Option A: Migration interactive

```bash
php bin/console doctrine:migrations:migrate

# Prompts:
# "WARNING! You are about to execute a migration... Continue?"
# Répondre: yes
```

### Option B: Migration silencieuse

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

### Option C: Avec rollback (développement)

```bash
# Voir les migrations
php bin/console doctrine:migrations:list

# Executer jusqu'à une migration spécifique
php bin/console doctrine:migrations:migrate "20260611153000"

# Rollback de la dernière
php bin/console doctrine:migrations:migrate "prev"
```

### Vérifier la base de données

```bash
# Mysql
mysql -u root -p cours_symfony -e "SHOW TABLES;" | grep property

# Output attendu:
# property
# property_ical_token         ← Nouvelle
# property_unavailability     ← Nouvelle
# ...
```

---

## Phase 5: Configurer Messenger (15 minutes)

### Créer le fichier de configuration

```bash
# Copier la configuration template
cp config/packages/messenger.yaml.stub config/packages/messenger.yaml

# Éditer le fichier pour l'environnement
```

### Configuration pour développement

```yaml
# config/packages/messenger.yaml

framework:
    messenger:
        # Configuration DNS pour les transports
        transports:
            async:
                # Mode développement: Base de données
                dsn: "%env(MESSENGER_TRANSPORT_DSN)%"

                # Options de retry
                options:
                    use_notify: true
                    max_retries: 3
                    multiplier: 2
                    delay: 100
                    max_delay: 0

        routing:
            # Routes les messages vers le transport async
            'App\Message\ReservationCreatedMessage': async
            'App\Message\ReservationConfirmedMessage': async
            'App\Message\ReservationRejectedMessage': async
            'App\Message\ReservationCancelledMessage': async
```

### Configuration pour production

```yaml
# config/packages/messenger.yaml (production)

framework:
  messenger:
    transports:
      async:
        # Mode production: Queue worker externe
        # Option 1: RabbitMQ
        dsn: 'amqp://guest:guest@localhost:5672/%2f/reservation_queue'

        # Option 2: Redis
        dsn: 'redis://localhost:6379/messages'

        options:
          max_retries: 5
          multiplier: 3
          delay: 1000
```

### Configurer les variables d'environnement

```bash
# .env.local

# Mode développement
MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=async

# Mode production (RabbitMQ)
# MESSENGER_TRANSPORT_DSN=amqp://user:pass@rabbitmq:5672/%2f/queue

# Email configuration
MAILER_DSN=smtp://mailpit:1025
MAILER_FROM="noreply@airbnb-clone.local"
```

---

## Phase 6: Générer les données de test (5 minutes)

```bash
# Charger les fixtures (données de test)
php bin/console doctrine:fixtures:load --no-interaction

# Output attendu:
# Loaded 10 users
# Loaded 5 properties
# Loaded 15 reservations
# ...
```

---

## Phase 7: Démarrer Messenger Worker (déploiement)

### Pour développement

**Terminal 1 - Serveur web:**

```bash
cd cours-symfony
php -S 127.0.0.1:8000

# Acceder à http://localhost:8000
```

**Terminal 2 - Serveur Messenger:**

```bash
cd cours-symfony

# Mode verbose (affiche chaque message traité)
php bin/console messenger:consume async -vv

# Mode standard
php bin/console messenger:consume async

# Mode avec limites
php bin/console messenger:consume async --limit=100 --time-limit=3600
```

### Pour production

```bash
# Démarrer avec supervisor (Linux)
[program:messenger]
command=php /path/to/cours-symfony/bin/console messenger:consume async --time-limit=3600
autorestart=true
stdout_logfile=/var/log/messenger.log

# Redémarrer supervisor
sudo supervisorctl restart messenger

# Ou avec systemd
systemctl restart symfony-messenger
```

---

## Phase 8: Configurer les emails (Mailpit)

### Pour développement

```bash
# Lancer Mailpit (Docker)
docker run -p 8025:8025 -p 1025:1025 axllent/mailpit

# Accedez à l'interface: http://localhost:8025
```

### Vérifier la configuration dans .env.local

```env
MAILER_DSN=smtp://localhost:1025
MAILER_FROM="noreply@airbnb-clone.local"
```

### Tester l'envoi d'email

```bash
# Créer une réservation de test
# → Email s'affichera dans Mailpit

# Ou test manuel
php bin/console symfony:mailer:test noreply@airbnb-clone.local
```

---

## Phase 9: Exécuter les tests (30 minutes)

```bash
# Tests unitaires uniquement
php bin/phpunit tests/Service/
php bin/phpunit tests/Repository/

# Tests d'intégration
php bin/phpunit tests/Controller/

# Tous les tests avec couverture
php bin/phpunit --coverage-html=coverage/

# Ouvrir le rapport
open coverage/index.html
```

**Objectif:** >85% de couverture

---

## Phase 10: Valider en développement (1-2 heures)

### Checklist de validation

#### Recherche

- [ ] `/search` sans filtres affiche tous les logements
- [ ] Filtre par destination fonctionne
- [ ] Filtre par dates et guests fonctionne
- [ ] Logements indisponibles sont exclus

#### Booking

- [ ] Créer réservation "instantanée"
- [ ] Créer réservation "sur demande"
- [ ] Vérifier prix total et calcul
- [ ] Email reçu par hôte si "sur demande"

#### Dashboard hôte

- [ ] Voir réservations par status
- [ ] Accepter une demande
- [ ] Refuser une demande avec motif
- [ ] Annuler une réservation avec motif
- [ ] Vérifier emails envoyés

#### Gestion indisponibilités

- [ ] Créer blocage (travaux, perso, etc.)
- [ ] Modifier blocage
- [ ] Supprimer blocage
- [ ] Vérifier que les dates bloquées sont indisponibles

#### Tokens iCal

- [ ] Générer un token
- [ ] Copier l'URL
- [ ] Ajouter dans Google Calendar
- [ ] Vérifier que les réservations apparaissent
- [ ] Révoquer le token
- [ ] Vérifier que l'accès est refusé (401)

---

## Phase 11: Préparer la production (2-3 heures)

### Infrastructure

```
- [ ] PHP 8.4 installé sur serveur
- [ ] Base de données PostgreSQL (recommandé)
- [ ] Redis pour cache/sessions
- [ ] RabbitMQ ou Redis pour Messenger
- [ ] SMTP configuré (SendGrid, AWS SES, etc.)
- [ ] Domaine SSL configuré
- [ ] Logs centralisés (ELK, Datadog)
```

### Configuration

```
- [ ] .env.prod configuré (DATABASE_URL, etc.)
- [ ] Clés secrètes générées (APP_SECRET)
- [ ] Mailer configuré (service SMTP)
- [ ] Messenger transport configuré (RabbitMQ/Redis)
- [ ] Cache configuré (Redis)
```

### Sécurité

```
- [ ] HTTPS/SSL certificat valide
- [ ] CORS configuré (si API)
- [ ] Rate limiting activé
- [ ] CSRF protection activé
- [ ] Security headers configurés
- [ ] Secrets sensibles en variables d'env
```

### Performance

```
- [ ] Opcache PHP activé
- [ ] Cache Symfony activé
- [ ] Indices database créés
- [ ] CDN configuré (pour assets)
- [ ] Compression gzip/brotli
```

### Monitoring

```
- [ ] Application healthcheck endpoint
- [ ] Logs centralisés
- [ ] Monitoring des jobs Messenger
- [ ] Alertes configurées
- [ ] Backup database automatique
```

---

## Phase 12: Déploiement production

### Déploiement initial

```bash
# Sur le serveur
git clone https://github.com/yourorg/cours-symfony.git
cd cours-symfony

# Installer les dépendances
composer install --no-dev --optimize-autoloader

# Générer les assets
php bin/console asset-map:compile

# Migrer la base
php bin/console doctrine:migrations:migrate --no-interaction

# Vider le cache
php bin/console cache:clear --env=prod

# Démarrer les workers
systemctl restart symfony-messenger
```

### Vérifier le déploiement

```bash
# Health check
curl https://airbnb-clone.production/healthcheck

# Application
curl https://airbnb-clone.production/

# API iCal (avec token valide)
curl "https://airbnb-clone.production/api/properties/123/calendar.ics?token=abc123"
```

---

## Priorités

### Critique (jour 1)

1. ✅ PHP 8.4 installé et configuré
2. ✅ Migrations appliquées
3. ✅ Messenger worker démarré
4. ✅ Mailer configuré (Mailpit développement)

### Important (jour 2-3)

5. ✅ Tests exécutés (>85% coverage)
6. ✅ Dashboard hôte validé
7. ✅ iCal export testé
8. ✅ Notifications emails vérifiées

### Avant production (semaine 1)

9. ✅ Load testing
10. ✅ Sécurité review
11. ✅ Performance tuning
12. ✅ Monitoring setup

---

## Timeline estimée

| Phase            | Durée    | Status |
| ---------------- | -------- | ------ |
| PHP 8.4 install  | 30 min   | ⏳     |
| Composer update  | 10 min   | ⏳     |
| Migrations       | 5 min    | ⏳     |
| Messenger config | 15 min   | ⏳     |
| Tests            | 30 min   | ⏳     |
| Validation       | 2h       | ⏳     |
| Production setup | 3h       | ⏳     |
| **TOTAL**        | **≈ 6h** | **⏳** |

---

## Support

**Documentation:**

- 📖 [RESERVATION_SYSTEM.md](RESERVATION_SYSTEM.md)
- 🚀 [DEPLOYMENT_INSTRUCTIONS.md](DEPLOYMENT_INSTRUCTIONS.md)
- 🏠 [HOST_DASHBOARD_GUIDE.md](HOST_DASHBOARD_GUIDE.md)
- 🧪 [TESTS_RECOMMENDATIONS.md](TESTS_RECOMMENDATIONS.md)

**Code source:** Tous les fichiers commentés et documentés

**Questions?** Consulter le `conception.txt` pour le contexte architectural

---

**Version:** 1.0  
**Date:** 11 juin 2026  
**Status:** 📋 À implémenter après PHP 8.4
