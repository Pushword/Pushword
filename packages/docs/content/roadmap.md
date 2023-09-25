---
title: 'Where Pushword is going ? Roadmap, TODO and Ideas'
h1: Roadmap
toc: true
parent: contribute
---

## BugFix && To finish



-   Conversation form controller
    -   manage multiHost
-   Scan : ajouter un check des liens vides `"></a`
-   Yaml editor in Autres paramêtres

-   Switch image block to use media.id moroever image.uri
    -   event listener on load to update json
-   Rectorize with all List
-   AdminBlockEditor : new list item, évitez d'ajouter le lien d'avant sans ancre...
-   Implement ESlint
-   https://github.com/jolicode/JoliTypo

-   [AdminBlockEditor] Add Layout https://github.com/hata6502/editorjs-layout#readme

-   [StaticGenerator] Very long CopierGenerator, MediaGenerator -> set a warning in docs

-   [MultiSiteEditor] : Make admin with better isolation

    -   parent page
    -   menu when editing a page
    -   admin page list with only 1 domain listing + default correct selected domain on click add new
    -   preview : manage page link to be OK when it's not the fist domain

-   // TODO check a new blank installation

-   [AdminBlockEditor] Image > Add link + Alt and redesign Legend
-   [AdminBlockEditor] document block and add Help link when advanced knowledge is needed
-   [AdminBlockEditor] édition avancée (template notamment dans pages, prose/unprise)
    -   rewrite the fullscreen, wide, and max width from prose - [see](https://github.com/tailwindlabs/tailwindcss-typography/pull/204)
-   [Core] : ImageManager - make optimizer bin path configurable

-   {Core] **pagination** : tester & documenter
    -   Bug quand une page a le même URI qu'une page de la pagination OU sur l'ID (attrapé avant la pagination)
        => En fait, c'est paginer la page d'accueil qui fait le max de bordel
-   [StaticGenerator] Make ErrorPageGenerator consistent with htaccess (on htaccess, filter by beginning url to return the correct one ?!)

-   [AdminBlockEditor] sanitize with https://github.com/editor-js/editorjs-php (see AdminFormEventSuscriber.php)
-   [AdminBlockEditor] prepare translating and transalte

## Feature

-   **Page scanner** : test current page anchor link
-   Toward wiki !
    -   **Change requester**
    -   **Public Historic** (or make accessible historic from page object)
-   Youtube Importer (from a youtube hash, create a page with video and text is imported from subtitle)
-   **Multi-upload** (see https://packagist.org/packages/silasjoisten/sonata-multiupload-bundle)
-   Intégrer **LinksImprover** (+ UX)
-   **Page-Scanner** :
    -   scanner une page en direct + scanner plus de choses
        -   texte alternatif manquant
        -   Check there is no translation with the same language than current page
    -   add <!-- page-scanner-ignore: what to ignore --> ou plutôt dans othersParameters
    -   page scanner --alter :)
-   **eCommerce** bridge with sylius ?!
-   **Advanced main image** : associé un champ vidéo à l'image d'en-tête
-   **Admin** : extend parameters and events to _filters_ and _lister_ will permit extension)
-   **Static**: copy only used media in public
-   **FacebookManager** : post from facebook
-   **Flat**:
    -   Transform markdown link to page link (useful for navigate in docs from editor)
    -   Throw error when the content is more up to date in database

## Others

-   **Core** : implement **SonataUserBlundle** (see user_block.html.twig), wait for https://github.com/sonata-project/SonataUserBundle/pull/1256
-   manage date i18n a better way than randomly (document the process)
-   Simplify request to external service with one pipe (toward Guzzle and 1 configuration for all extension)
-   API ?!
-   **Complex Right System** : Multi-user editor Multi-site but not everybody can edit everything (see draft.md) (extension or core ?)
-   **Core** : Rewrite filter componenent to use the power of symfony service

## One day (maybe)

-   Auto-update npm package (js-helper and editorjs-tool) via Github Actions (or at least git hooks)
-   **Best testing** Fluidifier le process de test et deploiement (tester avec les vrais données)
-   Move global app_base_url, name and color to à better spot (like évent suscriber)
-   Move weird entity trait constructor to lificycle callback
-   Move notify to messenger bus ? : https://symfony.com/doc/current/the-fast-track/fr/18-async.html

*   **Admin** : Automatic save without flooding version
*   **Version** : Rewrite to load in an entity versionned version and used sonata filters
*   **Admin** (and admin extensions): Manage SonataAdminMenuOrder a better way than randomly
*   **Wordpress** to Pushword/Core (and vice versa)
*   **Flat** (spatie/yaml-front-matter, vérif à chaque requête pour une sync constante admin <-> flat files)

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
