---
title: 'Where Pushword is going ? Roadmap, TODO and Ideas'
h1: Roadmap
publishedAt: '2025-12-23 05:48'
parentPage: contribute
toc: true
---

Long road till today ! Half way till tomorrow. First commit _Nov 10, 2018_.

## Before v1

### In progress

- finish upgrade to Symfony 8 /PHP 8.5
  - [ ] https://github.com/symfony/panther/pull/678
        `packages/admin/tests/Frontend/AdminJSTest.php~`
  - [ ] https://github.com/nunomaduro/termwind/pull/205
  - [ ] https://github.com/nunomaduro/collision/pull/340
  - [ ] restore twigstan - https://github.com/twigstan/twigstan/issues/277

- check the rerender fix in admin-block editor https://github.com/codex-team/editor.js/issues/2821

- [AdminBlockEditor] Manage pasting html (from google sheets/words/etc) -> convert to markdown and then do as usual

- [i18n] move en.md to en/index.md when en/ exists in flat

- [flat] import media tags from lightroom/darktable keywords (exif data ?)
  ➜ done, but need test

- [Ai] Add AiFeature to Flat
  - [x] Generate a map of the content = [Docs] Generate a map eg : https://docs.claude.com/en/docs/claude-code/claude_code_docs_map.md
        index.{locale}.csv
  - [x] Generate a map of the media
  - [ ] In new, create default AGENTS.md (inspired from altimood)

- [Core] fix glightbox or rollback to fslightbox
  - [ ] video in lightbox
  - [ ] simple image self linked (the pb is more a missing code after migrating to markdown block)
  - [ ] convertImageLinkToWebPLink() is not working anymore

- [New] When release v1, remove version from composer.json (restore 1.0.0-rc[0-9]+ to \*)

### One day maybe

- best bractice : migrate to #[MapQueryParameter] ?string $source = null, and #[MapFormParameter] instead of request

- [Flat] Implémenter un sync auto depuis [Admin] et un file watcher (bin/console content:watch) pour l'autre sens // l'implémentation rapide c'est un cron sur bin/console flat:sync

- [Admin] / [Version] Autosave with unsaved state : envoyer un event toutes les secondes si le contenu a été modifié, celui-ci créé une nouvelle version du contenu en précisant que c'est une sauvegarde automatique, si la précédente sauvegarde est une sauvegarde automatique et qu'elle date de moins d'une heure, alors on ne garde qu'une version dans le versionner (la dernière)

- [Core] / [Admin] **Media** :
  - [ ] revoir comment sont récoltés les usages d'un média
  - [ ] stocker en DB (donc mieux tuiler avec pages, quid des medias utiliser dans des templates ?)
  - [ ] pouvoir filtrer les médias non utilisés (cf point précédent) via Admin
  - [ ] cli tool to clean unused media ?

- [Core] / [Admin] **Media** : Ajouter les tags au media
  - [ ] Tags manuellement
  - [ ] Tags importé depuis les pages qui utilisent le média

- [Core] / [Admin] Bulk edition des tags depuis la page de listing

- [ ] Rename skeleton
      Wich is absolutely not a skeleton, it's more a dev-test env or a demo

- [JsHelper] start-show-more : voir pour améliorer le close :
  - [ ] show more :
    - si l'utilisateur clique sur un jump link qui renvoie vers un contenu dans un bloc show-more
    - si un hash dans l'url renvoie vers un contenu dans un bloc show-more
    - si un hash de type (`#:~:text=`) renvoie vers un contenu dans un bloc show-more
  - [ ] désactiver si le scroll est très rapide (couvre l'use case l'utilisateur utilise ctrl+f)
  - [ ] garder en mémoire qui est ouvert, qui est fermé (couvrira le rechargement)
  - [ ] désactiver si l'utilisateur n'est pas en haut de la page (couvrira ctrl+f ),

- [ ] Replace .clickable by css (https://codepen.io/potatoDie/pen/abzvGxG)

- [Admin] / [Core] easily customize navbar with favorites `page` ➜ utiliser plutôt les tags et ajouter un loader spécifique : #navbar100 #navbar200 #navbar300, charger toutes les pages qui ont un tag commençant par #navbar, organisé par ordre alphabétique et créer le menu d'après ces pages)

- [Core] **pagination** : tester & documenter
  - Bug quand une page a le même URI qu'une page de la pagination OU sur l'ID (attrapé avant la pagination)
    => En fait, c'est paginer la page d'accueil qui fait le max de bordel - changer pour un format d'uril + robuste (ex : /1 ➜ /p1 et interdire les slugs de type /p[0-9]+)

- [PageScanner] Ignorer les erreurs :
  - [ ] donner un code unique aux erreurs
  - [ ] via la config (fait pour les URLs)
  - [ ] via un code inline de type <!-- page-scanner-ignore: what to ignore --> ou othersParameters
- [PageScanner] Live page scanner
- [PageScanner] image ➜ texte alternatif manquant

- [Core] / [Admin] / [PageScanner] Check there is no translation with the same language than current page

- check a new blank installation + ci + last details
  - [x] dev environnement setup
  - [ ] Docker image / Frankenphp ?
  - [ ] usage setup - see if there is a prompt for first user
  - [ ] TwigStan + TwigFormatter
  - [ ] translate all packages (fr / en) + manage date i18n a better way than randomly

- [Admin] / [AdminBlockEditor] (cerise) TocAvoir un block à gauche de l'éditeur pour afficher la liste des blocs utilisés, pouvoir déplacer ces blocs facilement en sélecctionnant un bloc, ou un groupe de blocs naturellement groupés sous un header, fonctionne depuis le markdown ou depuis l'editorjs

- [AdminBlockEditor] New features
  - [ ] upgrade editorjs/list ajoute notamment le support des checklists
        https://github.com/editor-js/list/pull/126
  - [ ] Hyperlink - Custom rel (onclick button to configure the rel instead of hideForBot)
        same for _target_ (blank) and _class_ (button/discret) ➜ input with suggests + icon to set quick
  - [ ] Attaches / Images
    - [ ] Add a delete button to change the media
    - [ ] Add the inline uploader (Uploader.ts~) (?)
  - [ ] inline tool, on right or left from the border of inline tool, go outside the tag inline (bold, italic, strike, underline, link, marker)
  - [ ] on paste on paragraph, être capable de détecter si le contenu collé est du markdown et créer les blocs en fonction
  - [ ] New Block :
    - [ ] Audio block ?!
    - [ ] Notices block (with different notices level)
    - [ ] Group = div wrapper with anchor and class (and strettched ? flex ? grid ? start-show-more ?), nearest imlpementation:
      - https://github.com/serlo/backlog/issues/83
      - https://github.com/calumk/editorjs-columns/pull/6
  - [ ] Migrate to tiptap (lol)

- [Admin] Migrate to EasyAdmin

- [Static] revoir la compression pour du contenu statique ➜ https://dunglas.dev/2024/12/http-compression-in-php-new-symfony-assetmapper-feature/

- https://x.com/jh3yy/status/1798728699459563905 (altimood)

- [AdminBlockEditor] PagesList/CardList/Gallery ➜ Voir pour utiliser grid-col-12 and col-span-3/4/2 to be able to fully customize it - via Class ?

- [Version] Advanced Diff Checker basé sur Monaco et le versionning de markdown
  And **Change requester**, **Public Historic** (or make accessible historic from page object)

- [Static] Make ErrorPageGenerator consistent with htaccess (on htaccess, filter by beginning url to return the correct one ?!)

- Intégrer **LinksImprover** (+ UX)

- **Complex Right System** : Multi-user editor Multi-site but not everybody can edit everything (extension or core ?)

- **eCommerce** bridge with sylius or odoo ?!

```

```
