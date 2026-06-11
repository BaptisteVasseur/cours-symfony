# Plan d'action - Sujet reservation, calendrier et iCal

Date : 11 juin 2026


## 0. Statut de realisation

- Commit 1 - Conception : termine.
- Commit 2 - Modele reservation/calendrier : termine.
- Commit 3 - Service de disponibilite : termine.
- Commit 4 - Recherche par dates et voyageurs : termine.
- Commit 5 - Calendrier hote et blocage manuel : termine.
- Commit 6 - Workflow reservation et annulation : termine.
- Commit 7 - Emails asynchrones : termine.
- Commit 8 - Export iCal securise : termine.
- Commit 9 - Documentation finale : en cours.

## Objectif prioritaire

Traiter en priorite le sujet d'evaluation centre sur le moteur de reservation, le calendrier de disponibilite, les notifications email asynchrones et la synchronisation iCal.

Le livrable de conception `conception.txt` est prioritaire avant toute nouvelle implementation importante.

## 1. Etat actuel du projet

### Deja en place

- Projet Symfony fonctionnel avec Docker, Doctrine, Twig et Security.
- Authentification par formulaire : inscription, connexion, deconnexion.
- Roles principaux : voyageur, hote, admin.
- Front public : accueil, liste logements, detail logement.
- Logements publies charges depuis Doctrine.
- Espace hote :
  - liste des annonces ;
  - creation d'annonce brouillon ;
  - preparation de publication avec photo, disponibilites et politique d'annulation ;
  - soumission a moderation.
- Administration minimale :
  - dashboard admin ;
  - liste des annonces ;
  - publication ou renvoi en brouillon.
- Reservations :
  - creation d'une demande depuis une fiche logement ;
  - validation basique des dates, capacite, statut utilisateur/logement et disponibilites ;
  - historique voyageur ;
  - detail reservation ;
  - tableau hote des demandes ;
  - acceptation/refus hote ;
  - paiement simule voyageur ;
  - passage en reservation confirmee ;
  - disponibilites marquees reservees apres paiement.
- Notifications internes :
  - page `/notifications` ;
  - notifications hote/voyageur sur demande, acceptation/refus et paiement.

### Partiellement en place

- Calendrier : donnees journalieres via `Disponibilite`, mais pas encore de vraie vue mensuelle hote.
- Recherche : filtres destination, voyageurs, prix et type, mais pas encore de filtre strict `checkin` / `checkout`.
- Workflow reservation : proche du sujet, mais les statuts ne sont pas exactement nommes `Pending`, `Confirmed`, `Cancelled`, `Completed`.
- Notifications : in-app faites, email asynchrone non fait.
- Disponibilite : verifiee en memoire depuis la collection du logement, pas encore optimisee en requetes Doctrine de plages.

### Points du sujet et statut

- Fichier `conception.txt` a la racine. Fait.
- Propriete `instantBooking` sur `Logement`. Fait.
- Token iCal unique et revocable par logement. Fait.
- Endpoint export iCal securise `/api/properties/{id}/calendar.ics?token=...`. Fait.
- Interface calendrier hote en vue mensuelle. Fait.
- Blocage manuel de periodes par l'hote avec motif. Fait.
- Annulation voyageur/hote avec motif. Fait.
- Liberation des dates lors d'une annulation. Fait.
- Notifications email via Mailer + Messenger. Fait.
- Verification Mailpit des emails. Fait.
- Recherche stricte par `destination`, `checkin`, `checkout`, `guests`. Fait.
- Tests ou verifications de non-chevauchement. Partiel : verifications manuelles et revalidation metier.
- Bonus import iCal via commande `app:ical:sync`. Non fait.

## 2. Priorite 1 - Conception

Créer `conception.txt` avant de coder les nouvelles briques.

### Contenu attendu

1. Modele de donnees
   - `Logement.instantBooking`.
   - `Logement.icalToken`.
   - `Disponibilite` comme table de jours individuels.
   - `Reservation` comme source de verite des sejours.
   - Champs de motif d'annulation/refus.

2. Regles temporelles
   - Le jour de depart n'est pas bloque.
   - Une reservation du 10 au 15 bloque les nuits du 10, 11, 12, 13 et 14.
   - Une nouvelle arrivee le 15 est autorisee.

3. Gestion des statuts
   - `EN_ATTENTE_HOTE` correspond a `Pending`.
   - `CONFIRMEE` correspond a `Confirmed`.
   - `ANNULEE_PAR_VOYAGEUR`, `ANNULEE_PAR_HOTE`, `REFUSEE`, `EXPIREE` couvrent `Cancelled`.
   - `TERMINEE` correspond a `Completed`.

4. Choix Pending
   - Une demande en attente ne bloque pas definitivement les dates.
   - Le blocage intervient a la confirmation/paiement.
   - Risque accepte : plusieurs demandes peuvent viser les memes dates, mais une seule pourra confirmer.

5. Concurrence
   - Revalider la disponibilite juste avant confirmation.
   - Utiliser une contrainte unique `logement + date` sur les disponibilites.
   - En cas de conflit, refuser la confirmation ou afficher un message.

6. Performance
   - Ne pas verifier jour par jour avec une requete par date.
   - Faire une requete qui compte les jours disponibles sur la plage.
   - Comparer le nombre de jours disponibles au nombre de nuits demande.

7. Asynchronisme
   - Envoyer les messages email apres `flush()`.
   - Utiliser Messenger avec transport Doctrine local.
   - En cas de panne du worker, les messages restent en file et sont rejouables.

8. iCal
   - Export uniquement des reservations confirmees.
   - Endpoint protege par token.
   - Token stocke sur le logement et renouvelable par l'hote.
   - Import bonus via commande planifiee.

## 3. Priorite 2 - Implementation minimale obligatoire

### Etape A - Stabiliser le modele

- Ajouter `instantBooking` sur `Logement`.
- Ajouter `icalToken` sur `Logement`.
- Ajouter `motifAnnulation` et `motifRefus` sur `Reservation`.
- Generer et executer une migration.
- Adapter les fixtures.

### Etape B - Service de disponibilite

- Creer `DisponibiliteService`.
- Centraliser :
  - calcul des nuits ;
  - validation de plage ;
  - detection de jours bloques ;
  - detection de reservations confirmees qui se chevauchent ;
  - verrouillage/liberation de dates.
- Remplacer la logique actuelle de `DemandeReservationValidator` par ce service.

### Etape C - Recherche conforme au sujet

- Ajouter ou adapter une route `/search`.
- Parametres :
  - `destination` ;
  - `checkin` ;
  - `checkout` ;
  - `guests`.
- Adapter `LogementRepository` pour filtrer strictement les disponibilites.

### Etape D - Calendrier hote

- Ajouter `/hote/annonces/{id}/calendrier`.
- Vue mensuelle minimale.
- Formulaire de blocage manuel :
  - date debut ;
  - date fin ;
  - motif.
- Enregistrer les jours en `BLOQUEE`.

### Etape E - Workflow reservation

- Reservation instantanee :
  - si `instantBooking = true` et dates libres, passer directement a `CONFIRMEE`.
- Reservation sur demande :
  - garder `EN_ATTENTE_HOTE`.
- Acceptation hote :
  - revalider les dates ;
  - passer a `ACCEPTEE_EN_ATTENTE_PAIEMENT` ou directement `CONFIRMEE` selon le choix conserve.
- Annulation :
  - formulaire motif ;
  - statut annule ;
  - liberation des dates.

### Etape F - Notifications email asynchrones

- Creer des classes message Messenger.
- Creer des handlers.
- Envoyer les emails avec Mailer.
- Declencheurs :
  - nouvelle demande ;
  - reservation validee ;
  - refus ;
  - annulation.
- Verifier dans Mailpit : `http://localhost:8025`.

### Etape G - Export iCal

- Ajouter endpoint `/api/properties/{id}/calendar.ics?token=...`.
- Verifier le token.
- Generer un fichier `.ics` avec les reservations confirmees.
- Ajouter bouton/lien hote pour copier le flux.
- Ajouter regeneration du token.

## 4. Bonus apres le minimum

- Import iCal via commande `app:ical:sync`.
- Expiration automatique des demandes en attente apres 24h.
- Rappel check-in a J-1.
- Timeline voyageur.
- Tarification dynamique front.
- Amelioration icone cloche avec compteur de notifications non lues.

## 5. Ordre de travail recommande

1. Rediger `conception.txt`.
2. Ajouter les champs manquants et migration.
3. Creer `DisponibiliteService`.
4. Adapter recherche `/search`.
5. Ajouter calendrier hote et blocage manuel.
6. Finaliser instant booking et annulation.
7. Ajouter emails asynchrones.
8. Ajouter export iCal.
9. Mettre a jour `AVANCEMENT_PROJET.md`.
10. Tester le parcours complet avec fixtures.

## 6. Decoupage en commits

Chaque commit doit etre fait quand l'etape indiquee est terminee, relue, et que les verifications minimales passent.

### Commit 1 - Conception du moteur reservation/calendrier

Quand le faire :

- apres redaction de `conception.txt` ;
- avant de modifier le modele ou les controllers.

Contenu :

- creation de `conception.txt` ;
- mise a jour eventuelle de `PLAN_ACTION_SUJET.md` si le choix technique final evolue.

Message propose :

```text
docs: ajouter la conception reservation calendrier ical
```

Verification avant commit :

```bash
git diff -- conception.txt PLAN_ACTION_SUJET.md
```

### Commit 2 - Modele de donnees du sujet

Quand le faire :

- apres ajout des champs manquants et migration ;
- avant de changer la logique metier.

Contenu :

- `Logement.instantBooking` ;
- `Logement.icalToken` ;
- generation ou renouvellement du token iCal ;
- `Reservation.motifAnnulation` ;
- `Reservation.motifRefus` ;
- migration Doctrine ;
- fixtures adaptees.

Message propose :

```text
feat: completer le modele reservation calendrier
```

Verification avant commit :

```bash
php -l src/Entity/Logement.php
php -l src/Entity/Reservation.php
php -l src/DataFixtures/AppFixtures.php
php bin/console doctrine:schema:validate
```

### Commit 3 - Service central de disponibilite

Quand le faire :

- apres creation du service et remplacement des validations dispersees ;
- avant de toucher aux vues hote ou recherche.

Contenu :

- creation de `DisponibiliteService` ;
- methodes pour calculer les nuits, verifier une plage, detecter les chevauchements, bloquer/liberer les dates ;
- adaptation de `DemandeReservationValidator` ;
- requetes utiles dans `ReservationRepository` si necessaire.

Message propose :

```text
feat: centraliser la validation des disponibilites
```

Verification avant commit :

```bash
php -l src/Service/DisponibiliteService.php
php -l src/Service/DemandeReservationValidator.php
php -l src/Repository/ReservationRepository.php
```

### Commit 4 - Recherche conforme au sujet

Quand le faire :

- apres ajout ou adaptation de `/search` ;
- quand les filtres `destination`, `checkin`, `checkout`, `guests` fonctionnent.

Contenu :

- route `/search` ou alias depuis `/logements` ;
- adaptation de `LogementRepository` ;
- formulaire de recherche avec `checkin`, `checkout`, `guests` ;
- affichage des erreurs de dates invalides.

Message propose :

```text
feat: filtrer les logements par dates et voyageurs
```

Verification avant commit :

```bash
php -l src/Controller/LogementController.php
php -l src/Repository/LogementRepository.php
php bin/console debug:router | grep search
```

### Commit 5 - Calendrier hote et blocage manuel

Quand le faire :

- apres creation de la vue mensuelle hote ;
- quand l'hote peut bloquer une periode avec motif.

Contenu :

- route `/hote/annonces/{id}/calendrier` ;
- template calendrier mensuel ;
- formulaire de blocage de dates ;
- passage des jours en `BLOQUEE` ;
- lien depuis la fiche annonce hote.

Message propose :

```text
feat: ajouter le calendrier hote et le blocage manuel
```

Verification avant commit :

```bash
php -l src/Controller/HostLogementCalendarController.php
php bin/console debug:router | grep calendrier
```

### Commit 6 - Workflow reservation instantanee et annulation

Quand le faire :

- apres finalisation du comportement `instantBooking` ;
- quand voyageur et hote peuvent annuler avec motif.

Contenu :

- creation directe en `CONFIRMEE` si reservation instantanee et dates libres ;
- maintien en `EN_ATTENTE_HOTE` sinon ;
- revalidation de disponibilite avant confirmation ;
- annulation voyageur ;
- annulation hote ;
- liberation des dates ;
- affichage des motifs.

Message propose :

```text
feat: finaliser le workflow reservation et annulation
```

Verification avant commit :

```bash
php -l src/Controller/ReservationController.php
php -l src/Controller/HostReservationController.php
php -l src/Service/DisponibiliteService.php
```

### Commit 7 - Emails asynchrones via Messenger

Quand le faire :

- apres creation des messages, handlers et templates email ;
- quand les emails apparaissent dans Mailpit.

Contenu :

- classes Message Messenger ;
- handlers ;
- templates email ;
- envoi apres `flush()` ;
- declencheurs nouvelle demande, validation, refus, annulation ;
- configuration Messenger/Mailer si besoin.

Message propose :

```text
feat: envoyer les emails reservation en asynchrone
```

Verification avant commit :

```bash
php bin/console messenger:consume async -vv --time-limit=10
```

Controle manuel :

- ouvrir `http://localhost:8025` ;
- verifier les emails generes.

### Commit 8 - Export iCal securise

Quand le faire :

- apres ajout de l'endpoint `.ics` ;
- quand le flux refuse un token invalide et expose les reservations confirmees avec token valide.

Contenu :

- route `/api/properties/{id}/calendar.ics?token=...` ;
- generation du contenu iCal ;
- verification du token logement ;
- lien hote pour recuperer l'URL ;
- action pour regenerer le token si possible.

Message propose :

```text
feat: exposer un flux ical securise par logement
```

Verification avant commit :

```bash
php -l src/Controller/CalendarExportController.php
curl -i "http://localhost:8089/api/properties/1/calendar.ics?token=TOKEN"
```

### Commit 9 - Documentation d'avancement et verification finale

Quand le faire :

- apres implementation du minimum du sujet ;
- juste avant la PR ou le rendu.

Contenu :

- mise a jour de `AVANCEMENT_PROJET.md` ;
- eventuelle mise a jour de `README.md` ;
- checklist des fonctionnalites terminees ;
- commandes de lancement et comptes de test.

Message propose :

```text
docs: mettre a jour le suivi du sujet reservation
```

Verification avant commit :

```bash
php bin/console doctrine:schema:validate
php bin/console debug:router
php bin/console doctrine:fixtures:load --no-interaction
```

## 7. Regle de commit pendant l'implementation

Ne pas attendre la fin totale du sujet pour commit.

Faire un commit des qu'un bloc est coherent et testable :

- conception seule ;
- modele seul ;
- service de disponibilite seul ;
- recherche seule ;
- calendrier hote seul ;
- workflow reservation seul ;
- emails seuls ;
- iCal seul ;
- documentation finale seule.

Si une etape casse temporairement l'application, ne pas commit tant que le bloc n'est pas revenu a un etat executable.

