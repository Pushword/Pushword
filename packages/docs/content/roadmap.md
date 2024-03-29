---
title: 'Where Pushword is going ? Roadmap, TODO and Ideas'
h1: Roadmap
toc: true
parent: contribute
---

## BugFix && To finish

- [Core/Admin] catch Exception and show them in flash messages, eg : PagesListTwigTrait / PageController

- [Admin] Block (left column) for viewing Page subtitle and navigate in the content
- [Admin] Page : Code Editor for Autres paramêtres (yaml highlighting)
- [Admin] Implements htmx ?!
- [Admin] Better Parent UX : Title + Slug
- [Admin] switch code editor to https://microsoft.github.io/monaco-editor/
- [Admin] ctrl+s -> save (without reloading)

- [AdminBlockEditor] horizontal toggle button (for list or pages_list)
- [AdminBlockEditor] https://github.com/calumk/editorjs-nested-checklist
- [AdminBlockEditor] https://github.com/calumk/editorjs-codecup
- [AdminBlockEditor] Block Nav Menu (guttenberg style)
- [AdminBlockEditor] Span with class, nearest implementation https://github.com/hata6502/editorjs-style
- [AdminBlockEditor] Group
  - = div wrapper with anchor and class (and strettched ? flex ? grid ?), nearest imlpementation:
    - https://github.com/serlo/backlog/issues/83
- [AdminBlockEditor] Add media.id if media.url not found
- [AdminBlockEditor] Media Stretch bug
- [AdminBlockEditor] Add Layout https://github.com/hata6502/editorjs-layout#readme
- [AdminBlockEditor] Link suggester
- [AdminBlockEditor] Image > Add link + Alt and redesign Legend
- [AdminBlockEditor] document Pages Block on UI
- [AdminBlockEditor] édition avancée (template notamment dans pages, prose/unprise)
  - rewrite the fullscreen, wide, and max width from prose - [see](https://github.com/tailwindlabs/tailwindcss-typography/pull/204)
- [AdminBlockEditor] sanitize with https://github.com/editor-js/editorjs-php (see AdminFormEventSuscriber.php)

- [DX] Implement ESlint

- // TODO check a new blank installation

- [Core] **pagination** : tester & documenter

  - Bug quand une page a le même URI qu'une page de la pagination OU sur l'ID (attrapé avant la pagination)
    => En fait, c'est paginer la page d'accueil qui fait le max de bordel

- [StaticGenerator] Make ErrorPageGenerator consistent with htaccess (on htaccess, filter by beginning url to return the correct one ?!)

- [All] prepare translating and transalte

## Feature

- [Version] Advanced Diff Checker raw /editorjs

  - **Change requester**
  - **Public Historic** (or make accessible historic from page object)

- [Admin] **Multi-upload** (see https://packagist.org/packages/silasjoisten/sonata-multiupload-bundle) + Multi Select

- Intégrer **LinksImprover** (+ UX)

- [PageScanner] texte alternatif manquant
- [PageScanner] Check there is no translation with the same language than current page
- [PageScanner] add <!-- page-scanner-ignore: what to ignore --> ou plutôt dans othersParameters
  -> donner un code unique aux erreurs

- **eCommerce** bridge with sylius ?!

- [Core] MediaCleaner command : find unused media and removed them (page scanner ?!

- [Flat] Transform markdown link to page link (useful for navigate in docs from editor)
- [Flat] Throw error when the content is more up to date in database

- manage date i18n a better way than randomly (document the process)

- **Complex Right System** : Multi-user editor Multi-site but not everybody can edit everything (see draft.md) (extension or core ?)
-
- [Core] Rewrite filter componenent to use the power of symfony service

* [Admin] : Automatic save without flooding version

* [Flat] (spatie/yaml-front-matter, vérif à chaque requête pour une sync constante admin <-> flat files)

- [New] Batch Content Edition

-- [All] Drop Weird Config Prepender

Imply to rewrite for example :

```
declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
        'form_themes' => [
            '@PushwordAdminBlockEditor/editorjs_widget.html.twig',
        ],
    ]);
};
```

To

```
return [
    'twig' => [
        'form_themes' => [
            '@PushwordAdminBlockEditor/editorjs_widget.html.twig',
        ],
    ],
];
```

Why :

- Less custom code
- Stan ?
