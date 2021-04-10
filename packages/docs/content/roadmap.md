---
title: "Where Pushword is going ? Roadmap, TODO and Ideas"
h1: Roadmap
toc: true
parent: contribute
---

## TODO before v1

-   Admin Block Editor : filter only image (svg ?) on select
-   Admin Block Editor Tools : can't delete PagesList Block => https://github.com/codex-team/editor.js/issues/1640
-   Page scanner : media to download are shown as error
-   Page Scanner : , surveille les liens encryptés + liens vers la preprod (= surveiller canonical)
-   release de sonata 4
-   implement **SonataUserBlundle** (see user_block.html.twig), wait for https://github.com/sonata-project/SonataUserBundle/pull/1256
-   manage date i18n a better way than randomly (document the process)

-   **Editeur** : icône non voyant pour l'alt / lien sur image / rounded

## Soon

-   test fresh install
-   **Core** : ImageManger - make optimizer bin path configurable
-   **Core** on login success, redirect to previous route (see UserAuthenticator)
-   **Core** : use autowire and autoconfigure per default and remove useless lines
-   Simplify request to external service with one pipe (toward Guzzle)
-   **pagination** : tester & documenter
-   **Block editor** : édition avancée (template notamment dans pages, prose/unprise)
-   **Prose/Unprose** : Avoid empty prose div : (see two block unprose one after the other)
-   **clickable**
-   associé un champ vidéo à l'image d'en-tête (plugin !)
-   **Page Scanner** : add <!-- page-scanner-ignore: what to ignore --> ou plutôt dans othersParameters
-   API ?!
-   **Page Scanner** check redirection
-   **Page Scanner** Check there is no translation with the same language than current page
-   **eCommerce** bridge with sylius
-   **Admin** : extend parameters and events to _filters_ and _lister_ will permit extension)
-   **Static**: copy only used media in public
-   **FacebookManager** : post from facebook
-   **Flat**: Transform markdown link to page link (useful for navigate in docs from editor)
-   **Flat**: Throw error when the content is more up to date in database
-   Intégrer **LinksImprover** (+ UX), après précédent
-   **name suggester**: parse content, find words or multiple words used only in this doc, suggest it as potential name, s'active au moment du clic sur l'input name
-   **Complex Right System** : Multi-user editor Multi-site but not everybody can edit everything (see draft.md) (extension or core ?)
-   **Page-Scanner** : scanner une page en direct + scanner plus de choses (texte alternatif manquant, etc.)
-   **Multi-upload** (see https://packagist.org/packages/silasjoisten/sonata-multiupload-bundle)
-   **Admin Block Editor** : sanitize with https://github.com/editor-js/editorjs-php (see AdminFormEventSuscriber.php)

## One day (maybe)

-   Auto-update npm package (js-helper and editorjs-tool) via Github Actions
-   Stop using Repo and start autowiring thé good repo (used multiple repo)
-   Fluidifier le process de test et deploiement (tester avec les vrais données)
-   Move global app_base_url, name and color to à better spot (like évent suscriber)
-   Move weird entity trait constructor to lificycle callback
-   Move notify to messenger bus ? : https://symfony.com/doc/current/the-fast-track/fr/18-async.html

*   **Pagination** : move to extension and drop pagerfanta
*   **Admin** : Automatic save without flooding version
*   **Version** : Rewrite to load in an entity versionned version and used sonata filters
*   Add https://github.com/nan-guo/Sonata-Menu-Bundle
*   **Wordpress** to Pushword/Core (and vice versa)
*   **Flat** (spatie/yaml-front-matter, vérif à chaque requête pour une sync constante admin <-> flat files)
*   Create a page from a Media (media edit) => button to create a new page with title = name and mainImage = Media
    (useful for photographer website)... or create a dynamic page /media/[slug]/ showing data from Media

### Smart image optimizer (global - piedweb package)

Using all otpimizer avalaible, generating optimized image version and choosing the smallest file or keeping the default one)

### Switch to commonMark

Need to add [markdown=1](https://spec.commonmark.org/0.29/#example-158:~:text=markdown%3D1) feature in league/commonmark

### Settings Manager <smal>Extension</smal>

Rewrite the template editor by loading existing files and bundle files (view only and override action) in an file entity. CRUD via SONATA, listener to update file.

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
