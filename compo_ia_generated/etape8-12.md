# Étapes 8 → 12 — Tests de vérification

## Fichiers créés / modifiés
- `src/Message/BookingRequestMessage.php`
- `src/Message/BookingConfirmedMessage.php`
- `src/Message/BookingCancelledMessage.php`
- `src/Mail/BookingMailer.php`
- `src/MessageHandler/BookingRequestHandler.php`
- `src/MessageHandler/BookingConfirmedHandler.php`
- `src/MessageHandler/BookingCancelledHandler.php`
- `templates/email/booking_request.html.twig`
- `templates/email/booking_confirmed.html.twig`
- `templates/email/booking_cancelled.html.twig`
- `src/Controller/Front/BookingController.php` (+ dispatch)
- `src/Controller/Front/ReservationController.php` (+ dispatch cancel)
- `src/Controller/Front/Host/ReservationModerationController.php` (+ dispatch accept/reject)
- `src/Entity/Property.php` (+ calendarToken)
- `migrations/Version20260611120000.php`
- `src/Service/ICalExportService.php`
- `src/Controller/Api/ICalController.php`
- `templates/front/host/calendar.html.twig` (+ section export iCal)

---

## Prérequis

```bash
# Rebuild pour appliquer la migration calendarToken
docker compose down && docker compose up -d --build
# OU si déjà lancé :
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

---

## Test 1 — Email de demande (logement instantBooking = false)

1. Connecte-toi comme **voyageur**.
2. Réserve un logement avec `instantBooking = false`.
3. Ouvre **Mailpit** : http://localhost:8025
4. Résultats attendus :
   - Email au **voyageur** : "Demande envoyée" avec récapitulatif du séjour.
   - Email à l'**hôte** : "Nouvelle demande de réservation".

## Test 2 — Email de confirmation directe (instantBooking = true)

1. Réserve un logement avec `instantBooking = true`.
2. Dans Mailpit :
   - Un seul email : "Réservation confirmée ✓" au voyageur.
   - Fond vert dans le header.

## Test 3 — Email après acceptation hôte

1. Connecte-toi comme **hôte**.
2. Accepte une demande `pending` via `/hote/reservations`.
3. Dans Mailpit :
   - Email "Réservation confirmée ✓" au voyageur.

## Test 4 — Email après refus hôte

1. Depuis `/hote/reservations`, refuse une demande avec un motif.
2. Dans Mailpit :
   - Email "Réservation annulée" au voyageur ET à l'hôte.
   - Le motif d'annulation apparaît dans l'email.

## Test 5 — Email après annulation voyageur

1. Connecte-toi comme **voyageur**, ouvre une réservation `pending` ou `confirmed`.
2. Annule avec un motif.
3. Dans Mailpit :
   - Email "Réservation annulée" au voyageur.
   - Email "Réservation annulée" à l'hôte.

---

## Test 6 — Migration calendarToken

```sql
-- En BDD : vérifier que toutes les propriétés ont un token
SELECT id, title, calendar_token FROM properties LIMIT 5;
-- Résultat attendu : calendar_token renseigné (64 caractères hex) sur chaque ligne
```

## Test 7 — Export iCal

1. Connecte-toi comme **hôte**, va sur `/hote/proprietes/{id}/calendrier`.
2. La section **"Export iCal"** apparaît en bas.
3. L'URL affichée ressemble à `/api/properties/{id}/calendar.ics?token=xxxx`.
4. Clique sur "Télécharger" → téléchargement du fichier `calendar.ics`.

## Test 8 — Contenu du fichier iCal

Ouvre le fichier `.ics` téléchargé et vérifie :
```
BEGIN:VCALENDAR
VERSION:2.0
...
BEGIN:VEVENT
UID:{reservation_id}@stayhub.local
DTSTART;VALUE=DATE:20260903
DTEND;VALUE=DATE:20260910
SUMMARY:Réservation — Prénom Nom
STATUS:CONFIRMED
END:VEVENT
...
END:VCALENDAR
```

## Test 9 — Sécurité iCal (token invalide)

1. Accède à `/api/properties/{id}/calendar.ics?token=mauvais_token`
2. Résultat attendu : **403 Access Denied**.

## Test 10 — Sécurité iCal (sans token)

1. Accède à `/api/properties/{id}/calendar.ics` sans paramètre `token`.
2. Résultat attendu : **403 Access Denied**.

---

## Vérification rapide Messenger

```bash
# Voir les messages en attente dans la file doctrine
docker compose exec php php bin/console messenger:failed:show

# Si le worker n'est pas lancé, traiter manuellement :
docker compose exec php php bin/console messenger:consume async -vv --limit=5
```

## Vérification Mailpit en CLI

```bash
# Compter les emails reçus (API Mailpit)
curl -s http://localhost:8025/api/v1/messages | python3 -m json.tool | grep '"Total"'
```
