# Comptes de connexion

Identifiants pour se connecter à l'application : **http://localhost:8089/login**

> **Mot de passe commun à tous les comptes : `password`**
> (les mots de passe sont stockés hachés en bcrypt — le clair n'existe que dans les fixtures, pas en base.)

## Comptes de démonstration (emails fixes)

Ces comptes ont des **emails stables** : ils ne changent pas quand on recharge les fixtures.

| Rôle | Email | Mot de passe | Accès |
|------|-------|--------------|-------|
| Super administrateur | `superadmin@airbnb-clone.fr` | `password` | Tout, y compris la suppression (API `Delete`) |
| Administrateur | `admin@airbnb-clone.fr` | `password` | Back-office : `/admin`, `/property`, `/user` |
| Hôte | `hote@airbnb-clone.fr` | `password` | Espace hôte + ses annonces et réservations |
| Voyageur | `voyageur@airbnb-clone.fr` | `password` | `/reservations`, `/messages`, `/compte` |

- L'**hôte** de démo possède 2 annonces (« Logement démo n°1 / n°2 à Paris »).
- Le **voyageur** de démo a 2 réservations sur ces annonces.

## Autres comptes (générés par Faker)

Les fixtures créent aussi ~8 hôtes et ~40 voyageurs avec des **emails aléatoires** (ils changent à **chaque** rechargement des fixtures). Leur mot de passe est également `password`.

Pour lister les emails actuellement en base :

```bash
docker compose exec database psql -U app -d app -c "SELECT email, roles FROM users ORDER BY roles DESC;"
```

ou via **Adminer** : http://localhost:8088 (serveur `database`, utilisateur `app`, base `app`).

## Recharger les comptes

```bash
docker compose exec php php bin/console hautelook:fixtures:load --no-interaction
```

Cette commande **vide puis recharge** la base. Les 4 emails de démo ci-dessus restent identiques ; seuls les comptes Faker changent.
