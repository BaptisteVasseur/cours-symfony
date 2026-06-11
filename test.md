# Guide de test — StayNest

> URLs : app `http://localhost:8089` · Mailpit `http://localhost:8025` · Adminer `http://localhost:8088`
> Mot de passe de tous les comptes : `password123`

---

## Prérequis

Démarrer le worker Messenger **avant** les tests des parties B et D :

```bash
docker exec -it cours-symfony-php-1 php bin/console messenger:consume async --limit=10
```

---

## Comptes de test

| Email | Rôle |
|-------|------|
| `admin@test.local` | SUPER_ADMIN + ADMIN |
| `moderateur@test.local` | ADMIN |
| `alice@example.com` | HOST |
| `fabrice@example.com` | HOST |
| `jules@example.com` | HOST |
| `clara@example.com` | USER (voyageur) |
| `test@example.com` | HOST + ADMIN |

---

## Partie A — Disponibilités hôte

**A1 — Bloquer des dates**
1. Login `alice@example.com`
2. Menu dropdown → Espace hôte → Mes logements → cliquer sur un logement → page disponibilités
3. Remplir le formulaire de blocage (date début / fin + motif)
4. **Attendu** : les dates apparaissent dans la liste

**A2 — Débloquer**
1. Sur la même page, cliquer "Débloquer" sur un bloc existant
2. **Attendu** : le bloc disparaît de la liste

**A3 — Réservation refusée sur dates bloquées**
1. Login `clara@example.com`
2. Tenter de réserver le même logement sur les dates bloquées
3. **Attendu** : flash error "Une ou plusieurs nuits de cette période sont indisponibles"
4. **Attendu** : les dates bloquées sont grisées sur le calendrier de réservation

---

## Partie B — Parcours de réservation

**B1 — Réservation instant booking**
1. Login `clara@example.com`
2. Trouver un logement avec badge "Instant"
3. Réserver des dates libres
4. **Attendu** : statut `confirmed` directement, flash success

**B2 — Réservation pending**
1. Même chose sur un logement **sans** instant booking
2. **Attendu** : statut `pending`

**B3 — Accepter une demande (hôte)**
1. Login hôte du logement → Menu dropdown → Espace hôte → Demandes de réservation
2. Cliquer "Accepter" sur une demande `pending`
3. **Attendu** : statut passe à `confirmed`

**B4 — Refuser une demande (hôte)**
1. Cliquer "Refuser" sur une demande `pending`
2. Saisir un motif (min. 10 caractères) et confirmer
3. **Attendu** : statut `rejected`, retour au tableau de bord

**B5 — Annulation voyageur**
1. Login `clara@example.com` → Mes réservations → ouvrir une réservation `pending` ou `confirmed`
2. Cliquer "Annuler ma réservation" (sidebar droite) + saisir motif
3. **Attendu** : statut `cancelled`, bouton disparaît

**B6 — Chevauchement bloqué**
1. Tenter de réserver des dates déjà `confirmed` sur le même logement
2. **Attendu** : flash error "Ce logement est déjà réservé sur cette période"

**B7 — Capacité max**
1. Sur un logement avec `maxGuests = 2`, saisir `guestsCount = 5`
2. **Attendu** : flash error "Ce logement accepte au maximum 2 voyageurs"

---

## Partie C — Moteur de recherche

**C1 — Recherche par destination**
1. Page d'accueil → barre de recherche, saisir "Nice"
2. **Attendu** : seuls les logements dont city/country/title contiennent "nice" (insensible à la casse)

**C2 — Recherche par capacité**
1. Saisir `guests = 6`
2. **Attendu** : seuls les logements avec `maxGuests >= 6`

**C3 — Filtre disponibilité (réservation existante)**
1. Saisir des dates qui chevauchent une réservation `confirmed` existante
2. **Attendu** : le logement concerné n'apparaît **pas** dans les résultats

**C4 — Filtre disponibilité (dates bloquées manuellement)**
1. Saisir des dates qui incluent un jour bloqué manuellement (partie A)
2. **Attendu** : le logement concerné n'apparaît **pas** dans les résultats

**C5 — Combinaison des filtres**
1. Saisir destination + dates + guests simultanément
2. **Attendu** : intersection des 3 filtres appliquée

---

## Partie D — Notifications email

> Worker messenger requis (voir Prérequis).

**D1 — Email pending → hôte**
1. Faire une réservation `pending`
2. Ouvrir Mailpit `http://localhost:8025`
3. **Attendu** : email reçu sur l'adresse de l'hôte

**D2 — Email confirmed → voyageur + hôte**
1. L'hôte accepte la demande
2. **Attendu** : 2 emails dans Mailpit (voyageur + hôte)

**D3 — Email annulation → voyageur + hôte**
1. Annuler une réservation (voyageur ou hôte)
2. **Attendu** : email avec motif d'annulation dans Mailpit

---

## Partie E — Export iCal

**E1 — Récupérer le token d'une propriété**

Dans Adminer (`http://localhost:8088`) :
```sql
SELECT id, title, ical_token FROM properties LIMIT 5;
```

**E2 — Télécharger le .ics**
```
GET http://localhost:8089/api/properties/{id}/calendar.ics?token={ical_token}
```

**Attendu** :
- Content-Type `text/calendar; charset=utf-8`
- Fichier commence par `BEGIN:VCALENDAR`
- Un `BEGIN:VEVENT` / `END:VEVENT` par réservation active
- Format : `DTSTART;VALUE=DATE:YYYYMMDD`

**E3 — Token invalide**
```
GET http://localhost:8089/api/properties/{id}/calendar.ics?token=mauvais
```
**Attendu** : HTTP 403

**E4 — Propriété inconnue**
```
GET http://localhost:8089/api/properties/00000000-0000-0000-0000-000000000000/calendar.ics?token=x
```
**Attendu** : HTTP 404

---

## Partie F — Import iCal

**F1 — Dry run**
```bash
docker exec cours-symfony-php-1 php bin/console app:ical:sync --dry-run
```
**Attendu** : warning pour les URLs injoignables, "0 jour(s) bloqué(s)", aucune écriture en base

**F2 — Test avec un vrai fichier iCal**

Créer `test.ics` :
```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test//FR
BEGIN:VEVENT
UID:test-1@test
DTSTART;VALUE=DATE:20261201
DTEND;VALUE=DATE:20261205
SUMMARY:Test block
END:VEVENT
END:VCALENDAR
```

Copier dans le container et insérer un sync en base :
```bash
docker cp test.ics cours-symfony-php-1:/tmp/test.ics
```
```sql
INSERT INTO property_ical_sync (id, property_id, provider_name, i_cal_url, last_sync_at)
VALUES (gen_random_uuid(), '{property_id}', 'test', 'file:///tmp/test.ics', NULL);
```
```bash
docker exec cours-symfony-php-1 php bin/console app:ical:sync
```
**Attendu** : "4 jour(s) bloqué(s)", `last_sync_at` mis à jour

**F3 — Gestion des conflits**
1. Bloquer manuellement le 2/12 sur le même logement (partie A)
2. Supprimer les blocs iCal : `DELETE FROM property_availability WHERE reason = 'ical:test';`
3. Relancer `app:ical:sync`
4. **Attendu** : "3 jour(s) bloqué(s), 1 conflit(s) ignoré(s)"

---

## Vérifications SQL rapides (Adminer)

```sql
-- Réservations par statut
SELECT status, COUNT(*) FROM reservations GROUP BY status;

-- Tokens iCal générés
SELECT COUNT(*) FROM properties WHERE ical_token IS NOT NULL;

-- Blocs de disponibilité par origine
SELECT reason, COUNT(*) FROM property_availability GROUP BY reason;

-- Dernière sync iCal
SELECT provider_name, last_sync_at FROM property_ical_sync;
```
