# Plan d'implémentation — Moteur de Réservation & Calendrier iCal

> Ordre strict : chaque étape dépend des précédentes. Ne pas sauter d'étape.

---

## ÉTAPE 0 — Conception (OBLIGATOIRE avant tout code)

**Livrable :** `conception.txt` à la racine du projet.

Répondre de façon argumentée (1-2 pages) aux 6 questions du sujet :

1. **Modélisation temporelle** — le jour de checkout est-il réservable par un autre voyageur ?
   Gérer le cas départ 11h / arrivée 15h dans le schéma de données.
2. **Gestion des états** — une demande `pending` bloque-t-elle les dates ?
   Analyser les risques : blocage abusif (hôte qui n'accepte pas) vs overbooking.
3. **Concurrence** — que se passe-t-il si deux utilisateurs paient en même milliseconde pour les mêmes dates ?
   Penser aux verrous SQL (SELECT FOR UPDATE, contrainte UNIQUE, transaction).
4. **Performance** — comment valider la disponibilité sur 20 nuits sans N requêtes SQL itératives ?
   Penser à une requête agrégée COUNT ou à une jointure sur plage de dates.
5. **Asynchronisme** — pourquoi envoyer l'email après le `flush()` ? Que faire si Messenger tombe ?
6. **Structure SQL** — justifier le modèle jours individuels (`PropertyAvailability` avec `availableDate`)
   déjà en place dans le code vs une table de périodes (`date_start`, `date_end`).

---

## ÉTAPE 1 — Audit et mise en conformité du modèle de données

**Vérifier :**
- [ ] Lire `src/Entity/Property.php` → `instantBooking` est présent ✅
- [ ] Lire `src/Entity/PropertyAvailability.php` → modèle jours individuels ✅
- [ ] Lire `src/Entity/PropertyICalSync.php` → pour l'import ✅
- [ ] Lire `src/Entity/Reservation.php` → statuts, dates, prix ✅

**À ajouter :**
- [ ] Ajouter le champ `calendarToken` (string, nullable, unique) à `Property` pour l'export iCal sécurisé
- [ ] Générer la migration Doctrine correspondante
- [ ] Vérifier que les migrations existantes passent sur un environnement propre

**Commandes :**
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console hautelook:fixtures:load  # vérifier que les fixtures chargent encore
```

---

## ÉTAPE 2 — AvailabilityService (cœur du moteur)

**Fichier :** `src/Service/AvailabilityService.php`

**Responsabilités :**
1. `isAvailable(Property $property, DateTimeImmutable $checkin, DateTimeImmutable $checkout, int $guests): bool`
   Applique les 4 règles du sujet :
   - Le logement est `published`
   - Aucun jour de la période n'est dans `PropertyAvailability` avec `isAvailable = false`
   - Aucune réservation `confirmed` ne se chevauche (la réponse à la question de conception : `pending` bloque ou pas ?)
   - `guests <= maxGuests`

2. `getBlockedDates(Property $property, int $year, int $month): array`
   Retourne les jours bloqués pour l'affichage du calendrier.

**Repository à créer/compléter :**
- `PropertyAvailabilityRepository::findBlockedInRange()` — requête agrégée sur une plage de dates
- `ReservationRepository::findOverlapping()` — requête avec chevauchement de dates

**Algorithme de chevauchement :**
```
Deux réservations se chevauchent si :
  checkin_A < checkout_B ET checkout_A > checkin_B
```

---

## ÉTAPE 3 — Compléter BookingController (Partie B.1)

**Fichier :** `src/Controller/Front/BookingController.php`

**Ce qui manque actuellement :**
- [ ] Appel à `AvailabilityService::isAvailable()` avant de créer la réservation
- [ ] Gestion de la concurrence (transaction + verrou au niveau base de données)
- [ ] Création d'une entrée dans `ReservationStatusHistory` à la création
- [ ] Dispatch d'un message Messenger (`ReservationCreatedMessage`) après le `flush()`
- [ ] Redirection vers une route `app_reservation_show` (vérifier qu'elle existe)

**Ordre des opérations dans le contrôleur :**
1. Valider le formulaire
2. Appeler `AvailabilityService::isAvailable()` → si false, flash erreur + re-render
3. Démarrer une transaction Doctrine
4. Persister `Reservation`
5. Créer `ReservationStatusHistory` (initial state)
6. `flush()` dans la transaction
7. Dispatcher le message async email
8. Flash success + redirect

---

## ÉTAPE 4 — Moteur de recherche (Partie C)

**Fichier :** `src/Repository/PropertyRepository.php` + `src/Controller/Front/HomeController.php`

**Ajouter dans `PropertyRepository` :**
```php
findAvailable(
    ?string $destination,
    ?\DateTimeImmutable $checkin,
    ?\DateTimeImmutable $checkout,
    ?int $guests
): array
```

**Filtres à implémenter :**
1. `destination` → `LIKE` sur `a.city` ou `a.fullAddress` (jointure avec `PropertyAddress`)
2. `checkin`+`checkout` → sous-requête ou NOT EXISTS sur les réservations `confirmed` qui se chevauchent
   + sous-requête sur `PropertyAvailability` avec `isAvailable = false` dans la plage
3. `guests` → `p.maxGuests >= :guests`

**Mettre à jour `HomeController::search()` :**
- Remplacer `findForListing('published')` par `findAvailable(...)` avec les paramètres GET

---

## ÉTAPE 5 — Interface de gestion du calendrier hôte (Partie A.1)

**Nouveaux fichiers :**
- `src/Controller/Front/CalendarController.php`
- `templates/front/calendar/index.html.twig`

**Routes à créer :**
- `GET /compte/calendrier/{propertyId}` → vue mensuelle des disponibilités
- `POST /compte/calendrier/{propertyId}/bloquer` → créer des `PropertyAvailability` avec `isAvailable=false`
- `DELETE /compte/calendrier/{propertyId}/debloquer/{availabilityId}` → supprimer ou remettre à `true`

**Sécurité :**
- Vérifier que l'utilisateur courant est bien l'hôte du logement (Voter ou check manuel)

**Vue mensuelle :**
- Appeler `AvailabilityService::getBlockedDates()` + récupérer les réservations `confirmed` du mois
- Rendre un calendrier HTML (grid CSS) avec des états colorés : libre / bloqué manuellement / réservé

---

## ÉTAPE 6 — Dashboard hôte de modération (Partie B.2)

**Fichier :** `src/Controller/Front/ReservationController.php` (ou nouveau `HostDashboardController`)

**Routes à créer :**
- `GET /compte/demandes` → liste des réservations `pending` pour les logements de l'hôte
- `POST /compte/demandes/{id}/accepter` → `pending → confirmed` + history + email async
- `POST /compte/demandes/{id}/refuser` → `pending → cancelled` (motif requis) + history + email async

**Logique de transition :**
- Déléguer à un `ReservationService::confirm()` et `ReservationService::refuse()`
- Chaque transition crée une entrée `ReservationStatusHistory`
- Dispatcher les messages Messenger correspondants

---

## ÉTAPE 7 — Processus d'annulation (Partie B.3)

**Ajout dans le contrôleur de réservation :**
- `POST /reservations/{id}/annuler` → accessible au voyageur ET à l'hôte
- Requiert un motif (`cancellationReason` non vide)
- Transitions : `pending → cancelled` ou `confirmed → cancelled`
- Libère les dates (aucune action nécessaire sur `PropertyAvailability` si le modèle ne bloque pas sur `pending`)
- Dispatcher `ReservationCancelledMessage` → email aux deux parties

---

## ÉTAPE 8 — Notifications email asynchrones (Partie D)

**Architecture Messenger :**

**Messages à créer (`src/Message/`) :**
- `ReservationCreatedMessage` (id de réservation)
- `ReservationConfirmedMessage`
- `ReservationCancelledMessage`

**Handlers à créer (`src/MessageHandler/`) :**
- Chaque handler récupère la réservation depuis la BDD via son id
- Utilise `Symfony\Component\Mailer\MailerInterface` pour envoyer l'email

**Templates email (`templates/email/`) :**
- `reservation_created.html.twig` (pour l'hôte : logement, dates, voyageur, total, CTA)
- `reservation_confirmed.html.twig` (pour voyageur + hôte : récapitulatif)
- `reservation_cancelled.html.twig` (motif d'annulation)

**Validation :**
- Démarrer le container Docker → vérifier les emails dans Mailpit (`http://localhost:8025`)

---

## ÉTAPE 9 — Export iCal (Partie E)

**Prérequis :** champ `calendarToken` sur `Property` (Étape 1).

**Nouveau contrôleur :** `src/Controller/Api/ICalExportController.php`

**Route :** `GET /api/properties/{id}/calendar.ics?token={secret}`

**Logique :**
1. Récupérer la Property par id
2. Valider `token == property.calendarToken` → 401 si invalide
3. Récupérer toutes les réservations `confirmed` de cette property
4. Générer le contenu `.ics` à la main (pas de librairie obligatoire) :
   ```
   BEGIN:VCALENDAR
   VERSION:2.0
   PRODID:-//Clone Airbnb//FR
   [foreach reservation]
   BEGIN:VEVENT
   UID:res-{id}@clone-airbnb.local
   SUMMARY:{property.title} — {guest.fullName}
   DTSTART;VALUE=DATE:{checkinDate:Ymd}
   DTEND;VALUE=DATE:{checkoutDate:Ymd}
   DESCRIPTION:Séjour {nights} nuits — {totalPrice}€ — {guest.email}
   END:VEVENT
   [endforeach]
   END:VCALENDAR
   ```
5. Retourner une `Response` avec `Content-Type: text/calendar`

**Génération du token :**
- Ajouter une route `POST /compte/proprietes/{id}/regenerer-token` pour que l'hôte puisse régénérer son token
- Utiliser `bin2hex(random_bytes(32))` ou `Uuid::v4()`

---

## ÉTAPE 10 (BONUS) — Import iCal (Partie F)

**Fichier :** `src/Command/ICalSyncCommand.php`

**Commande :** `app:ical:sync [--property-id=X]`

**Logique :**
1. Récupérer tous les `PropertyICalSync` (ou pour une property spécifique)
2. Pour chaque : faire un `GET` HTTP sur `iCalUrl` via `Symfony\Contracts\HttpClient\HttpClientInterface`
3. Parser le contenu `.ics` (chercher les `VEVENT`, extraire `DTSTART`, `DTEND`)
4. Pour chaque événement distant : créer/mettre à jour des `PropertyAvailability` avec `isAvailable=false`
5. Mettre à jour `lastSyncAt`

**Stratégie de gestion des conflits (à documenter dans conception.txt) :**
- Si un événement distant chevauche une réservation `confirmed` locale → logger un warning, ne pas supprimer la réservation
- Si un événement distant est supprimé depuis la dernière sync → supprimer les `PropertyAvailability` correspondantes (comparer les UID iCal)

---

## ÉTAPE 11 (BONUS) — Fonctionnalités avancées (Partie G)

### G.1 — Expiration automatique des `pending` après 24h

**Fichier :** `src/Message/ExpireReservationMessage.php` + handler
- À la création d'une réservation `pending`, dispatcher un message différé de 24h
- Le handler vérifie que la réservation est toujours `pending` puis la passe à `cancelled`
- Alternative : Symfony Scheduler ou cron + commande `app:expire-pending-reservations`

### G.2 — Rappel check-in J-1

**Fichier :** commande ou Scheduler
- Chaque jour, chercher les réservations `confirmed` avec `checkinDate = tomorrow`
- Envoyer un email récapitulatif avec les informations d'accès

### G.5 — Timeline voyageur

**Fichier :** template `templates/front/reservation/show.html.twig`
- Sur la page de détail d'une réservation, afficher la liste des `ReservationStatusHistory` ordonnée
- Rendu visuel : timeline verticale avec icônes et horodatages

### G.6 — Tarification dynamique en front

**Fichier :** `assets/controllers/booking_controller.js` (Stimulus)
- Sur le formulaire de réservation, écouter les changements de dates
- Calculer en JS : `nuits × pricePerNight + cleaningFee + 12% serviceFee`
- Mettre à jour un bloc récapitulatif en temps réel sans rechargement

### G.8 — Notifications in-app

**Entité `Notification` déjà prête.**
- À chaque transition de statut, créer une `Notification` en base pour l'utilisateur concerné
- Dans la navbar : récupérer `count(notifications where isRead = false)` de l'utilisateur courant
- Afficher l'icône cloche avec badge
- Route `GET /notifications` pour lister + marquer comme lues

---

## Modalités de rendu

```bash
# Branche de travail
git checkout -b tp/reservation-calendrier

# Commits au fil du développement (Conventional Commits)
git commit -m "feat(booking): add availability check before reservation creation"
git commit -m "feat(search): filter by destination, dates and guest count"
git commit -m "feat(ical): add calendar export endpoint with token auth"

# Pull Request vers main
gh pr create --title "feat: moteur de réservation + calendrier iCal" \
  --body "Iness Ben aïssa — Fonctionnalités implémentées : A, B, C, D, E, G.5, G.6, G.8"
```

**Description de la PR :** Nom, Prénom + liste exhaustive des parties traitées (A/B/C/D/E + bonus).
