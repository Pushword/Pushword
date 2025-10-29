# Pushword Docs Assets

Ce répertoire contient les assets (CSS/JS) pour la documentation Pushword.

## Développement

```bash
# Installer les dépendances
yarn install

# Démarrer le serveur de développement Vite
yarn dev

# Build en mode watch (pour les changements en temps réel)
yarn watch

# Build pour la production
yarn build
```

## Structure

- `app.js` - Point d'entrée JavaScript principal
- `app.css` - Feuille de style principale (avec Tailwind CSS v4)
- `favicons/` - Icônes favicon
- `logo.svg` - Logo Pushword
- `vite.config.js` - Configuration Vite

## Configuration

### Tailwind CSS

La configuration Tailwind CSS v4 est gérée via le fichier `app.css` avec la directive `@import 'tailwindcss'`.

### Fichiers à copier

Les fichiers suivants sont copiés vers le répertoire de sortie :
- `logo.svg`
- `favicons/*`

## Output

Les assets compilés sont générés dans `/packages/skeleton/public/assets/` :
- `app.min.js` - Bundle JavaScript minifié
- `tw.min.css` - Styles CSS minifiés
- Icônes favicon
- Logo SVG

## Migration depuis Webpack

Cette configuration a été migrée de Webpack/Encore vers Vite pour améliorer les performances de build et de développement.

Changes clés :
- `package.json` scripts : `encore` → `vite`
- `webpack.config.js` → `vite.config.js`
- PostCSS Loader → `@tailwindcss/vite` plugin


