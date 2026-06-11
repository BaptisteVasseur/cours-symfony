# État des features — StayNest (cours-symfony)

> Dernière mise à jour : 2026-06-11 (branche tp/reservation-calendarier)

---

## Partie A — Gestion des disponibilités hôte

- [x] Interface calendrier hôte : liste des dates bloquées (`GET /hote/logement/{id}/disponibilites`)
- [x] Formulaire de blocage manuel de dates par l'hôte (`PropertyAvailability` + `BlockedPeriodType`)
- [x] Déblocage avec CSRF (`POST /debloquer/{id}`)
- [x] `hasBlockedDayInRange()` intégré dans l'algo de réservation (`BookingController`)
- [x] Vérification `property.status === 'published'` dans l'algo de dispo

---

## Partie B — Parcours de réservation

- [x] `instantBooking` → statut `confirmed` automatique, sinon `pending`
- [x] Tableau de bord hôte : liste des demandes `pending` + réservations confirmées/annulées
- [x] Accepter une demande (hôte) — CSRF + `ReservationStatusHistory`
- [x] Refuser une demande (hôte) avec motif — `CancellationReasonType`
- [x] Annulation par l'hôte avec motif
- [x] Annulation par le voyageur avec motif
- [x] Vérification `findOverlapping()` à la soumission
- [x] Notifications email asynchrones via Symfony Messenger (3 messages : pending, confirmed, cancelled)
- [x] Handlers null-safe sur `getHost()` (fix post code-review)
- [x] Vérification capacité `guestsCount <= property.maxGuests` à la soumission

---

## Partie C — Moteur de recherche

- [x] Route `/search` et template `front/search/index.html.twig` présents
- [x] Paramètres `destination`, `checkin`, `checkout`, `guests` transmis au template
- [x] Filtrage réel dans `PropertyRepository::findWithFilters()` : par ville/pays/titre (LIKE)
- [x] Filtrage par disponibilité sur une période (sous-requête excluant les réservations actives ET les jours bloqués manuellement)
- [x] Filtrage par capacité (`maxGuests >= guests`)

---

## Partie D — Notifications transactionnelles

- [x] `src/Message/ReservationPendingMessage.php`
- [x] `src/Message/ReservationConfirmedMessage.php`
- [x] `src/Message/ReservationCancelledMessage.php`
- [x] Handlers correspondants dans `src/MessageHandler/`
- [x] Transport `doctrine://default` configuré dans `messenger.yaml`
- [x] Routing des 3 messages vers `async`
- [x] Email nouvelle demande `pending` → hôte
- [x] Email réservation confirmée → voyageur + hôte
- [x] Email annulation/refus → voyageur + hôte avec motif
- [x] Mailpit disponible sur `http://localhost:8025`

---

## Partie E — Export iCal

- [x] Champ `icalToken` sur `Property` (unique, 64 hex chars, `generateIcalToken()`)
- [x] Endpoint `GET /api/properties/{id}/calendar.ics?token={secret}` (`Api\ICalController`)
- [x] Génération format `.ics` (VCALENDAR / VEVENT, dates VALUE=DATE, Content-Type text/calendar)

---

## Partie F — Import iCal (BONUS)

- [x] Commande `app:ical:sync` (`src/Command/ICalSyncCommand.php`)
- [x] Consommation des `PropertyICalSync` : fetch URL, parse iCal (DTSTART/DTEND/DURATION), bloque les jours dans `PropertyAvailability`, met à jour `lastSyncAt`
- [x] Gestion des conflits : les blocs manuels existants sont conservés (skip si date déjà présente) ; option `--dry-run` disponible

---

## Partie G — Fonctionnalités avancées (BONUS)

| Id  | Feature                                                 | Statut |
| --- | ------------------------------------------------------- | ------ |
| G.1 | Expiration automatique des `pending` après 24h (Worker) | ❌ |
| G.2 | Rappel email J-1 check-in                               | ❌ |
| G.5 | Timeline voyageur (historique statuts visuels)          | ❌ (`ReservationStatusHistory` en base, pas de template) |
| G.6 | Tarification dynamique JS (calcul temps réel)           | ❌ |
| G.8 | Notifications in-app (cloche header)                    | ❌ (`Notification` en base, pas de vue) |

---

## Front-end

- [x] Refonte complète : typo Inter, palette brand `#FF385C`, Tailwind config étendu
- [x] Hero section plein-écran avec barre de recherche intégrée (home)
- [x] Cards logement avec zoom hover, badge Instant, aspect-ratio 4/3
- [x] Header sticky avec menu profil dropdown + déconnexion
- [x] Section "Espace hôte" dans le menu dropdown (tableau de bord, mes logements)
- [x] Footer sombre avec colonnes de liens
- [x] Login / Register : split layout (image + formulaire)
- [x] Mes réservations : groupement "À venir" / "Historique", badge statut coloré
- [x] Bouton "Annuler ma réservation" sur la page détail réservation voyageur
- [x] Page "Mes logements" → lien vers gestion disponibilités (pas vers réservation)
- [x] Page profil avec sidebar nav et avatar
- [x] Page paramètres avec zone dangereuse + déconnexion
- [x] Flash messages stylisés (success / error / warning)

---

## Fixtures & données de test

- [x] `UserFixtures` : 12 comptes avec rôles (`ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_HOST`, `ROLE_USER`)
- [x] `AppFixtures` : 8 propriétés publiées + réservations (confirmed/pending/completed)
- [x] Conflits de doublons corrigés (policies, amenities, references)

Comptes disponibles (mot de passe : `password123`) :

| Email | Rôle |
|-------|------|
| `admin@test.local` | SUPER_ADMIN + ADMIN |
| `moderateur@test.local` | ADMIN |
| `alice@example.com` | HOST |
| `fabrice@example.com` | HOST |
| `jules@example.com` | HOST |
| `clara@example.com` | USER (voyageur) |
| `test@example.com` | HOST + ADMIN (compte démo upstream) |

---

## Livrable obligatoire

- [ ] `conception.txt` à la racine du projet (**à rédiger manuellement — IA interdite**)

---

## Prochaines priorités

```
CRITIQUE
└── conception.txt ← rédiger soi-même

BONUS
├── G.6 tarification dynamique JS
├── G.5 timeline statuts ReservationStatusHistory
├── G.1 expiration pending (Worker)
├── G.2 rappel J-1
└── G.8 notifications in-app
```
