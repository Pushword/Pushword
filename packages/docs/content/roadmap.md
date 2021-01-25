---
title: "Where Pushword is going ? Roadmap, TODO and Ideas"
h1: Roadmap
toc: true
parent: contribute
---

## TODO before v1

-   release de sonata 4
-   Issue : User Password Edit don't work from admin

### Image-Intervention-Bundle

Move ImageManager in a new bundle and add scandir (pw.media_dir) to replace repository if $mediaClass is not autowired.

### Settings Manager <smal>Extension</smal>

Simple textarea permitting to edit pushword config and parameters ? and rebooting cache from admin

Same via command line

How to manage yaml/xml/php config files ?!

### Dynamic URL <smal>Extension</smal>

C'est un gros morceau pour garder la compatibilité avec le static generator et le router actuel

Le but: une page est crée avec un slug classique `/blog/example-tag/`

Une propriété `dynamic` lui est attributé qui permettra de créer une nouvelle route

Une autre proprité `dynamicPage` permettra d'assigner automatiquement un paramètre à ces pages enfants
(par exemple la `parentPage` ou encore la `metaRobots`)

Ex: dynamic: `/blog/{tag<[a-z-]+>?1`} (cf https://symfony.com/doc/current/routing.html#optional-parameters:~:text=inlined%20requirements)

_Une bonne pratique serait de définir toutes les variantes possible ou une contrainte pour éviter de se retrouver avec un nombre de page infini._

callback ?! sans ça, impossible de maintenir la compatibilité avec le static generator

Quand l'utilisateur cherchera à atteindre /blog/anotherexample/

Si la page n'a pas été créée (elle peut d'ailleur l'être en utilisant _extend_).

Alors la page chargé sera `/blog/example-tag/` (entity créée à la volée sans sauvegarde dans la BDD)
avec l'argument accessible getDynamicArg et les propriétés écrasés par **dynamicPage**.

Drawback : le sitemap actuelle ne permettra pas de générer ces pages. Switch to a sitemap render after a crawl ?

### Pagination

Pagination children/list

PageController capture par défaut les pages /slug/{paginate<\d+>?1`} et renvoie si existe la même page mais avec la liste intégrée

Deux choses :

-   soit charger les résultats via _ajax_ (compatibilité avec turbolinks ?) en renvoyant juste le block concerné (donc utilisé `liveBlock`)
-   soit on génère une nouvelle page dans ce cas là :
    comment gérer le duplicate content autour de la liste, l'indexation, la génération dans le sitemap, la compatibilité avec turbolinks si on charge via ajax (donc à éviter)
    KISS : un paramètre `paginatePage` écrase les propriétés (en les préservant dans _extend_) avec par défault un page.title = page.title ~ '- ' ~ paginate

Peut-être un pas vers dynamic URL ou s'appuyer dessus.

## One day

-   Extend <smal>Extension</smal> Partially implemented in core. May added test and form field in admin ?
-   template editor : permit to list and read template file from bundle
-   Intégrer Schema.org dans le backend d'une page
-   Static: copy only used media in public
-   FacebookManager (post from facebook and ~~show last facebook status~~ )
-   Flat: Transform markdown link to page link (useful for navigate in docs from editor)
-   Flat: Throw error when the content is more up to date in database... add export (and maintain ID)
-   Wordpress To Pushword/Core (and vice versa)
-   Intégrer LinksImprover (+ UX), après précédent
-   name suggester : parse content, find words or multiple words used only in this doc, suggest it as potential name
-   export/import FLAT FILES (spatie/yaml-front-matter, vérif à chaque requête pour une sync constante admin <-> flat files)
-   Create a page from a Media (media edit) => button to create a new page with title = name and mainImage = Media
    (useful for photographer website)... or create a dynamic page /media/[slug]/ showing data from Media
-   Author for page (will permit to manage page view right later)
-   Archive edit (page) (extension or core ?)
-   Multi-user editor Multi-site but not everybody can edit everything (see draft.md) (extension or core ?)
-   Look for a better writer experience (https://github.com/front/g-editor or https://editorjs.io) (1/2)
-   Gérer un système d'extension viable pour l'admin : à l'install, créer les fichiers Admin qui étendent l'admin de base
    L'ajout d'un plugin modifie automatiquement ce nouveau fichier en ajoutant le code nécessaire (ajout d'une trait + édition d'une fonction)
    Retro-compatibilité : créer le fichier admin + le services (autowire) si il n'existe pas
-   Scan : scanner une page en direct + scanner plus de choses (liens externes, texte alternative manquant, etc.)
-   Multi upload
-   Test the code, search for all "todo" in the code,
-   Page with dynamic slug ?!
-   Add https://github.com/nan-guo/Sonata-Menu-Bundle
-   Move route to annotation (less extendable but more pratical with priority)

### Smart image optimizer (global - piedweb package)

Using all otpimizer avalaible, generating optimized image version and choosing the smallest file or keeping the default one)

### Switch to commonMark

Need to add [markdown=1](https://spec.commonmark.org/0.29/#example-158:~:text=markdown%3D1) feature in league/commonmark
