docker compose down -v
make install
make sh
composer install
Création des fixtures pour remplir la base, setup du package alice pour ecrire en Fr
php bin/console hautelook:fixtures:load --no-interaction

# Appli Symfony Clone Airbnb

## Etapes suivies pour le développement

1. Lire le cahier des charges
2. En fonction du cahier des charges, générer un schéma de BDD avec PlantUML (plant text)
3. Créer un projet Symfony de 0 soit via Composer, soit via Symfony CLI, soit via un repo + image Docker
4. Créer les entités Doctrine en fonction du schéma de BDD
5. Générer les migrations et les exécuter pour créer la BDD
6. Créer des fixtures (php ou YAML) pour peupler la BDD avec des données de test
7. Générer un premier controller + générer un layout user
8. Découper le template (héritage + sous-templates)
9. Créer les controllers et 4 pages pour :

    - Page d'accueil (listing des annonces) -> Repository
    - Page d'historique des reservations -> Repository
    - Page de détail d'une annonce -> Entité
    - Page de confirmation de reservation -> Entité

10. Faire l'authentification
11. Bloquer l'accés à certaines pages en fonction des roles
    - Role user
    - Role admin
    - Tout bloquer pour un utilisateur banni
12. Afficher dynamiquement un bouton pour se connecter si on est pas connecté
    Un bouton pour se déconnecter si on est connecté
13. Afficher que les réservations du user connecté sur la page d'historique

    Actuellement : `app.user.reservations` dans Twig → requêtes lazy (N+1 queries)

    Version optimisée avec une méthode dédiée dans le Repository :

    **ReservationRepository** : ajouter une méthode `findForUser(User $user)` avec QueryBuilder + jointures sur `property`, `media`, `address` → 1 seule requête SQL

    **Controller** : injecter `ReservationRepository`, appeler `findForUser($this->getUser())`, passer le résultat au template via `['reservations' => ...]`

    **Template** : remplacer `app.user.reservations` par la variable `reservations` passée par le controller

14. Créer un CRUD pour ajouter des propriétés, des users, des résas
15. Ajouter les contraintes de validation sur les entités
