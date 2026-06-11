# Analyse des features — Clone Airbnb Symfony

## Architecture existante vs sujet d'évaluation

### Ce qui est déjà en place

| Élément | Statut |
|---|---|
| Entité `Reservation` avec statuts, dates, prix | ✅ Présent |
| Entité `ReservationStatusHistory` | ✅ Présent |
| Entité `PropertyAvailability` (jours bloquables, priceOverride, minimumStay) | ✅ Présent |
| Entité `PropertyICalSync` (URL externe, lastSyncAt) | ✅ Présent |
| `Property.instantBooking` (réservation instantanée vs sur demande) | ✅ Présent |
| `Property.checkinTime` / `checkoutTime` | ✅ Présent |
| `Notification`, `Conversation`, `Review`, `Dispute` | ✅ Présent (entités) |
| Tunnel de réservation basique (book → confirm) | ✅ Présent |
| Vérification de chevauchement de dates (`findOverlapping`) | ✅ Implémenté |
| Calendrier Flatpickr avec dates bloquées (frontend) | ✅ Implémenté |

---

## Features manquantes par partie

### Partie A — Gestion des disponibilités hôte

- [ ] Interface calendrier hôte (vue mensuelle)
- [ ] Formulaire de blocage manuel de dates (travaux, usage personnel)
- [ ] Intégration des `PropertyAvailability` dans l'algorithme de disponibilité (`findOverlapping` ne consulte que les réservations)
- [ ] Vérification `property.status === 'published'` dans l'algo de dispo

### Partie B — Parcours de réservation

- [ ] `instantBooking` non géré — le controller crée toujours en `pending`, doit passer en `confirmed` si `true`
- [ ] Tableau de bord hôte : liste des demandes `pending`, actions accepter/refuser avec motif
- [ ] Processus d'annulation (voyageur et hôte) avec motif, libération des dates
- [ ] Notifications email asynchrones via Symfony Messenger
- [ ] Vérification capacité (`guestsCount <= property.maxGuests`) à la soumission

### Partie C — Moteur de recherche

- [ ] Route `/search` inexistante
- [ ] Filtrage par `destination` (ville/adresse)
- [ ] Filtrage par `checkin` / `checkout` (disponibilité sur une période)
- [ ] Filtrage par `guests` (exclusion si capacité insuffisante)

### Partie D — Notifications transactionnelles

- [ ] Service Mailer configuré
- [ ] Classes `Message/` + `MessageHandler/` Symfony Messenger
- [ ] Transport RabbitMQ (ou fallback DB) dans `messenger.yaml`
- [ ] Email : nouvelle demande `pending` → hôte
- [ ] Email : réservation validée → voyageur + hôte
- [ ] Email : annulation/refus → parties concernées
- [ ] Vérification des envois via Mailpit (`http://localhost:8025`)

### Partie E — Export iCal

- [ ] Endpoint `/api/properties/{id}/calendar.ics?token={secret}`
- [ ] Champ `icalToken` sur `Property` (token unique, révocable)
- [ ] Génération du format `.ics` (VCALENDAR / VEVENT) avec séjours confirmés

### Partie F — Import iCal (BONUS)

- [ ] Commande Symfony `app:ical:sync`
- [ ] Consommation des `PropertyICalSync` (URL + lastSyncAt existent déjà en base)
- [ ] Stratégie de gestion des conflits documentée

### Partie G — Fonctionnalités avancées (BONUS)

| Id | Feature | Statut |
|---|---|---|
| G.1 | Expiration automatique des `pending` après 24h (Worker) | ❌ Manquant |
| G.2 | Rappel email J-1 check-in | ❌ Manquant |
| G.5 | Timeline voyageur (historique statuts visuels) | ❌ Manquant (`ReservationStatusHistory` existe mais pas de template) |
| G.6 | Tarification dynamique JS (calcul temps réel nuits × prix + frais) | ❌ Manquant |
| G.8 | Notifications in-app (cloche header) | ❌ Manquant (`Notification` existe en base, pas de vue) |

### Livrable obligatoire

- [ ] `conception.txt` à la racine du projet (à rédiger manuellement — IA interdite pour cette partie)

---

## Priorités recommandées

```
CRITIQUE (évalué directement)
├── conception.txt                          ← rédiger soi-même (IA interdite)
├── instantBooking : pending vs confirmed
├── Dashboard hôte (accepter/refuser pending)
├── Annulation avec motif
├── Route /search avec filtres
├── Messenger + Mailer (emails async)
└── Export iCal + token sécurisé

IMPORTANT (bonus solides)
├── Intégration PropertyAvailability dans l'algo dispo
├── Import iCal (commande app:ical:sync)
├── G.6 tarification dynamique JS
└── G.5 timeline statuts (base déjà présente)

SECONDAIRE
├── G.1 expiration pending (Worker)
├── G.2 rappel J-1
└── G.8 notifications in-app
```

---

## Conventions de nommage à corriger

| Fichier | Problème |
|---|---|
| `templates/property/myproperties.html.twig` | → `my_properties.html.twig` |
| `src/Entity/OauthAccount.php` | → `OAuthAccount.php` |
| `src/Repository/OauthAccountRepository.php` | → `OAuthAccountRepository.php` |
| `src/Controller/ChehController.php` | Nom non métier (controller de test ?) |
| `src/EventSubscriber/ChehAccessSubscriber.php` | Idem |
