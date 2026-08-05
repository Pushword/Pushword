---
title: 'Where Pushword is going ? Roadmap, TODO and Ideas'
h1: Roadmap
publishedAt: '2025-12-23 05:48'
toc: true
---

Long road till today ! Half way till tomorrow. First commit _Nov 10, 2018_.

### On our way

- [ ] npx pushword ai plugin inspired from https://github.com/gsd-build/get-shit-done https://github.com/pbakaus/impeccable and current prompt/cmd/skills on my projects (wip -> ai-skills package)
- [ ] upgrade skills -> composer req pushword/docs, composer update (retrieve pushword core version upgrade), read vendor/pushword/docs.../upgrade.md, fix local codebase, test (manual composer dev + check few pages) and composer test if available claude --resume b88a1a80-843c-4e8f-994c-23ef98e63f59

## Set aside (for now) and known issues

- md/editorjs/media -> audio (no need for know)
-
- **Complex Right System** : Multi-user editor Multi-site but not everybody can edit everything (extension or core ?) ➜ `Plans/scoped-permissions.md`

- [Core] **pagination** : documentée dans `/pages-list`, reste le format d'URL
  - Bug quand une page a le même URI qu'une page de la pagination OU sur l'ID (attrapé avant la pagination)
    => En fait, c'est paginer la page d'accueil qui fait le max de bordel - changer pour un format d'uril + robuste (ex : /1 ➜ /p1 et interdire les slugs de type /p[0-9]+)
    `RoutePatterns::PAGER` vaut toujours `\d+`
    mais c'est un pattern

- [Core] / [Admin] Bulk edition des tags depuis la page de listing -> must rely on checkbox, on checked instead of only having "Delete" action, having "Bulk edit"

- [Admin] / [Core] easily customize navbar with favorites `page` ➜ utiliser plutôt les tags et ajouter un loader spécifique : #navbar100 #navbar200 #navbar300, charger toutes les pages qui ont un tag commençant par #navbar, organisé par ordre alphabétique et créer le menu d'après ces pages)

- [Version] **Change requester**, **Public Historic** (or make accessible historic from page object)
  Le diff Monaco est fait (`version/src/templates/compare.html.twig`).
