# Étapes 0 → 3 — Tests de vérification

## Fichiers créés / modifiés
- `conception.txt` (racine)
- `src/Service/AvailabilityService.php`
- `src/Service/ReservationWorkflowService.php`
- `src/Repository/PropertyAvailabilityRepository.php` (+ 2 méthodes)
- `src/Repository/ReservationRepository.php` (+ 2 méthodes)
- `src/Controller/Front/BookingController.php` (vérif dispo + workflow)

---

## Test 1 — Syntaxe PHP OK

```bash
php bin/console debug:router | grep reserver
```
Résultat attendu : la route `app_booking_checkout` apparaît sans erreur.

---

## Test 2 — Container Symfony se compile

```bash
php bin/console cache:clear
```
Résultat attendu : aucune erreur (services autowirés correctement).

---

## Test 3 — Réservation sur logement non publié

1. Prends un logement en statut `draft` ou `pending` dans la BDD.
2. Accède directement à `/logement/{id}/reserver`.
3. Résultat attendu : page 404.

---

## Test 4 — Réservation sur logement disponible (instantBooking = false)

1. Connecte-toi avec un compte voyageur (pas l'hôte du logement).
2. Va sur `/logement/{id}/reserver` pour un logement `published`.
3. Saisis des dates libres et un nombre de voyageurs valide.
4. Soumets le formulaire.
5. Résultats attendus :
   - Redirection vers `/reservations/{id}`.
   - Flash : *"Demande envoyée ! L'hôte doit valider votre réservation."*
   - En BDD : `reservations.status = 'pending'`.
   - En BDD : une ligne dans `reservation_status_history` avec `old_status = NULL`, `new_status = 'pending'`.

---

## Test 5 — Réservation sur logement disponible (instantBooking = true)

1. Passe un logement en `instant_booking = 1` en BDD (ou via admin).
2. Répète le test 4.
5. Résultats attendus :
   - Flash : *"Réservation confirmée ! Vous recevrez une confirmation par email."*
   - En BDD : `status = 'confirmed'`.
   - En BDD : `reservation_status_history.new_status = 'confirmed'`.

---

## Test 6 — Réservation bloquée sur dates indisponibles

1. Dans la table `property_availability`, insère une ligne avec `is_available = 0` pour une date dans ta plage de test.
2. Essaie de réserver sur cette plage.
3. Résultat attendu :
   - Reste sur la page de réservation.
   - Flash d'erreur : *"Ce logement n'est pas disponible pour les dates ou le nombre de voyageurs sélectionnés."*
   - Aucune ligne créée dans `reservations`.

---

## Test 7 — Réservation bloquée sur dates déjà confirmées

1. Crée une réservation `confirmed` pour un logement sur les dates J+10 → J+15.
2. Essaie de réserver le même logement sur J+12 → J+17.
3. Résultat attendu : même comportement que le test 6.

---

## Test 8 — Hôte ne peut pas réserver son propre logement

1. Connecte-toi avec le compte hôte du logement.
2. Accède à `/logement/{id}/reserver`.
3. Résultat attendu : redirection vers la fiche logement + flash d'erreur.

---

## Vérification SQL rapide

```sql
-- Vérifie qu'une entrée d'historique est bien créée à chaque réservation
SELECT r.status, rsh.old_status, rsh.new_status, rsh.created_at
FROM reservations r
JOIN reservation_status_history rsh ON rsh.reservation_id = r.id
ORDER BY rsh.created_at DESC
LIMIT 5;
```
