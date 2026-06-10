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
