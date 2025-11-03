# Extensions Markdown Pushword

Extensions personnalisées pour league/commonmark intégrant les fonctionnalités spécifiques de Pushword.

## Architecture

Cette extension suit le pattern standard de league/commonmark avec :

- **Node/** : Nœuds personnalisés de l'AST
- **Parser/** : Parsers inline pour détecter la syntaxe personnalisée
- **Renderer/** : Renderers pour générer le HTML final
- **Util/** : Classes utilitaires
- **PushwordExtension.php** : Extension principale qui enregistre tous les composants

## Fonctionnalités

### 1. Liens obfusqués (`ObfuscatedLink`)

Syntaxe pour créer des liens obfusqués qui masquent l'URL des robots :

```markdown
#[texte du lien](https://example.com)
```

**Avec attributs :**

```markdown
#[texte](url){.class-css} #[texte](url){#identifiant}
```

**Rendu :**
Le lien est rendu via `LinkProvider->renderLink()` avec obfuscation activée.

### 2. Images personnalisées (`ImageRenderer`)

Remplace le renderer d'images standard pour utiliser le template Twig personnalisé de Pushword.

```markdown
![Alt text](image.jpg)
```

**Rendu :**
L'image est rendue via le template `@Pushword/component/image_inline.html.twig`

## Utilisation

L'extension est automatiquement enregistrée dans `MarkdownParser` :

```php
use Pushword\Core\Service\Markdown\MarkdownParser;

$parser = new MarkdownParser($linkProvider, $twig, $apps);
$html = $parser->transform($markdownText);
```
