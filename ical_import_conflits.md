# strategie import ical et conflits

Ce fichier explique vite fait comment je gere l'import iCal pour un logement.
Le but c'est surtout de pas casser les reservations deja faites sur le site.

## principe general

quand la commande sync recupere un calendrier externe, je lis les
VEVENT du fichier .ics. pour chaque event je garde son uid, sa date de
debut et sa date de fin.

dans ma base, un evenement ical importe devient un AvailabilityBlock avec :

- reason = ical_import
- externalUid = UID du calendrier
- startDate et endDate

donc si le meme uid revient plus tard, je sais que c'est le meme bloc et pas un
nouveau.

## si il y a un chevauchement

la regle la plus importante : une reservation deja confirmee gagne toujours.

si un evenement ical arrive et qu'il tombe sur une periode ou il y a deja une
reservation confirmed, je ne bloque pas ces dates. Je skip l'evenement ical et
je log un warning pour pouvoir le voir apres.

je ne fais pas d'annulation automatique, parce que le calendrier externe peut
etre faux, vieux, ou pas encore a jour. ce serait trop dangereux d'annuler le
sejour d'un client juste a cause d'un import.

pour tester le chevauchement j'utilise la meme logique partout :

```
date_debut_existante < checkout_demande
ET
date_fin_existante > checkin_demande
```

comme ca le jour de depart reste libre pour un autre voyageur. par exemple si
quelqu'un part le 15, une autre personne peut arriver le 15.

## si l'evenement existe deja

si je retrouve un bloc avec le meme externalUid :

- si les dates sont pareil, je fais rien
- si les dates ont change, je tente de mettre a jour le bloc
- si les nouvelles dates touchent une reservation confirmee, je ne met pas a jour
  et je le compte comme saute

donc l'import peut corriger les dates, mais pas au point de prendre la place
d'une vraie reservation locale.

## suppression des events distants

a chaque synchro je garde la liste des uid que j'ai vu dans le calendrier
distant.

apres la lecture du fichier, je regarde mes blocs ical_import en base :

- si le uid existe encore dans le flux, je le garde
- si le uid n'existe plus, je supprime le bloc local

par contre je supprime seulement les blocs qui viennent de l'import iCal. Je ne
touche jamais aux blocages faits a la main par l'hote, et je ne touche pas non
plus aux reservations.

en gros, si l'hote supprime un evenement dans son calendrier externe, la periode
redevient disponible sur notre site, sauf si elle est bloquee par autre chose.
