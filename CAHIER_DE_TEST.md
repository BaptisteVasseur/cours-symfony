# Cahier de Recette / Plan de Test - Clone Airbnb Symfony

Ce document liste les scénarios à tester pour valider l'ensemble des fonctionnalités du moteur de réservation.

---

## 1. Recherche & Disponibilités (Partie A & C)

| ID | Scénario | Données de test | Résultat attendu |
|:---|:---|:---|:---|
| **T1.1** | Recherche par destination | Destination: "Paris" | N'affiche que les logements situés à Paris. |
| **T1.2** | Recherche par dates (libre) | 01/07 au 05/07 | Affiche les logements n'ayant aucune réservation confirmée sur ces dates. |
| **T1.3** | Recherche par dates (occupé) | Dates superposées à un séjour | Le logement n'apparaît PAS dans les résultats. |
| **T1.4** | Filtrage par voyageurs | Voyageurs: 10 | Exclut les logements dont la capacité max est < 10. |

---

## 2. Parcours Voyageur (Partie B.1)

| ID | Scénario | Étapes | Résultat attendu |
|:---|:---|:---|:---|
| **T2.1** | Réservation Instantanée | Logement avec `instantBooking=true` | Statut direct `Confirmed`, message de succès. |
| **T2.2** | Réservation sur Demande | Logement avec `instantBooking=false` | Statut `Pending`, mail envoyé à l'hôte. |
| **T2.3** | Blocage date identique | Tenter de réserver des dates déjà confirmées | Message d'erreur : "Ce logement n'est pas disponible". |
| **T2.4** | Calcul du prix | Nuits x Prix + Frais | Le total affiché correspond au calcul (ex: +12% service). |

---

## 3. Espace Hôte & Modération (Partie B.2 & A.1)

| ID | Scénario | Étapes | Résultat attendu |
|:---|:---|:---|:---|
| **T3.1** | Acceptation Demande | Cliquer "Accepter" sur une ligne `Pending` | Statut passe à `Confirmed`, mail au voyageur. |
| **T3.2** | Refus avec Motif | Cliquer "Refuser" + Saisir "Indispo" | Statut passe à `Cancelled`, motif enregistré, mail au voyageur. |
| **T3.3** | Blocage manuel de dates | Aller sur `/host/logement/{id}/disponibilites` | Création d'une indispo, le logement devient invisible sur ces dates en recherche. |
| **T3.4** | Auto-réservation interdite | Tenter de réserver son propre logement | Message : "Vous ne pouvez pas réserver votre propre logement". |

---

## 4. iCal & Synchronisation (Partie E & F)

| ID | Scénario | Étapes | Résultat attendu |
|:---|:---|:---|:---|
| **T4.1** | Export iCal (Accès) | Ouvrir `/api/properties/{id}/calendar.ics?token=...` | Téléchargement d'un fichier `.ics`. |
| **T4.2** | Export iCal (Sécurité) | Ouvrir l'URL sans token ou mauvais token | Erreur 403 Access Denied. |
| **T4.3** | Contenu iCal | Ouvrir le `.ics` avec un éditeur de texte | Voir les balises `BEGIN:VEVENT` pour chaque réservation `Confirmed`. |
| **T4.4** | Import Externe | Lancer `php bin/console app:ical:sync` | Bloque automatiquement les dates récupérées du flux distant. |

---

## 5. Notifications & Technique (Partie D)

| ID | Scénario | Étapes | Résultat attendu |
|:---|:---|:---|:---|
| **T5.1** | Mailpit (Validation) | Faire une action (Réservation/Refus) | Le mail apparaît instantanément dans Mailpit (port 8025). |
| **T5.2** | Asynchronisme | Vérifier le log Messenger | L'envoi ne bloque pas l'affichage de la page (délégué au worker). |
| **T5.3** | Annulation | Annuler une réservation confirmée | Les dates sont immédiatement relibérées pour la recherche. |

---

## 💡 Astuces pour les tests :
*   Utilise deux navigateurs différents (ou mode incognito) pour simuler à la fois le Voyageur et l'Hôte.
*   Pense à vérifier les logs de Symfony en cas de page blanche : `tail -f var/log/dev.log`.
