# Dashboard Hôte - Guide d'utilisation

## 🏠 Accueil du Dashboard Hôte

Route: `/host/reservations`

### Vue - Liste des réservations

Le dashboard affiche 4 sections:

```
┌─────────────────────────────────────────────────────────┐
│ Dashboard Hôte - [Propriété: Appartement Centre-Ville]  │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ 🔴 EN ATTENTE (3 demandes)                              │
│ ├─ Jean Dupont - 10 au 15 juil - 450€ [VOIR] [RÉPONDRE]│
│ ├─ Marie Martin - 16 au 20 juil - 500€ [VOIR] [RÉPONDRE]│
│ └─ Pierre Lefebvre - 21 au 25 juil - 600€ [VOIR]        │
│                                                          │
│ ✅ CONFIRMÉES (5 réservations)                          │
│ ├─ Alice B. - 01 au 05 juil - 400€ [ANNULER]           │
│ ├─ Bob C. - 25 au 30 juil - 550€ [ANNULER]             │
│ └─ [+3 autres...]                                       │
│                                                          │
│ ✔️ COMPLÉTÉES (8 réservations)                          │
│ └─ [Voir historique]                                    │
│                                                          │
│ ❌ ANNULÉES (2 réservations)                            │
│ └─ [Voir historique]                                    │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

## 📋 Détail d'une réservation

Route: `/host/reservations/{id}`

```
┌────────────────────────────────────────────────────────┐
│ Détail Réservation - Réf. #res-12345@clone-airbnb     │
├────────────────────────────────────────────────────────┤
│                                                        │
│ INFOS VOYAGEUR                                         │
│ • Jean Dupont                                          │
│ • Email: jean@example.com                             │
│ • Téléphone: +33 6 12 34 56 78                        │
│                                                        │
│ DÉTAILS DE LA RÉSERVATION                              │
│ • Logement: Appartement Centre-Ville (Paris 4e)      │
│ • Arrivée: Mercredi 10 juillet 2026 (15:00)          │
│ • Départ: Lundi 15 juillet 2026 (11:00)              │
│ • Durée: 5 nuits                                       │
│ • Nombre de voyageurs: 2 personnes                    │
│                                                        │
│ MONTANT                                                │
│ • 5 nuits × 90€ = 450€                                │
│ • Frais de ménage: 50€                                │
│ • Frais de service (12%): 60€                         │
│ • Caution: 200€                                       │
│ • TOTAL: 760€                                          │
│                                                        │
│ STATUT ACTUEL: EN ATTENTE D'APPROBATION              │
│                                                        │
│ ACTIONS DISPONIBLES (Status: Pending)                 │
│ ┌────────────────────────────────────────────────┐   │
│ │ [✅ ACCEPTER CETTE RÉSERVATION]                │   │
│ │ → Les dates seront verrouillées                │   │
│ │ → Confirmation email au voyageur               │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ ┌────────────────────────────────────────────────┐   │
│ │ [❌ REFUSER CETTE RÉSERVATION]                 │   │
│ │ Motif (requis):                                │   │
│ │ ┌──────────────────────────────────────────┐  │   │
│ │ │ [Propriété non adaptée au profil]      │  │   │
│ │ └──────────────────────────────────────────┘  │   │
│ │ [VALIDER LE REFUS]                           │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
└────────────────────────────────────────────────────────┘
```

## 📅 Gestion des indisponibilités

Route: `/host/properties/{id}/unavailability`

### Liste des blocages

```
┌────────────────────────────────────────────────────────┐
│ Calendrier - Périodes d'indisponibilité               │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Propriété: Appartement Centre-Ville                   │
│                                                        │
│ [+ NOUVEAU BLOCAGE]                                    │
│                                                        │
│ PÉRIODES BLOQUÉES                                      │
│ ┌────────────────────────────────────────────────┐   │
│ │ 📌 Travaux - 01 au 05 juin 2026               │   │
│ │    Motif: Maintenance                          │   │
│ │    Notes: Rénovation électrique                │   │
│ │    [MODIFIER] [SUPPRIMER]                      │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ ┌────────────────────────────────────────────────┐   │
│ │ 📌 Utilisation personnelle - 15 au 20 août    │   │
│ │    Motif: Owner stay                           │   │
│ │    Notes: Vacances familiales                  │   │
│ │    [MODIFIER] [SUPPRIMER]                      │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ ┌────────────────────────────────────────────────┐   │
│ │ 📌 Nettoyage - 25 juillet 2026                │   │
│ │    Motif: Cleaning                             │   │
│ │    Notes: Changement de linge                  │   │
│ │    [MODIFIER] [SUPPRIMER]                      │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
└────────────────────────────────────────────────────────┘
```

### Créer un blocage

```
┌────────────────────────────────────────────────────────┐
│ Nouveau Blocage - Ajouter une période d'indisponibilité │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Date de début *                                        │
│ ┌────────────────────────────────────────────────┐   │
│ │ [📅 01/06/2026                             ▼]   │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Date de fin *                                          │
│ ┌────────────────────────────────────────────────┐   │
│ │ [📅 05/06/2026                             ▼]   │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Motif *                                                │
│ ┌────────────────────────────────────────────────┐   │
│ │ [Sélectionner le motif                      ▼]   │   │
│ │ - Travaux                                      │   │
│ │ - Utilisation personnelle                      │   │
│ │ - Nettoyage                                    │   │
│ │ - Séjour du propriétaire                       │   │
│ │ - Autre                                        │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ Notes (optionnel)                                      │
│ ┌────────────────────────────────────────────────┐   │
│ │ Rénovation électrique et mise aux normes      │   │
│ │ Durée estimée: 5 jours                         │   │
│ │                                                │   │
│ │                                                │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ [ENREGISTRER LE BLOCAGE] [ANNULER]                    │
│                                                        │
└────────────────────────────────────────────────────────┘
```

## 🔗 Gestion des tokens iCal

Route: `/host/properties/{id}/ical-tokens`

### Liste des tokens

```
┌────────────────────────────────────────────────────────┐
│ Synchronisation iCal - Tokens d'accès                  │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Synchronisez votre calendrier personnel (Google,       │
│ Outlook, Apple) avec vos réservations                  │
│                                                        │
│ [+ GÉNÉRER UN NOUVEAU TOKEN]                           │
│                                                        │
│ TOKENS ACTIFS                                          │
│ ┌────────────────────────────────────────────────┐   │
│ │ Token #1                                       │   │
│ │ Créé le: 01 juin 2026 à 14:23                 │   │
│ │ Dernier accès: Aujourd'hui à 09:15            │   │
│ │ [AFFICHER URL] [RÉVOQUER]                     │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ ┌────────────────────────────────────────────────┐   │
│ │ Token #2 (RÉVOQUÉ)                            │   │
│ │ Créé le: 15 mai 2026 à 10:00                  │   │
│ │ Révoqué le: 01 juin 2026 à 14:25              │   │
│ │ [ARCHIVED]                                     │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
└────────────────────────────────────────────────────────┘
```

### Afficher URL iCal

```
┌────────────────────────────────────────────────────────┐
│ URL de flux iCal - Copier-coller dans votre calendrier │
├────────────────────────────────────────────────────────┤
│                                                        │
│ 🔗 Lien de synchronisation                             │
│ ┌────────────────────────────────────────────────┐   │
│ │ https://airbnb-clone.local/api/properties/    │   │
│ │ 550e8400-e29b-41d4-a716-446655440000/         │   │
│ │ calendar.ics?token=a1b2c3d4e5f6g7h8i9j0k1l2  │   │
│ │                                                │   │
│ │ [📋 COPIER LE LIEN]                           │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ 📖 Instructions:                                       │
│ 1. Copier le lien ci-dessus                          │
│ 2. Ouvrir Google Calendar / Outlook / Apple Cal      │
│ 3. Ajouter un calendrier "Importer URL"              │
│ 4. Coller le lien                                    │
│ 5. Valider                                           │
│                                                        │
│ ℹ️ Les réservations confirmées apparaîtront          │
│ dans votre calendrier personnellement en temps       │
│ réel (avec 5-15min de délai)                         │
│                                                        │
│ ⚠️ IMPORTANT: Ne partager ce lien qu'à vous-même     │
│ Contient la clé d'accès à vos réservations!          │
│                                                        │
│ [REVENIR]                                             │
│                                                        │
└────────────────────────────────────────────────────────┘
```

## 🔄 Flux d'acceptation d'une demande

```
1. Hôte voit une demande "EN ATTENTE"
         ↓
2. Hôte clique "VOIR"
         ↓
3. Hôte consulte les détails du voyageur et dates
         ↓
4a. SCENARIO: Hôte accepte
    ├─ Clique "ACCEPTER CETTE RÉSERVATION"
    ├─ DB: Status passe de "pending" → "confirmed"
    ├─ Email à hôte: "Réservation confirmée"
    ├─ Email au voyageur: "Réservation acceptée!"
    └─ ✅ Les dates sont maintenant verrouillées

4b. SCENARIO: Hôte refuse
    ├─ Clique "REFUSER"
    ├─ Remplit le motif (ex: "Disponibilités modifiées")
    ├─ Clique "VALIDER LE REFUS"
    ├─ DB: Status passe de "pending" → "cancelled"
    ├─ Email à hôte: "Réservation refusée"
    ├─ Email au voyageur: "Demande refusée - Motif: ..."
    └─ ✅ Les dates restent disponibles
```

## 🔄 Flux d'annulation d'une réservation

```
1. Hôte voit une réservation "CONFIRMÉE"
         ↓
2. Hôte clique sur la réservation
         ↓
3. Hôte clique "ANNULER"
         ↓
4. Formulaire: Entrer le motif d'annulation
   (ex: "Propriété indisponible", "Circonstances imprévues")
         ↓
5. Clique "VALIDER L'ANNULATION"
         ↓
6. DB: Status passe de "confirmed" → "cancelled"
         ↓
7. Emails:
   - À hôte: "Annulation confirmée"
   - Au voyageur: "Votre réservation a été annulée - Motif: ..."
         ↓
8. ✅ Les dates sont IMMÉDIATEMENT libérées pour réservation
```

## 📊 Calendrier de réservations (Bonus - à implémenter)

Pour un calendrier visuel, ajouter une vue avec:

- Grille mensuelle
- Jours confirmés en vert
- Jours pending en orange
- Jours bloqués en gris
- Survol pour voir les détails

```
JUIN 2026
┌────────────────────────────────────────────────┐
│ Lun | Mar | Mer | Jeu | Ven | Sam | Dim       │
├────────────────────────────────────────────────┤
│  1  │  2  │  3  │  4  │  5  │  6  │  7       │
│ [📌]│[📌]│[📌]│[📌]│[📌]│     │     │  (Travaux)
├────┼────┼────┼────┼────┼────┼────┤
│  8  │  9  │ 10  │ 11  │ 12  │ 13  │ 14      │
│     │[✓] │[✓] │[✓] │[✓] │[✓] │     │  (Dupont)
├────┼────┼────┼────┼────┼────┼────┤
│ 15  │ 16  │ 17  │ 18  │ 19  │ 20  │ 21      │
│     │[?] │[?] │[?] │[?] │     │     │  (Martin - pending)
└────────────────────────────────────────────────┘

Légende:
[✓] = Confirmée
[?] = En attente
[📌] = Bloquée (indisponibilité)
```

---

**Note:** Tous les contrôleurs incluent la vérification d'ownership:

- L'hôte ne peut voir/modifier que SES propres propriétés
- Accès non autorisé → Erreur 403 Forbidden
