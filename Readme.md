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




10. Faire l'authentification, mot de passe oublié, etc
11. API, Asynchrone, Events, etc
12. Tests unitaires, fonctionnels, etc
