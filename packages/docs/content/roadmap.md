---
title: 'Where Pushword is going ? Roadmap, TODO and Ideas'
h1: Roadmap
publishedAt: '2025-12-23 05:48'
toc: true
---

Long road till today ! Half way till tomorrow. First commit _Nov 10, 2018_.

### In progress

- [ ] npx pushword ai plugin inspired from https://github.com/gsd-build/get-shit-done https://github.com/pbakaus/impeccable and current prompt/cmd/skills on my projects (wip -> ai-skills package)

### On our way

- [Ai] Add AiFeature to Flat
  - [x] Generate a map of the content = [Docs] Generate a map eg : https://docs.claude.com/en/docs/claude-code/claude_code_docs_map.md
  - [x] Generate a map of the media
  - [ ] upgrade skills -> composer req pushword/docs, composer update (retrieve pushword core version upgrade), read vendor/pushword/docs.../upgrade.md, fix local codebase, test (manual composer dev + check few pages) and composer test if available claude --resume b88a1a80-843c-4e8f-994c-23ef98e63f59

- best practice : migrate to #[MapQueryParameter] ?string $source = null, and #[MapFormParameter] instead of request
  (zero usage in the repo today)

- [Admin] / [Version] Autosave with unsaved state : envoyer un event toutes les secondes si le contenu a été modifié, celui-ci créé une nouvelle version du contenu en précisant que c'est une sauvegarde automatique, si la précédente sauvegarde est une sauvegarde automatique et qu'elle date de moins d'une heure, alors on ne garde qu'une version dans le versionner (la dernière)

- [Core] / [Admin] Bulk edition des tags depuis la page de listing -> must rely on checkbox, on checked instead of only having "Delete" action, having "Bulk edit"

- [JsHelper] show-more : gérer les fragments de texte (`#:~:text=`)
  Le reste est fait (jump links, hash, mémoire localStorage, Ctrl+F) —
  `packages/js-helper/src/ShowMore.js`. Reste ce seul cas : `querySelector()`
  ne sait pas résoudre un text fragment, l'exception est avalée.

- [ ] Replace .clickable by css (https://codepen.io/potatoDie/pen/abzvGxG)

- [Admin] / [Core] easily customize navbar with favorites `page` ➜ utiliser plutôt les tags et ajouter un loader spécifique : #navbar100 #navbar200 #navbar300, charger toutes les pages qui ont un tag commençant par #navbar, organisé par ordre alphabétique et créer le menu d'après ces pages)

- [Core] **pagination** : documentée dans `/pages-list`, reste le format d'URL
  - Bug quand une page a le même URI qu'une page de la pagination OU sur l'ID (attrapé avant la pagination)
    => En fait, c'est paginer la page d'accueil qui fait le max de bordel - changer pour un format d'uril + robuste (ex : /1 ➜ /p1 et interdire les slugs de type /p[0-9]+)
    `RoutePatterns::PAGER` vaut toujours `\d+`

- [PageScanner] Ignorer les erreurs :
  - [ ] donner un code unique aux erreurs
  - [x] via la config (`errors_to_ignore`, global ou `host/slug:`, fnmatch)
  - [ ] via un code inline de type <!-- page-scanner-ignore: what to ignore --> ou othersParameters
- [PageScanner] Live page scanner : le polling existe (`getScanOutput` +
  `output_fragment.html.twig`) — préciser ce qui manque encore

- check a new blank installation + ci + last details
  - [x] dev environnement setup
  - [ ] Docker image / Frankenphp ? (documenté, mais aucune image)
  - [x] usage setup - prompt for first user
  - [x] TwigFormatter (`.twig-cs-fixer.dist.php`, dans `composer format` et la CI)
  - [ ] TwigStan
  - [ ] manage date i18n a better way than randomly
        (les clés fr/en sont à parité ; reste `card.html.twig` qui code en dur `d/m/Y à H:i`)

- [Admin] / [AdminBlockEditor] (cerise) TocAvoir un block à gauche de l'éditeur pour afficher la liste des blocs utilisés, pouvoir déplacer ces blocs facilement en sélecctionnant un bloc, ou un groupe de blocs naturellement groupés sous un header, fonctionne depuis le markdown ou depuis l'editorjs

- [AdminBlockEditor] New features ➜ `Plans/editorjs-next-features.md`
  - [ ] upgrade editorjs/list ajoute notamment le support des checklists
        v2.0.9 est publiée (on est en 1.10) — elle absorbe `nested-list` et change
        la forme stockée, donc migration à écrire
  - [ ] Hyperlink - Custom rel (onclick button to configure the rel instead of hideForBot)
        _target_ et _class_ sont déjà là ; il ne manque que la valeur du rel
  - [ ] Attaches / Images
    - [ ] Add a delete button to change the media
    - [ ] Add the inline uploader (Uploader.ts~) (?)
  - [ ] inline tool, on right or left from the border of inline tool, go outside the tag inline (bold, italic, strike, underline, link, marker)
  - [ ] on paste on paragraph, être capable de détecter si le contenu collé est du markdown et créer les blocs en fonction
        (remark/rehype sont déjà dans package.json et jamais importés)
  - [ ] New Block :
    - [ ] Audio block ?!
    - [ ] Notices block (with different notices level)
    - [ ] Group = div wrapper with anchor and class (and strettched ? flex ? grid ? start-show-more ?), nearest imlpementation:
      - https://github.com/serlo/backlog/issues/83
      - https://github.com/calumk/editorjs-columns/pull/6
  - [ ] Migrate to tiptap (lol)

- [JsHelper] horizontal scroll + fondus en CSS ➜ `Plans/horizontal-scroll.md`
  `HorizontalScroll.js` et `ScrollEnhancer.js` sont dans le repo, jamais importés.
  Les fondus JS de `ScrollEnhancer` sont remplaçables par `animation-timeline: view(inline)`
  (https://x.com/jh3yy/status/1798728699459563905, altimood).

- [AdminBlockEditor] PagesList/CardList/Gallery ➜ Voir pour utiliser grid-col-12 and col-span-3/4/2 to be able to fully customize it - via Class ?
  (la gallery a une grille auto-calculée ; `pages_list` et `cardList` n'en ont aucune)

- [Version] **Change requester**, **Public Historic** (or make accessible historic from page object)
  Le diff Monaco est fait (`version/src/templates/compare.html.twig`).

- [Static] Make ErrorPageGenerator consistent with htaccess (on htaccess, filter by beginning url to return the correct one ?!)

- Intégrer **LinksImprover** (+ UX) ➜ `Plans/links-improver-integration.md`
  Dépendance déjà installée et référencée nulle part : à intégrer ou à retirer.

- **Complex Right System** : Multi-user editor Multi-site but not everybody can edit everything (extension or core ?) ➜ `Plans/scoped-permissions.md`

- **eCommerce** bridge with sylius or odoo ?!
