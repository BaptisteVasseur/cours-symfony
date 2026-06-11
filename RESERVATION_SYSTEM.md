# Système de Réservation et Gestion des Disponibilités - Documentation Technique

## Vue d'ensemble

Ce document décrit l'implémentation complète du moteur de réservation et du système de gestion des disponibilités pour le projet clone Airbnb, basé sur l'architecture Symfony existante et les spécifications du cahier des charges.

## 🏗️ Architecture implémentée

### 1. Entités (Entities)

#### **PropertyUnavailability** (Nouvelle)

Gère les périodes de blocage de dates définies par l'hôte.

**Champs:**

- `startDate`: Date début de l'indisponibilité
- `endDate`: Date fin de l'indisponibilité
- `reason`: Motif (`maintenance`, `personal_use`, `cleaning`, `owner_stay`, `other`)
- `notes`: Notes optionnelles
- `createdAt`, `updatedAt`: Timestamps

**Repository:** `PropertyUnavailabilityRepository`

- `findByProperty()`: Obtenir toutes les indisponibilités d'une propriété
- `hasUnavailabilityBetween()`: Vérifier s'il y a un blocage entre deux dates
- `findOverlappingUnavailability()`: Trouver les blocages qui chevauchent une plage

#### **PropertyICalToken** (Nouvelle)

Gère les tokens sécurisés pour l'export iCal des réservations.

**Champs:**

- `token`: String unique (généré aléatoirement avec `bin2hex(random_bytes(32))`)
- `createdAt`: Date de création
- `revokedAt`: Date de révocation (NULL = valide)
- `lastAccessedAt`: Dernière utilisation

**Repository:** `PropertyICalTokenRepository`

- `findValidToken()`: Récupérer un token non révoqué
- `findFirstValidToken()`: Obtenir le premier token valide d'une propriété
- `findValidTokensByProperty()`: Lister tous les tokens valides

#### Modification de Property

Ajout de collections pour les relations:

```php
/** @var Collection<int, PropertyUnavailability> */
private Collection $unavailabilities;

/** @var Collection<int, PropertyICalToken> */
private Collection $iCalTokens;
```

### 2. Services

#### **AvailabilityChecker** (Clé du système)

Implémente l'algorithme de vérification de disponibilité optimisé en une seule requête SQL.

**Logique de base (conception.txt IV):**

```
Une plage est disponible si et seulement si:
1. Le statut du logement est 'published'
2. La capacité est suffisante (maxGuests >= guestCount)
3. Aucun blocage d'indisponibilité
4. Aucune réservation 'confirmed' qui chevauche
```

**Méthodes principales:**

- `isAvailable()`: Vérification booléenne rapide
- `getAvailabilityDetails()`: Détails complets avec raisons et conflits
- `getBlockedDates()`: Lister tous les jours bloqués dans une plage
- `getNextAvailableDate()`: Trouver le prochain jour disponible

**Performance:**

- Utilise une seule requête SQL pour les chevauchements de réservations
- Évite les requêtes SQL itératives (conception.txt IV)

#### **ReservationManager**

Gère la state machine des réservations avec transitions d'état validées.

**État du cycle de vie:**

```
pending → confirmed → completed
   ↓
cancelled
```

**Règles:**

- `pending`: Demande en attente de validation de l'hôte
- `confirmed`: Dates verrouillées, paiement requis
- `cancelled`: Libère immédiatement les dates
- `completed`: Séjour terminé

**Méthodes:**

- `confirm()`: Passer `pending` → `confirmed`
- `reject()`: Passer `pending` → `cancelled` avec motif
- `cancel()`: Annuler une réservation à tout moment
- `complete()`: Marquer comme terminée (après checkout)

**Notifications:**
Envoie des messages Messenger pour les notifications asynchrones.

### 3. Repositories enrichis

#### **ReservationRepository** (Modifications)

Nouvelles méthodes d'optimisation SQL:

```php
countOverlappingReservations(Property, DateStart, DateEnd, ExcludeRes)
// Single SQL query pour compter les chevauchements

findOverlappingReservations(Property, DateStart, DateEnd, ExcludeRes)
// Récupère les réservations confirmées qui chevauchent

findPendingByProperty(Property)
// Pour le dashboard de modération

findByHostForListing(User)
// Toutes les réservations du logement de l'hôte
```

### 4. Controllers

#### **BookingController** (Amélioré)

- Route: `/logement/{id}/reserver`
- Utilise `AvailabilityChecker` pour validation en temps réel
- Dispatche `ReservationCreatedMessage` via Messenger

#### **SearchController** (Nouveau)

Route: `/search`

Filtrage avec paramètres GET:

- `destination`: Recherche textuelle (titre, ville, pays)
- `checkin` / `checkout`: Filtrage stricte des disponibilités (format: Y-m-d)
- `guests`: Exclusion des logements sous-dimensionnés

**Exemple URL:**

```
/search?destination=Paris&checkin=2026-07-10&checkout=2026-07-15&guests=2
```

#### **HostReservationController** (Nouveau)

Route racine: `/host/reservations`

**Actions:**

- `list`: Dashboard des réservations groupées par statut (pending/confirmed/completed/cancelled)
- `detail`: Vue détaillée avec actions (confirm/reject/cancel)

Nécessite verification d'ownership de la propriété.

#### **HostUnavailabilityController** (Nouveau)

Route racine: `/host/properties/{propertyId}/unavailability`

**Actions:**

- `list`: Lister les périodes d'indisponibilité
- `new`: Créer une nouvelle période de blocage
- `edit`: Modifier une période existante
- `delete`: Supprimer une période

**Motifs supportés:**

- `maintenance`: Travaux de maintenance
- `personal_use`: Utilisation personnelle
- `cleaning`: Nettoyage
- `owner_stay`: Séjour du propriétaire
- `other`: Autre motif

#### **HostICalTokenController** (Nouveau)

Route racine: `/host/properties/{propertyId}/ical-tokens`

**Actions:**

- `list`: Afficher les tokens actifs
- `new`: Générer un nouveau token
- `revoke`: Révoquer un token
- `viewUrl`: Afficher l'URL de flux iCal

**Format d'URL iCal:**

```
/api/properties/{propertyId}/calendar.ics?token={secureToken}
```

#### **PropertyICalController** (API, Nouveau)

Route: `/api/properties/{id}/calendar.ics`

Paramètres:

- `token`: Token sécurisé (obligatoire)

**Format de sortie iCal (RFC 5545):**

```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Clone Airbnb//FR
BEGIN:VEVENT
UID:res-{reservationId}@clone-airbnb.local
SUMMARY:{PropertyTitle} — {GuestName}
DTSTART;VALUE=DATE:{CheckinDate}
DTEND;VALUE=DATE:{CheckoutDate}
DESCRIPTION:{NightsCount} nuits — {TotalPrice}€ — {GuestEmail}
END:VEVENT
END:VCALENDAR
```

**Sécurité:**

- Token validation obligatoire
- Tokens révocables par l'hôte
- Tracking du `lastAccessedAt`
- Code HTTP 401 si token invalide

### 5. Forms

#### **PropertyUnavailabilityType** (Nouveau)

Champs:

- `startDate`: Date picker
- `endDate`: Date picker
- `reason`: Select (maintenance, personal_use, cleaning, owner_stay, other)
- `notes`: Textarea optionnel

## 📨 Système de notifications asynchrones (Messenger)

### Messages

**Créés par:**

- `BookingController` → `ReservationCreatedMessage` (après création)
- `ReservationManager.confirm()` → `ReservationConfirmedMessage`
- `ReservationManager.reject()` → `ReservationRejectedMessage`
- `ReservationManager.cancel()` → `ReservationCancelledMessage`

### MessageHandlers

**Chaque message a un handler dédié:**

1. **ReservationCreatedMessageHandler**
    - Si `pending`: Email à l'hôte pour approbation
    - Si `confirmed`: Email de confirmation au voyageur

2. **ReservationConfirmedMessageHandler**
    - Email de confirmation au voyageur
    - Email de notification à l'hôte

3. **ReservationRejectedMessageHandler**
    - Email au voyageur expliquant le refus

4. **ReservationCancelledMessageHandler**
    - Email au voyageur (motif)
    - Email à l'hôte (motif)

**Configuration Messenger (à ajouter dans `config/packages/messenger.yaml`):**

```yaml
framework:
    messenger:
        failure_transport: failed
        transports:
            async:
                dsn: "%env(MESSENGER_TRANSPORT_DSN)%"
            failed: "doctrine://default?queue_name=failed"
        routing:
            'App\Message\ReservationCreatedMessage': async
            'App\Message\ReservationConfirmedMessage': async
            'App\Message\ReservationRejectedMessage': async
            'App\Message\ReservationCancelledMessage': async
```

**Configuration Mailer (dans `.env`):**

```
MAILER_DSN=smtp://localhost:1025  # Mailpit en développement
```

## 📊 Flux de réservation complet

### Scénario 1: Réservation instantanée (instantBooking = true)

```
1. Voyageur sélectionne dates → BookingController
2. AvailabilityChecker valide disponibilité
3. Réservation créée avec status = 'confirmed'
4. ReservationCreatedMessage(isPending=false) → Messenger
5. Email de confirmation au voyageur
6. Dates verrouillées dans le calendrier
```

### Scénario 2: Réservation sur demande (instantBooking = false)

```
1. Voyageur sélectionne dates → BookingController
2. AvailabilityChecker valide disponibilité
3. Réservation créée avec status = 'pending'
4. ReservationCreatedMessage(isPending=true) → Messenger
5. Email à l'hôte pour approbation
6. Dates NON bloquées (conception.txt II - pending ne bloque pas)
7. Hôte accepte/refuse via HostReservationController
   a. Si accepte: ReservationManager.confirm()
      → ReservationConfirmedMessage → Emails de confirmation
   b. Si refuse: ReservationManager.reject(raison)
      → ReservationRejectedMessage → Email au voyageur
8. Dates verrouillées (si confirmée)
```

### Scénario 3: Annulation

```
1. Hôte ou voyageur demande annulation
2. ReservationManager.cancel(motif)
3. Status = 'cancelled'
4. Dates immédiatement libérées
5. ReservationCancelledMessage → Emails à tous
```

## 🔒 Gestion de la concurrence (conception.txt III)

**Problème:** Deux voyageurs valident un paiement simultanément.

**Solution implémentée:**

1. `AvailabilityChecker.countOverlappingReservations()` retourne le count
2. Si count > 0 avant insert → refus de la réservation
3. Transaction SQL garantit l'atomicité
4. La seconde requête sera rejetée avant la persistance

**Note:** Pour la vraie gestion de concurrence multi-milliseconde:

- Ajouter un verrou pessimiste avec `LockMode::PESSIMISTIC_WRITE` en Doctrine
- Ou implémenter une file d'attente avec priorité

## 📈 Performance (conception.txt IV)

**Optimisations réalisées:**

1. **Single SQL query pour vérifier les chevauchements**

    ```php
    // Une seule requête, pas de boucle
    SELECT COUNT(*) WHERE:
    - property = ?
    - status = 'confirmed'
    - checkinDate < ?checkoutDate
    - checkoutDate > ?checkinDate
    ```

2. **Eager loading des relations (N+1 prevention)**

    ```php
    // Dans les repositories
    ->addSelect('g', 'gp', 'p', 'a')
    ->leftJoin('r.guest', 'g')
    ```

3. **Requêtes séparées par responsabilité**
    - `countOverlappingReservations()`: Pour validation rapide
    - `findOverlappingReservations()`: Pour détails + conflits

## 🛠️ Installation et déploiement

### Prérequis

- PHP 8.4+ (Symfony 8.0)
- MySQL/PostgreSQL
- Composer
- Mailpit (development) ou SMTP (production)

### Étapes de déploiement

```bash
# 1. Installation des dépendances
composer install

# 2. Créer les tables (migrations)
php bin/console doctrine:migrations:migrate

# 3. Configuration Messenger
# - Ajouter config/packages/messenger.yaml
# - Configurer .env

# 4. Démarrer le worker Messenger (en production)
php bin/console messenger:consume async -vv

# 5. Vérifier les notifications avec Mailpit
# http://localhost:8025
```

### Variables d'environnement (.env)

```bash
# Messenger
MESSENGER_TRANSPORT_DSN=doctrine://default

# Email
MAILER_DSN=smtp://localhost:1025  # Dev
MAILER_DSN=smtp://user:pass@host:port  # Prod

# Database
DATABASE_URL="mysql://user:pass@host/dbname"
```

## 🎯 Cas d'utilisation et routes

### Voyageur

| Action            | Route                                                         | Méthode |
| ----------------- | ------------------------------------------------------------- | ------- |
| Rechercher        | `/search?destination=...&checkin=...&checkout=...&guests=...` | GET     |
| Réserver          | `/logement/{id}/reserver`                                     | POST    |
| Voir réservations | `/reservation/{id}`                                           | GET     |
| Annuler           | `/host/reservations/{id}`                                     | DELETE  |

### Hôte

| Action          | Route                                      | Méthode  |
| --------------- | ------------------------------------------ | -------- |
| Dashboard       | `/host/reservations`                       | GET      |
| Modérer demande | `/host/reservations/{id}`                  | POST     |
| Bloquer dates   | `/host/properties/{id}/unavailability/new` | POST     |
| Gérer tokens    | `/host/properties/{id}/ical-tokens`        | GET/POST |

### API

| Action      | Route                               | Paramètres  |
| ----------- | ----------------------------------- | ----------- |
| Export iCal | `/api/properties/{id}/calendar.ics` | token (GET) |

## 📋 Fichiers créés/modifiés

### Créés

```
src/Entity/PropertyUnavailability.php
src/Entity/PropertyICalToken.php
src/Repository/PropertyUnavailabilityRepository.php
src/Repository/PropertyICalTokenRepository.php
src/Service/AvailabilityChecker.php
src/Service/ReservationManager.php
src/Form/PropertyUnavailabilityType.php
src/Controller/Front/SearchController.php
src/Controller/Front/HostReservationController.php
src/Controller/Front/HostUnavailabilityController.php
src/Controller/Front/HostICalTokenController.php
src/Controller/Api/PropertyICalController.php
src/Message/ReservationMessages.php
src/MessageHandler/ReservationMessageHandlers.php
migrations/VersionXXXXXXXXXXXXXX.php (Auto-generée)
```

### Modifiés

```
src/Entity/Property.php
  - Ajout $unavailabilities
  - Ajout $iCalTokens
  - Getter/Setter correspondants

src/Repository/ReservationRepository.php
  - countOverlappingReservations()
  - findOverlappingReservations()
  - findPendingByProperty()
  - findByHostForListing()

src/Controller/Front/BookingController.php
  - Utilisation AvailabilityChecker
  - Dispatch ReservationCreatedMessage
```

## 🧪 Tests recommandés

### Unit Tests

- `AvailabilityChecker::isAvailable()`
- `ReservationManager` transitions d'état
- Logique de calcul de prix

### Integration Tests

- Création réservation → Vérification BD
- Flux complet reservations (pending → confirmed → completed)
- Endpoint iCal avec token

### E2E Tests (Seleniumou Playwright)

- Formulaire de recherche
- Flux de réservation complet
- Dashboard hôte (modération)

## 📚 Architecture de base de données

### Tables ajoutées

```sql
CREATE TABLE property_unavailability (
  id UUID PRIMARY KEY,
  property_id UUID NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason VARCHAR(50) NOT NULL,
  notes LONGTEXT,
  created_at DATETIME NOT NULL,
  updated_at DATETIME,
  FOREIGN KEY (property_id) REFERENCES properties(id)
);

CREATE TABLE property_ical_token (
  id UUID PRIMARY KEY,
  property_id UUID NOT NULL,
  token VARCHAR(255) UNIQUE NOT NULL,
  created_at DATETIME NOT NULL,
  revoked_at DATETIME,
  last_accessed_at DATETIME,
  FOREIGN KEY (property_id) REFERENCES properties(id)
);
```

### Indices recommandés

```sql
CREATE INDEX idx_unavailability_property ON property_unavailability(property_id);
CREATE INDEX idx_unavailability_dates ON property_unavailability(property_id, start_date, end_date);
CREATE INDEX idx_ical_token_property ON property_ical_token(property_id);
CREATE INDEX idx_ical_token_token ON property_ical_token(token);
CREATE INDEX idx_reservation_property_dates ON reservations(property_id, checkin_date, checkout_date);
```

## ⚠️ Limitation de capacité et gestion des dépassements

Voir conception.txt II: Les demandes `pending` ne bloquent PAS les dates. Cela permet:

- À l'hôte de voir les dates disponibles malgré les demandes
- D'accepter/refuser les demandes en connaissance de cause
- De gérer les conflits de date (deux demandes, une date)

## 🔍 Debugging

### Vérifier les tokens iCal

```bash
# En dev
docker-compose exec app php bin/console doctrine:query:sql "SELECT * FROM property_ical_token WHERE revoked_at IS NULL"
```

### Tester notifications

```bash
# Afficher les messages en queue
docker-compose exec app php bin/console debug:messenger

# Consommer les messages
docker-compose exec app php bin/console messenger:consume async -vv

# Vérifier les emails
# http://localhost:8025 (Mailpit)
```

### Logs

```bash
tail -f var/log/dev.log
tail -f var/log/prod.log
```

## 📖 Références

- [Conception.txt](../conception.txt) - Spécifications techniques
- [Cahier des charges](../Cahier%20des%20charges.md)
- [Doctrine Queries](https://www.doctrine-project.org/projects/doctrine-orm/en/2.12/reference/query-builder.html)
- [Symfony Messenger](https://symfony.com/doc/current/messenger.html)
- [iCalendar RFC 5545](https://tools.ietf.org/html/rfc5545)

## 📝 Notes de développement

### Choix d'architecture

1. **Pourquoi une seule table PropertyUnavailability au lieu de PropertyAvailability?**
    - Plus simple: on stocke ce qui est bloqué, pas chaque jour dispo
    - Mieux pour la performance (conception.txt VI)
    - Facile à requêter avec `BETWEEN`

2. **Pourquoi Messenger pour les emails?**
    - Asynchrone → Interface réactive
    - Persistant → Pas de perte en cas de panne (conception.txt V)
    - Retry automatique en cas d'erreur
    - Scalable pour de nombreux emails

3. **Pourquoi diviser les messages par action?**
    - Chaque message = chaque scénario de notification
    - Facile à tester et déboguer
    - Extensible pour d'autres notif (SMS, push)

### TODO Future

- [ ] Implémenter les lockMode pessimistes pour la concurrence réelle
- [ ] Ajouter synchronisation iCal en provenance (import)
- [ ] Historique des prix journaliers (dynamic pricing)
- [ ] Système de frais de nettoyage par période
- [ ] Calendrier graphique (Vue composant)
- [ ] API GraphQL pour recherche
- [ ] Caching des résultats de recherche
- [ ] Bulk operations pour l'hôte (bloquer plusieurs mois)

---

**Version:** 1.0  
**Date:** 2026-06-11  
**Auteur:** Développeur Symfony
