# Admin Block Editor Tools

Extensions EditorJS pour Pushword CMS avec support de la conversion Markdown.

## Utilisation

```bash

yarn lint      # Linter le code
yarn lint:fix  # Linter le code et corriger les erreurs
yarn format    # Formater le code
```

Le build est réalisé directement dans admin-block-editor.

## Développement

Chaque bloc est un outil EditorJS qui comprend ses fonctionnalités propres à l'éditeur et ajoute :

- Fonction `exportToMarkdown()` pour la conversion vers Markdown
- Support de la syntaxe attributes `{#anchor .class}`
- Intégration des fonctions Twig Pushword

### 📝 Logging

Utilisez le système de logging unifié :

```typescript
import { logger } from '../utils/logger'

// Debug (seulement en développement)
logger.debug('Message de debug', { data })

// Info (seulement en développement)
logger.info('Information', { data })

// Warning (toujours affiché)
logger.warn('Avertissement', { data })

// Error (toujours affiché)
logger.error('Erreur', { data })

// Erreur avec contexte
logger.logError(error, 'Contexte', { additionalInfo })
```
