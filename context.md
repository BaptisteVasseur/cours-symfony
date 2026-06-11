# Contexte du projet — Moteur de Réservation & Calendrier iCal

## Objectif de l'évaluation

Implémenter de A à Z le **moteur de réservation** et la **gestion des disponibilités** d'un clone Airbnb
en Symfony 8. Le projet repose sur une base de code existante (entités, contrôleurs squelettes, templates)
qu'il faut compléter et brancher ensemble.

---

## Stack technique

| Couche       | Technologie                                    |
|-------------|------------------------------------------------|
| Backend     | PHP 8.4 + Symfony 8.0                          |
| ORM         | Doctrine ORM 3.6 + Migrations                  |
| Base de données | PostgreSQL 16 (Docker)                     |
| Frontend    | Twig + TailwindCSS 4 + Stimulus + UX Turbo     |
| Async       | Symfony Messenger (transport Doctrine en dev)  |
| Mails       | Symfony Mailer + Mailpit (test SMTP local)     |
| API         | API Platform 4.2                               |

---

## Ce qui est déjà fait

### Entités (31 au total — structure en place)

| Entité | État | Observations |
|--------|------|-------------|
| `Property` | Complet | Champ `instantBooking` présent (`bool`, défaut `false`) |
| `PropertyAvailability` | Complet | Modèle **jour par jour** (`availableDate: DATE`, `isAvailable: bool`, `priceOverride`, `minimumStay`) |
| `PropertyICalSync` | Complet | Pour l'import iCal (`iCalUrl`, `providerName`, `lastSyncAt`) |
| `Reservation` | Complet | Statuts, dates, calcul de prix, liens vers Property & User |
| `ReservationStatusHistory` | Complet | Trace les transitions de statut |
| `Notification` | Complet | Notifications in-app (en base) |
| `CancellationPolicy` | Complet | `refundPercentage` pour les remboursements |
| Toutes les autres | Complets | User, Review, Conversation, Payment, etc. |

**Point clé manquant sur `Property` :** le champ `calendarToken` pour sécuriser l'export iCal n'existe pas encore — il faut l'ajouter + migration.

### Contrôleurs existants

| Contrôleur | Chemin | État réel |
|-----------|--------|-----------|
| `BookingController::checkout()` | `POST /logement/{id}/reserver` | **Partiellement implémenté** — voir détail ci-dessous |
| `HomeController::search()` | `GET /search` | **Squelette** — parse les params GET mais ne filtre rien |
| `HomeController::detail()` | `GET /logement/{id}` | Fonctionnel |
| `AccountController` | `/compte/*` | Profil, settings, liste de propriétés |
| Admin controllers | `/admin/*` | CRUD complet Property, User, Reservation, Review, Report |

### Ce que fait déjà `BookingController::checkout()`

- ✅ Vérifie que le logement est `published`
- ✅ Bloque l'hôte de réserver son propre logement
- ✅ Valide `checkin < checkout`
- ✅ Vérifie `guestsCount <= maxGuests`
- ✅ Calcule le prix total (nuits × tarif + cleaningFee + 12% service fee)
- ✅ Définit le statut selon `instantBooking` (`confirmed` ou `pending`)
- ✅ Persiste la réservation
- ❌ **Pas de vérification de disponibilité réelle** (ni PropertyAvailability ni réservations confirmed existantes)
- ❌ **Pas d'enregistrement dans `ReservationStatusHistory`**
- ❌ **Pas de dispatch de notification email** (asynchrone)
- ❌ **Pas de gestion de concurrence** (pas de verrou)

### Ce que fait déjà `HomeController::search()`

- ✅ Parse `checkin`, `checkout`, `guests`, `destination` depuis les query params GET
- ❌ **Appelle juste `findForListing('published')` sans aucun filtre actif**
- La recherche n'est donc qu'un affichage de tous les logements publiés

### Repository `PropertyRepository`

Méthodes existantes : `findForListing()`, `findOneForDetail()`, `findByHost()`, `findPendingForModeration()`, `countAll()`, `countByStatus()`, `findMostPopular()`.

**Manque :** méthode `findAvailable()` filtrant par destination, dates et nombre de voyageurs.

---

## Ce qui est absent / à construire

### Services métier (inexistants)
- `AvailabilityService` — algorithme de vérification de disponibilité
- `BookingService` / `ReservationService` — orchestration des transitions de statut

### Fonctionnalités front manquantes
- Interface de gestion du calendrier hôte (vue mensuelle, blocage de dates)
- Dashboard hôte des demandes en attente (`pending`)
- Formulaire d'annulation avec motif obligatoire
- Moteur de recherche complet (filtres sur destination, dates, capacité)
- Calcul de prix dynamique en front (Stimulus/JS)

### Système de notifications email (Partie D)
- Classes Message Messenger (ex. `ReservationCreatedMessage`)
- Handlers correspondants (ex. `ReservationCreatedHandler`)
- Templates Twig pour les emails

### Export iCal (Partie E)
- Champ `calendarToken` sur `Property` + migration
- `ICalExportController` sur `/api/properties/{id}/calendar.ics?token={secret}`
- Génération du fichier `.ics` avec les réservations `confirmed`

### (BONUS) Import iCal (Partie F)
- Commande `app:ical:sync` (Symfony Console Command)
- HTTP Client pour récupérer l'URL externe
- Parsing du .ics → création de `PropertyAvailability` bloquées

### (BONUS) Fonctionnalités avancées (Partie G)
- G.1 Expiration auto des `pending` après 24h (Messenger Worker)
- G.2 Rappel check-in J-1 par email
- G.5 Timeline visuelle des changements de statut
- G.6 Tarification dynamique en front (JS réactif)
- G.8 Notifications in-app (cloche dans la nav, entité `Notification` déjà prête)

---

## Choix de modélisation déjà figés (à respecter)

### Modèle de disponibilité : jours individuels

L'entité `PropertyAvailability` utilise **un enregistrement par jour** (`availableDate: DATE`), et non une
plage de dates (`date_start` / `date_end`). Ce choix est déjà en base.

**Implications :**
- L'algorithme de disponibilité doit interroger une liste de jours bloqués pour la période demandée.
- La requête SQL pour valider une disponibilité sur N nuits est un `COUNT` sur les jours `isAvailable = false`
  dans la plage, combiné à un check sur les réservations `confirmed` qui se chevauchent.
- Le blocage d'une période hôte crée autant de lignes `PropertyAvailability` que de jours.

### Réservation instantanée

L'entité `Property` possède déjà `instantBooking: bool` (défaut `false`). Le `BookingController` l'utilise
déjà pour déterminer le statut initial. Il faut uniquement **ajouter le check de disponibilité réelle** avant.

---

## Questions de conception à trancher (pour conception.txt)

1. **Modélisation temporelle** : le jour de checkout est-il réservable par un nouveau voyageur ?
2. **Statut Pending** : bloque-t-il les dates ou non ? (risque : blocage abusif vs overbooking)
3. **Concurrence** : que se passe-t-il si deux utilisateurs réservent simultanément les mêmes dates ?
4. **Performance** : comment valider 20 nuits consécutives sans N requêtes SQL ?
5. **Asynchronisme** : pourquoi l'email doit-il être envoyé après le `flush()` ?
6. **Structure SQL** : justifier le choix jours individuels vs périodes (déjà tranché, à argumenter)

---

## Contrainte importante sur conception.txt

Le sujet indique explicitement que **`conception.txt` doit être issu de ta réflexion personnelle**.
L'IA peut aider à structurer les questions mais les réponses argumentées doivent venir de toi.
