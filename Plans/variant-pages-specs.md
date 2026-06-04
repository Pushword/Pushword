# Pages variantes — spécifications (validées)

Comportement convenu après retour du premier consommateur. Pushword porte la
**couche données + SEO** (qui est maîtresse, et la réversibilité) ; il ne
réimplémente pas l'UI ni la mesure.

## Le problème

Plusieurs pages présentent quasiment le même contenu avec des variations
mineures (ex. une même fiche séjour / un même produit décliné par vendeur ou par
partenaire). Pour Google ce sont des quasi-doublons qui se cannibalisent. On veut
garder la personnalisation **sans** créer de doublon SEO.

## Le principe (modèle retenu)

- Une page peut être désignée **variante d'une page maîtresse**.
- La variante est une **vraie page, avec sa propre URL, rendue serveur**, et son
  propre contenu complet.
- Côté SEO, **la maîtresse fait foi** : la variante émet `canonical → maîtresse`.
  Une seule page est indexée ; le « jus » se consolide sur la maîtresse.
- **La consolidation passe par la réécriture des liens, pas par du masquage
  d'URL.** Tout lien interne vers une variante est réécrit en :

  ```html
  <a href="{url-maîtresse}" data-variant="{url-variante}">…</a>
  ```

  → un crawler ou un visiteur **sans JS** suit `href` = la maîtresse (lien
  consolidé, variante invisible pour Google). La couche JS, optionnelle, charge la
  variante.

## Comportement détaillé

### SEO / sans JS (garanti par Pushword)

- La maîtresse est la seule référencée ; chaque variante déclare la maîtresse en
  *canonical*.
- **Pas de `noindex`** sur les variantes (signaux contradictoires avec la
  canonical, risque de dé-référencer aussi la maîtresse). La canonical seule
  consolide.
- Les liens internes vers une variante pointent, côté HTML brut, vers la
  maîtresse.
- Les variantes sont **exclues du sitemap, de la recherche interne et des menus**.
- Les variantes ne sont **pas** exclues d'office des listes / cartes de contenu :
  un catalogue peut garder la carte de la variante visible (son lien étant
  réécrit). Le rendu de ces listes est à la main du site consommateur.

### Avec JS (amélioration progressive, optionnelle)

- Fournie par `packages/js-helper` (pas par le cœur) : au clic sur un lien
  `[data-variant]`, le helper charge l'URL de la variante, remplace la zone de
  contenu, et met à jour l'adresse (`pushState` vers l'URL de la variante, pour le
  partage et la provenance).
- **Sans JS, aucun effet** : on reste sur la maîtresse (dégradation propre).
- Un site avec son propre JS peut brancher son comportement à la place du helper ;
  le helper peut aussi exposer un branchement htmx/Alpine (présents sur la quasi
  totalité des projets Pushword).
- Après remplacement, le helper **émet un événement** pour que le site ré-initialise
  ses composants (Alpine, widget de réservation, Glightbox…). Cette ré-init est à
  la charge du site.

### Édition (administration)

- Sur une page, un champ permet de la désigner **variante d'une autre page**.
- **Réversibilité en 1 clic** (exigence cœur) : promouvoir une variante en
  maîtresse / rétrograder. Les autres variantes basculent automatiquement sous la
  nouvelle maîtresse. C'est le levier d'arbitrage SEO au cas par cas.
- **Suppression de la maîtresse** : une variante est automatiquement promue à sa
  place (pas d'orphelins).
- En bonus, indépendant des variantes : définir une **canonical personnalisée**
  libre sur n'importe quelle page (livrable séparé, fondation de la feature).

## Périmètre

**Porté par Pushword** (couche données + SEO) :
- entité `variantOf` / `variants` / `isVariant()` + `customCanonical` ;
- rendu canonical → maîtresse ; réécriture des liens internes
  (`href` maîtresse + `data-variant`) ;
- exclusions sitemap / recherche / menus ;
- réversibilité (promote/demote, promotion à la suppression) ;
- aller-retour `flat` (variante référencée par slug) ;
- le helper JS opt-in dans `js-helper`.

**À la charge du site consommateur** :
- l'UI « switcher » (cartes des variantes frères, état actif/grisé, fiche
  technique) ;
- la ré-initialisation des composants JS après remplacement de zone ;
- la mesure SEO et la décision associée (Search Console / Matomo) — Pushword porte
  la décision et sa réversibilité, pas la mesure ;
- le rendu des listes / cartes de catalogue.

## Limites assumées

1. **Statistiques** : sans le helper, la vue est comptée sur l'URL de la variante
   (vraie page). Avec le helper, l'URL bascule en `pushState` vers la variante —
   la mesure fine reste à câbler côté site.
2. La variante étant une vraie page, elle reste **accessible en direct par son
   URL** ; ce sont les *liens internes* qui sont consolidés vers la maîtresse.
