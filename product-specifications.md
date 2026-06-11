# 📄 Cahier des charges fonctionnel — Clone d’Airbnb

## 1. 🎯 Contexte et objectif du projet

Le projet consiste à développer une plateforme web (et éventuellement mobile) permettant la mise en relation entre des hôtes proposant des logements et des voyageurs recherchant des hébergements.

### Objectif principal
Permettre la recherche, la réservation et la gestion de locations de courte durée de manière simple, sécurisée et fluide.

---

## 2. 👥 Utilisateurs cibles

### 2.1 Voyageurs
- Recherchent un logement
- Réservent un séjour
- Laissent des avis

### 2.2 Hôtes
- Publient des annonces
- Gèrent leurs disponibilités
- Suivent leurs réservations et revenus

### 2.3 Administrateurs (back-office)
- Modèrent les annonces
- Gèrent les utilisateurs
- Résolvent les litiges

---

## 3. 🧭 Parcours utilisateurs

### 3.1 Voyageur
1. Inscription / connexion
2. Recherche de logement (ville, dates, prix)
3. Consultation fiche logement
4. Réservation + paiement
5. Confirmation
6. Séjour
7. Avis

### 3.2 Hôte
1. Création de compte hôte
2. Création d’annonce
3. Ajout photos, description, prix
4. Gestion calendrier
5. Réception de réservations
6. Suivi des revenus

---

## 4. 🧩 Fonctionnalités principales

## 4.1 Authentification
- Inscription (email, Google, Apple)
- Connexion / déconnexion
- Réinitialisation mot de passe
- Vérification email

---

## 4.2 Gestion des profils
- Profil utilisateur
- Photo de profil
- Historique des réservations
- Vérification d’identité (optionnel)

---

## 4.3 Recherche de logements
- Recherche par :
    - Ville / localisation
    - Dates
    - Nombre de voyageurs
    - Budget

### Filtres
- Type de logement
- Équipements (wifi, piscine, etc.)
- Note moyenne

- Carte interactive (type Google Maps)

---

## 4.4 Annonces logements
Chaque annonce contient :
- Titre
- Description
- Galerie photos
- Prix par nuit
- Frais additionnels (ménage, service)
- Disponibilités
- Règles du logement
- Localisation
- Équipements

---

## 4.5 Réservation
- Sélection des dates
- Calcul du prix total
- Statuts :
    - En attente
    - Confirmée
    - Annulée
- Politique d’annulation

---

## 4.6 Paiement
- Paiement en ligne (Stripe / PayPal)
- Gestion des commissions plateforme
- Facturation automatique

---

## 4.7 Messagerie
- Chat entre hôte et voyageur
- Notifications temps réel
- Historique des messages

---

## 4.8 Avis et notation
- Note sur 5 étoiles
- Commentaires
- Publication après séjour uniquement

---

## 4.9 Tableau de bord hôte
- Gestion des annonces
- Calendrier des réservations
- Suivi des revenus
- Statistiques (vues, taux de conversion)

---

## 4.10 Admin panel
- Validation des annonces
- Gestion utilisateurs
- Modération contenu
- Gestion des litiges

---

## 5. ⚙️ Règles de gestion

- Une réservation bloque automatiquement les dates
- Paiement requis pour confirmer une réservation
- Un utilisateur ne peut laisser un avis qu’après séjour
- Annulation selon politique définie par l’hôte

---

## 6. 📱 Plateformes

- Web responsive (prioritaire MVP)
- Application mobile (phase 2 optionnelle)

---

## 7. 🔐 Sécurité

- Authentification sécurisée (JWT / OAuth)
- Protection des données (RGPD)
- Paiements sécurisés via prestataire externe
- Détection de fraude basique

---

## 8. 🚀 MVP (Minimum Viable Product)

### Fonctionnalités MVP obligatoires
- Inscription / connexion
- Recherche de logements
- Fiche logement
- Réservation
- Paiement
- Espace hôte basique

### Fonctionnalités MVP+ (optionnelles)
- Messagerie
- Avis
- Carte interactive
- Admin panel avancé

---

## 9. 📊 KPI (indicateurs de performance)

- Nombre d’annonces actives
- Taux de conversion recherche → réservation
- Nombre de réservations mensuelles
- Taux de rétention utilisateurs
- Revenus générés

------------
