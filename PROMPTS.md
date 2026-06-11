# Traçabilité des prompts — Moteur de Réservation & Calendrier iCal

Auteur : Fu Danny
Assistant utilisé : Claude (Anthropic)

Pour chaque fonctionnalité : le prompt que j'ai utilisé, la difficulté rencontrée et la solution retenue.

---

## 0. Prompt initial — cadrage de la mission

Prompt :
> Tu es un développeur Symfony senior (Symfony 8 / PHP 8.4 / PostgreSQL, avec API Platform, Messenger
> et Mailer-Mailpit, le tout sous Docker). On part d'un clone Airbnb déjà en place (entités,
> authentification, rôles, contrôleurs, formulaires, fixtures) et je veux que tu développes de A à Z
> le module « moteur de réservation et calendrier iCal ».
>
> Avant d'écrire la moindre ligne de code : explore tout le code existant et ne crée rien qui entre
> en conflit avec l'architecture en place (on a déjà une entité `Listing`, ne la renomme pas en
> `Property` ; il y a aussi un `ListingAvailability` par jour, ne le détourne pas). Produis d'abord un
> rapport d'analyse de l'existant (entités, relations, routes, services, rôles). Lors de la planification,
> réponds précisément à : modélisation des dates (le jour de check-out est-il réservable ?), gestion des états
> (une demande en attente bloque-t-elle les dates ? risque de blocage abusif vs overbooking),
> concurrence (deux validations à la milliseconde sur les mêmes dates), performance
> (valider 20 nuits sans 20 requêtes SQL), asynchronisme (pourquoi envoyer l'email après le `flush()`
> et que faire si la file tombe), et structure SQL (table de périodes `start/end` ou table de jours).
>
> Côté code, je veux une architecture orientée services : toute la logique métier sort des
> contrôleurs (BookingService, AvailabilityService, CalendarService, ICalExportService,
> ICalImportService, NotificationService, et une machine à états pour les réservations). Implémente
> ensuite, dans l'ordre : la gestion des disponibilités (calendrier hôte + blocages), le tunnel de
> réservation (instantané ou sur demande, modération par l'hôte, annulation avec motif), le moteur de
> recherche, les emails transactionnels asynchrones (Messenger + Mailpit), l'export iCal sécurisé par
> token, l'import iCal, et au moins un bonus.
>
> Exigences transverses tout du long : code propre (SOLID, DRY, KISS, PSR-12, types stricts,
> DTO / Value Objects si pertinent) ; sécurité par Voters (seul l'hôte gère ses disponibilités, seul
> le voyageur concerné peut annuler sa réservation) ; performance (pas de N+1 ni de requête par jour,
> privilégie JOIN / EXISTS / QueryBuilder) ; et des tests unitaires et fonctionnels. À la fin,
> donne-moi l'arborescence des fichiers créés, les migrations, les commandes à lancer et la liste des
> fonctionnalités et bonus terminés. Avant te lancer dans le code, attends ma validation du cadrage.
> Aide de toi du fichier `conception.txt` pour t'aider à mettre en place le projet.
> C'est un document de conception détaillé qui explique les choix techniques et les solutions retenues
> pour chaque problématique soulevée.

Cadrage : ce prompt pose le contexte de toute la session. Réponse en deux temps avant tout code —
un rapport d'analyse de l'existant (mapping `Property → Listing`, `ListingAvailability` par-jour
conservé, nouvelle entité période `AvailabilityBlock`) en se basant sur le fichier `conception.txt`.
Tout le reste du document détaille chaque fonctionnalité issue de ce cadrage.

---

## 1. Gestion des disponibilités

Prompt :
> Mets en place la gestion des disponibilités d'un logement. Je veux une vue calendrier mensuelle
> pour l'hôte et qu'il puisse bloquer des périodes manuellement (travaux, usage perso). Crée surtout
> un service `isAvailable()` qui vérifie qu'un logement est publié, a la capacité suffisante, et
> qu'aucun blocage ni réservation confirmée ne chevauche les dates demandées. Optimise les requêtes,
> je ne veux pas d'une boucle qui teste jour par jour.

Problématique : valider une plage de N nuits sans requête par jour, et afficher une grille mensuelle.

Résolution : `AvailabilityService::isAvailable()` en 2 requêtes EXISTS (réservations confirmées +
blocages), prédicat semi-ouvert `start < :out AND end > :in`. `CalendarService` construit la grille
(2 requêtes pour le mois, marquage en mémoire). `HostAvailabilityController` + `host/calendar.html.twig`.

---

## 2. Parcours de réservation

Prompt :
> Implémente le tunnel de réservation complet. Passe le statut en enum PHP
> (pending/confirmed/cancelled/completed). En réservation instantanée la résa est confirmée
> directement, sinon elle reste en attente de validation par l'hôte. Il me faut une page récap
> (checkout) avec le prix total et une vérification de disponibilité au moment de valider, une page
> côté hôte pour accepter ou refuser avec un motif obligatoire, et l'annulation (par le voyageur ou
> l'hôte, motif obligatoire) qui relibère les dates. Garde qui a annulé et quand.

Problématique : orchestrer un workflow multi-acteurs en gardant la cohérence d'état et la
disponibilité même en cas d'accès concurrent.

Résolution : `BookingService` (transaction + verrou pessimiste), `BookingStateMachineService`
(transitions autorisées), `ReservationController` (tunnel), `HostBookingController` (modération).
Migration de `Booking.bookingStatus` vers l'enum + ajout de `cancelledBy`.

---

## 3. Moteur de recherche

Prompt :
> Crée la page /search avec trois filtres : destination (ville ou adresse), dates d'arrivée et de
> départ, et nombre de voyageurs. N'affiche que les logements réellement disponibles sur la période.
> Fais tout le filtrage en QueryBuilder, je ne veux aucun filtrage en PHP.

Problématique : filtrer la disponibilité sur une plage de dates en SQL pur, sans itérer en PHP.

Résolution : `ListingRepository::search()` en QueryBuilder avec des NOT EXISTS corrélés (réservations
confirmées + blocages) — coût constant, 100 % SQL.

---

## 4. Notifications email asynchrones

Prompt :
> Rends tous les emails de réservation asynchrones avec Messenger (on a un worker et le transport
> doctrine). Crée un message et un handler dédié pour chaque cas : nouvelle demande, confirmation,
> refus, annulation, avec des templates Twig, et le tout doit être vérifiable dans Mailpit.

Problématique : garantir que l'email parte après la persistance, sans bloquer le voyageur.

Résolution : 4 messages (UUID seul) routés sur le transport `async` (doctrine://default), 4 handlers
dédiés qui rechargent l'agrégat, 4 templates. Dispatch après le flush + try/catch tolérant aux
pannes. Vérifié dans Mailpit.

---

## 5. Export iCal sécurisé

Prompt :
> Chaque logement doit exposer son calendrier en iCal sécurisé. Ajoute un calendarToken généré
> automatiquement et une route /api/properties/{id}/calendar.ics?token=... Vérifie le token
> strictement et renvoie un vrai fichier .ics conforme RFC 5545 listant les réservations confirmées.
> Mets la génération dans un service dédié.

Problématique : produire un `.ics` strictement conforme et sécuriser l'accès sans authentification
de session.

Résolution : `ICalExportService` (VCALENDAR/VEVENT, DTEND exclusif, pliage à 75 octets, CRLF,
échappement). `calendarToken` (32 octets) comparé en temps constant via `hash_equals`.

---

## 6. Import iCal

Prompt :
> Ajoute la synchro inverse : une commande app:ical:sync qui récupère un flux iCal externe avec
> HttpClient, le parse et crée les indisponibilités correspondantes. Explique comment tu gères les
> doublons, la disparition d'événements distants et les conflits avec des réservations existantes.

Problématique : synchroniser un flux distant de façon idempotente et gérer les conflits.

Résolution : `ICalImportService` (parseur avec dépliage des lignes) + commande `app:ical:sync`
(HttpClient). Réconciliation par `external_uid` (upsert, suppression des événements disparus,
avertissement en cas de conflit avec une résa confirmée, et on ne touche jamais aux blocs `manual`).

---

## 7. Fonctionnalités bonus

Prompt :
> Ajoute quelques bonus : l'expiration automatique des demandes en attente depuis plus de 24h, une
> timeline de l'historique de la réservation (statut, date, auteur, commentaire), la mise à jour du
> prix total en AJAX au checkout sans recharger la page, et des notifications in-app avec une cloche
> dans le header.

Résolution :
- G.1 : commande `app:bookings:expire-pending` (option `--hours`).
- G.5 : entité `BookingHistory` écrite à chaque transition + timeline sur la fiche réservation.
- G.6 : endpoint JSON `/api/listings/{id}/quote` + JS `fetch` qui met à jour le total au checkout.
- G.8 : `NotificationService` + cloche dans le header (extension Twig `unread_notifications_count`).

---

## 8. Rappel de check-in

Prompt :
> Implémente le rappel de check-in : un mail envoyé au voyageur la veille de son arrivée (J-1) avec
> les informations d'accès au logement. Fais en sorte qu'il ne parte pas deux fois si la commande
> tourne plusieurs fois.

Problématique : envoyer un rappel J-1 sans double envoi si la commande cron est rejouée.

Résolution : champ `Booking.checkinReminderSentAt` (garde-fou d'idempotence),
`BookingRepository::findNeedingCheckinReminder()` (filtre ce champ), message + handler async,
template avec les infos d'accès (adresse + contact hôte), commande `app:bookings:checkin-reminder`
(option `--days`). 2 tests dédiés (envoi + non-doublon).

---

## 9. Problématiques techniques résolues en cours d'implémentation

Ces points ne venaient pas d'un prompt précis : ils sont apparus à l'exécution (tests, build) et ont
été résolus en itérant erreur → diagnostic → correctif. C'est la partie la plus représentative du
travail d'ingénierie.

| # | Problème rencontré | Diagnostic | Correctif |
|---|---|---|---|
| 1 | `SQLSTATE[0A000] FOR UPDATE cannot be applied to the nullable side of an outer join` au moment du verrou pessimiste | `em->find(Listing, id, PESSIMISTIC_WRITE)` joint en LEFT JOIN l'association OneToOne inverse (`location`) ; PostgreSQL refuse `FOR UPDATE` sur un outer join | `ListingRepository::findForUpdate()` en DQL sur l'entité racine seule + `setLockMode` |
| 2 | `Class "AvailabilityService" is declared "final" and cannot be doubled` (PHPUnit) | On ne peut pas mocker une classe `final` | Test de `BookingService` avec les vrais collaborateurs `final`, et seulement les repositories / EM mockés |
| 3 | Migration `ADD calendar_token NOT NULL` en échec sur les lignes existantes | Aucune valeur par défaut pour les logements déjà en base | Migration en 3 temps : colonne nullable → `UPDATE` (2× md5, sans pgcrypto) → `SET NOT NULL` + index UNIQUE |
| 4 | L'endpoint iCal renvoyait 302 → /login au lieu de 403 sur token invalide | `createAccessDeniedException()` déclenche l'entry point de sécurité pour un visiteur anonyme | Lever une `AccessDeniedHttpException` (403 HTTP pure, non interceptée par la sécurité) |
| 5 | Migrer `bookingStatus` (string) vers un enum sans casser les données | Les fixtures stockaient déjà `pending/confirmed/...` | Enum backed sur ces mêmes chaînes → mapping `enumType` sans migration de données |
| 6 | Mailpit restait vide après une réservation | Le worker Messenger est un process long : il tournait avec l'ancien code (handlers ajoutés après son démarrage) | `docker compose restart messenger-worker` (en prod : `messenger:stop-workers` au déploiement) |
| 7 | Isoler les tests d'intégration sans polluer la base | Les tests écrivent dans `app_test` | `beginTransaction()` / `rollback()` autour de chaque test + transport Messenger `in-memory` en env de test |

---

Note : l'IA a servi d'accélérateur, mais les choix de conception (intervalle semi-ouvert, verrou
pessimiste plutôt qu'optimiste, table de périodes, idempotence des commandes) et les diagnostics
ci-dessus ont été validés et compris à chaque étape. Le détail des décisions est dans `conception.txt`.
