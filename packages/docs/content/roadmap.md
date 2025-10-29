---
title: 'Where Pushword is going ? Roadmap, TODO and Ideas'
h1: Roadmap
toc: true
parent: contribute
---

Long road till today ! Half way till tomorrow. First commit _Nov 10, 2018_.

## Before v1

### In progress

- [AdminBlockEditor] transition JSON -> Markdown -> EditorJS
  - [x] editorjs to markdown
  - [x] markdown to editorjs... en cours, non fonctionnel
  - [x] implémenter prettier markdown
  - [x] enregistrer en DB uniquement le markdown
  - [x] admin - revoir la class qui instantie pour montrer EditorJS si le contenu est en markdown (donc markdown ➜ editorjs à l'initialisation, nouveau comportement par défaut)
  - [ ] test test test (with docs)
  - [ ] cli tool pour convertir le json en markdown (upgrade) qui s'appuie sur EditorJsExportMarkdown.js (ou puppeteer)
  - selecting a block alone like gallery : copy / paste is not working

- [Config] Utiliser prepend plutôt que le système bizarre mis en place

- [JsHelper] Finir la transition tailwind v3 -> v4 et vite
  - [ ] finir la transition : ajouter vite bundle dans les requirements de skeleton plutôt que gérer les assets de manière autonome
  - [ ] Mettre à jour la doc pour un upgrade
  - [ ] Renommer JsHelper en FrontEndHelper or FrontEnd (and transforming it in a bundle wich extends the vite bundle ?)

- [Flat] ⚡ Accélérer l'import-export en changeant de paradigme ➜ **sync**
  1. [ ] Lister les slugs + dernière date de moficiation machine (ajouter le timestamp dans l'entité en le vérouillant pour que seul Doctrine ou le DB engine puisse le modifier, clarifier les 2 autres éléments createdAt et updatedAt)
  2. [ ] Idem en chargeant la liste des slugs depuis la liste des fichiers à importer /(.\*)\.md$/ + la dernière fois que le fichier a été modifié
  3. [ ] Diff entre les 2 liste : les slugs à supprimer, les slugs à ajouter, les slugs à mettre à jour
  4. [ ] Bridge avec versionner (si lastEditMsg change, on créé une nouvelle version ? ou on considère comme une autosave)
  5. [ ] Implémenter un sync auto depuis [Admin] et un file watcher (bin/console content:watch) pour l'autre sens // l'implémentation rapide c'est un cron sur bin/console flat:sync

- [Flat]
  - [ ] mettre à jour le dossier par défault : content/%domain%/
  - [ ] Revoir la transformation de lien markdown (./../test.md) en lien vers la page (/test) (useful for navigate in docs from editor)
  - [ ] s'assurer que la réciproque fonctionne au moment de l'export
  - [ ] idem pour les médias (/media/test.jpg ➜ ./../../media/test.jpg) : plus simple que le point précédent, il faut calculer le nombre de "../" entre le dossier courant et la racine du projet

### One day maybe

- [Admin] / [Version] Autosave with unsaved state : envoyer un event toutes les secondes si le contenu a été modifié, celui-ci créé une nouvelle version du contenu en précisant que c'est une sauvegarde automatique, si la précédente sauvegarde est une sauvegarde automatique et qu'elle date de moins d'une heure, alors on ne garde qu'une version dans le versionner (la dernière)

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
  - [ ] show more :
    - si l'utilisateur clique sur un jump link qui renvoie vers un contenu dans un bloc show-more
    - si un hash dans l'url renvoie vers un contenu dans un bloc show-more
    - si un hash de type (`#:~:text=`) renvoie vers un contenu dans un bloc show-more
  - [ ] désactiver si le scroll est très rapide (couvre l'use case l'utilisateur utilise ctrl+f)
  - [ ] garder en mémoire qui est ouvert, qui est fermé (couvrira le rechargement)
  - [ ] désactiver si l'utilisateur n'est pas en haut de la page (couvrira ctrl+f ),

- [ ] Replace .clickable by css (https://codepen.io/potatoDie/pen/abzvGxG)

- [Admin] / [Core] easily customize navbar with favorites `page` ➜ utiliser plutôt les tags et ajouter un loader spécifique : #navbar100 #navbar200 #navbar300, charger toutes les pages qui ont un tag commençant par #navbar, organisé par ordre alphabétique et créer le menu d'après ces pages)

- [Core] **pagination** : tester & documenter
  - Bug quand une page a le même URI qu'une page de la pagination OU sur l'ID (attrapé avant la pagination)
    => En fait, c'est paginer la page d'accueil qui fait le max de bordel - changer pour un format d'uril + robuste (ex : /1 ➜ /p1 et interdire les slugs de type /p[0-9]+)

- [PageScanner] Ignorer les erreurs :
  - [ ] donner un code unique aux erreurs
  - [ ] via la config (fait pour les URLs)
  - [ ] via un code inline de type <!-- page-scanner-ignore: what to ignore --> ou othersParameters
- [PageScanner] Live page scanner
- [PageScanner] image ➜ texte alternatif manquant

- [Core] / [Admin] / [PageScanner] Check there is no translation with the same language than current page

- check a new blank installation + ci + last details
  - [x] dev environnement setup
  - [ ] Docker image / Frankenphp ?
  - [ ] usage setup - see if there is a prompt for first user
  - [ ] TwigStan + TwigFormatter
  - [ ] translate all packages (fr / en) + manage date i18n a better way than randomly

- [Admin] / [AdminBlockEditor] (cerise) TocAvoir un block à gauche de l'éditeur pour afficher la liste des blocs utilisés, pouvoir déplacer ces blocs facilement en sélecctionnant un bloc, ou un groupe de blocs naturellement groupés sous un header, fonctionne depuis le markdown ou depuis l'editorjs

- [AdminBlockEditor] New features
  - [ ] upgrade editorjs/list ajoute notamment le support des checklists
        https://github.com/editor-js/list/pull/126
  - [ ] Hyperlink - Custom rel (onclick button to configure the rel instead of hideForBot)
        same for _target_ (blank) and _class_ (button/discret) ➜ input with suggests + icon to set quick
  - [ ] Attaches / Images
    - [ ] Add a delete button to change the media
    - [ ] Add the inline uploader (Uploader.ts~) (?)
  - [ ] inline tool, on right or left from the border of inline tool, go outside the tag inline (bold, italic, strike, underline, link, marker)
  - [ ] on paste on paragraph, être capable de détecter si le contenu collé est du markdown et créer les blocs en fonction
  - [ ] New Block :
    - [ ] Audio block ?!
    - [ ] Notices block (with different notices level)
    - [ ] Group = div wrapper with anchor and class (and strettched ? flex ? grid ? start-show-more ?), nearest imlpementation:
      - https://github.com/serlo/backlog/issues/83
      - https://github.com/calumk/editorjs-columns/pull/6
  - [ ] Migrate to tiptap (lol)

- [Admin] Migrate to EasyAdmin

- [Static] revoir la compression pour du contenu statique ➜ https://dunglas.dev/2024/12/http-compression-in-php-new-symfony-assetmapper-feature/

- https://x.com/jh3yy/status/1798728699459563905 (altimood)

- [AdminBlockEditor] PagesList/CardList/Gallery ➜ Voir pour utiliser grid-col-12 and col-span-3/4/2 to be able to fully customize it - via Class ?

- [Version] Advanced Diff Checker basé sur Monaco et le versionning de markdown
  And **Change requester**, **Public Historic** (or make accessible historic from page object)

- [Static] Make ErrorPageGenerator consistent with htaccess (on htaccess, filter by beginning url to return the correct one ?!)

- Intégrer **LinksImprover** (+ UX)

- **Complex Right System** : Multi-user editor Multi-site but not everybody can edit everything (extension or core ?)

- **eCommerce** bridge with sylius or odoo ?!
