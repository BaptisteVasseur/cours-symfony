Cahier des charges — Plateforme de location immobilière type Airbnb

⸻

1. Présentation du projet

1.1 Objectifs

Concevoir une application web de location de biens immobiliers mettant en relation des propriétaires (hôtes) et des locataires (invités), avec un parcours couvrant la publication, la recherche, la réservation, le paiement, l'évaluation et la fidélisation via un système de gamification.

Le système doit être robuste, scalable et optimisé pour une expérience utilisateur rapide et engageante.

1.2 Contexte

Projet basé sur Symfony 8 avec composants asynchrones (RabbitMQ) pour gérer les opérations critiques (paiements, notifications, gamification) et temps réel via Mercure.

1.3 Cibles utilisateurs

Quatre profils principaux :

* Invités : utilisateurs recherchant et réservant des biens
* Hôtes : utilisateurs proposant des biens à la location
* Administrateurs : gestion de la plateforme, modération et supervision
* Super Administrateurs : accès total à la plateforme

1.4 KPIs

* Nombre d'inscriptions
* Nombre de réservations
* Taux de conversion
* Taux d'occupation moyen
* Valeur moyenne des réservations
* Revenus de commission
* Taux de satisfaction utilisateurs

⸻

2. Périmètre fonctionnel

2.1 Authentification & profils

Fonctionnalités :

* Inscription email/mot de passe
* Connexion sécurisée
* OAuth : Google, Facebook, Apple
* Vérification email
* Réinitialisation de mot de passe
* Double authentification (2FA)
* Gestion du profil :
    * photo, bio, téléphone, langues
    * documents d'identité
    * rôle (hôte, invité ou mixte)
    * historique des réservations
    * statistiques de gamification

2.2 Gestion des propriétés

Les hôtes peuvent créer, modifier, publier et désactiver leurs propriétés.

Chaque propriété inclut :

* Titre, description, type de logement
* Localisation et géolocalisation
* Capacité, chambres, lits, salles de bain
* Prix par nuit, frais de ménage, caution
* Horaires check-in/check-out
* Liste d'équipements et règles de la maison
* Photos multiples (upload, compression automatique, tri)
* Vidéos
* Calendrier de disponibilités avec blocage manuel et synchronisation iCal

2.3 Recherche & filtres

Critères de recherche :

* Localisation
* Dates de séjour
* Nombre de voyageurs
* Prix (min/max)
* Type de bien
* Équipements
* Note minimale
* Accessibilité
* Animaux acceptés

Carte interactive :

* Intégration Google Maps / Mapbox
* Pins dynamiques avec clustering

Tri :

* Prix, popularité, distance, notes, nouveautés

Pagination et résultats triables.

Recommandations :

* Suggestions personnalisées
* Historique de navigation

2.4 Réservations

Workflow complet :

1. Sélection d'un bien
2. Choix des dates
3. Vérification de disponibilité
4. Calcul du tarif total
5. Création d'une réservation (statut : en_attente)
6. Paiement
7. Confirmation automatique

Gestion :

* Acceptation automatique ou manuelle par l'hôte
* Historique des réservations
* Factures PDF
* Politique d'annulation : flexible, modérée, stricte, personnalisée

2.5 Paiements

Intégration PSP :

* Stripe (principal)
* PayPal
* Apple Pay
* Google Pay

Fonctionnalités :

* Paiement sécurisé
* Split payment (commission plateforme / reversement hôte)
* Dépôt de garantie
* Remboursements
* Multi-devise

Statuts de paiement :

* en_attente
* terminé
* échoué
* remboursé

Les paiements déclenchent des événements asynchrones (RabbitMQ).

Tableau de bord hôte — revenus :

* Historique paiements
* Taux d'occupation
* Tarification dynamique
* Promotions
* Export CSV/PDF

2.6 Avis & notation

Les utilisateurs peuvent laisser un avis après un séjour terminé.

Chaque avis inclut :

* Note (1 à 5)
* Commentaire texte
* Photos
* Double notation : hôte ↔ invité

Modération :

* Signalement
* Validation admin
* Détection automatique de fraude

2.7 Messagerie interne

Système de messagerie permettant :

* Communication hôte/invité en temps réel (Mercure)
* Notifications liées aux réservations
* Partage de fichiers/images
* Historique des conversations
* Filtrage anti-spam

2.8 Notifications

Types :

* Email (SendGrid)
* Notifications push (Firebase)
* SMS (Twilio)

Événements déclencheurs :

* Nouvelle réservation
* Annulation
* Confirmation de paiement
* Nouveau message
* Nouvel avis
* Attribution badge/récompense

2.9 Gamification

Système comprenant :

* Badges (ex : "Super hôte", "Voyageur assidu")
* Défis (ex : "5 réservations en 30 jours")
* Récompenses (réductions, avantages, points de fidélité)
* Parrainage

Attribution automatique via événements asynchrones.

2.10 Administration

Fonctionnalités :

* Gestion des utilisateurs (suspension, vérification identité, support)
* Modération des contenus et des annonces
* Supervision des transactions
* Gestion des badges et défis
* Gestion des litiges et réclamations
* Reporting (chiffre d'affaires, réservations, utilisateurs actifs)

⸻

3. Règles métier

3.1 Réservations

| Règle         | Description                                              |
|---------------|----------------------------------------------------------|
| Disponibilité | Réservation impossible si période déjà occupée           |
| Validation    | Réservation doit être payée pour être confirmée          |
| Annulation    | Possible avant date limite selon politique choisie       |
| Statuts       | en_attente → confirmé → terminé / annulé                 |

3.2 Propriétés

| Règle       | Description                                  |
|-------------|----------------------------------------------|
| Publication | Propriété doit contenir au moins 1 photo     |
| Statut      | en_attente → actif → inactif                 |
| Validation  | Vérification des champs obligatoires         |

3.3 Paiements

| Règle         | Description                                              |
|---------------|----------------------------------------------------------|
| Atomicité     | Paiement et confirmation doivent être cohérents          |
| Échec         | Réservation reste en_attente si paiement échoue          |
| Remboursement | Possible selon politique d'annulation                    |
| Commission    | Prélevée automatiquement à la confirmation               |

3.4 Avis

| Règle     | Description                                               |
|-----------|-----------------------------------------------------------|
| Condition | Avis possible uniquement si réservation terminée          |
| Unicité   | Un seul avis par utilisateur et par réservation           |

3.5 Gamification

| Règle       | Description                                        |
|-------------|----------------------------------------------------|
| Attribution | Automatique via événements asynchrones             |
| Expiration  | Certaines récompenses expirent                     |
| Progression | Suivi dans GamificationUserStats                   |

⸻

4. Cas d'utilisation

UC1 — Réserver un bien

Acteur : Invité

Flux principal :

1. Recherche d'un bien
2. Consultation des détails
3. Sélection des dates
4. Validation
5. Paiement
6. Confirmation

Flux alternatif :

* Paiement échoué → retour statut en_attente
* Bien indisponible → message d'erreur

⸻

UC2 — Publier une propriété

Acteur : Hôte

Flux :

1. Création de l'annonce
2. Ajout photos/vidéos
3. Définition disponibilités et tarifs
4. Soumission pour validation
5. Publication

⸻

UC3 — Laisser un avis

Acteur : Invité / Hôte

Conditions :

* Réservation terminée
* Avis non encore soumis pour cette réservation

⸻

UC4 — Gérer un litige

Acteur : Administrateur

Flux :

1. Réception d'une réclamation
2. Analyse des échanges et paiements
3. Décision d'arbitrage
4. Remboursement ou clôture

⸻

5. Architecture technique

5.1 Architecture globale

Architecture en couches :

[ Controller ]
      ↓
[ Application Services ]
      ↓
[ Domain / Entities ]
      ↓
[ Infrastructure ]

5.2 Frontend

* Twig + TailwindCSS
* Responsive : desktop, tablette, mobile

5.3 Backend

* Symfony 8 / PHP 8.4
* API REST via API Platform
* Doctrine ORM 3.6

5.4 Messaging asynchrone (RabbitMQ)

Utilisé pour :

* Envoi d'emails et SMS
* Mise à jour gamification
* Notifications push
* Traitement paiements

5.5 Temps réel (Mercure)

* Messagerie hôte/invité
* Notifications en direct

5.6 Cache (Redis)

Utilisé pour :

* Résultats de recherche
* Sessions
* Données fréquemment consultées

5.7 CI/CD

* GitHub Actions
* Tests automatisés à chaque push

⸻

6. Modèle de données

6.1 Relations principales

User ───< Property
User ───< Booking >─── Property
Booking ─── Payment
Booking ─── Review
User ───< Message
Property ───< Availability
Property ───< PropertyPhoto
User ───< UserBadge >── Badge
User ───< UserChallenge >── Challenge
User ───< UserReward >── Reward
User ─── GamificationUserStats

6.2 Description synthétique

* User : utilisateur principal (hôte / invité / admin)
* Property : bien immobilier avec médias et disponibilités
* Booking : réservation avec statut et politique d'annulation
* Payment : transaction financière (PSP + commission)
* Review : avis double notation hôte ↔ invité
* Message : communication interne temps réel
* GamificationUserStats : progression globale gamification

⸻

7. Sécurité

Exigences techniques :

* HTTPS obligatoire
* Hashage des mots de passe (bcrypt/argon2)
* Protection CSRF
* Protection XSS/injection SQL
* Limitation brute force (rate limiting)
* Double authentification (2FA)
* Journalisation des actions sensibles
* Sauvegardes automatiques PostgreSQL

Conformité :

* RGPD (consentement cookies, droit à l'oubli, export données)
* PCI-DSS (données carte via Stripe uniquement, jamais stockées)

⸻

8. SEO & Performance

SEO :

* URLs optimisées (slugs)
* Meta tags dynamiques
* Sitemap XML
* Structured data (Schema.org)

Performance :

* Temps de réponse < 300 ms pour 90 % des requêtes
* Lazy loading images
* CDN pour médias
* Cache Redis pour recherches
* Optimisation images automatique (compression à l'upload)

⸻

9. Exigences non-fonctionnelles

9.1 Disponibilité

* Disponibilité > 99,9 %
* Monitoring et alerting

9.2 Scalabilité

* Architecture compatible horizontal scaling
* RabbitMQ pour découplage des traitements lourds

9.3 Accessibilité

* Conformité WCAG 2.1 niveau AA
* Navigation clavier
* Contrastes adaptés

⸻

10. Contraintes techniques

* PHP 8.4
* Symfony 8.0
* PostgreSQL 16
* Doctrine ORM 3.6
* Redis (cache et sessions)
* RabbitMQ (messaging asynchrone)
* Mercure (temps réel)

Conventions :

* Tables : snake_case
* Propriétés entités : camelCase
* Langue : français pour labels utilisateurs

⸻

11. Planning prévisionnel

| Phase               | Durée          |
|---------------------|----------------|
| Analyse & conception| 2 semaines     |
| UX/UI               | 3 semaines     |
| Développement MVP   | 12 à 16 semaines|
| QA & Recette        | 3 semaines     |
| Déploiement         | 1 semaine      |

⸻

12. MVP recommandé

Inclus dans le MVP :

* Authentification (email + OAuth)
* Gestion des annonces
* Recherche avec carte
* Réservation
* Paiement Stripe
* Messagerie
* Avis
* Notifications email
* Dashboard hôte / admin
* Gamification basique (badges)

Exclu du MVP (Phase 2) :

* IA recommandations et tarification dynamique
* Apps mobiles natives
* Programme fidélité avancé
* Multi-devise
* Expériences / activités

⸻

13. Modèle économique

Sources de revenus :

* Commission sur chaque réservation (% configurable)
* Abonnements premium hôtes
* Mise en avant d'annonces
* Programme de parrainage

⸻

14. Livrables attendus

* Module authentification (OAuth, 2FA)
* Module propriétés (annonces, médias, calendrier)
* Module recherche (filtres, carte)
* Module réservation (workflow complet)
* Module paiement (Stripe, split, remboursement)
* Module avis (double notation, modération)
* Module messagerie (temps réel Mercure)
* Module notifications (email, push, SMS)
* Module gamification (badges, défis, récompenses)
* Interface administration (backoffice complet)
* Tests unitaires et fonctionnels
* Documentation API (API Platform)
* Documentation technique

⸻

15. Critères de recette

Fonctionnels :

* Réservation complète opérationnelle de bout en bout
* Paiements validés (Stripe sandbox + production)
* Notifications fonctionnelles sur tous canaux
* Responsive validé sur desktop / tablette / mobile

Techniques :

* Temps de chargement < 3 sec (LCP)
* Disponibilité > 99,9 %
* Sécurité validée (audit OWASP)
* Tests couvrant les flux critiques

⸻

16. Glossaire

| Terme       | Définition                                    |
|-------------|-----------------------------------------------|
| Hôte        | Propriétaire proposant un bien à la location  |
| Invité      | Utilisateur louant un bien                    |
| Réservation | Transaction de location                       |
| Badge       | Récompense symbolique (gamification)          |
| Défi        | Objectif à atteindre (gamification)           |
| Récompense  | Avantage obtenu (réduction, points)           |
| Split payment| Partage automatique du montant entre plateforme et hôte |
| PSP         | Prestataire de services de paiement           |
