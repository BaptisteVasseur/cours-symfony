# Guide de test manuel

## Comptes de démonstration

Mot de passe universel : **`password`**

| Email | Rôle | Profil |
|---|---|---|
| `admin@test.fr` | Admin | Accès back-office |
| `host@test.fr` | Hôte (Julien Dupré) | Gère les 4 logements |
| `alice@test.fr` | Voyageuse | A une réservation confirmée + une passée |
| `bob@test.fr` | Voyageur | A une réservation en attente + une annulée |
| `charlie@test.fr` | Voyageur | A une réservation en attente |

---

## Logements (tous chez Julien)

| Logement | Réservation instantanée | Comportement |
|---|---|---|
| **Appartement Paris Centre** | ✅ Oui | Réservation directe → statut `confirmed` |
| **Villa Côte d'Azur** | ✅ Oui | Réservation directe → statut `confirmed` |
| **Chalet Alpin** | ❌ Non | En attente d'approbation hôte → statut `pending` |
| **Maison de Campagne Normande** | ❌ Non | En attente d'approbation hôte → statut `pending` |

---

## Réservations existantes

| Voyageur | Logement | Dates | Statut |
|---|---|---|---|
| Alice | Appartement Paris Centre | dans 10–14 j | **confirmed** |
| Bob | Villa Côte d'Azur | dans 20–24 j | **pending** — Julien doit accepter |
| Charlie | Chalet Alpin | dans 30–35 j | **pending** — Julien doit accepter |
| Alice | Maison de Campagne | il y a 20 j | **completed** |
| Bob | Chalet Alpin | dans 40–43 j | **cancelled** |

---

## Scénarios de test manuels

### 1. Recherche de logements

1. Aller sur `/` ou `/recherche`
2. Taper **Paris** → 1 logement affiché
3. Taper **France** → 4 logements
4. Filtrer par **3 voyageurs** → tous s'affichent (max 6)
5. Dates valides lointaines → logements disponibles
6. Date checkout < checkin → pas de crash, liste vide

---

### 2. Réservation instantanée (sans confirmation)

**Compte :** `charlie@test.fr` — **Logement :** Appartement Paris Centre

1. Se connecter, aller sur la fiche du logement
2. Cliquer **Réserver**, dates libres (ex. 2027-03-01 → 2027-03-05), 1 voyageur
3. Soumettre → redirection vers la réservation
4. Statut attendu : **confirmed** immédiatement

---

### 3. Réservation avec confirmation requise

**Compte :** `alice@test.fr` — **Logement :** Chalet Alpin

1. Se connecter, aller sur la fiche du Chalet Alpin
2. Réserver avec des dates libres
3. Soumettre → redirection vers la réservation
4. Statut attendu : **pending** (en attente de Julien)

---

### 4. Dashboard hôte — accepter une réservation

**Compte :** `host@test.fr`

1. Aller sur `/hote/reservations`
2. Voir les réservations **pending** de Bob et Charlie
3. Cliquer **Accepter** sur celle de Bob → statut passe à **confirmed**
4. La cloche de Bob doit avoir une nouvelle notification

---

### 5. Dashboard hôte — refuser une réservation

**Compte :** `host@test.fr`

1. Aller sur `/hote/reservations`
2. Cliquer **Refuser** sur celle de Charlie
3. Saisir un motif obligatoire ex. **"Dates personnelles"** → statut **cancelled**
4. Tester sans motif → erreur flash, statut inchangé

---

### 6. Annulation par le voyageur

**Compte :** `bob@test.fr`

1. Aller sur `/reservations`
2. Ouvrir la réservation **pending** (Villa Côte d'Azur)
3. Annuler avec un motif → statut **cancelled**
4. Annuler sans motif → erreur, statut inchangé

---

### 7. Calendrier hôte

**Compte :** `host@test.fr`

1. `/hote/reservations` → lien Calendrier d'un logement
2. Cliquer un jour futur → passe en rouge (bloqué)
3. Re-cliquer → repasse en blanc (libre)
4. Jours passés → grisés, non cliquables

---

### 8. Notifications

**N'importe quel compte connecté**

1. Cliquer sur la cloche 🔔 en haut à droite
2. Liste de notifications affichée en texte pur (pas de HTML injecté)
3. **Tout marquer lu** → disparaissent ou s'estompent

---

### 9. Export iCal

**Compte :** `host@test.fr`

1. Aller sur `/hote/reservations`
2. La section **"Export calendrier (iCal)"** liste chaque logement avec un bouton **Exporter .ics**
3. Cliquer le bouton → téléchargement du fichier `.ics` contenant les réservations confirmées
4. Importer le fichier dans Google Calendar, Apple Calendar, Outlook…

En direct (URL brute) :
```
GET /api/properties/{uuid}/calendar.ics?token={token}
```
- Sans token ou mauvais token → 403 (pas de redirect vers /login)

---

## Tests automatisés

```bash
# Recharger la base de test
docker compose exec -e APP_ENV=test php php bin/console doctrine:fixtures:load --no-interaction

# Lancer la suite
docker compose exec php php bin/phpunit --testdox
```

Résultat attendu : **31 tests, 0 failure**

---

## Réinitialiser les données

```bash
# Dev
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

# Test
docker compose exec -e APP_ENV=test php php bin/console doctrine:fixtures:load --no-interaction
```

---

## Cas limites à vérifier

| Situation | Comportement attendu |
|---|---|
| Hôte réserve son propre logement | Redirigé / erreur |
| Voyageur accède à la réservation d'un autre | 403 |
| Refus/annulation sans motif | Flash erreur, statut inchangé |
| Dates checkout ≤ checkin | Formulaire invalide (422, message d'erreur) |
| iCal sans token ou mauvais token | 403 (pas de redirect vers /login) |
| Booking sur logement complet ou bloqué | "Ce logement n'est pas disponible" |
