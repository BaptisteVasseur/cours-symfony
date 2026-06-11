# Analyse des features — Clone Airbnb Symfony

> Mis à jour après rebase sur `BaptisteVasseur/cours-symfony:main` (commit `00aa2b1`, 10 juin 2026)

---

## Ce qui est déjà en place (upstream + notre branche)

### Architecture & Infrastructure
| Élément | Statut |
|---|---|
| Entités Doctrine complètes (Reservation, Property, User, Review, Dispute, Payment…) | ✅ |
| `ReservationStatusHistory` | ✅ |
| `PropertyAvailability` (jours bloquables, priceOverride, minimumStay) | ✅ |
| `PropertyICalSync` (URL externe, lastSyncAt) | ✅ |
| `Property.instantBooking` | ✅ |
| `Property.checkinTime` / `checkoutTime` | ✅ |
| `UserChecker` (sécurité connexion) | ✅ upstream |
| Voters : `PropertyVoter`, `ReservationVoter`, `ConversationVoter` | ✅ upstream |
| Fixtures complètes (`TestAccountFixture`, `ReviewFixture`) | ✅ upstream |

### Controllers & Routes
| Élément | Statut |
|---|---|
| `Front/HomeController` (page d'accueil) | ✅ upstream |
| `Front/Auth/LoginController` + `RegisterController` | ✅ upstream |
| `Front/BookingController` (tunnel réservation) | ✅ upstream |
| `Front/ReservationController` (espace voyageur) | ✅ upstream |
| `Front/MessageController` (messagerie) | ✅ upstream |
| `Front/AccountController` (profil, settings) | ✅ upstream |
| `Front/HelpController` | ✅ upstream |
| `Admin/PropertyController`, `ReservationController`, `UserController`… | ✅ upstream |
| Route `/search` (template présent) | ✅ upstream (template only) |

### Templates
| Élément | Statut |
|---|---|
| Layout `front/` et `admin/` restructurés | ✅ upstream |
| `front/property/show.html.twig` (fiche logement) | ✅ upstream |
| `front/reservation/index.html.twig` + `show.html.twig` | ✅ upstream |
| `front/message/index.html.twig` + `show.html.twig` | ✅ upstream |
| `front/account/profile`, `settings`, `properties` | ✅ upstream |
| `front/search/index.html.twig` | ✅ upstream (squelette) |

### Réservation
| Élément | Statut |
|---|---|
| Vérification chevauchement de dates (`findOverlapping`) | ✅ notre branche |
| Calendrier Flatpickr avec dates bloquées | ✅ notre branche |
| `findBookedRanges` pour le frontend | ✅ notre branche |
| `findAllForListing`, `findByGuestForListing`, `findOneForDetail` | ✅ upstream |

---

## Features manquantes (à implémenter pour l'évaluation)

### Partie A — Gestion des disponibilités hôte
- [ ] Interface calendrier hôte (vue mensuelle) côté front
- [ ] Formulaire de blocage manuel de dates par l'hôte (`PropertyAvailability`)
- [ ] Intégration des `PropertyAvailability` dans `findOverlapping` (actuellement seules les réservations sont vérifiées)
- [ ] Vérification `property.status === 'published'` dans l'algo de dispo

### Partie B — Parcours de réservation
- [ ] `instantBooking` non géré — doit passer en `confirmed` automatiquement si `true`
- [ ] Tableau de bord hôte : liste des demandes `pending`, accepter/refuser avec motif
- [ ] Processus d'annulation (voyageur et hôte) avec motif + libération des dates
- [ ] Vérification capacité (`guestsCount <= property.maxGuests`) à la soumission
- [ ] Notifications email asynchrones via Symfony Messenger

### Partie C — Moteur de recherche
- [ ] Logique de filtrage dans `/search` (template présent mais controller sans filtres)
- [ ] Filtrage par `destination` (ville/adresse)
- [ ] Filtrage par `checkin` / `checkout` (disponibilité sur une période)
- [ ] Filtrage par `guests` (exclusion si capacité insuffisante)

### Partie D — Notifications transactionnelles
- [ ] Classes `Message/` + `MessageHandler/` Symfony Messenger
- [ ] Transport RabbitMQ (ou fallback DB) dans `messenger.yaml`
- [ ] Email : nouvelle demande `pending` → hôte
- [ ] Email : réservation validée → voyageur + hôte
- [ ] Email : annulation/refus → parties concernées
- [ ] Vérification des envois via Mailpit (`http://localhost:8025`)

### Partie E — Export iCal
- [ ] Endpoint `/api/properties/{id}/calendar.ics?token={secret}`
- [ ] Champ `icalToken` sur `Property` (token unique, révocable par l'hôte)
- [ ] Génération du format `.ics` (VCALENDAR / VEVENT) avec séjours confirmés

### Partie F — Import iCal (BONUS)
- [ ] Commande Symfony `app:ical:sync`
- [ ] Consommation des `PropertyICalSync` (URL + lastSyncAt existent déjà en base)
- [ ] Stratégie de gestion des conflits documentée

### Partie G — Fonctionnalités avancées (BONUS)
| Id | Feature | Statut |
|---|---|---|
| G.1 | Expiration automatique des `pending` après 24h (Worker) | ❌ |
| G.2 | Rappel email J-1 check-in | ❌ |
| G.5 | Timeline voyageur (historique statuts visuels) | ❌ (`ReservationStatusHistory` en base, pas de template) |
| G.6 | Tarification dynamique JS (calcul temps réel) | ❌ |
| G.8 | Notifications in-app (cloche header) | ❌ (`Notification` en base, pas de vue) |

### Livrable obligatoire
- [ ] `conception.txt` à la racine du projet (**à rédiger manuellement — IA interdite**)

---

## Priorités recommandées

```
CRITIQUE (évalué directement)
├── conception.txt                            ← rédiger soi-même
├── instantBooking : pending vs confirmed
├── Accepter / refuser demandes pending (hôte)
├── Annulation avec motif
├── /search avec filtres réels
├── Messenger + Mailer (emails async)
└── Export iCal + token sécurisé

IMPORTANT (bonus solides)
├── Intégration PropertyAvailability dans l'algo dispo
├── Import iCal (app:ical:sync)
├── G.6 tarification dynamique JS
└── G.5 timeline statuts

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
| `src/Controller/ChehController.php` | Nom non métier (controller de test) |
| `src/EventSubscriber/ChehAccessSubscriber.php` | Idem |
