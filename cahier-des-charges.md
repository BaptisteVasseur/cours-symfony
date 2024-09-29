Cahier des charges pour un site de streaming en ligne payant

1. Présentation du projet

Le projet consiste à développer un site de streaming en ligne, similaire à des plateformes telles que Netflix ou Prime Vidéo, où les utilisateurs peuvent accéder à un catalogue de films et de séries contre un abonnement payant. L’objectif principal est de fournir une expérience utilisateur riche et complète, tout en assurant une interface fluide et moderne. Le site intègrera un module de paiement sécurisé via Stripe, ainsi qu’une gestion avancée des contenus pour permettre aux utilisateurs de profiter pleinement des médias proposés.

2. Pourquoi ce projet ?

Dans un contexte où le streaming devient le mode de consommation privilégié pour les contenus audiovisuels, ce projet vise à offrir une plateforme de qualité qui répond aux besoins croissants des utilisateurs pour l’accès illimité à des films et séries. En proposant plusieurs niveaux d’abonnements, le site sera accessible à un large éventail de consommateurs, tout en offrant des fonctionnalités interactives pour renforcer leur engagement et leur fidélité.

3. Description des fonctionnalités principales

Le site proposera plusieurs types d’abonnement de durées et de prix variables, gérés via la paramètrage de l'administrateur. Chaque utilisateur aura la possibilité de créer un compte et de s’authentifier grâce à un système complet de gestion des utilisateurs, incluant les fonctionnalités classiques d’inscription, de connexion, de réinitialisation de mot de passe, et de confirmation de compte.

Chaque film ou série sera présenté dans une fiche dédiée, contenant un résumé court et un résumé long, accompagnés d’une pochette et des informations sur le casting ainsi que les membres du staff. Les films et séries seront disponibles dans plusieurs langues et pourront être visionnés avec des sous-titres dans différentes langues. Les séries seront structurées par saisons et épisodes pour une navigation aisée.

Les utilisateurs pourront parcourir le catalogue via des catégories spécifiques (action, comédie, drame, etc.) et effectuer des recherches par nom de film ou de série. Le site intègrera également un historique de visionnage pour chaque utilisateur, leur permettant de suivre le nombre de fois qu’ils ont visionné un média et de consulter la dernière date de visionnage.

Les commentaires seront disponibles sur chaque fiche de film ou série, mais devront être validés par un administrateur (ou une IA) avant publication afin de maintenir un contenu de qualité sur la plateforme. Par ailleurs, les utilisateurs auront la possibilité de créer des listes de films et séries, qu’ils pourront partager avec d’autres via un lien. Les autres utilisateurs pourront s’abonner à ces listes pour suivre les recommandations de leurs amis ou contacts.

4. Description des fonctionnalités secondaires

Afin de rendre l’expérience encore plus personnalisée, un système de recommandations sera mis en place, proposant des films et des séries basés sur les tendances de visionnage de l’utilisateur, ainsi que son historique et ses préférences. Ce système vise à améliorer l’engagement de l’utilisateur en lui proposant des contenus pertinents et adaptés à ses goûts.

Les visiteurs non connectés auront accès à certaines parties du site, comme la possibilité de consulter les fiches des films et des séries ou de parcourir le catalogue. Cependant, ils ne pourront ni lancer le visionnage des médias, ni ajouter des films à une liste personnelle, ni laisser des commentaires. Ces actions seront exclusivement réservées aux utilisateurs inscrits et connectés.

5. Technologies imposées

Le site sera développé avec le framework Symfony pour assurer une structure robuste et évolutive. Twig sera utilisé pour la gestion des templates et le rendu des pages, garantissant une expérience utilisateur fluide. Stripe sera intégré pour la gestion des paiements et des abonnements, offrant ainsi une solution sécurisée et fiable pour les transactions en ligne. Enfin, MariaDB/MySQL servira de base de données pour stocker toutes les informations liées aux utilisateurs, aux abonnements, aux médias, ainsi qu’à l’historique de visionnage et aux commentaires.

6. Fonctionnalités futures envisagées

En plus des fonctionnalités principales, le site intégrera des options supplémentaires pour améliorer l’expérience des utilisateurs. Un système de notation permettra aux utilisateurs de donner une note aux films et séries qu’ils visionnent, avec un score moyen affiché sur chaque fiche de média. Pour renforcer l’aspect social, un mode de visionnage en groupe sera disponible, permettant de regarder des films ou des séries simultanément avec d’autres utilisateurs à distance, avec un chat intégré pour interagir pendant la session de groupe.

Le site inclura également un système de badges et récompenses, permettant aux utilisateurs de débloquer des badges en fonction de leurs activités (comme le nombre de films visionnés ou la création de listes). Pour les amateurs de séries, un système de suivi des séries enverra des notifications automatiques lorsque de nouveaux épisodes ou saisons seront ajoutés aux séries suivies par l’utilisateur.

Enfin, pour les abonnés disposant d’un abonnement familial, un support multi-profils permettra de créer plusieurs profils sous un même compte, chacun avec ses propres préférences et historique de visionnage. Le site proposera également un mode “Cinéaste”, avec des dossiers spéciaux comprenant des making-of, des interviews exclusives, et des documentaires sur la production de certains films ou séries pour enrichir l’expérience de visionnage des utilisateurs les plus passionnés.

7. Conclusion

Le site de streaming en ligne payant proposera une expérience complète et immersive pour les amateurs de films et de séries, en offrant un catalogue riche et varié, des fonctionnalités interactives et sociales, ainsi qu’un système de recommandations personnalisées pour satisfaire les attentes de chaque utilisateur. Grâce à une interface moderne et fluide, le site vise à devenir une référence dans le domaine du streaming, en offrant une alternative de qualité aux plateformes existantes et en répondant aux besoins des utilisateurs les plus exigeants.

8. Annexes

Listes des récompenses et badges : 

- Badge “Cinéphile” : débloqué après avoir visionné 50 films
- Badge “Séries Addict” : débloqué après avoir visionné 3 saisons complètes de séries
- Badge “Critique en herbe” : débloqué après avoir laissé 10 commentaires validés
- Badge “Explorateur” : débloqué après avoir visionné au moins un film de chaque catégorie
- Badge “Pionnier” : débloqué après avoir visionné un film le jour de sa sortie en salle
- Badge “Collectionneur” : débloqué après avoir ajouté 50 films à sa liste personnelle
- Badge “Marathonien” : débloqué après avoir visionné 5 films en une seule journée
- Badge “Noctambule” : débloqué après avoir visionné 3 films entre minuit et 6h du matin
- Badge “Globe-trotter” : débloqué après avoir visionné un film dans une langue étrangère
- Badge “Festif” : débloqué après avoir visionné un film de Noël pendant la période des fêtes
- Badge “Déjà-vu” : débloqué après avoir visionné 3 fois le même film
- Badge “Tendance” : débloqué après avoir visionné un film recommandé par le système de recommandations
- Badge “Social” : débloqué après avoir partagé une liste de films avec au moins 5 utilisateurs
- Badge “Fidèle” : débloqué après 1 an d’abonnement sans interruption
- Badge “VIP” : débloqué après 3 ans d’abonnement sans interruption
- Badge “Loyal” : débloqué après 5 ans d’abonnement sans interruption
- Badge “Pionnier” : débloqué après avoir été parmi les 100 premiers inscrits sur le site
- Badge “Créatif” : débloqué après avoir créé une liste de films personnalisée
- Badge “Généreux” : débloqué après avoir offert un abonnement à un autre utilisateur
- Badge “Curieux” : débloqué après avoir visionné un film d’un genre inconnu
- Badge “Expert” : débloqué après avoir visionné 100 films
- Badge “Mélomane” : débloqué après avoir visionné 10 films musicaux
- Badge “Fan” : débloqué après avoir visionné tous les films d’un réalisateur
- Badge “Sélectionneur” : débloqué après avoir ajouté 10 films à une liste personnelle
- Badge "Cinéphile Débutant" : Pour avoir visionné son premier film ou série.
- Badge "Marathonien" : Pour avoir regardé 10 épisodes d’une série en une journée.
- Badge "Explorateur de Genres" : Pour avoir visionné des films/séries dans au moins 5 genres différents (ex" : action, comédie, horreur, etc.).
- Badge "Collectionneur" : Pour avoir créé et partagé sa première liste de films/séries.
- Badge "Critique en Herbe" : Pour avoir laissé un commentaire sur 5 films ou séries.
- Badge "Connaisseur" : Pour avoir noté 20 films ou séries.
- Badge "Superstar des Salles" : Pour avoir visionné un film ou une série en mode groupe avec 5 amis.
- Badge "Série Addict" : Pour avoir suivi et terminé au moins 3 séries complètes.
- Badge "Maître du Popcorn" : Pour avoir visionné plus de 100 heures de contenu.
- Badge "Découvreur de Trésors" : Pour avoir regardé 10 films ou séries avec une note moyenne inférieure à 3 étoiles (basée sur les votes des autres utilisateurs).
- Badge "Historien du Cinéma" : Pour avoir regardé 10 films classiques (avec une date de sortie avant l’année 2000).
- Badge "Compagnon Fidèle" : Pour avoir regardé au moins 10 films ou séries recommandés par d’autres utilisateurs.
- Badge "Nouvelle Vague" : Pour avoir visionné les 5 derniers films ou séries ajoutés à la plateforme.
- Badge "Cinéaste Virtuel" : Pour avoir contribué à 5 discussions dans le mode “Cinéaste” (interviews, making-of).
- Badge "Abonné de longue date" : Pour avoir maintenu un abonnement actif pendant 1 an.
- Badge "Amateur de Sagas" : Pour avoir visionné toutes les saisons d’une série longue (plus de 5 saisons).
- Badge "Nuit Blanche" : Pour avoir regardé du contenu en continu de minuit à 6 heures du matin.
- Badge "Féru de Documentaires" : Pour avoir visionné 10 documentaires.
- Badge "Fan Hardcore" : Pour avoir regardé 3 fois le même film ou série.
- Badge "Binge-watcher" : Pour avoir regardé une saison complète d’une série en une seule journée.
- Badge "Partageur Prolifique" : Pour avoir partagé des listes avec 10 amis.
- Badge "Film Festival Addict" : Pour avoir regardé 5 films primés dans des festivals de cinéma majeurs (Cannes, Sundance, etc.).
- Badge "Globetrotter" : Pour avoir regardé des films ou séries dans au moins 5 langues différentes.
- Badge "Sous-titreur" : Pour avoir utilisé des sous-titres en 3 langues différentes.
- Badge "Enthousiaste de la Première" : Pour avoir regardé un film ou un épisode le jour de sa sortie.
- Badge "Chef de Bande" : Pour avoir organisé 5 sessions de visionnage en groupe.
- Badge "Duo Dynamique" : Pour avoir regardé au moins 5 films/séries en mode groupe avec la même personne.
- Badge "Gourmand de Bonus" : Pour avoir regardé 5 making-of ou bonus de films/séries.
- Badge "Cinéphile du Monde" : Pour avoir regardé des films ou séries de 10 pays différents.
- Badge "Génie des Langues" : Pour avoir regardé des films ou séries dans 5 langues différentes.
- Badge "Festivalier" : Pour avoir regardé 5 films ou séries lors d’un festival de cinéma.

Schéma BDD : https://www.planttext.com/?text=jLV9Rjim4BthAmIVsg06qW2v146GKo-zz0-8iKZRB2XH82cxHj9_xt0HHKbKhC2eB-8id7cpS4Y_QWFATBKe2jOORYxbHdSxNIdU6YuRlIb1boDE-LQK-BihEIsVIjwOyldvry-N5yVTRjsdWPgXy0IgEeBwylt-_glZjufNLa1PnMiGAFXMFdWHxHHO-uGtf9QDEMfllVWx8EiqKnwHMwr1OXMNqB0Qk4W43u-F0IPeVPQAvcHLfnJY8MbEI0BWHmy0gafsLj60wMmmhEdgD9gHXIFNHghBBDszO6siMIXNiRec81ICAmv-V7uQEk8bs2ka3r9nviinaBTqXu0T4rD1uhHcb8FpvrPhN3b5OYujwmjWkMXEi5nnzL7QFB2OBLGox8_ny6HpM98f9XXeDajX9K-s9ZKSyluCxFSe-Arbu_L1cvvE8vSdpdpaTdKjyhJ9c0extUfJEq770U3NQpBktEIRp1aXM8GTK_F3pLgk9GqDrHFhF3gxEPUBXUwdZQqXhzbY3HDy0ff3XtNq02DrIyDN6EArVYTZlnDUzExvkHpSHQmpSVP0a-6OJPchl8LDbm-zoRh6THWUWRXfn2qZQi7Dw-0bgrBXVOEoz40lJkqpcEeu6jSZpdyAGk1S8CuHew4tOz_w0qNadk1eE6UkpLR0HI0yd-v8tGHrqjB3zQWOOgC4p1HcrzAC90CpQS0nAmAEx7m7BgSHA5s5OFu4oeiYegKRTdK-FmmfMD_bi8_nlBzlj_8jF8-UoiqHzARmL11b7nUzPjROqWwkIMkV3NA_AQPZoMrz3_SqwYgsHmp4o4NLWV5avqDZW6FgYza8cbg-hfeyJleu4_bRk59TG1E9lpA3n1DHuY_4cBX01t6yldfvd77pn0Y4vpCpLOfLF2jkS42HtMMk56bdh1Ky61jFfQdRj7PPpUWx1JYCZgbMV-mjmicX3Qh3tedxl7gclWj7C6S_EJ0fxlpEyM4fNl7PY_-7_W40
