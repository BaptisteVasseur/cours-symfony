# Étapes 4 → 7 — Tests de vérification

## Fichiers créés / modifiés
- `src/Controller/Front/Host/AvailabilityController.php`
- `src/Controller/Front/Host/ReservationModerationController.php`
- `src/Controller/Front/ReservationController.php` (+ action cancel)
- `src/Controller/Front/SearchController.php`
- `src/Repository/PropertyRepository.php` (+ search)
- `src/Repository/ReservationRepository.php` (+ findConfirmedForPeriod)
- `templates/front/host/calendar.html.twig`
- `templates/front/host/reservations/index.html.twig`
- `templates/front/reservation/show.html.twig` (+ annulation + timeline)

---

## Prérequis

Charge les fixtures pour avoir des données réelles :
```bash
docker compose exec php php bin/console doctrine:fixtures:load
```

---

## Test 1 — Calendrier hôte (Étape 4)

1. Connecte-toi avec un compte **hôte** (propriétaire d'un logement).
2. Va sur `/hote/proprietes/{id}/calendrier`.
3. Résultats attendus :
   - Grille mensuelle s'affiche avec navigation ← →.
   - Les jours disponibles sont blancs.
   - Les jours avec réservation `confirmed` sont **verts** avec 🔒.
   - Au survol d'un jour libre → lien "bloquer" apparaît.

## Test 2 — Blocage d'un jour unique

1. Survole un jour libre → clique "bloquer".
2. Résultat : jour passe en **rouge**, flash "Période bloquée".
3. En BDD : `SELECT * FROM property_availability WHERE is_available = false`.

## Test 3 — Blocage d'une période via formulaire

1. Remplis le formulaire "Bloquer une période" (ex : du 2026-08-01 au 2026-08-05).
2. Résultat : 5 jours passent en rouge.
3. En BDD : 5 lignes `is_available = false` pour ces dates.

## Test 4 — Déblocage

1. Survole un jour rouge (bloqué manuellement) → clique "débloquer".
2. Résultat : jour redevient blanc, flash "Jour débloqué".
3. En BDD : ligne supprimée de `property_availability`.

## Test 5 — Accès refusé à un non-hôte

1. Connecte-toi avec un compte **voyageur** (pas l'hôte).
2. Accède à `/hote/proprietes/{id}/calendrier`.
3. Résultat attendu : **403 Access Denied**.

---

## Test 6 — Modération hôte (Étape 5)

1. Connecte-toi comme **hôte**.
2. Va sur `/hote/reservations`.
3. Si aucune demande pending : message "Aucune demande en attente".
4. Crée une réservation `pending` (via le formulaire de booking sur un logement `instantBooking=false`).
5. Reviens sur `/hote/reservations` → la demande apparaît.

## Test 7 — Accepter une demande

1. Depuis `/hote/reservations`, clique **Accepter**.
2. Résultats :
   - Flash "Réservation acceptée".
   - En BDD : `reservations.status = 'confirmed'`.
   - En BDD : nouvelle ligne dans `reservation_status_history` avec `old_status='pending'`, `new_status='confirmed'`.

## Test 8 — Refuser une demande

1. Depuis `/hote/reservations`, remplis le motif et clique **Refuser**.
2. Résultats :
   - Flash "Demande refusée".
   - En BDD : `reservations.status = 'cancelled'`, `cancellation_reason` renseigné.
3. Si le motif est vide → flash "Le motif de refus est obligatoire", pas d'annulation.

---

## Test 9 — Annulation voyageur (Étape 6)

1. Connecte-toi comme **voyageur** avec une réservation `pending` ou `confirmed`.
2. Va sur `/reservations/{id}`.
3. Vérifie que le bouton **"Annuler la réservation"** est visible.
4. Remplis le motif et confirme.
5. Résultats :
   - Redirection vers `/reservations` + flash "Réservation annulée".
   - En BDD : `status = 'cancelled'`.
6. Retourne sur la fiche : le bouton d'annulation n'est **plus visible** (status cancelled).

## Test 10 — Annulation sans motif

1. Laisse le champ motif vide → soumets.
2. Résultat : flash "Le motif d'annulation est obligatoire", réservation non annulée.

## Test 11 — Timeline (Étape 6, bonus visuel)

1. Ouvre une fiche réservation `/reservations/{id}` qui a subi des transitions.
2. La section **Historique** en bas doit lister les changements de statut avec dates.

---

## Test 12 — Recherche /search (Étape 7)

1. Va sur `/search` → tous les logements `published` s'affichent.
2. Cherche par destination (ex: "Paris") → seuls les logements dont la ville/adresse contient "Paris".
3. Filtre par guests (ex: 5) → logements avec `max_guests < 5` exclus.

## Test 13 — Recherche avec dates

1. Cherche `/search?checkin=2026-09-03&checkout=2026-09-10`.
2. Les logements ayant une réservation `confirmed` qui chevauche cette plage ne doivent **pas apparaître**.
3. Cherche `/search?checkin=2026-09-07&checkout=2026-09-14` → le même logement **réapparaît** (checkout exclusif).

---

## Vérification SQL rapide

```sql
-- Vérifier les blocages manuels
SELECT available_date, is_available FROM property_availability ORDER BY available_date;

-- Vérifier la timeline des réservations
SELECT r.status, h.old_status, h.new_status, h.created_at
FROM reservations r
JOIN reservation_status_history h ON h.reservation_id = r.id
ORDER BY h.created_at DESC LIMIT 10;
```
