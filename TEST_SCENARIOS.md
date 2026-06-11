# Scénarios de test — Moteur de réservation & calendrier iCal

Guide de validation manuelle + automatisée de tout ce qui a été développé (Parties A→G).
Coche chaque scénario après vérification.

---

## 0. Pré-requis

```bash
make install          # build + démarrage (première fois)
make up               # démarrage si déjà buildé
make fixtures         # (ou make fixtures-fresh pour repartir d'une base vierge)
docker compose ps     # vérifier : php, database, mailer, messenger-worker, adminer = running
```

> ⚠️ Le **worker Messenger doit tourner** pour les emails/notifications async :
> `docker compose ps messenger-worker` → `running`. Sinon : `docker compose start messenger-worker`.

**Accès :**
| Service | URL |
|---|---|
| Application | http://localhost:8089 |
| Mailpit (emails) | http://localhost:8025 |
| Adminer (BDD) | http://localhost:8088 |

**Comptes de démo** (mot de passe pour tous : `password`) :
| Rôle | Email |
|---|---|
| Super admin | admin@airbnb-clone.fr |
| Admin / modération | moderation@airbnb-clone.fr |
| Hôte | jeanmarc.dupont@email.com |
| Hôte | elena.k@email.com |
| Voyageur | sophie.chen@email.com |
| Voyageur | lucas.bernard@email.com |

**Astuce — récupérer des identifiants depuis la BDD** (utile pour les URLs avec `{id}`) :
```bash
# logements publiés (id, instantBooking, hôte, jeton iCal)
docker compose exec php php bin/console dbal:run-sql \
 "SELECT p.id, p.title, p.instant_booking, h.email AS hote, p.ical_export_token \
  FROM properties p JOIN users h ON h.id=p.host_id WHERE p.status='published'"
```

---

## A. Gestion des disponibilités (espace hôte)

### A.1 — Calendrier mensuel + blocage de période
1. Se connecter en **hôte** (`jeanmarc.dupont@email.com`).
2. Menu profil → **Mes propriétés** → sous une annonce, cliquer **Gérer le calendrier**.
   (URL directe : `/compte/logements/{id}/calendrier`)
3. ✅ La **grille mensuelle** s'affiche, avec navigation ‹ / › entre les mois et une légende
   *Disponible / Bloqué / Réservé*.
4. Dans « Bloquer / débloquer une période », saisir **Du** = aujourd'hui+5j, **Au** = aujourd'hui+8j → **Bloquer**.
5. ✅ Les jours concernés passent en **gris (Bloqué)**. Refaire avec **Débloquer** → ils redeviennent **Disponibles**.

### A.2 — Algorithme de disponibilité (vérifié via le tunnel B.1 et la recherche C)
> L'algo (publié + capacité + jour non bloqué + pas de chevauchement `confirmed`) est éprouvé
> par les scénarios B.1, C et le test automatisé (§Automatisé).

---

## B. Parcours de réservation

### B.1 — Réservation instantanée (→ Confirmée)
1. Connecté en **voyageur** (`sophie.chen@email.com`).
2. Ouvrir un logement **publié en réservation instantanée** (ex. *Loft Industriel Vue Mer* / Santorin),
   cliquer **Réserver**.
3. Choisir des dates **libres** (ex. mois prochain), 2 voyageurs.
4. ✅ Au changement de dates, le **total se recalcule en direct** (G.6) dans le récapitulatif.
5. Valider → ✅ redirection vers la fiche réservation, **statut Confirmée**, message flash de confirmation.
6. ✅ Dans **Mailpit** : email *« Votre réservation est confirmée »* (voyageur **et** hôte).

### B.1 bis — Réservation sur demande (→ En attente)
1. Réserver un logement **publié non-instantané** (ex. *Penthouse Parisien* ou *Appartement Cosy* / Lyon).
2. ✅ Statut **En attente** + flash « demande envoyée à l'hôte ».
3. ✅ Mailpit : email *« Nouvelle demande de réservation »* à l'**hôte** (avec bouton CTA).

### B.1 ter — Dates indisponibles refusées
1. Réserver les **mêmes dates** qu'une réservation déjà **confirmée** sur le même logement.
2. ✅ Refus : message *« Ces dates sont déjà réservées »*, **aucune** réservation créée.
3. Tenter de réserver **son propre** logement → ✅ refus *« vous ne pouvez pas réserver votre propre logement »*.

### B.2 — Modération hôte (Accepter / Refuser)
1. Connecté en **hôte** propriétaire du logement « sur demande » réservé en B.1 bis.
2. Menu profil → **Demandes reçues** (`/compte/demandes`).
3. ✅ La demande **En attente** apparaît. Cliquer **Accepter**.
4. ✅ Statut → **Confirmée** ; Mailpit : email *« réservation confirmée »* aux deux parties.
5. Sur une autre demande, cliquer **Refuser**, saisir un **motif** → valider.
6. ✅ Statut → **Annulée** ; Mailpit : email *« demande refusée »* au voyageur (motif inclus).
   Refuser **sans motif** → ✅ erreur « motif obligatoire ».

### B.3 — Annulation (libère les dates)
1. Connecté en **voyageur**, ouvrir une réservation **Confirmée** → section **Annuler la réservation**.
2. Saisir un **motif** → confirmer.
3. ✅ Statut → **Annulée**, motif affiché ; Mailpit : email *« réservation annulée »* aux deux parties.
4. ✅ Re-réserver **les mêmes dates** est de nouveau possible (dates libérées).

---

## C. Moteur de recherche `/search`

1. Sur l'accueil, utiliser la barre de recherche (ou aller sur `/search`).
2. **Destination** : `Lyon` → ✅ seuls les logements de Lyon ressortent.
3. **Destination** : `zzz` (inexistant) → ✅ aucun résultat.
4. **Voyageurs** : `8` → ✅ seuls les logements de capacité ≥ 8.
5. **checkin/checkout** sur des dates où un logement a une réservation **confirmée** ou un **jour bloqué**
   → ✅ ce logement **disparaît** des résultats.

Vérif rapide en ligne de commande :
```bash
curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8089/search?destination=Lyon&checkin=2026-09-10&checkout=2026-09-15&guests=2"   # 200
```

---

## D. Notifications transactionnelles (asynchrones, Mailpit)

> Tous les emails partent via **Messenger** (file `async` doctrine) consommée par le worker.

1. Ouvrir **http://localhost:8025** (Mailpit).
2. Rejouer B.1 / B.2 / B.3 → ✅ chaque action produit l'email attendu :
   | Déclencheur | Destinataire(s) | Sujet |
   |---|---|---|
   | Demande en attente | Hôte | Nouvelle demande de réservation |
   | Confirmée | Voyageur + Hôte | Votre réservation est confirmée |
   | Refus | Voyageur | demande refusée (motif) |
   | Annulation | Voyageur + Hôte | Réservation annulée (motif) |

État de la file / robustesse :
```bash
docker compose exec php php bin/console messenger:stats          # async / failed
docker compose exec php php bin/console messenger:failed:show     # échecs éventuels
```

---

## E. Export iCal sécurisé par jeton

1. Récupérer `id` + `ical_export_token` d'un logement (cf. requête SQL du §0).
2. **Jeton valide** :
   ```bash
   curl -i "http://localhost:8089/api/properties/{id}/calendar.ics?token={token}"
   ```
   ✅ `200`, `Content-Type: text/calendar`, contenu `BEGIN:VCALENDAR … VEVENT … DTSTART;VALUE=DATE:… END:VCALENDAR`
   (un VEVENT par séjour **confirmé**).
3. **Jeton invalide / absent** :
   ```bash
   curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8089/api/properties/{id}/calendar.ics?token=FAUX"   # 403
   curl -s -o /dev/null -w "%{http_code}\n" "http://localhost:8089/api/properties/{id}/calendar.ics"               # 403
   ```
4. **Révocation** : en hôte, page calendrier → **Régénérer le lien**.
   ✅ L'**ancien** jeton renvoie désormais `403`, le **nouveau** lien fonctionne.

---

## F. Import iCal `app:ical:sync` (bonus)

> Un flux iCal externe est configuré sur une annonce (fixture). Pour une démo **reproductible**,
> on peut pointer ce flux vers notre **propre** export (URL interne `http://127.0.0.1:8000/...`).

```bash
# 1) Récupérer le sync + un logement source ayant des séjours confirmés (cf. §0 pour le token)
docker compose exec php php bin/console dbal:run-sql \
 "SELECT s.id sync_id, s.property_id cible, s.provider_name FROM property_ical_sync s"

# 2) (Démo) pointer le flux vers notre export interne
docker compose exec php php bin/console dbal:run-sql \
 "UPDATE property_ical_sync SET i_cal_url='http://127.0.0.1:8000/api/properties/{SRC_ID}/calendar.ics?token={SRC_TOKEN}' WHERE id='{sync_id}'"

# 3) Lancer la synchronisation
docker compose exec php php bin/console app:ical:sync --property={cible}
```
✅ Sortie : *« N nuitée(s) bloquée(s), M conflit(s) »*.
✅ Idempotence : relancer la commande → **même** nombre de blocages (purge + réinsertion par source).
✅ Les blocages importés portent `source='ical:{provider}'` et n'écrasent pas les blocages manuels (`source='host'`).

```bash
# Vérifier les blocages importés
docker compose exec php php bin/console dbal:run-sql \
 "SELECT count(*), min(available_date), max(available_date) FROM property_availability WHERE property_id='{cible}' AND source LIKE 'ical:%'"
```
Options : `--property={uuid}` (cibler un logement), `--window=365` (fenêtre en jours).

---

## G. Fonctionnalités avancées (bonus)

### G.5 — Timeline du voyageur
- Ouvrir une réservation ayant subi des changements (ex. après B.2/B.3).
- ✅ Section **Historique de la réservation** : *Demande créée* → transitions (Confirmée, Annulée…) avec date + auteur (ou *Système*).

### G.6 — Tarification dynamique
- Sur la page **Réserver**, changer les dates.
- ✅ Le récapitulatif (nuits × prix + ménage + frais de service + **Total estimé**) se met à jour **sans recharger**.

### G.8 — Notifications in-app (cloche)
- Connecté, après quelques actions (B.x) : ✅ **badge** sur la cloche de l'en-tête.
- Cliquer la cloche → `/notifications` : ✅ liste des notifications, **Tout marquer comme lu** → badge disparaît.

### G.1 — Expiration des demandes (> 24 h)
```bash
# Vieillir artificiellement les demandes en attente puis expirer
docker compose exec php php bin/console dbal:run-sql \
 "UPDATE reservations SET created_at = created_at - INTERVAL '2 days' WHERE status='pending'"
docker compose exec php php bin/console app:reservations:expire-pending
```
✅ *« N demande(s) expirée(s) et annulée(s) »* ; les réservations passent **Annulée** avec historique **Système** (`changed_by_id` NULL). Option : `--hours=24`.

### G.2 — Rappel de check-in (J-1)
```bash
# Placer un séjour confirmé à demain, puis envoyer les rappels
docker compose exec php php bin/console dbal:run-sql \
 "UPDATE reservations SET checkin_date = CURRENT_DATE + 1 WHERE id=(SELECT id FROM reservations WHERE status='confirmed' LIMIT 1)"
docker compose exec php php bin/console app:reservations:checkin-reminder
```
✅ *« N rappel(s) … programmé(s) »* → Mailpit : email **« Votre arrivée approche »** + notification in-app.

---

## Tests automatisés (PHPUnit)

```bash
# Base de test (une seule fois, ou après changement de schéma)
docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction --env=test
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction --env=test

# Lancer le test fonctionnel du moteur de réservation
docker compose exec php vendor/bin/phpunit --filter BookingTest
```
✅ `OK (2 tests, 14 assertions)` :
- réservation instantanée → **Confirmée** + notification dispatchée ;
- dates déjà confirmées → **rejet**, aucune réservation créée.

---

## Vérifications transverses (santé du code)

```bash
docker compose exec php php bin/console lint:container          # DI valide
docker compose exec php php bin/console lint:twig templates     # templates valides
docker compose exec php php bin/console doctrine:schema:validate # mapping OK
docker compose exec php php bin/console debug:messenger          # messages -> handlers
```
