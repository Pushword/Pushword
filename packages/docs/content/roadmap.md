---
title: 'Where Pushword is going ? Roadmap, TODO and Ideas'
h1: Roadmap
toc: true
parent: contribute
---

Long road till today ! Half way till tomorrow. First commit _Nov 10, 2018_.

## Before v1

- [ ] TODO check a new blank installation

  - [x] dev environnement setup
  - [ ] usage setup - see if there is a prompt for first user

## Features

- [AdminBlockEditor] Image > Add link + Alt and redesign Legend + edit image (inspired by Bard)
-
- [AdminBlockEditor] Make a POC with ProseEditor (then a migration tool ?)

- [ ] Block Editor wich can be insert dinamically in Page (often to replace template editor)

- [ ] [AdminBlockEditor] https://github.com/calumk/editorjs-codecup (or ACE or codemirror)

- [AdminBlockEditor] Noticies block with different notices level (inspired by Grav)
- [AdminBlockEditor] To Markdown (to go back to standard editor) or To Html (easy, just need to created block.md.twig) and the transpiler)
- [AdminBlockEditor] Card List (like page list but custom entry, custom url, custom everything... see piedvert.com cards everywhere)
- [AdminBlockEditor] PagesList/CardList/Gallery ➜ Heavily use grid-col-12 and col-span-3/4/2 to be able to fully customize it

- [ ] [Admin] easily customize navbar with favorites `page`

- [Version] Advanced Diff Checker raw /editorjs

  - And :
    - **Change requester**
    - **Public Historic** (or make accessible historic from page object)

- [All] prepare translating and transalte

- [Admin] AssetMapper instead build ?! will avoid 20 seconds build time
- [Admin] switch code editor to https://microsoft.github.io/monaco-editor/ or codeMirror ( + prose editor) ➜ https://blog.replit.com/code-editors

- [AdminBlockEditor] Group
  - = div wrapper with anchor and class (and strettched ? flex ? grid ?), nearest imlpementation:
    - https://github.com/serlo/backlog/issues/83
- [AdminBlockEditor] https://github.com/calumk/editorjs-nested-checklist
- [AdminBlockEditor] Block Nav Menu (guttenberg style) and moving blocks
- [AdminBlockEditor] **Multi-upload** (see https://packagist.org/packages/silasjoisten/sonata-multiupload-bundle) + Multi Select

- [Core] **pagination** : tester & documenter

  - Bug quand une page a le même URI qu'une page de la pagination OU sur l'ID (attrapé avant la pagination)
    => En fait, c'est paginer la page d'accueil qui fait le max de bordel

- [StaticGenerator] Make ErrorPageGenerator consistent with htaccess (on htaccess, filter by beginning url to return the correct one ?!)

- Intégrer **LinksImprover** (+ UX)

- [PageScanner] add <!-- page-scanner-ignore: what to ignore --> ou plutôt dans othersParameters
  - donner un code unique aux erreurs
  - en 1 click (ignore error)
  - - see ignored errors ui
- [PageScanner] image ➜ texte alternatif manquant
- [PageScanner] Check there is no translation with the same language than current page

- [Core] MediaCleaner command : find unused media and removed them (page scanner or cli-tool?!)

- [x] [Flat] Transform markdown link to page link (useful for navigate in docs from editor)
- [ ] [Flat] Throw error when the content is more up to date in database
- [ ] [Flat] (spatie/yaml-front-matter, vérif à chaque requête pour une sync constante admin <-> flat files)

- manage date i18n a better way than randomly (document the process)
- **Complex Right System** : Multi-user editor Multi-site but not everybody can edit everything (extension or core ?)
- **eCommerce** bridge with sylius ?!
