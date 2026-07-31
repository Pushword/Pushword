# Plan — Moteur de filtres unifié

> **Réalisé.** Les étapes 0 à 7 sont livrées. Ce qui suit reste le raisonnement ;
> les écarts assumés à l'exécution sont consignés ci-dessous.
>
> | Écart | Pourquoi |
> |---|---|
> | Étape 0 : la mutualisation de `parameterized()`/`property()` entre les deux résolveurs n'a pas été faite | L'étape 5 supprime les deux corps. En un seul release, c'était ~30 lignes écrites pour être effacées avant publication — le même argument qui avait écarté l'étape 0 ter. L'échappement LIKE, lui, est allé directement à sa place définitive (`Core\Query\LikePattern`). |
> | Étape 3 : seule la remontée de `JSON_SCALAR` y a été faite ; le passage de `customProperty:` dessus est à l'étape 4 | Sans compilateur, la seule façon d'y arriver était de détourner `key_prefix` en `JSON_SCALAR(p.` — du code faux supprimé une étape plus loin. |
> | Les stratégies contextuelles restent résolues **au parsing** (`PageSearchVocabulary`), pas à la compilation | `children` était déjà résolu là, avec la page courante en main, et y résoudre `related` en composite est plus simple qu'un contexte de compilation traversant le compilateur. Le registre les **déclare** (`PageFieldRegistry::CONTEXTUAL_FIELDS`), ce qui donne aux deux surfaces le refus nommé qui était le vrai objectif. |
> | `related` conservé verbatim ; aucune variante `publishedAt` introduite | La décision restait ouverte et rien n'en dépendait. Les audits comptent 0 usage sur 792 appels. |
> | `contactWhen` n'accepte **pas** la surface string ; `pageWhen` oui | Les opérateurs contact (`olderThan 7d`, `isSet`) n'ont pas d'orthographe dans une grammaire `champ:valeur` : en inventer une aurait été une troisième grammaire, pas une mutualisation. `pageWhen` réutilise la langue que l'éditeur écrit déjà. L'imbrication, elle, est bien arrivée des **deux** côtés — c'était le plafond à lever. |
> | `parentPage` (newsletter) renommé `parent` | Le core l'appelle `parent`. Deux noms pour une chose était l'incohérence qu'on supprimait. Package neuf, aucun aval. |
> | `LegacyArrayRenderer` conservé | `StringToDQLCriteria`, déprécié, s'en sert encore ; c'est sa seule raison d'exister. |
>
> **Non résolu, hors de ce chantier** : la règle rector
> `RemoveDefaultValueFromAssignedPropertyRector` retire le `= null` de
> `PageScannerController::$fileCache`, dont le setter lit la propriété avant de
> l'écrire — `composer rector` casse donc la suite d'intégration à chaque passage.
> Même règle sur `RequestContext::$currentSite` et `SiteRegistry::$defaultHost`,
> où elle ne laisse que du bruit PHPStan.

Deux moteurs filtrent des `Page` dans ce dépôt : la DSL string de `pages_list`
(core) et la grammaire JSON du newsletter. Un troisième filtre des `Contact`,
né par copier-coller du second à l'intérieur du newsletter — et c'est parce
qu'il en est une copie qu'il fait partie du chantier, pas parce qu'il filtre une
`Page`.

L'objectif n'est pas de supprimer une **surface** — la string sert un rédacteur,
le JSON un opérateur, et les deux publics existent — mais de n'avoir qu'un IR,
qu'un parcours d'arbre et qu'une façon de déclarer un champ dessous, quelle que
soit l'entité filtrée.

## Constat vérifié

Chaque point ci-dessous a été vérifié en exécutant le code, pas en le lisant.

1. **`FilterWhereParser` compile déjà des arbres à profondeur arbitraire**, un
   opérateur par niveau, avec le bon parenthésage :
   `[[[a,'OR',b],[c]],'OR',[d]]` → `((a OR b) AND c) OR d`. Le moteur n'est pas
   la limite.
2. **`StringToDQLCriteria` n'a pas de lexer** : `explode(' OR ')` puis
   `explode(' AND ')` sur la chaîne brute. Conséquences — pas de parenthèses
   (`(children)` cherche un tag nommé `(children)`), opérateurs mixtes
   silencieusement cassés, et tout préfixe inconnu tombe dans la recherche de
   tag.
3. **La DSL émet déjà un arbre à 2 niveaux** : `title:foo AND children` →
   `(h1 LIKE OR title LIKE) AND parentPage = X`. L'IR plat du newsletter
   (`{any}|{all}`) ne peut pas le représenter.
4. **`PageCriteria` et `SegmentCriteria` sont ~130 lignes identiques** —
   `normalize`, `normalizeConditions`, `validate`, `toJson`, `fromJson`,
   `isProperty`, `propertyPath`, `readString`, `assertOperator`.
5. **La copie a divergé** : `PageMatcher::tag()` échappe les jokers LIKE avec
   `ESCAPE '!'`, `SegmentResolver::tag()` non — un tag contact contenant `_`
   sur-matche.
6. **`JSON_SCALAR` (newsletter) s'applique à `Page.customProperties` (core)** —
   la fonction DQL est du mauvais côté de la frontière de package.
7. **`customProperty:` du core fait un match de sous-chaîne JSON brute**
   (`%"key":"value"%`) — cassé sur les valeurs non-string et sur toute variation
   d'espacement.

### Qui est plus puissant, et où

| | `pages_list` (A) | newsletter (B) |
|---|---|---|
| Structure de l'IR | **arbre, profondeur libre** | plat, un opérateur |
| Structure de la grammaire | un opérateur, pas de parenthèses | = l'IR |
| Opérateurs | **libres** en forme tableau | whitelist par champ |
| Champs | slug, h1, title, mainContent, tags, customProperties, parentPage(id), id | slug, template, parentPage(**par slug**), **ancestor récursif**, tag, prop.* |
| JSON | substring brut | **`JSON_SCALAR`** |
| Échappement LIKE | aucun | **portable (`ESCAPE`)** |
| Sémantique NULL | aucune | **raisonnée par champ** |
| Contexte page courante | **`children`/`sisters`/`related`/`grandchildren`** | aucun |
| Erreurs | silencieuses | **indexées, explicites** |

A est le meilleur **moteur** avec le pire **langage**. B est le meilleur langage
sur un moteur plus pauvre mais plus correct.

## Cible

```
  surface string  ─┐                        ┌─ registre de champs
  (rédacteur)      │                        │  (champ → opérateurs → stratégie)
                   ├─→  IR  ─→  compilateur ─┤
  surface JSON    ─┤   Group/Condition      └─ Doctrine QueryBuilder
  (opérateur/API)  │
  forme tableau   ─┘   (non validée, échappatoire assumée)
```

**IR** — la forme de A avec le typage de B :

```php
Group     { op: AND|OR, children: (Group|Condition)[] }
Condition { field: string, op: string, value: mixed }
```

**L'IR et le parcours d'arbre ne connaissent pas l'entité.** Ce qui la connaît,
c'est le registre — et il y en a un par entité filtrée :

```
Core\Query\Group, Condition          objets valeur, sans entité
Core\Query\QueryCompiler             parcours d'arbre → Andx/Orx, feuilles déléguées
Core\Query\FieldRegistryInterface    champ → opérateurs admis → stratégie
Core\Query\Strategy\*                feuilles réutilisables : colonne simple,
                                     LIKE échappé, tag JSON, prop JSON_SCALAR

Core\Query\PageFieldRegistry              + parent, ancestor, contextuels
Newsletter\Query\ContactFieldRegistry     + seuils de durée (olderThan/newerThan)
```

Une `Page` et un `Contact` portent tous deux des tags en colonne JSON et des
`customProperties` : ces deux stratégies-là sont partagées, paramétrées par alias
et colonne. Le reste est propre à chaque entité.

## Décisions

| Sujet | Décision | Pourquoi |
|---|---|---|
| IR | Arbre typé `Group`/`Condition`, **introduit dès l'étape 2** | Ni l'un ni l'autre des IR existants ne convient : A est un arbre non typé, B est typé mais plat. La cible est l'union. L'introduire avec le parseur évite de l'écrire deux fois. |
| Compilateur | **Un seul parcours d'arbre dans `core`** (`QueryCompiler`), agnostique de l'entité, paramétré par un registre | Le newsletter n'a pas à porter un compilateur d'entité `Page` — il porte déjà `JSON_SCALAR` sur une colonne du core. Et un arbre se parcourt de la même façon quelle que soit la feuille. |
| `FilterWhereParser` | **Conservé**, comme adaptateur de la forme tableau | C'est l'API publique brute (static-generator, sites aval). Elle reste non validée par choix, pas par oubli. |
| Registre | Un par entité, même interface : `PageFieldRegistry` (core), `ContactFieldRegistry` (newsletter) — champ → opérateurs admis → stratégie de compilation | Un seul endroit à lire pour savoir ce qui est filtrable, et un seul à modifier pour ajouter un champ à **toutes** les surfaces qui filtrent cette entité. |
| Côté contact | `SegmentCriteria` / `SegmentResolver` migrent **avec** `pageWhen`, pas après | Sinon l'imbrication existe pour les pages et pas pour les contacts, dans le même formulaire, deux textareas voisines : l'incohérence qu'on supprime au niveau du dépôt reviendrait au niveau du trigger. Et l'argument qui fonde le plat aujourd'hui (`CriteriaGroup` : « *one operator per expression is learnable, a tree is not, which is the rule a `pages_list` search follows* ») devient faux à l'étape 2 — sa prémisse disparaît. |
| Forme par défaut | La liste plate reste ce que l'admin affiche et ce que la doc enseigne ; l'imbrication est un plafond, pas le point d'entrée | Ce qui était juste dans la décision d'origine — un opérateur se comprend d'un coup d'œil — ne cesse pas de l'être parce que l'arbre devient possible. |
| Noms de champ partagés | Résolution **positionnelle** (le champ dépend de la surface éditée) conservée ; le message d'erreur référence l'autre surface. `publishedAt` admis dans `pages_list`, refusé dans `pageWhen` | `tag` et `prop.*` sont déjà partagés et résolus ainsi sans ambiguïté vécue. Le plan porterait la collision à 5 noms (`createdAt`, `updatedAt`, `publishedAt`, `locale`) : un préfixe d'entité coûterait à toutes les règles pour désambiguïser un cas que la position tranche déjà. `publishedAt` fait exception : dans un `ContentTrigger` il est déjà borné par `triggerFrom`/`now`, une condition dessus contredirait les gardes. |
| Champs contextuels | Stratégies de premier rang (`ChildrenStrategy`, `RelatedStrategy`…), consommant un contexte de compilation ; refusés par la surface JSON avec un message | C'est ce qui distingue le moteur A. Un `ContentTrigger` n'a pas de page courante : c'est une règle explicite, pas un trou. |
| Filtre de locale | `slug:`/`page:` **cesse de le désactiver** ; `locale:` devient un champ filtrable au registre (étape 4) | L'exemption est un no-op là où elle serait inoffensive (un hôte = une locale : altimood, piedvert) et active là où elle nuit (hôte multi-locale — le dev-app sert `en`/`fr`/`fr-CA` sur `localhost.dev`). Et l'intention ne survit pas au `OR` : `slug:X OR tmb` désactive la locale pour le tag aussi. Le cross-locale volontaire s'écrit alors explicitement. |
| Sémantique de `related` | `id < currentId + 3` **conservé verbatim** ; la fenêtre `publishedAt` arrive sous un nouveau nom | Redéfinir `related` est la seule rupture majeure du plan. Elle reste possible, mais se décide et s'assume à l'étape 4. |
| Fallback tag | **Conservé définitivement**. `tag:` ajouté comme forme explicite. Préfixe inconnu → **jamais une erreur**, à aucun majeur | Le tag namespacé est une convention de production : GA porte `type:product` (1167 pages), `type:listing` (693), `type:blog` (376), et les interroge par le fallback. Le moteur ne peut pas distinguer `type:blog` (intentionnel) de `tags:blog` (faute de frappe d'altimood) — la distinction est **indécidable au parsing**. |
| Recherches mortes | Une **commande de lint**, pas une erreur de parseur | Exécuter chaque recherche du site et signaler celles qui ne matchent aucune page est décidable — c'est un résultat empirique, pas une question de grammaire. Et ça attrape strictement plus : la faute de frappe, mais aussi un `slug:` pointant une page supprimée. Se branche sur `AgentOutputTrait`. |
| Surface string | Lexer + parseur à précédence (`AND` > `OR`), parenthèses | Surensemble strict de l'existant. Le compilateur ne bouge pas — il sait déjà le faire. |
| Surface JSON | Gagne l'imbrication (un enfant peut être un groupe), **des deux côtés** — `pageWhen` comme `segment` | Le newsletter vient d'être ajouté : aucun contrat à préserver. |
| `pageWhen` et `contactWhen` | Acceptent tous deux les deux surfaces | Une seule chose à apprendre pour les deux côtés d'un trigger. Le parseur est agnostique de l'entité : n'offrir la string qu'à un côté serait un choix, pas une contrainte — et un mauvais choix. |
| `JSON_SCALAR` | Remonte dans `core`, à côté de `JSON_EXTRACT` | Corrige `customProperty:` par construction. |

## Étapes

### 0 — Dédoublonner le newsletter · ½ j · risque nul

Aucune surface publique touchée, aucun changement visible.

- Extraire `Pushword\Newsletter\Criteria\AbstractCriteria` : les ~130 lignes
  communes. `SegmentCriteria` / `PageCriteria` ne gardent que
  `FIELD_OPERATORS` et un hook de validation de valeur (la durée, côté segment).
- Mutualiser `parameterized()` et `property()` entre `SegmentResolver` et
  `PageMatcher`.
- **Corriger `SegmentResolver::tag()`** : échappement + `ESCAPE '!'`, comme
  `PageMatcher::tag()`.

Ce travail n'est pas jeté à l'étape 5 : `AbstractCriteria` y garde ses deux
enfants et sa mécanique de round-trip JSON. Seul `FIELD_OPERATORS` cède la place
au registre.

Tests : `SegmentCriteriaTest` et `PageCriteriaTest` passent inchangés ; ajouter
un cas de tag contenant `_` et `%` dans `SegmentResolverTest`.

### 0 bis — Retirer l'exemption de locale sur `slug:` · 3 lignes · indépendant

`PageExtension.php:272` renifle `str_contains($search, 'slug:')` et, si vrai,
saute `andLocale()` pour **toute** l'expression. À supprimer :

- l'exemption ne fait rien sur un hôte mono-locale (altimood, piedvert — le
  scoping par hôte suffit) et se déclenche sur un hôte multi-locale, le seul
  contexte où le mélange de langues se voit (`localhost.dev` sert `en`, `fr`,
  `fr-CA`) ;
- elle ne survit pas au `OR` : `slug:X OR tmb` désactive la locale pour le tag ;
- c'est un reniflage de sous-chaîne : `monslug:foo` la déclenche sans qu'aucune
  condition ne porte sur le slug.

Contrepartie assumée : sur un hôte multi-locale, `slug:` désignant une page d'une
autre langue ne renvoie plus rien. Le cross-locale volontaire redevient possible
à l'étape 4 via le champ `locale:`.

Vérifié : aucune page n'a de locale vide — `setLocaleIfNotDefined()` tourne sur
`prePersist` et `preUpdate`, 0 ligne vide en base. Les trois tests qui s'appuient
sur le contournement posent `locale = 'en'` sur fixtures et contexte ; le
commentaire `PageExtensionPagesListExcludeLinkedTest.php:35` devient faux et
saute.

Livrable avant l'étape 1. `upgrade.md` à annoter.

### 1 — L'oracle de corpus · ½ j · risque nul

Il précède tout le reste : il protège le premier changement dont l'échec serait
silencieux (l'étape 2).

`PageSearchCorpusTest` — un corpus de recherches (le tableau de
`pages-list.md`, les 38 appels du dev-app, les combinaisons `OR`/`AND`, les
formes tableau des appelants) → assertion sur le **SQL généré**, snapshot
capturé sur le comportement d'aujourd'hui.

`PageRepositoryTest:395` fait déjà ça sur la DQL pour une requête ; le corpus
généralise le procédé.

### 2 — Lexer + parseur à précédence · 1–2 j · risque faible

Se branche sur `FilterWhereParser` **sans le modifier** : il compile déjà les
arbres. Aucun changement au repository ni au newsletter.

- Lexer + parseur à précédence dans un `PageSearchParser` ;
  `StringToDQLCriteria` devient un alias déprécié.
- **L'IR arrive ici, pas à l'étape 4.** `Pushword\Core\Query\Group` et
  `Condition` — objets valeur nus, sans registre ni validation — plus un
  `LegacyArrayRenderer` qui les rend au format tableau attendu par
  `FilterWhereParser`. À l'étape 4 on remplace le renderer par le compilateur
  typé ; le parseur ne bouge pas. Écrire un AST anonyme ici reviendrait à
  l'écrire deux fois.
  `Condition` porte `key_prefix` (forme tableau) et un `value` `mixed`
  (`IN` prend une liste, `IS` prend `null`).
  **Aucune référence à `Page` dans `Group`/`Condition`** : l'étape 5 les réutilise
  tels quels pour les `Contact`. Un `field` est une chaîne, le registre en donne
  le sens.
- Parenthèses, `AND`/`OR` mixtes, précédence `AND` > `OR`.
- **Nouveaux préfixes atteignables sans compilateur** : `template:` (colonne
  simple), `parent:` (`key_prefix` `parent.` — le join existe déjà dans
  `buildPageQuery()`), `tag:` (forme explicite du mot nu). Vérifié : la cible de
  `parent:foo OR (parent:bar AND template:default)` est déjà productible
  aujourd'hui en forme tableau.
  N'exposer que `=` sur `parent:` — le `!=` demande la sémantique NULL
  (`parent.slug IS NULL OR parent.slug != :x`) que l'étape 4 formalise.
- Erreurs structurelles dures : parenthèse non fermée, opérateur en fin
  d'expression, groupe vide. (La validation de *vocabulaire* attend le registre,
  étape 4 — le fallback tag rend « préfixe inconnu » indistinguable d'un tag.)

**Invariants du renderer**, tous vérifiés contre `FilterWhereParser` :

| Invariant | Sinon |
|---|---|
| Niveaux homogènes — jamais `AND` et `OR` au même niveau | `in_array('OR', …)` fait basculer **tout** le niveau en `OR`, silencieusement |
| Jamais de groupe vide | `Exception` au message vide (`containsSubQuery`) |
| Un groupe ne commence jamais par un marqueur | Un groupe est détecté par « premier élément = tableau » |

**Pas de risque de régression sémantique** : aujourd'hui `retrieve()` splitte sur
`' OR '` puis `' AND '` et le fragment non splitté tombe dans le fallback tag —
donc toute expression qui fonctionne aujourd'hui est homogène, et les mixtes sont
déjà cassées. La précédence SQL standard ne peut changer le sens d'aucune requête
qui marche.

**Pré-vol — hors dépôt.** Un listener de `PagesListSearchEvent` reçoit la chaîne
brute avant parsing ; une regex en `\S+` y avale une parenthèse fermante dès que
les parenthèses existent. Cas réel identifié : `ProductSearchSubscriber`
(`travel-booking-bundle`, GA), `preg_replace('/product:(\S+)/', …)` → `[^\s)]+`.
Latent aujourd'hui, armé le jour où l'étape 2 sort. À corriger avant.

Doc : `packages/docs/content/pages-list.md` — les deux lignes ✗ deviennent ✔.
`CriteriaGroup` : le docblock justifie l'absence d'imbrication par « *which is
the rule a `pages_list` search follows* » — cette étape rend la phrase fausse.

### 3 — `JSON_SCALAR` dans le core · ½ j · risque faible

- Déplacer `Newsletter\Repository\DQL\JsonScalarFunction` →
  `Core\Repository\DQL\JsonScalarFunction`, enregistré dans
  `PushwordCoreExtension` à côté de `JSON_EXTRACT` ; retirer la déclaration de
  `newsletter/src/config/packages/doctrine.php`.
- `StringToDQLCriteria` : `customProperty:k:v` produit une condition compilée par
  `JSON_SCALAR` au lieu du substring JSON.

**BC** : `customProperty:count:3` matchera désormais la valeur numérique `3`, ce
qu'il ne fait pas aujourd'hui. C'est le correctif, mais c'est un changement de
comportement → `packages/docs/content/upgrade.md`.

Tests : `StringToDQLCriteriaTest` (les assertions de forme changent), + cas
valeur numérique et booléenne.

### 4 — L'IR et le compilateur · 3–5 j · le cœur

`Group`/`Condition` existent depuis l'étape 2 : cette étape leur donne un sens
(registre) et une cible (compilateur), elle ne les invente pas.

- `Pushword\Core\Query\` : `QueryCompiler` (parcours d'arbre, agnostique de
  l'entité), `FieldRegistryInterface`, `Strategy\*` (les feuilles réutilisables :
  colonne simple, LIKE échappé, tag JSON, prop `JSON_SCALAR`), et
  `PageFieldRegistry` qui les câble pour `Page`. L'étape 5 n'aura qu'à en écrire
  un second pour `Contact` — si le compilateur connaissait `Page`, elle devrait
  le dupliquer.

**Les stratégies contextuelles sont de premier rang ici.** Ce sont elles qui
distinguent le moteur A ; elles ne sont pas de la dette à ranger en fin de
parcours.

| Stratégie | Compile vers |
|---|---|
| `ChildrenStrategy` | `parentPage = ctx.currentPage.id` |
| `SistersStrategy` | `parentPage = ctx.currentPage.parentPage.id` |
| `GrandchildrenStrategy` | `parentPage IN (ctx.currentPage.childrenPages.ids)` |
| `RelatedStrategy` | composite : sisters `AND id < currentId + 3` ; la variante `related:comment:` substitue un `mainContent LIKE` à la condition sisters |

Elles consomment un **contexte de compilation** (`?Page $currentPage`), pas une
valeur de filtre. Le registre le déclare, et c'est ce qui fonde le refus de ces
champs par la surface JSON : un `ContentTrigger` n'a pas de page courante.

**Décision `related` — à prendre ici, pas plus tard.** Recommandation : garder
`related` verbatim dans `RelatedStrategy` et introduire la sémantique propre
(fenêtre sur `publishedAt`) sous un **nouveau nom**. Zéro rupture, sémantique
claire disponible, migration au rythme des sites. L'argument : l'ordre des id
n'est l'ordre de publication que si les pages ont été créées dans cet ordre — ce
qu'un import flat ne garantit pas ; l'heuristique est donc déjà semi-arbitraire
sur un site importé, ce qui plaide pour *offrir* une alternative définie, pas
pour redéfinir `related` en silence.
Redéfinir `related` lui-même reste possible : c'est **la rupture majeure du
plan**, elle s'assume ici, s'annonce dans `upgrade.md`, et l'oracle de corpus
dira exactement quelles entrées changent.

- Le registre porte, par champ, les opérateurs admis et la stratégie de
  compilation : colonne simple / LIKE échappé / tag JSON / prop `JSON_SCALAR` /
  join `parent.slug` / ancestor récursif / contextuel.
- Les stratégies absorbent de `PageMatcher` : `escapeLike`, `tag`, `parent`,
  `ancestor` + `sectionIds`, `template`, `property`. `QueryCompiler` absorbe de
  `FilterWhereParser` : le parcours d'arbre, `key_prefix`, `IN`, `IS`/`IS NOT`.
- `FilterWhereParser` devient un adaptateur : tableau brut → `Group`/`Condition`
  non validés → compilateur.
- `PageRepository::applyListCriteria()` appelle le compilateur.
- Le renderer « tableau imbriqué » de l'étape 2 est remplacé par le compilateur
  typé ; le parseur ne bouge pas.
- Le registre expose ses nouveaux préfixes à la surface string : `ancestor:`,
  `template:`, `tag:`, `prop:`. C'est ici qu'arrive la validation de
  vocabulaire.

### 5 — Le newsletter sur le socle · 1 j

**Les deux côtés du trigger migrent ensemble.** Faire passer `pageWhen` sur
l'arbre en laissant `segment` plat déplacerait l'incohérence du dépôt vers le
formulaire, entre deux textareas voisines.

- `PageMatcher` = ses trois gardes (hosts, `triggerFrom`, déjà traité) + un appel
  au compilateur.
- `SegmentResolver` = ses deux gardes (audience, `Subscribed`) + un appel au
  compilateur. Les gardes restent ANDées avec le groupe entier, jamais un
  disjoint : c'est l'invariant dont dépend tout l'envoi.
- `Newsletter\Query\ContactFieldRegistry` : `tag` et `prop.*` par les stratégies
  partagées, `locale` en colonne simple, `createdAt`/`confirmedAt` par seuil de
  durée (`olderThan`/`newerThan`, `SegmentCriteria::threshold()`).
  Ce seuil consomme un **contexte de compilation** (`DateTimeImmutable $now`),
  exactement comme les champs contextuels du core consomment `?Page $currentPage`.
  Même mécanisme, deux entités — la confirmation que le contexte est au bon
  endroit.
- `CriteriaGroup::unwrap()` devient récursif : un enfant peut être un groupe. Les
  deux surfaces JSON gagnent l'imbrication en même temps.
- `PageCriteria` et `SegmentCriteria` deviennent des vues sur leur registre
  respectif. Les champs contextuels (`children`, `sisters`, `related`,
  `grandchildren`) sont refusés côté JSON avec un message explicite : un
  `ContentTrigger` n'a pas de page courante.
- Un champ inconnu d'un registre mais connu de l'autre le dit :
  « *`createdAt` filtre un contact, pas une page* ». C'est la collision qu'on
  résout par la position, pas par un préfixe d'entité.
- `pageWhen` **et** `contactWhen` acceptent une string en plus du JSON. Le
  parseur est agnostique de l'entité — seul le registre contre lequel il résout
  change ; n'offrir la string qu'à un côté recréerait l'asymétrie qu'on vient de
  supprimer.
- Devient redondant : le correctif d'échappement de `SegmentResolver::tag()`
  (étape 0) — la stratégie tag partagée porte l'`ESCAPE`. Le correctif reste
  livré à l'étape 0, avec son test, qui devient l'oracle de cette migration.
- Doc : `packages/docs/content/extension/newsletter.md`.

### 6 — Lint des recherches mortes · ½ j

C'est la contrepartie de la décision « préfixe inconnu → jamais une erreur ». Le
parseur ne peut pas distinguer `type:blog` (tag namespacé intentionnel, GA) de
`tags:blog` (faute de frappe, altimood) ; **l'exécution le peut**.

`pushword:pages-list:lint` — collecte les recherches du contenu et des templates,
exécute chacune, signale celles qui ne matchent aucune page. Résultat empirique,
pas une question de grammaire, et attrape strictement plus qu'une erreur de
parseur : la faute de frappe, mais aussi un `slug:` pointant une page supprimée
et un `ancestor:` sur une rubrique renommée.

Après l'étape 4, il signale en plus les préfixes absents du registre — en
avertissement, jamais en erreur : ils restent des recherches de tag valides.

Motivation vérifiée : altimood porte 16 `pages_list('tags:blog')` qui rendent des
listes vides et 54 `tags:tmb` en disjoints morts, sur 257 pages taguées
`blog tmb`. Aucun outil ne les voit aujourd'hui.

Branché sur `AgentOutputTrait` (`--format=auto|agent|text`), à ajouter à
`packages/docs/content/agent-output.md`.

### 7 — Nettoyage

- Retirer les `@phpstan-ignore nullsafe.neverNull` de `StringToDQLCriteria`
  devenus inutiles.
- Supprimer `LegacyArrayRenderer` une fois tous les appelants sur le
  compilateur.

## Ordre et dépendances

**Un seul release.** L'ordre ci-dessous est un ordre de travail, pas de
livraison : rien ne sort tant que l'étape 5 n'est pas là. Ça a deux
conséquences.

D'abord, aucune étape n'a à être autonome. Les étapes 1 → 2 prises seules
donneraient à `pages_list` les parenthèses et les opérateurs mixtes — mais les
audits comptent **0 `AND` et 0 parenthèse sur 792 appels** dans les trois sites
aval : la DSL y sert de sélecteur de pages (`677` `slug:`), pas de langage de
requête. La demande réelle est côté newsletter — `(tag:A OR tag:B) AND prop.x = y`,
inexprimable aujourd'hui et non contournable par deux campagnes (double envoi) —
et elle arrive à l'étape 5. C'est le bon ordre **parce que** tout part ensemble ;
ce serait le mauvais s'il fallait publier par tranches.

Ensuite, l'étape 0 bis (retrait de l'exemption de locale) n'ouvre aucune fenêtre
de régression : `locale:` arrive au registre à l'étape 4, dans le même release.

L'oracle de l'étape 1 est rejoué à chaque étape suivante. Il est capturé **après**
l'étape 0 bis, qui change le SQL de toute recherche `slug:`. L'étape 3 modifie
délibérément le snapshot (cas `customProperty:`), le delta doit être relu.

## Hors périmètre

- **La forme tableau reste non validée.** C'est l'échappatoire brute, assumée.
- **`pageWhen OR contactWhen` n'est pas une requête.** Les deux critères filtrent
  deux entités, et un trigger est un **produit** : chaque page qui matche devient
  une campagne, envoyée aux contacts qui matchent. Le `AND` y est implicite ; un
  `OR` porterait sur des *paires* (page, contact) — un autre modèle. Aucune
  imbrication ne comble ça : elle opère à l'intérieur de chaque côté.
  L'intention derrière est réelle (« tel contenu à tel public, tel autre à tel
  autre, en un trigger »), et le contournement actuel — deux triggers — double
  les envois : `ContentTriggerLog` est unique sur `(trigger_id, page_id)`, donc
  deux triggers journalisent indépendamment et un contact présent dans les deux
  segments reçoit deux mails pour le même article. C'est un trou du **modèle
  newsletter**, à traiter dans ses *Known limits*, pas ici : l'y replier rendrait
  l'étape 5 non bornée, et ça se décide sur des critères d'envoi, pas de
  grammaire.
- **Loupe garde sa grammaire.** Elle filtre un index, pas des entités Doctrine.
  Unifier les deux n'aurait de sens qu'au niveau de la syntaxe de surface, et ce
  n'est pas le sujet ici.
- **Pas de lib externe.** `doctrine/collections` ne sait rien de l'extraction
  JSON ni des ancêtres récursifs ; RulerZ est à l'arrêt depuis ~2020 et ne
  saurait pas plus les exprimer ; `symfony/expression-language` évalue en PHP.
  Le domaine — arbre de pages, tags JSON, propriétés custom, sémantique NULL
  choisie — est trop spécifique. La valeur est dans le fait qu'il n'y ait qu'un
  moteur, pas dans une dépendance.

## Compatibilité

| Change | Impact |
|---|---|
| Forme tableau (`pages()`, `getPublishedPages()`, `key_prefix`) | **aucun** — adaptateur conservé |
| Toute recherche string existante | **aucun** — surensemble strict, vérifié par l'oracle |
| Fallback tag sur préfixe inconnu | **aucun, jamais** — le tag namespacé est une convention de production (GA) |
| Listener `PagesListSearchEvent` | **aucun** — il reçoit la chaîne brute avant parsing. Attention toutefois aux regex en `\S+` : elles avaleront une parenthèse fermante dès que l'étape 2 les rend disponibles (cas réel : `ProductSearchSubscriber` de `travel-booking-bundle`) |
| `customProperty:k:v` sur valeur non-string | **change** (étape 3) — c'est le correctif |
| Grammaire JSON du newsletter (`pageWhen` **et** `segment`) | libre — package neuf, aucun site aval. Les règles plates existantes restent valides : l'imbrication est un surensemble |
| Tag contact contenant `_` ou `%` | **corrigé** (étape 0) — sur-matche aujourd'hui, faute d'`ESCAPE` |
| Expressions `AND`/`OR` mixtes | **corrigé** (étape 2) — elles ne fonctionnent pas aujourd'hui, le fragment non splitté tombe dans le fallback tag |
| Parenthèses | **nouveau** (étape 2) — aujourd'hui avalées dans le nom du tag |
| Sortie de `StringToDQLCriteria::retrieve()` | **change** (étapes 2 puis 4) — asserté par `StringToDQLCriteriaTest`, test à réécrire |
