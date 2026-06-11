# CAHIER DES CHARGES — PLATEFORME DE LOCATION TYPE AIRBNB

## Version : Copie Conforme Fonctionnelle et UX d’Airbnb

## Document SRS (Software Requirements Specification)

---

# 1. PRÉSENTATION DU PROJET

## 1.1 Nom du projet

Nom temporaire : **HomeStay Clone**

Le nom définitif sera déterminé après validation branding et disponibilité juridique/domaines.

---

# 1.2 Objectif du projet

Développer une plateforme web et mobile reproduisant le fonctionnement, l’ergonomie, les parcours utilisateurs, les mécaniques métier et l’expérience globale d’Airbnb.

Le système devra permettre :

* la mise en location de logements,
* la réservation courte et moyenne durée,
* la gestion sécurisée des paiements,
* la communication entre voyageurs et hôtes,
* la gestion administrative complète,
* le support multi-pays,
* la montée en charge internationale.

---

# 1.3 Vision Produit

Créer une marketplace mondiale de location saisonnière avec :

* UX/UI premium identique aux standards Airbnb,
* haute disponibilité,
* forte scalabilité,
* architecture cloud moderne,
* expérience mobile first,
* sécurité bancaire et réglementaire complète.

---

# 1.4 Objectifs Business

| Objectif                 | Description                      |
| ------------------------ | -------------------------------- |
| Acquisition utilisateurs | Attirer voyageurs et hôtes       |
| Génération de revenus    | Commissions sur réservations     |
| Expansion internationale | Support mondial                  |
| Fidélisation             | Maximiser rétention utilisateurs |
| Automatisation           | Réduction des coûts support      |

---

# 1.5 Positionnement

Le produit doit être une plateforme équivalente à Airbnb en termes :

* d’expérience utilisateur,
* de design,
* de performances,
* de fonctionnalités,
* de fluidité mobile,
* de confiance et sécurité.

---

# 1.6 Personas

## Voyageur

| Élément  | Description           |
| -------- | --------------------- |
| Profil   | Voyageur loisir       |
| Objectif | Réserver rapidement   |
| Attentes | Simplicité, confiance |

---

## Hôte

| Élément  | Description     |
| -------- | --------------- |
| Profil   | Propriétaire    |
| Objectif | Générer revenus |
| Attentes | Gestion simple  |

---

## Administrateur

| Élément  | Description           |
| -------- | --------------------- |
| Objectif | Superviser plateforme |
| Besoin   | Outils de modération  |

---

# 1.7 Parcours Utilisateur

## Parcours Voyageur

1. Inscription
2. Recherche logement
3. Application filtres
4. Consultation annonce
5. Réservation
6. Paiement
7. Confirmation
8. Messagerie hôte
9. Séjour
10. Avis

---

## Parcours Hôte

1. Création compte
2. Vérification identité
3. Création annonce
4. Ajout photos
5. Gestion calendrier
6. Publication
7. Gestion réservations
8. Paiements

---

# 2. FONCTIONNALITÉS PRINCIPALES

# 2.1 MODULE AUTHENTIFICATION

## Inscription

### Méthodes supportées

* Email/password
* Google OAuth
* Apple Sign-In
* Facebook Login

---

## Champs utilisateur

| Champ             | Type    |
| ----------------- | ------- |
| id                | UUID    |
| prénom            | VARCHAR |
| nom               | VARCHAR |
| email             | VARCHAR |
| téléphone         | VARCHAR |
| mot_de_passe_hash | TEXT    |
| photo             | TEXT    |
| langue            | VARCHAR |
| devise            | VARCHAR |

---

## Vérification Email

Fonctionnalités :

* lien confirmation,
* expiration token,
* renvoi email.

---

## Connexion

### Sécurité

* JWT access token,
* refresh token,
* expiration session,
* cookies HTTPOnly,
* CSRF protection.

---

## Mot de passe oublié

Workflow :

1. saisie email,
2. email reset,
3. token sécurisé,
4. nouveau mot de passe.

---

# 2.2 GESTION PROFIL UTILISATEUR

## Fonctionnalités

* photo profil,
* biographie,
* numéro téléphone,
* langue,
* devise,
* préférences notifications.

---

## Vérification identité

### Documents acceptés

* passeport,
* carte nationale,
* permis de conduire.

### Processus

* upload document,
* selfie,
* validation manuelle/automatique.

---

# 2.3 RECHERCHE LOGEMENTS

# Barre de recherche principale

## Critères

* destination,
* dates,
* voyageurs.

---

# Recherche avancée

## Filtres

| Filtre                  | Description            |
| ----------------------- | ---------------------- |
| Prix                    | Min/max                |
| Type logement           | Appartement, maison    |
| Chambres                | Nombre                 |
| Lits                    | Nombre                 |
| Salles de bain          | Nombre                 |
| Équipements             | Wifi, cuisine, piscine |
| Réservation instantanée | Oui/non                |

---

# Carte interactive

## Fonctionnalités

* affichage logements,
* clustering,
* zoom,
* géolocalisation.

Technologie :

* Mapbox ou Google Maps.

---

# 2.4 FICHE LOGEMENT

# Sections

## Galerie photos

* slider,
* plein écran,
* lazy loading.

---

## Informations principales

| Champ       | Description          |
| ----------- | -------------------- |
| titre       | Nom logement         |
| description | Description complète |
| capacité    | Nombre voyageurs     |
| chambres    | Quantité             |
| lits        | Quantité             |
| salles_bain | Quantité             |

---

## Équipements

Liste standard :

* wifi,
* cuisine,
* télévision,
* climatisation,
* chauffage,
* parking.

---

## Calendrier disponibilités

* dates réservées,
* prix par nuit,
* minimum stay.

---

## Bloc réservation

Affiche :

* prix,
* frais ménage,
* taxes,
* total.

---

## Avis utilisateurs

### Contenu

* note globale,
* commentaires,
* photos éventuelles.

---

# 2.5 RÉSERVATIONS

## Workflow réservation

1. Sélection dates
2. Vérification disponibilité
3. Calcul prix
4. Paiement
5. Confirmation réservation

---

## États réservation

| Statut    | Description |
| --------- | ----------- |
| pending   | En attente  |
| confirmed | Confirmée   |
| cancelled | Annulée     |
| completed | Terminée    |

---

## Politique annulation

Types :

* flexible,
* modérée,
* stricte.

---

# 2.6 PAIEMENTS

# Intégration Stripe Connect

## Fonctionnalités

* paiement CB,
* reversement hôtes,
* remboursements,
* commissions plateforme.

---

## Moyens de paiement

* Visa,
* Mastercard,
* Apple Pay,
* Google Pay,
* PayPal.

---

## Multi-devises

Support :

* EUR,
* USD,
* GBP,
* CAD.

---

# 2.7 MESSAGERIE

# Fonctionnalités

* temps réel,
* notifications,
* pièces jointes,
* historique.

---

## Technologies

* WebSockets,
* Socket.IO.

---

# 2.8 WISHLIST / FAVORIS

Fonctionnalités :

* sauvegarde logements,
* collections personnalisées,
* synchronisation mobile/web.

---

# 2.9 NOTIFICATIONS

## Types

| Canal | Description |
| ----- | ----------- |
| Email | Réservation |
| Push  | Messages    |
| SMS   | Urgences    |

---

# 2.10 AVIS ET NOTES

## Conditions

Seuls les utilisateurs ayant terminé un séjour peuvent laisser un avis.

---

## Critères de notation

| Critère       | Note |
| ------------- | ---- |
| propreté      | /5   |
| communication | /5   |
| emplacement   | /5   |
| précision     | /5   |

---

# 3. MODULE HÔTE

# 3.1 Création d’annonce

## Champs

| Champ         | Type    |
| ------------- | ------- |
| titre         | VARCHAR |
| description   | TEXT    |
| type_logement | ENUM    |
| prix_nuit     | DECIMAL |
| adresse       | TEXT    |

---

# 3.2 Upload Photos

## Contraintes

* drag & drop,
* max 50 photos,
* compression automatique.

---

# 3.3 Gestion calendrier

Fonctionnalités :

* blocage dates,
* prix personnalisés,
* disponibilités.

---

# 3.4 Gestion réservations

Actions :

* accepter,
* refuser,
* annuler.

---

# 3.5 Revenus

Dashboard :

* revenus mensuels,
* historique paiements,
* statistiques réservations.

---

# 4. ADMINISTRATION

# 4.1 Dashboard Admin

## Modules

* utilisateurs,
* annonces,
* paiements,
* litiges,
* modération.

---

# 4.2 Gestion Utilisateurs

Actions :

* suspendre,
* bannir,
* vérifier identité.

---

# 4.3 Gestion Paiements

* suivi transactions,
* remboursements,
* commissions.

---

# 4.4 Modération

* suppression annonces,
* validation contenus,
* gestion signalements.

---

# 5. UX/UI DESIGN

# 5.1 Direction Artistique

Le design doit reprendre les principes Airbnb :

* minimaliste,
* moderne,
* fort espace blanc,
* cartes arrondies,
* navigation fluide.

---

# 5.2 Palette Couleurs

| Usage      | Couleur |
| ---------- | ------- |
| Primaire   | #FF385C |
| Texte      | #222222 |
| Fond       | #FFFFFF |
| Gris clair | #F7F7F7 |

---

# 5.3 Typographie

Police principale :

* Circular (ou alternative Inter).

---

# 5.4 Responsive Design

Compatibilité :

* mobile,
* tablette,
* desktop.

---

# 5.5 Dark Mode

Support complet :

* automatique,
* manuel.

---

# 5.6 Accessibilité

Conformité WCAG 2.1 AA.

---

# 6. PAGES À DÉVELOPPER

# Front Public

* Landing page
* Recherche logements
* Fiche logement
* Checkout
* Wishlist
* Profil utilisateur
* Messagerie
* Notifications
* Paramètres
* FAQ
* Contact
* CGU
* Politique confidentialité

---

# Hôte

* Dashboard
* Gestion annonces
* Calendrier
* Revenus
* Réservations

---

# Admin

* Dashboard
* Modération
* Paiements
* Support

---

# 7. ARCHITECTURE TECHNIQUE

# 7.1 Frontend

| Technologie  | Usage            |
| ------------ | ---------------- |
| React        | UI               |
| Next.js      | SSR              |
| TypeScript   | Typage           |
| Tailwind CSS | Styling          |
| Zustand      | State management |

---

# 7.2 Backend

| Technologie | Usage             |
| ----------- | ----------------- |
| Node.js     | Runtime           |
| NestJS      | Framework         |
| REST API    | Communication     |
| GraphQL     | Requêtes avancées |

---

# 7.3 Base de données

| Technologie   | Usage               |
| ------------- | ------------------- |
| PostgreSQL    | Données principales |
| Redis         | Cache               |
| Elasticsearch | Recherche           |

---

# 7.4 Infrastructure

## Cloud

AWS recommandé :

* EC2,
* RDS,
* S3,
* CloudFront,
* Route53.

---

## Conteneurisation

* Docker,
* Kubernetes.

---

# 7.5 CI/CD

* GitHub Actions,
* Docker Registry,
* déploiement automatique.

---

# 8. APPLICATION MOBILE

# Technologie

React Native recommandé.

---

# Fonctionnalités

* authentification,
* réservation,
* push notifications,
* upload caméra,
* géolocalisation.

---

# 9. SÉCURITÉ

# Authentification

* JWT,
* refresh tokens,
* MFA optionnel.

---

# Protection API

* rate limiting,
* validation payloads,
* protection brute force.

---

# Données

* chiffrement AES-256,
* HTTPS TLS 1.3.

---

# Conformité

* RGPD,
* PCI DSS.

---

# 10. SEO & MARKETING

# SEO Technique

* SSR Next.js,
* sitemap,
* meta tags,
* OpenGraph.

---

# Analytics

* Google Analytics 4,
* Meta Pixel.

---

# Blog

CMS intégré pour SEO organique.

---

# 11. MODÈLE ÉCONOMIQUE

| Revenus             | Description   |
| ------------------- | ------------- |
| Commission voyageur | % réservation |
| Commission hôte     | % réservation |
| Frais service       | Frais fixes   |

---

# 12. STRUCTURE BDD

# Tables principales

```sql
USERS
LISTINGS
BOOKINGS
PAYMENTS
REVIEWS
MESSAGES
NOTIFICATIONS
WISHLISTS
CALENDAR_AVAILABILITY
```

---

# Relations

```text
USER 1:N LISTINGS
USER 1:N BOOKINGS
LISTING 1:N BOOKINGS
BOOKING 1:1 PAYMENT
BOOKING 1:N MESSAGES
```

---

# 13. APIs PRINCIPALES

# Auth

```http
POST /auth/register
POST /auth/login
POST /auth/logout
```

---

# Listings

```http
GET /listings
GET /listings/:id
POST /listings
PUT /listings/:id
DELETE /listings/:id
```

---

# Bookings

```http
POST /bookings
GET /bookings/:id
```

---

# Payments

```http
POST /payments/checkout
POST /payments/refund
```

---

# 14. PERFORMANCE & SCALABILITÉ

# Objectifs

| KPI              | Valeur |
| ---------------- | ------ |
| Temps chargement | <2 sec |
| Uptime           | 99.9%  |
| Latence API      | <200ms |

---

# Scalabilité

* autoscaling Kubernetes,
* CDN global,
* cache Redis,
* DB replication.

---

# 15. TESTS

# Types

* unitaires,
* intégration,
* E2E,
* charge,
* sécurité.

---

# Outils

* Jest,
* Cypress,
* Playwright.

---

# 16. ÉQUIPE RECOMMANDÉE

| Poste           | Nombre |
| --------------- | ------ |
| Product Manager | 1      |
| UX/UI Designer  | 2      |
| Frontend Dev    | 4      |
| Backend Dev     | 4      |
| Mobile Dev      | 2      |
| DevOps          | 2      |
| QA              | 2      |

---

# 17. PLANNING

# MVP

| Phase    | Durée      |
| -------- | ---------- |
| UX/UI    | 4 semaines |
| Backend  | 8 semaines |
| Frontend | 8 semaines |
| Mobile   | 6 semaines |
| QA       | 4 semaines |

---

# Durée totale

6 à 9 mois selon équipe.

---

# 18. BUDGET ESTIMATIF

| Poste          | Budget       |
| -------------- | ------------ |
| Design UX/UI   | 20k–40k€     |
| Développement  | 200k–500k€   |
| Infrastructure | 5k–20k€/mois |
| QA/Sécurité    | 20k–50k€     |

---

# 19. LIVRABLES ATTENDUS

* Maquettes Figma,
* Design System,
* API Swagger,
* Documentation technique,
* Architecture cloud,
* Schéma BDD,
* Applications web,
* Applications mobiles,
* Tests automatisés,
* Pipelines CI/CD.

---

# 20. CONCLUSION

Le projet consiste à reproduire fidèlement l’expérience Airbnb avec :

* une architecture scalable,
* une UX/UI premium,
* un système de réservation complet,
* une gestion hôte/voyageur/admin,
* des paiements sécurisés,
* des applications web et mobiles modernes.

Le produit devra être industrialisable à l’échelle internationale et supporter une forte montée en charge.
