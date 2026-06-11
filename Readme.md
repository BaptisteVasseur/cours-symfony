# Appli Symfony Clone Airbnb

## Etapes suivies pour le développement

1. Lire le cahier des charges
2. En fonction du cahier des charges, générer un schéma de BDD avec PlantUML (plant text)
3. Créer un projet Symfony de 0 soit via Composer, soit via Symfony CLI, soit via un repo + image Docker
4. Créer les entités Doctrine en fonction du schéma de BDD
5. Générer les migrations et les exécuter pour créer la BDD
6. Créer des fixtures (php ou YAML) pour peupler la BDD avec des données de test
7. Générer un premier controller + générer un layout user (+ d'autres layout ? admin ? hôte ? autre ?)
8. Découper le template (avec de l'héritage + sous-templates/sous-composants)
9. Créer les controllers et 4 pages pour : 
   - Page d'accueil (listing des annonces) -> Repository
   - Page d'historique des reservations -> Repository
   - Page de détail d'une annonce -> Entité
   - Page de confirmation de reservation -> Entité


10. Faire l'authentification 
11. Bloquer l'accés à certaines pages en fonction des roles 
    - Page détail d'un logement : que ceux qui sont connectés
    - Pages admin : que ceux qui ont le role admin
12. Afficher dynamiquement un bouton pour se connecter si on est pas connecté
    + Un bouton pour se déconnecter si on est connecté
    + Un bouton 'interface admin' si on a le role admin
13. Afficher les réservations du user connecté sur la page d'historique des résas
14. Créer un CRUD pour ajouter des propriétés, des users, des résas + adapter les forms types pour retirer ce qui est pas utile + faire faire le design des cruds à l'IA
15. Ajouter les contraintes de validation sur les entités
16. Intro API Platform et normalisation/dénormalisation

<!-- Voir les Events ? Faire de l'Asynchrone ? Ajouter des commandes personnalisées ? Faire de services pour séparer le code ? Voir l'envoie de mail ? Faire des appels API avec HTTP Client ? Système de Traductions ? -->

---

## Stack technique

| Composant | Version |
|---|---|
| PHP | 8.4 |
| Symfony | 8.x |
| API Platform | 4.x |
| Doctrine ORM | 3.x + Migrations |
| Base de données | PostgreSQL 16 |
| Frontend | Twig, Asset Mapper, TailwindCSS |
| Emails (dev) | Mailpit |
| File d'attente | Symfony Messenger (transport `doctrine://default`) |

---

## Services Docker

| Service | URL / Port |
|---|---|
| Application | http://localhost:8089 |
| Adminer (BDD) | http://localhost:8088 |
| Mailpit (emails) | http://localhost:8025 |
| PostgreSQL | `localhost:5439` |

> Le worker Messenger (`messenger-worker`) tourne en parallèle du conteneur PHP et consomme le transport `async` en continu. Les emails (confirmation réservation, décision hôte, annulation) sont tous envoyés de manière **asynchrone** via ce worker.

---

## Lancement du projet

### Premier lancement
```bash
make install
```
Construit les images Docker et démarre tous les services.

### Démarrage quotidien
```bash
make up
```

### Accéder au shell PHP (pour les commandes Symfony/Composer)
```bash
make sh
```

### Commandes utiles dans le conteneur PHP
```bash
# Installer les assets JS
php bin/console importmap:install

# Appliquer les migrations
php bin/console doctrine:migrations:migrate

# Charger les fixtures de test
php bin/console doctrine:fixtures:load --append

# Vider le cache
php bin/console cache:clear

# Générer une migration après modification d'entité
php bin/console doctrine:migrations:diff
```

### Autres commandes Make
```bash
make down     # Arrêter les services
make restart  # Redémarrer
make cache    # Vider le cache Symfony
make logs     # Suivre les logs du conteneur PHP
```

---

## Variables d'environnement clés

Définies directement dans `compose.yaml` pour l'environnement de développement :

| Variable | Valeur dev |
|---|---|
| `DATABASE_URL` | `postgresql://app:my-super-secret-password@database:5432/app` |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default` |
| `MAILER_DSN` | `smtp://mailer:1025` |

> Pour la production, ces valeurs doivent être surchargées via un `.env.local` ou les secrets du serveur. Ne jamais committer de secrets en clair.

---

## Fonctionnalités implémentées

### Moteur de réservation
- Création de réservation avec lock pessimiste (anti double-booking)
- Statuts : `pending` → `confirmed` / `cancelled` / `completed`
- Instant booking (confirmation automatique)
- Soft-lock TTL 15 min pour les `pending`
- Historique des transitions (`ReservationStatusHistory`)

### Tableau de bord hôte
- Liste des demandes en attente (`/compte/demandes`)
- Acceptation (CSRF protégé) → passage en `confirmed`
- Refus avec motif obligatoire → passage en `cancelled`

### Processus d'annulation
- Annulation par le voyageur **ou** l'hôte (`/reservations/{id}/annuler`)
- Motif obligatoire (5–500 caractères)
- Libération immédiate des dates
- Notification email des deux parties

### Moteur de recherche (`/search`)
- Filtre `destination` : LIKE sur ville, adresse, pays
- Filtre `checkin`/`checkout` : exclusion des propriétés avec réservation confirmée ou blockout en overlap
- Filtre `guests` : capacité maximale ≥ nombre de voyageurs demandé

### Notifications email (asynchrones)
Tous les emails transitent par le worker Messenger (`transport: async`) et sont visualisables dans **Mailpit** (`http://localhost:8025`).

| Événement | Destinataire(s) |
|---|---|
| Nouvelle réservation | Voyageur + Hôte |
| Réservation acceptée | Voyageur |
| Réservation refusée (avec motif) | Voyageur |
| Annulation (par voyageur ou hôte) | Voyageur + Hôte |
