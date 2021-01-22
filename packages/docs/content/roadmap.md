---
title: "Where Pushword is going ? Roadmap, TODO and Ideas"
h1: <s>ROADMAP</s> Just TODO and IDEA
toc: true
parent: contribute
---

## TODO before v1

-   release de sonata 4
-   Issue : User Password Edit don't work from admin
-   installer via composer-create (command pushword:new creating a new app config array)

## TODO Extension

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
-   Settings Manager (simple textarea permitting to edit pushword config and parameters ? and rebooting cache)
-   smart image optimizer (using all otpimizer avalaible and choosing the smallest file)
-   interventionImageBundle (sortir mediaManager du bundle principal)

### To plan

-   Media Management v2 : utiliser IPTC&exif pour stocker toutes les infos stockées en bdd (static power)
-   CI : Test Installer
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
-   Installation without composer (download composer if not installed)
-   Pagination children/list (molto idea : PageController capture les pages /slug/[0-0]\*/ et renvoie si existe)
-   Page with dynamic slug ?!
-   Add https://github.com/nan-guo/Sonata-Menu-Bundle
-   Move route to annotation (less extendable but more pratical with priority)
-   Move media to var (and create a link ?!)
