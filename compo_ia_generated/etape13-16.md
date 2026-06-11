# Étapes 13 → 16 (Bonus F, G1, G6, G8) — Tests de vérification

## Fichiers créés / modifiés
- `src/Message/ExpireBookingMessage.php`
- `src/MessageHandler/ExpireBookingHandler.php`
- `src/Service/NotificationService.php`
- `src/Twig/NotificationExtension.php`
- `src/Command/ICalSyncCommand.php`
- `src/Controller/Front/NotificationController.php`
- `templates/front/notification/index.html.twig`
- `templates/front/property/booking.html.twig` (+ pricing dynamique)
- `templates/layout/partials/app/header.html.twig` (+ cloche)
- `src/Controller/Front/BookingController.php` (+ DelayStamp + notifications)
- `src/Controller/Front/ReservationController.php` (+ notifications)
- `src/Controller/Front/Host/ReservationModerationController.php` (+ notifications)

---

## Test 1 — Notifications in-app (G8)

1. Effectue une réservation sur un logement `instantBooking = false`.
2. Connecte-toi en tant qu'**hôte** de ce logement.
3. Dans le header, la **cloche** doit afficher un badge rouge avec le nombre de notifications non lues.
4. Clique sur la cloche → http://localhost:8089/notifications
5. Résultat : notification "Nouvelle demande de réservation" visible.
6. Après visite, le badge disparaît (toutes marquées comme lues).

## Test 2 — Notification après confirmation

1. En tant qu'hôte, accepte la demande via `/hote/reservations`.
2. Connecte-toi en tant que **voyageur**.
3. Badge cloche → notification "Réservation confirmée".

## Test 3 — Notification après annulation

1. Annule une réservation (voyageur ou hôte).
2. Les deux parties voient une notification "Réservation annulée".

---

## Test 4 — Pricing dynamique (G6)

1. Va sur la page de réservation d'un logement : `/logement/{id}/reserver`.
2. Sélectionne une date d'**arrivée**, puis une date de **départ**.
3. Résultat immédiat dans le panneau de droite :
   - "X nuits × Y €"
   - Frais de ménage (si définis)
   - Frais de service (12 %)
   - **Total** en gras
4. Change les dates → le total se recalcule instantanément, sans rechargement.

---

## Test 5 — Auto-expiration 24h (G1)

> Note : En dev, le délai de 24h est réel. Pour tester rapidement, réduire le DelayStamp à 30s dans BookingController puis remettre.

1. Crée une réservation `pending` sur un logement `instantBooking = false`.
2. Vérifie en BDD : `status = 'pending'`.
3. Après expiration du délai, le worker Messenger traite le message.
4. Résultat en BDD : `status = 'cancelled'`, `cancellation_reason = 'Expiration automatique...'`.

```bash
# Vérifier que le worker tourne
docker compose logs messenger-worker --tail=20

# Voir les messages en attente
docker compose exec php php bin/console messenger:stats
```

---

## Test 6 — Import iCal externe (F)

```bash
# Lancer la commande de synchro
docker compose exec php php bin/console app:ical:sync -v

# Synchro d'une propriété spécifique
docker compose exec php php bin/console app:ical:sync --property={uuid}
```

1. Ajoute un enregistrement `PropertyICalSync` en BDD avec une URL iCal valide.
2. Lance la commande → résultat : "X jour(s) bloqué(s) au total."
3. Vérifie dans le calendrier hôte que les jours importés sont en rouge.

```sql
-- Insérer un flux iCal de test (remplacer les IDs)
INSERT INTO property_ical_sync (id, property_id, provider_name, i_cal_url, last_sync_at)
VALUES (gen_random_uuid(), '{property_id}', 'Airbnb', 'https://example.com/cal.ics', NULL);
```

---

## Récapitulatif complet du projet

| Partie | Statut |
|--------|--------|
| A — Calendrier hôte + disponibilités | ✅ |
| B — Tunnel de réservation | ✅ |
| C — Moteur de recherche | ✅ |
| D — Notifications transactionnelles (Messenger + emails) | ✅ |
| E — Export iCal sécurisé | ✅ |
| F — Import iCal externe (BONUS) | ✅ |
| G1 — Auto-expiration 24h (BONUS) | ✅ |
| G5 — Timeline historique (BONUS) | ✅ |
| G6 — Prix dynamique (BONUS) | ✅ |
| G8 — Notifications in-app (BONUS) | ✅ |
