# Rapport de Travail - Évaluation Continue Symfony

## 1. Conception
- Création de `conception.txt` répondant aux 6 problématiques techniques (concurrence, performance, modélisation temporelle).

## 2. Développement du Moteur de Réservation
- **BookingService** : Centralisation de la logique métier (Accept/Reject/Cancel).
- **Host Moderation** : Ajout des routes et contrôleurs permettant à l'hôte d'accepter ou refuser des demandes avec motif. Un jeton CSRF est utilisé pour chaque action.
- **Cycle de vie** : Gestion automatique du statut `confirmed` pour les logements en `instantBooking`.

## 3. Gestion des disponibilités
- **HostPropertyController** : Permet à l'hôte de déclarer des périodes d'indisponibilité manuelles.
- **Contrôle de superposition** : Les réservations et blocages manuels sont pris en compte dans l'algorithme de recherche (`PropertyRepository::findSearch`).

## 4. iCal (Import/Export)
- **Export (.ics)** : Route `/api/properties/{id}/calendar.ics` sécurisée par un token spécifique au logement.
- **Import (Bonus)** : Commande Symfony `app:ical:sync` implémentée pour synchroniser des flux externes.

## 5. Notifications & Performance
- **Asynchronisme** : Configuration de Symfony Messenger (`messenger.yaml`) pour l'envoi des emails en arrière-plan.
- **Notifications Emails** : Envoi au voyageur lors de la confirmation ou du refus d'une réservation via `BookingService`.

## 6. Interface Utilisateur
- **Vue Hôte** : Création d'un tableau de bord de modération (`templates/front/host/reservation_index.html.twig`).
- **Correction des Assets** : Résolution de l'erreur Stimulus via `importmap:install`.
