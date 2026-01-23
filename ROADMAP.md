# Refactoring : Rendu sans simulation de requête HTTP

## Analyse du problème

### Situation actuelle

Le `PageScanner` et `StaticGenerator` simulent des requêtes HTTP :

```php
$request = Request::create($liveUri);
$response = self::getKernel()->handle($request);
```

**Temps observé** : 26.84s pour le test PageScanner.

### Pourquoi c'est lent ?

`kernel->handle()` fait beaucoup de travail même si le kernel est réutilisé :

- Dispatch `kernel.request`, `kernel.controller`, `kernel.response`
- Routing complet
- Security checks
- Redirections SEO
- Locale resolution

---

## Complexités identifiées

### 1. Services avec état au constructeur (CRITIQUE)

**`PushwordRouteGenerator`** :

```php
public function __construct(..., RequestStack $requestStack) {
    $this->currentHost = $requestStack->getCurrentRequest()?->getHost() ?? '';
}
```

Ce singleton garde le host de la première requête. Sans simulation HTTP, il sera incorrect.

### 2. Filtre `HtmlLinkMultisite` (CRITIQUE)

Dépend de `$this->router->mayUseCustomPath()` qui utilise `$currentHost`.

### 3. Twig inline dans le contenu (CRITIQUE)

Le filtre `Twig` rend du code inline :

```php
$templateWrapper = $this->twig->createTemplate($string);
return $templateWrapper->render(['page' => $this->page]);
```

Ce code peut utiliser `app.request` ou `pages_list()` qui dépend de `RequestStack`.

### 4. Pager et route name (MOYEN)

`PageExtension` lit `requestStack->getCurrentRequest()->attributes`.

---

## Approches proposées

### Option A : RequestStack push (RECOMMANDÉE)

Pousser une requête dans `RequestStack` sans passer par `kernel->handle()` :

```php
public function renderPage(Page $page, int $pager = 1): string
{
    $request = Request::create($this->generateLivePathFor($page));
    $request->attributes->set('pager', $pager);
    $request->attributes->set('_route', $this->getRouteName($page));

    $this->requestStack->push($request);
    try {
        $this->apps->switchCurrentApp($page);
        $this->apps->setCurrentPage($page);
        $this->translator->setLocale($page->getLocale());

        $params = ['page' => $page, ...$this->apps->get()->getParamsForRendering()];
        $view = $this->apps->get()->getView($page->getTemplate() ?? '/page/page.html.twig');
        return $this->twig->render($view, $params);
    } finally {
        $this->requestStack->pop();
    }
}
```

**Prérequis** : Modifier `PushwordRouteGenerator` pour lire le host dynamiquement :

```php
private function getCurrentHost(): string
{
    // Priorité 1: Page courante
    if (null !== ($page = $this->apps->getCurrentPage())) {
        return $page->getHost();
    }
    // Priorité 2: Requête courante
    return $this->requestStack->getCurrentRequest()?->getHost() ?? '';
}
```

**Avantages** :

- `app.request` fonctionne dans les templates
- Compatibilité 100%
- Gain ~50%

---

### Option B : RenderingContext

Créer un contexte explicite stocké dans `AppPool` :

```php
final class RenderingContext
{
    public function __construct(
        public readonly string $host,
        public readonly int $pager = 1,
        public readonly string $routeName = 'pushword_page',
    ) {}
}
```

Chaque service vérifie ce contexte en priorité sur `RequestStack`.

**Inconvénient** : `app.request` ne fonctionnera pas dans le Twig inline.

---

### Option C : Optimiser kernel->handle()

Garder la simulation mais créer un environnement `static` avec moins de listeners.

**Avantage** : Pas de risque de régression.

---

## Comparaison

| Critère                     | Option A  | Option B  | Option C |
| --------------------------- | --------- | --------- | -------- |
| Gain performance            | ~50%      | ~70%      | ~20%     |
| Compatibilité `app.request` | ✅ 100%   | ⚠️ 80%    | ✅ 100%  |
| Effort                      | 2-3 jours | 5-7 jours | 0.5 jour |
| Risque régression           | Moyen     | Élevé     | Faible   |

---

## Checklist Option A

1. [ ] Modifier `PushwordRouteGenerator` : lire host dynamiquement
2. [ ] Modifier `PageExtension::getCurrentPage()` : fallback sur `AppPool`
3. [ ] Modifier `PageExtension::getPagerRouteName()` : déduire depuis page
4. [ ] Modifier `page_default.html.twig` : `pager|default(0)`
5. [ ] Créer `PageRenderer` avec push/pop RequestStack
6. [ ] Refactorer `PageGenerator` et `PageScannerService`
7. [ ] Tests de comparaison HTML
