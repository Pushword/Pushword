---
title: 'Where Pushword is going ? Roadmap, TODO and Ideas'
h1: Roadmap
publishedAt: '2025-12-23 05:48'
toc: true
---

Long road till today ! Half way till tomorrow. First commit _Nov 10, 2018_.

### On our way

- [ ] port to rust integrally (poc) https://www.arewewebyet.org/topics/database/
- [ ] https://github.com/Smaug6739/Alexandrie https://github.com/emdash-cms/emdash
- [ ] https://dunglas.dev/2024/12/http-compression-in-php-new-symfony-assetmapper-feature/
- [ ] https://mist.inanimate.tech https://proofeditor.ai/?utm_source=everywebsite

- [ ] npx pushword ai plugin inspired from https://github.com/gsd-build/get-shit-done https://github.com/pbakaus/impeccable and current prompt/cmd/skills on my projects (wip -> ai-skills package)
- [ ] upgrade skills -> composer req pushword/docs, composer update (retrieve pushword core version upgrade), read vendor/pushword/docs.../upgrade.md, fix local codebase, test (manual composer dev + check few pages) and composer test if available claude --resume b88a1a80-843c-4e8f-994c-23ef98e63f59

## Set aside (for now) and known issues

- md/editorjs/media -> audio (no need for know)

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

<!--

## Content

- [ ] **Grav vs Sculpin vs Stenope vs Pushword: Three PHP Paths to a Static Website (Symfony)** - Réaliser le même petit site dans les deux outils : édition, Git, médias, multisite et publication + bench
- [ ] **Bolt vs Pushword: Choosing a Symfony CMS for a Content Site**
- [ ] **Migrating a WordPress Site to Pushword: What Actually Has to Move?**
- [ ] **Which PHP CMS Are Still Maintained? A Dated, Verifiable Inventory**
- [ ] **Kirby vs Pushword: Flat-File Publishing for a Small Editorial Team**
- [ ] **Which CMS Should You Use for a Documentation Site With Nontechnical Editors?**
- [ ] **When Does a Flat-File CMS Stop Being the Simple Choice?** - Présenter les limites : volume de contenu, recherche, écritures simultanées et synchronisation, avec des mesures reproductibles
- [ ] **Page Revisions vs Git History: What Can Each One Restore?** - Simuler une erreur éditoriale et montrer précisément les deux procédures de récupération.


## Content and migration

Voici le tableau complété. **« Sur GitHub » désigne les sources ; seule la mention GitHub Pages indique que le site y est aussi publié.** La difficulté est une estimation pour une démonstration de migration vers Pushword.

| Cible | Sources et publication actuelle | Ce que le fork permettrait de démontrer | Difficulté |
|---|---|---|---|
| **[Fabien Potencier](https://github.com/fabpot/fabien.potencier.org)** | Articles Markdown, Hugo, GitHub Pages | Hugo → Pushword dans l’écosystème PHP, avec conservation des URLs et des médias | Moyenne |
| **[Simon Willison](https://github.com/simonw/simonwillisonblog)** | Application Django ; [contenu sauvegardé séparément en NDJSON](https://github.com/simonw/simonwillisonblog-backup). Site publié sur Heroku | Import d’un blog riche depuis une base de données : articles, tags, liens et redirections | Très élevée |
| **[Dylan Beattie](https://github.com/dylanbeattie/dylanbeattie.net)** | Jekyll, GitHub Pages | Migration complète d’un blog statique avec articles, images et flux RSS | Moyenne |
| **[Andrej Karpathy](https://github.com/karpathy/karpathy.github.io)** | Jekyll, GitHub Pages | Démonstration courte sur un blog de développeur très connu | Faible à moyenne |
| **[Dan Abramov](https://github.com/gaearon/overreacted.io)** | Next.js avec export statique ; sources sur GitHub | Migration de Markdown et de composants propres aux articles | Élevée |
| **[Peter Steinberger](https://github.com/steipete/steipete.me)** | Astro et Markdown sur GitHub ; publication sur Vercel | Astro → Pushword, avec contrôle du rendu statique | Moyenne |
| **[API Platform Docs](https://github.com/api-platform/docs)** | Documentation Markdown ; publication sur api-platform.com | Cas documentaire dans l’écosystème Symfony, plus circonscrit que Symfony Docs | Moyenne à élevée |
| **[Symfony Docs](https://github.com/symfony/symfony-docs)** | reStructuredText ; publication sur symfony.com | Migration d’un chapitre de documentation : références croisées, code, URLs et versions | Très élevée |


-->
