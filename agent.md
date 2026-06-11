# Directives agent - Projet cours-symfony

Ce fichier sert de guide de travail pour Codex sur ce projet. Il peut etre modifie par l'equipe pour ajouter des consignes, priorites, interdits ou conventions locales.

## Contexte du projet

- Application Symfony de reservation de logements entre particuliers.
- Le cahier des charges de reference est disponible dans `../CahierDesCharges.md`.
- Le perimetre couvre les voyageurs, hotes, administrateurs, support, annonces, recherches, reservations, paiements, remboursements, messagerie, avis, signalements, litiges et administration.
- Le modele de reservation est uniquement par demande : l'hote accepte, puis le voyageur paie, puis la reservation est confirmee.

## Stack technique

- PHP `>=8.4`
- Symfony `8.0.*`
- Doctrine ORM `^3.6`
- API Platform `^4.2`
- PostgreSQL via Docker Compose
- Twig, Asset Mapper, Stimulus et Turbo pour le frontend
- PHPUnit `^12.5` pour les tests

## Directives de travail

- Lire le code existant avant de modifier un fichier.
- Respecter le cahier des charges, meme quand une demande est courte.
- Garder les changements scopes a la demande en cours.
- Ne pas supprimer ou renommer du code sans raison explicite.
- Ne pas inventer de fonctionnalites hors perimetre sans validation.
- Preferer les patterns Symfony, Doctrine et API Platform deja presents dans le projet.
- Utiliser des enums PHP pour les statuts, roles et valeurs metier fermees.
- Ajouter ou mettre a jour les tests quand la logique metier ou les contrats publics changent.
- Expliquer clairement les changements effectues et les commandes de verification lancees.

## Conventions de code

- Namespace applicatif : `App\`.
- Entites Doctrine dans `src/Entity`.
- Repositories dans `src/Repository`.
- Enums dans `src/Enum`.
- Services metier dans `src/Service` si necessaire.
- Controleurs dans `src/Controller`.
- Noms de classes en `PascalCase`.
- Proprietes et methodes en `camelCase`.
- Noms de tables et colonnes en `snake_case` cote base de donnees.
- Preferer `DateTimeImmutable` pour les dates.
- Utiliser les attributs PHP modernes pour Doctrine, validation et API Platform.
- Garder les messages utilisateur en francais.
- Garder les noms techniques en anglais ou francais selon l'existant, sans melanger dans une meme entite.

## Regles metier importantes

- Un utilisateur suspendu ne peut pas reserver, publier, communiquer ou laisser un avis.
- Un utilisateur doit avoir au moins 18 ans pour reserver ou publier un logement.
- Un voyageur ne peut pas reserver son propre logement.
- Une annonce publiee doit avoir au minimum un titre, une description, une adresse, une photo, un tarif, des disponibilites, un reglement interieur et une politique d'annulation.
- Une annonce suspendue ne doit pas apparaitre dans la recherche.
- Une annonce archivee ne peut plus recevoir de demande.
- Une demande de reservation ne bloque pas definitivement les dates.
- Les dates sont bloquees uniquement apres paiement confirme.
- Une reservation confirmee ne peut exister que si le paiement est valide par Stripe ou PayPal.
- Les montants, frais et commissions doivent etre historises au moment de la reservation.
- Un avis ne peut etre laisse qu'apres une reservation terminee.
- Les actions sensibles d'administration doivent etre historisees.
- Les donnees personnelles et documents d'identite doivent etre proteges selon les exigences RGPD.

## Priorites d'implementation

1. Modeliser correctement le domaine avec entites, enums, relations Doctrine et validations.
2. Mettre en place l'authentification et les droits selon les roles.
3. Exposer les ressources API ou pages necessaires de facon progressive.
4. Implementer les parcours principaux : recherche, annonce, demande, paiement, confirmation.
5. Ajouter la messagerie, les avis, les favoris, les notifications et l'administration.

## Commandes utiles

```bash
make up
make down
make cache
make sh
php bin/console make:entity
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/phpunit
```

## Verification attendue

- Lancer les tests pertinents quand ils existent.
- Pour les entites Doctrine, verifier au minimum que le mapping est coherent avec :

```bash
php bin/console doctrine:schema:validate
```

- Pour les changements de schema, generer une migration Doctrine si la demande le necessite.
- Signaler clairement si une commande n'a pas pu etre lancee.

## Consignes specifiques du developpeur

Ajoute ici tes directives personnelles. Exemples :

- Ne pas utiliser EasyAdmin sauf demande explicite.
- Exposer les entites via API Platform uniquement apres validation.
- Preferer les UUID pour les identifiants.
- Toujours nommer les statuts metier en francais.
- Ne pas generer de fixtures sans demande.

### Directives actives

- Respecter le cahier des charges comme source fonctionnelle principale.
- Demander confirmation avant de modifier une decision d'architecture importante.
- Garder les reponses et messages fonctionnels en francais.
