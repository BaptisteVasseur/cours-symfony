# Installation

## Installer Symfony, Mysql (ou Postgresql)

- Installez **Symfony** via le repo git officiel, via le cli Symfony ou via composer
- Installez **MySQL** soit en local, soit via Docker, soit via MAMP/WAMP/LAMP/XAMP

## Installer les autres trucs

- Installez **Github Copilot** (ou faites la demande dans un 1er temps)
- Installez **XDebug**

## Regarder les fichiers / dossiers

- Regardez le contenu du fichier **composer.json**
- Regardez le contenu de **config/routes.yaml** , **config/services.yaml**
- Regardez les dossiers **src/** et **templates/**

<br>

> Si à un moment, vous êtes confrontés à une erreur (dans la console ou sur votre page web), pensez à BIEN LIRE L'ERREUR OKKK ??? en général, c'est facile de savoir ce qu'il faut faire pour corriger le problème.

<br>

# Entités, Migrations, Fixtures

## Entités

- **Lisez le cahier des charges**
- **Regardez le schéma** de base de donnée

> Pour chaque entité (table dans la db), le nom de l'entité == au nom de la table au singulier, en camelCase et en anglais

> Pour chaque entité (table dans la db), vous devez ajouter des propriétés grace à la commande symfony. Le nom des propriété est toujours en camelCase et en anglais. Le nom de la propriété ne doit pas contenir "id"

- Créez une entité pour chacune des tables suivantes : **categories**, **languages**, **user**, **subscription**, **playlist**, **comment**, **media**, **movie**, **serie**, **season**, **episode**
- Créez une entité pour chacune des tables suivantes : **subscription_history**, **playlist_subscription**, **watch_history**, **playlist_media**
- **Faites les liaisons** entre les différentes tables (OneToMany, ManyToMany)
- **Faites les liaisons** entre Categorie et Media et entre Language et Media (ManyToMany)
- **Faites l'héritage** entre les entités Movie et Serie (Serie hérite de Media et Movie hérite de Media)

## Migration

- **Créez une migration** grâce à la commande Symfony
- Ouvrez le fichier créé et **supprimez les commentaires** générés

> Supprimer les commentaires permet de dire aux autres développeurs que vous avez bien relu la migration

- **Exécutez la migration** grâce à la commande Symfony

## Fixtures PHP

- Créez un fichier **fixtures php** avec la commande Symfony
- Ouvrez le fichier créé
- Créez 2 catégories différentes (Action et Aventure par exemple) en faisant du PHP Objet (donc en faisant un 'new Category()' et en utilisant les setters pour mettre des données à l'intérieur)
- **Chargez vos fixtures** en DB grace à la commande Symfony

> Ne pas oublier le **persist()** et le **flush()**, sans ça votre fixture ne sera pas en DB

- Faites la même chose pour les **langues**, cette fois en utilisant une boucle for (ou foreach peu importe)
- Faites pareil en ajoutant un **utilisateur**, un **média**, une **playlist**
- Relier dans les fixtures les médias aux catégories, aux langues et aux playlists
- Faites ça pour **TOUTES** les entités

## Fixtures YAML

- Installez "**alice**" avec composer et les recettes **Symfony Flex**
- Ouvrez le dossier à la racine du projet "**fixtures/**"
- Dans ce dossier, créez un fichier **categories.yaml** et faites des fixtures pour les "Categorie" dedans
- Pareil pour les **langues**, les **users**, les **medias**, ....

> C'est quand même plus simple en YAML non ?


# Templates

## Commencer à intégrer les templates

- Prenez tous les fichiers **.html** du dossier téléchargé sur Github et déplacez-les dans le dossier "**templates/**" du projet Symfony
- Pour chaque fichier, **renommez l'extension en .html.twig**
- **Organisez vos fichiers** comme vous le voulez dans des sous-dossiers pour qu'il y ait une structure de dossier cohérente (les templates pour les films ensemble, ceux pour l'authentification ensemble...)
- Pour chaque fichier de template, **créez un Controller** qui affiche le template. (Utilisez la même architecture de dossier pour vos controller et vos templates)

## Sous template et héritage

- Ajouter un fichier **base.html.twig**, dans ce fichier, mettez toute la structure de base de vos pages : la balise **head**, une partie de votre balise **body** (en gros les balises **jusqu'au <main></main>**)

> En gros, tout le contenu commun à vos pages (menu gauche, sidebar droite, top bar, footer ...) vont dans ce fichier 'base.html.twig'

- Ajoutez 2 "blocks" (block **title**, **content**) dans le fichier **base.html.twig** (un au niveau du titre de la page, un au niveau du contenu de la page dans la balise main)
- **Découpez votre fichier base.html.twig en sous template**, (menu gauche dans **parts/left-menu.html.twig** et menu droit dans **parts/right-sidebar.html.twig**)
  <br><br>
- Ouvrez le fichier index.html.twig, **supprimez tout le code en commun** avec le fichier base.html.twig et **entourez le code spécifique de votre page par un "block" content**. **Ajoutez un titre à votre page** avec le block "title". Sans oubliez '**{% extends "base.html.twig" %}**' en haut du fichier pour dire que ce fichier **hérite** de base.html.twig
  <br><br>
- **Découpez votre page index en sous template** (par exemple un sous template pour les cards des films/séries) : "parts/movies/movie-card.html.twig
- Faites la même chose pour les pages "**discover.html.twig**", "**category.html.twig**", "**subscription.html.twig**", "**list.html.twig**", "**detail.html.twig**" et "**detail_serie.html.twig**"

> Vos pages doivent donc ne plus avoir de code en commun et doivent toutes avoir un block "content" et un block "title"

> Vous devez également sur toutes vos pages avoir au minimum un "include" de sous template (pour des cards par exemple)

- Modifiez le sous template **left-menu.html.twig** pour **ajouter les liens** vers les différentes pages de l'application. Pensez à utiliser la fonction twig "**path()**" pour générer les URL dynamiquement **en fonction du nom des routes** que vous avez définies dans le #[Routes(name: 'nom_de_la_route')] de vos controllers
- Pensez à utiliser des "if" pour ajouter des classes tailwind sur **l'element actif** en utilisant : **{% if app.current_route == 'nom_de_la_page' %}fill-red-600{% endif %}** (app.current_route est une variable globale de twig qui contient le nom de la route actuelle)

## Utilisez la BDD avec les templates

- Allez sur la page qui liste les catégories, **identifiez le controller qui affiche la page**

> Pour identifier le controller : soit on regarde l'URL et on regarde dans tous nos controllers lequel correspond, soit on install la debug toolbar de Symfony avec "composer require debug". En rafraichissant la page, en bas à droite, il y a le nom du controller qui s'est executé.

- Utilisez le repository des Categories en l'**injectant en dépendance**, et faites un **findAll()** pour tout récupérer depuis la table en BDD. Passez le résultat au template. Ensuite dans votre template, utilisez **une boucle "for"** pour boucler sur l'affichage du sous template "**parts/category/category-card.html.twig**". Modifier le sous template pour **rendre dynamique l'affichage** (afficher le nom de la catégorie)

> On n'a pas encore l'icône de la catégorie en BDD, donc on peut ajouter une propriété sur l'entité "Categorie" "icon" de type "text". Une fois ajoutée avec la commande Symfony, on doit générer une migration, la vérifier et l'exécuter... (quand on modifie une entité, on doit toujours faire ça pour que la BDD soit à jour)

- Maintenant la page pour voir **le détail d'une catégorie** : on identifie le controller qui affiche la page, on ajoute un paramètre dans l'URL : "id", on injecte l'entité qu'on souhaite récupérer et on passe le résultat dans le template twig. Dans le template : on affiche le nom de la catégorie en haut en gras et on affiche les medias de la catégorie grâce à la boucle for.
- On retourne dans le fichier discover.html.twig et on génère une URL dynamiquement grâce à la fonction twig 'path()' dans le href du lien de la card 'parts/category/category-card.html.twig'
  <br><br>
- **Faites pareil pour la page des abonnements** : injectez le repository des abonnements, récupérez les abonnements en BDD et passez les abonnements au template. Dans le template, bouclez sur les abonnements pour afficher le nom de l'abonnement et le prix. 
- Pour les cards qui concernent les films, **ajoutez les liens dynamiques avec path()** pour pouvoir cliquer sur les cards et aller sur la page de détail
- Rendez dynamique la page pour voir **le détail d'un film** (toutes les infos, le listing des commentaires, du staff, du casting, des catégories ...)

> La prochaine page, ce n'est pas que du Symfony, là ça demande à réfléchir à une logique de dev : "Comment est-ce que je peux implémenter cette feature avec ce que je connais déjà ?"

- **Rendez dynamique la page pour lister les playlists** : là, il faut injecter 2 repositories : PlaylistRepository et PlaylistSubscriptionRepository et faire 2 'for' dans le template au niveau du sélecteur. Ajoutez un peu de JS : quand on change le sélecteur, on ajoute un paramètre GET dans l'URL "?selectedPlaylist=XXX" (XXX c'est l'id de la playlist sélectionnée dans le sélecteur).
- Quand le JS et fait, il faut retourner dans le controller et ajouter un peu de code PHP : Récupérez le paramètre de query 'selectedPlaylist' et allez chercher en BDD l'entité. Passez cette entité dans le template et affichez les films liés à cette playlist.
- Si vous revenez sur la page sans le paramètre de query 'selectedPlaylist' vous devriez avoir une erreur, corrigez l'erreur en ajoutant quelques 'if' dans le code php et dans le template twig.
  <br><br>
- Sur la page d'accueil, **ajoutez une feature pour afficher les films les plus populaires**. Pour ça, ouvrez le bon controller, injectez le MediaRepository. Dans le MediaRepository, ajoutez une methode personnalisée findPopular(). Faites la requête avec le QueryBuilder pour récupérer les médias les plus populaires (populaire = un film regardé beaucoup de fois, donc souvent dans WatchHistory). Utilisez votre methode et passez le résulat au template twig. Dans le template twig, utilisez un 'for' autour du sous template 'parts/movies/movie-card.html.twig' pour afficher les films les plus populaires.



# Authentification

## 1ère étape

- **Lancez la commande pour générer le formulaire de login** (quand on ne connait pas la commande, on peut toujours taper "php bin/console login" pour voir toutes les commandes Symfony avec "login" dedans)
- **Corrigez l'erreur** liée à l'interface
- Rajoutez une propriété "**roles**" (de type json) (en faisant un '**make:entity**') à l'entité User
- **Faites une migration et exécutez-la**
- Ajoutez les 3 méthodes requises par l'interface **UserInterface** (getRoles, getUserIdentifier, eraseCredentials)

> getRoles : sert à retourner les roles/permissions de l'utilisateur. getUserIdentifier : sert à retourner l'identifiant de l'utilisateur (ici, l'email). eraseCredentials : sert à effacer les données sensibles de l'utilisateur après l'authentification (nous on s'en fout, on ne fait rien)

- **Relancez la commande** pour générer le formulaire de login, cette fois-ci, il ne devrait plus y avoir d'erreur. Symfony a généré un formulaire de login pour vous, un controller et a modifié le fichier security.yaml

### 2éme étape

- Allez faire un tour dans le **security.yaml**
- Ajoutez **un nouveau provider** de type entity (voir la doc)
- Modifiez le provider du firewall "**main**"
- Définissez une **role_hierarchy** avec 2 ou 3 roles différents (Par exemple : ROLE_USER, ROLE_ADMIN, ROLE_BANNED. Le ROLE_USER est le role par défaut. Le ROLE_ADMIN a plus de permissions que le ROLE_USER. Le ROLE_BANNED a moins de permissions que le ROLE_USER)

### 3éme étape

- Retournez dans les fixtures et injecter en dépendance dans le constructeur le **UserPasswordHasherInterface**
- Au niveau de la génération des utilisateurs, **hashez le mot de passe** (voir la doc pour la fonction à utiliser) et mettre le nouveau mdp hashé dans le user avec le **setPassword()**
- Ajoutez un rôle dans le user grace au **setRoles()** (sinon vous pouvez définir le role par défaut dans le constructeur de l'entité User)
- **Relancez les fixtures**

- Rendez-vous dans le template de login généré par la commande Symfony, modifiez le nom du block '**body**' par '**content**' (le nom du block généré automatiquement par Symfony n'est pas bon et ne correspond pas à ce que l'on a défini dans notre base.html.twig)

> Il est possible que le controller généré par Symfony ait la même route que notre controller de login, dans ce cas, il faut changer la route de notre controller de login pour éviter les conflits et pouvoir voir la page de login générée par Symfony (toute façon on va supprimer notre controller de login après)

- Essayez de vous connecter sur la page de login **générée par Symfony** avec un utilisateur que vous avez créé avec les fixtures

### 4éme étape

- Une fois connecté, allez dans le template **right-sidebar.html.twig**, ajoutez en haut du champ de recherche une div contenant "**Bonjour XXX**" avec XXX = username de l'utilisateur connecté
- **Déconnectez-vous**. Maintenant, vous avez une erreur sur la page d'accueil. **Corrigez l'erreur** en ajoutant un if autour de la div créée

> Quand on est connecté, on a une variable globale de twig qui s'appelle **app.user** qui contient l'utilisateur connecté. Si on n'est pas connecté, cette variable est nulle donc il faut penser à vérifier si elle est nulle ou non avant de l'utiliser sur les pages accessibles aux utilisateurs connectés et non connectés

- Allez dans **left-menu.html.twig**, modifiez le menu pour afficher un bouton de connexion lorsque l'on n'est pas connecté et un bouton de déconnexion quand on est connecté.
- Sur la page des abonnements, rendre dynamique en fonction de si l'utilisateur est connecté ou non **la div qui affiche l'abonnement en cours de l'utilisateur** (et afficher le nom de l'abonnement en cours si l'utilisateur à un abonnement en cours)
- Sur la page des playlists, **ne récupérez que les playlists de l'utilisateur connecté**. S'il n'est pas connecté, redirigez le keumé vers la page d'accueil. (Plus besoin de récupérer les données via les repositories, on peut directement les récupérer via la variable globale de twig **app.user** ou **$this->getUser()** dans le controller)

### 5éme étape

- La page de login générée par Symfony n'est pas trop trop belle donc on va utiliser la nôtre. Pour ça, supprimez **NOTRE controller login** (créé au tout début du cours). Ensuite, dans **NOTRE template twig** 'login.html.twig', ajoutez **name="_username"** et **name="_password"** sur les inputs pour l'email et le mot de passe. Ajoutez aussi '**value="{{last_username}}"**' sur l'input pour l'email. Ajoutez dans notre fichier l'input '_**csrf_token**'. Oubliez-pas la **method="post"** sur le formulaire de la page.

> Ajouter _username et _password c'est pour que Symfony comprenne bien comment récupérer les données du formulaire. Le csrf_token c'est pas empêcher un type de faille de sécurité sur les formulaires. Le last_username c'est pour pas avoir à retaper 15 fois son email si on se trompe de password.

- Vous pouvez aussi ajouter le **{% if error %}...{% endif %}** dans le template de notre page login.

> Notre projet est sous tailwind, Symfony préfère bootstrap donc il faut remplacer 'alert alert-danger' par des classes tailwind pour que ce soit tout beau tout propre en rouge quand il y a une erreur de connexion.

- **Supprimez le template de login généré par Symfony**. Dans le controller de login généré par Symfony, n'oubliez pas de changer le nom du template à afficher par le nôtre. **Et voilà**
- Maintenant, on devrait avoir une page de login **de toute beauté** quand on va sur /login

### 6éme étape

##### Feature de Forgot Password : L'utilisateur demande à réinitialiser son mot de passe

- Allez sur la page qui affiche le template **forgot.html.twig**, et ouvrez le controller qui affiche la page. Ajoutez **name="_email"** sur l'input email dans le template twig. Le form doit envoyer le form en **"POST"**. Dans le controller, récupérez grâce à la request (en injectant la Request dans le controller) le paramètre 'email' (**$request->get('email')**). Cherchez en DB l'utilisateur avec l'adresse email correspondante. Si l'utilisateur n'est pas trouvé, **envoyer un message d'erreur** grâce à un message flash. S'il est trouvé, **généré un resetToken pour l'utilisateur** (grâce à la lib **UUID** de Symfony) et modifiez sa propriété. Sauvegardez le token en base de données grâce à l'EntityManager (que vous devez injecter en dépendance dans le controller).
  <br><br>
- Après le flush, **envoyez un email à l'utilisateur** avec un lien pour réinitialiser son mot de passe. Pour envoyer un email, il faut configurer la variable MAILER_DSN dans le fichier **.env**. Pour envoyer un email, il faut utiliser le service **MailerInterface** (que vous devez injecter en dépendance dans le controller). Pour envoyer un email, il faut créer un **TemplatedEmail** et utiliser la méthode **send()** du service MailerInterface (à injecter en dépendance dans le controller). Pour le lien de réinitialisation, il faut utiliser la fonction **path()** de twig pour générer l'URL dynamiquement. Pour le context, il faut passer le **resetToken** et l'**email** de l'utilisateur. Pour le template de l'email, il faut créer un fichier **reset.html.twig** dans le dossier **templates/email**. 

> L'avantage de passer par Twig pour envoyer un email, c'est qu'on peut envoyer un email en HTML avec du CSS et tout et tout. C'est plus joli pour l'utilisateur.

##### Feature de Reset Password : L'utilisateur clique sur le lien pour réinitialiser son mot de passe

- Allez sur la page qui affiche le template **reset.html.twig**, identifiez le controller. Ouvrez le controller. Ajouter un paramètre dans l'url "token" (ce sera le resetToken généré juste avant). Cherchez l'utilisateur en BDD avec le resetToken. Si pas trouvé = erreur. Si trouvé = affichez la page et le formulaire de reset.
  <br><br>
- Dans la même méthode, si la méthode de la requête est POST **$request->isMethod('POST')**, récupérez le mot de passe et le mot de passe de confirmation. Si les deux mots de passe ne sont pas identiques, **envoyez un message d'erreur**. Si les deux mots de passe sont identiques, **hasher le mot de passe** et le sauvegarder dans l'utilisateur avec le **setPassword()** puis sauvegardez-le avec le **flush()** dans l'**EntityManagerInterface** (injection de dépendance encore !!). **Ajoutez un message de succès dans les messages flash** puis redirigez l'utilisateur vers la page de login.

> Pour hasher le mot de passe, il faut utiliser le service **UserPasswordHasherInterface** (comme pour les fixtures)
> Pour les messages flash, il faut utiliser la méthode **addFlash()** du controller
> Pour rediriger l'utilisateur, il faut utiliser la méthode **redirectToRoute()** du controller

### 7éme étape

- On va maintenant protéger les pages de l'application. Pour ça, on va utiliser les **attributs** de Symfony. Pour protéger une page, il faut ajouter une annotation **@IsGranted()** au-dessus de la méthode du controller qui affiche la page. Pour les pages qui ne doivent être accessibles qu'aux utilisateurs connectés, on peut utiliser **@IsGranted("ROLE_USER")**. (car le rôle par défaut est ROLE_USER). Pour les pages qui ne doivent être accessibles qu'aux administrateurs, on peut utiliser **@IsGranted("ROLE_ADMIN")**.   
- Ouvrez le controller qui affiche la page 'mes lists', ajoutez l'annotation **@IsGranted("ROLE_USER")**. Faites pareil pour la page 'mes abonnements'.
- Pour la page 'administration', ajoutez l'annotation **@IsGranted("ROLE_ADMIN")**.

> Si un utilisateur non connecté essaie d'accéder à une page protégée, il sera redirigé vers la page de login. Si un utilisateur connecté essaie d'accéder à une page protégée pour laquelle il n'a pas les permissions, il aura une erreur 403.
> Pour les pages qui doivent être accessibles à tout le monde, il ne faut pas mettre d'annotation **@IsGranted()**.
> IsGranted est disponible aussi dans twig, donc on peut faire des conditions dans les templates pour afficher ou non des éléments en fonction des permissions de l'utilisateur connecté. (ex : afficher un bouton de suppression uniquement si l'utilisateur connecté est l'auteur du média ou si l'utilisateur connecté est admin)

- Dans le sous template 'parts/left-menu.html.twig', ajoutez un lien pour accéder à la page 'administration' si l'utilisateur connecté a le rôle 'ROLE_ADMIN'. Pour ça, utilisez la fonction twig **is_granted('ROLE_ADMIN')**. Si l'utilisateur connecté a le rôle 'ROLE_ADMIN', affichez le lien vers la page 'administration'.



# Formulaires / CRUD (simple)

- On va maintenant créer un **CRUD** (create, read, update, delete) pour les catégories et les langues. Pour ça, utilisez la commande **make:crud** de Symfony.
- Générez le CRUD pour **Category** et pour **Language**. Pleins de fichiers ont été générés. Ouvrez le controller généré et regardez les différentes méthodes. Ouvrez les templates générés et regardez comment sont affichés les différents éléments.

> Liste des fichiers générés lors de la création du CRUD pour **Category** : 
> - **src/Controller/CategoryController.php** → Le controller qui gère les différentes actions pour les catégories (afficher la liste, afficher le détail, ajouter, modifier, supprimer)
> - **src/Form/Category1Type.php** → Le formulaire pour ajouter ou modifier une catégorie (celui-ci contient les champs que va avoir le formulaire)
> - **templates/category/_delete_form.html.twig** → Le formulaire pour supprimer une catégorie
> - **templates/category/_form.html.twig** → Le formulaire pour ajouter ou modifier une catégorie
> - **templates/category/edit.html.twig** → Le template pour modifier une catégorie
> - **templates/category/index.html.twig** → Le template pour afficher la liste des catégories
> - **templates/category/new.html.twig** → Le template pour ajouter une catégorie
> - **templates/category/show.html.twig** → Le template pour afficher le détail d'une catégorie

> On n'aura pas besoin de tous les fichiers générés, on va en supprimer certains. On va aussi modifier les templates pour qu'ils soient plus jolis.

- Supprimez **templates/category/show.html.twig** (on n'en a pas besoin, on a déjà la page de détail des catégories)
- Modifiez les templates '**edit**', '**index**', '**new**', '**delete**' pour qu'ils soient plus jolis. Pour ça, vous pouvez vous inspirer des autres templates de l'application (admin_films.html.twig, admin_users.html.twig, admin_add_films_form.html.twig...). (**C'est du HTML/Tailwind après, plus trop du Symfony**)

> Vous pouvez utiliser des composants tailwind pour rendre les templates plus jolis.
> Pour les formulaires, vous pouvez utiliser les classes tailwind pour les inputs, les labels, les boutons, les messages d'erreur...

> Vous pouvez personnaliser l'affichage des formulaires en modifiant le fichier twig **_form.html.twig** ou le fichier php **CategoryType.php** (pour ajouter des champs, enlever des champs, changer le type d'un champ, ajouter des classes...) ([voir la doc](https://symfony.com/doc/current/forms.html))
> Si vous voulez définir **un thème pour les formulaires**, vous pouvez le faire dans le fichier **config/packages/twig.yaml** en appliquant un thème de formulaire par défaut ([voir la doc](https://symfony.com/doc/current/form/form_themes.html)) ou en créant le vôtre.

- Utilisez les différentes pages créées pour **ajouter**, **modifier**, **supprimer** des catégories et des langues (afin de tester que tout fonctionne bien)
- Remarquez que vous pouvez ajouter des catégories/langues avec un nom de 1 caractère, essayez de corriger ça en ajoutant **une contrainte de validation** sur le nom/label des catégories/langues. ([voir la doc](https://symfony.com/doc/current/validation.html)) 
- Pour empêcher ça, ajoutez un attribut **#[Assert\Length(min=3)]** sur le champ de l'entité Category/Language qui correspond au nom/label de la catégorie/langue. Ajoutez aussi une annotation **#[Assert\NotBlank]** pour que le champ ne soit pas vide.

> Ce qu'il faut retenir : On peut créer des formulaires (**form**) ou des **CRUD** via des commandes Symfony pour gagner un max de temps. 
> Ensuite, on ajoute **des contraintes de validation** sur les propriétés des entités pour éviter les erreurs de saisie. 
> On peut aussi personnaliser les formulaires en modifiant les fichiers twig ou les fichiers php générés.



# Feature d'upload de fichiers

- On va créer un endroit sur le site pour qu'un admin puisse uploader une image (template : **upload.html.twig**). Il pourra ensuite se servir des images partout sur le site en copiant le lien de l'image.

- Ajouter une entitée '**Upload**' (propriété à ajouter : uploadedBy, uploadedAt, url)

> uploadedBy : l'utilisateur qui a uploadé l'image \
> uploadedAt : la date d'upload \
> url : l'url de l'image stockée sur le serveur 

- Générer une migration et exécutez-la. Ajoutez dans le constructeur de l'entité **$this->uploadedAt = new \DateTimeImmutable()** pour que la date d'upload soit automatiquement ajoutée à l'entité.
- Allez dans le controller UploadController, dans la méthode qui existe déjà, injectez le repository UploadRepository et récupérez tous les uploads en BDD. Passez les uploads au template twig 'upload.html.twig' et modifiez le template pour afficher les images stockées en BDD. (pour l'instant, il n'y a rien, donc ça n'affiche rien)
- Ajoutez une autre méthode uploadApi disponible via l'url '/api/upload' avec comme nom 'api_upload'

```php
#[Route(path: '/api/upload', name: 'api_upload')]
public function uploadApi(
    Request $request,
    FileUploader $fileUploader,
    EntityManagerInterface $entityManager
): Response
{
    /** @var UploadedFile[] $files */
    $files = $request->files->all()['files'];

    foreach ($files as $file) {
        $fileName = $fileUploader->upload($file);
        $upload = new Upload();
        $upload->setUploadedBy($this->getUser());
        $upload->setUrl($fileName);
        $entityManager->persist($upload);
    }

    $entityManager->flush();

    return $this->json([
        'message' => 'Upload successful!',
    ]);
    
    return $this->json([
        'message' => 'Upload failed!',
    ], Response::HTTP_BAD_REQUEST);
}
```

> Ce code permet de récupérer les fichiers envoyés par le formulaire en front, de les uploader sur le serveur, de les stocker en BDD et de retourner un message de succès ou d'erreur en JSON.
> **FileUploader** n'existe pas encore, c'est un service qu'on va créer nous même pour gérer l'upload de fichier. (On vient dire que ce code est dépendant du service FileUploader qu'on va créer)
 
- Dans un nouveau fichier dans **src/service/FileUploader.php**, ajoutez le code suivant :

```php
public function __construct(
    #[Autowire('%kernel.project_dir%/public')] private string $targetDirectory,
    private SluggerInterface $slugger,
    private LoggerInterface  $logger,
) {
}

public function upload(UploadedFile $file, $folder = '/uploads'): string
{
    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    $safeFilename = strtolower($this->slugger->slug($originalFilename));
    $fileName = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

    try {
        $file->move($this->targetDirectory . $folder, $fileName);
    } catch (FileException $e) {
        $this->logger->error('An error occurred while uploading the file: '.$e->getMessage());
    }

    return $folder . '/' . $fileName;
}
```

> Ce code permet de : \
> 1 - Récupérer le nom du fichier uploadé \
> 2 - Générer un nom de fichier sécurisé (grâce à une dépendance : le Slugger Symfony et un id unique aléatoire) \
> 3 - Déplacer le fichier dans le dossier public/uploads (chemin absolu du dossier) \
> 4 - Retourner le chemin du fichier pour le stocker en BDD (chemin du fichier relatif au dossier public/)

- Créez un dossier **public/uploads** à la racine du projet pour stocker les images uploadées.
- Allez dans le fichier template '**upload.html.twig**' et dans le code JS à la toute fin dans **document.querySelector('form').addEventListener('submit')**, ajoutez 

```js
const formData = new FormData();
uploadedFiles.forEach(file => {
    formData.append('files[]', file);
});

fetch('{{ path('api_upload') }}', {
    method: 'POST',
    body: formData
}).then(response => {
    window.location.reload();
}).catch(error => {
    console.error(error);
    alert('Une erreur est survenue');
});
```

> Ce code permet de récupérer les fichiers uploadés dans le formulaire, de les envoyer en POST à l'API uploadApi, de recharger la page si l'upload est réussi et d'afficher une alerte si l'upload a échoué. A noté : On gère l'upload multiple de fichiers

- **Testez** l'upload d'images sur la page 'upload.html.twig'. Vous devriez voir les images uploadées s'afficher sur la page.

<br><br>
<br><br>
<br><br>

# Prochaines étapes : 

- Utiliser le système **Serializer** de Symfony pour transformer des objets en JSON (API)
- Utiliser le système de **Deserializer** de Symfony pour transformer du JSON en objet (API)
- Utiliser le système d'**événements** de Symfony pour hasher le mot de passe avant de le sauvegarder en BDD
- Utiliser le système d'**événements** de Symfony pour envoyer un email dés qu'un episode d'une série est ajouté
- Utiliser le système de message flash pour **afficher des messages à l'utilisateur** (succès, erreur, warning)
