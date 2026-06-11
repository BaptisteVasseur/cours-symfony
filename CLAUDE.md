# HomeStay Clone — Symfony 8 / PHP 8.4

Airbnb-type rental platform API built with Symfony 8, API Platform 4, and PostgreSQL 16. All runtime tooling (PHP, Composer, Doctrine) lives inside Docker — never run it locally.

## Stack

| Layer | Technology |
|---|---|
| Framework | Symfony 8.0 |
| PHP | 8.4 |
| Database | PostgreSQL 16 |
| ORM | Doctrine ORM 3.6 + Migrations 4.0 |
| API | API Platform 4.2 |
| Messaging | Symfony Messenger (Doctrine transport) |
| Mailer | Symfony Mailer + Mailpit |
| Tests | PHPUnit 12.5 |

## Docker — règle absolue

`vendor/` et `var/` sont des **named volumes Docker**, pas des bind mounts. Ils sont vides localement. **Toute commande PHP/Composer doit être exécutée dans le conteneur.**

```bash
# Commandes Symfony
docker exec cours-symfony-php-1 php bin/console <commande>

# Composer
docker exec cours-symfony-php-1 composer <commande>

# Shell interactif
docker exec -it cours-symfony-php-1 sh
```

### Services et ports locaux

| Service | URL / Port |
|---|---|
| App PHP | http://localhost:8089 |
| PostgreSQL | localhost:5439 |
| Adminer | http://localhost:8088 |
| Mailpit (web) | http://localhost:8025 |
| Mailpit (SMTP) | localhost:1025 |

### Démarrage

```bash
docker compose up -d
# L'entrypoint exécute automatiquement : composer install, cache:warmup, migrations
```

## Base de données

```bash
# Créer une migration après modification d'entité
docker exec cours-symfony-php-1 php bin/console doctrine:migrations:diff

# Appliquer les migrations
docker exec cours-symfony-php-1 php bin/console doctrine:migrations:migrate --no-interaction

# Valider le mapping
docker exec cours-symfony-php-1 php bin/console doctrine:schema:validate
```

**Note** : La table `user` est un mot réservé PostgreSQL. Elle porte l'attribut `#[ORM\Table(name: '`user`')]` avec des backticks — ne pas supprimer.

## Entités Doctrine

### Conventions obligatoires
- Nom de classe : **anglais, singulier, PascalCase** (`ListingPhoto`, `UserIdentity`)
- Nom de propriété : **anglais, singulier, camelCase** (`pricePerNight`, `bookingStatus`)
- Clé primaire : **UUID** via `symfony/uid` + `UuidGenerator`

### Template de base pour une nouvelle entité

```php
#[ORM\Entity(repositoryClass: XxxRepository::class)]
class Xxx
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;
}
```

### Entités existantes (22)

**Core** : `User`, `Listing`, `ListingPhoto`, `ListingAvailability`, `ListingLocation`, `Amenity`

**Booking** : `Booking`, `Payment`, `Payout`

**Social** : `Review`, `ReviewPhoto`, `Conversation`, `Message`, `Notification`, `Wishlist`, `Report`

**Admin** : `AdminAction`

**Auth** : `RefreshToken`, `EmailVerification`, `PasswordReset`, `UserIdentity`, `AuthProvider`

### Pivot tables (ManyToMany — pas d'entité séparée)

| Table SQL | Relation |
|---|---|
| `listing_amenities` | `Listing` ↔ `Amenity` |
| `conversation_participants` | `Conversation` ↔ `User` |
| `wishlist_items` | `Wishlist` ↔ `Listing` |

### Use statements dans `App\Entity`
Les entités dans le même namespace (`App\Entity`) n'ont **pas besoin** de `use App\Entity\Xxx`. PHP les résout automatiquement. Seuls les imports externes sont nécessaires (`Doctrine\ORM\Mapping`, `Symfony\Component\Uid\Uuid`, etc.).

## Tests

```bash
docker exec cours-symfony-php-1 php bin/phpunit
```

## Fixtures

```bash
docker exec cours-symfony-php-1 php bin/console doctrine:fixtures:load --no-interaction
```

## Variables d'environnement clés

Définies dans `compose.yaml` (injectées dans le conteneur, pas dans `.env` local) :

```
DATABASE_URL=postgresql://app:my-super-secret-password@database:5432/app?serverVersion=16&charset=utf8
MESSENGER_TRANSPORT_DSN=doctrine://default
MAILER_DSN=smtp://mailer:1025
```
