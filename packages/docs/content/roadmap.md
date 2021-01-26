---
title: "Where Pushword is going ? Roadmap, TODO and Ideas"
h1: Roadmap
toc: true
parent: contribute
---

## TODO before v1

-   release de sonata 4
-   Issue : User Password Edit don't work from admin
-   manage date i18n a better way than randomly

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

### Editor.js

Look for a better writer experience (https://github.com/front/g-editor or https://editorjs.io) (1/2)

## Soon

-   **Admin** : extend parameters and events to filters and form for all admin (will permit extension)
-   **Extend** : Partially implemented in core. May added test and form field in admin ?
-   Schema.org dans le backend d'une page
-   **Static**: copy only used media in public
-   **FacebookManager** : post from facebook
-   **Flat**: Transform markdown link to page link (useful for navigate in docs from editor)
-   **Flat**: Throw error when the content is more up to date in database
-   Intégrer **LinksImprover** (+ UX), après précédent
-   **name suggester**: parse content, find words or multiple words used only in this doc, suggest it as potential name, s'active au moment du clic sur l'input name
-   **Complex Right System** : Multi-user editor Multi-site but not everybody can edit everything (see draft.md) (extension or core ?)
-   **Page-Scanner** : scanner une page en direct + scanner plus de choses (texte alternatif manquant, etc.)
-   **Multi-upload** (see https://packagist.org/packages/silasjoisten/sonata-multiupload-bundle)
-   Test the code, search for all "todo" in the code,

## One day (maybe)

-   Add https://github.com/nan-guo/Sonata-Menu-Bundle
-   **Wordpress** to Pushword/Core (and vice versa)
-   **Flat** (spatie/yaml-front-matter, vérif à chaque requête pour une sync constante admin <-> flat files)
-   Create a page from a Media (media edit) => button to create a new page with title = name and mainImage = Media
    (useful for photographer website)... or create a dynamic page /media/[slug]/ showing data from Media

### Smart image optimizer (global - piedweb package)

Using all otpimizer avalaible, generating optimized image version and choosing the smallest file or keeping the default one)

### Switch to commonMark

Need to add [markdown=1](https://spec.commonmark.org/0.29/#example-158:~:text=markdown%3D1) feature in league/commonmark

### Settings Manager <smal>Extension</smal>

Rewrite the template editor by loading existing files and bundle files (view only and override action) in an file entity. CRUD via SONATA, listener to update file.

How to manage yaml/xml/php config files ?!
