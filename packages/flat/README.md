# Flat File CMS with Pushword

Transform Pushword in a FlatFile CMS.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

[![Code Coverage](https://codecov.io/gh/Pushword/Pushword/branch/main/graph/badge.svg)](https://codecov.io/gh/Pushword/Pushword/tree/main)
[![Type Coverage](https://shepherd.dev/github/pushword/pushword/coverage.svg)](https://shepherd.dev/github/pushword/pushword)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/core.svg?style=flat)](https://packagist.org/packages/pushword/core)

## Documentation

Visit [pushword.piedweb.com](https://pushword.piedweb.com/extension/flat)

## Contributing

If you're interested in contributing to Pushword, please read our [contributing docs](https://pushword.piedweb.com/contribute) before submitting a pull request.

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/Pushword/Core/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license#license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>

---

# Schéma du flux `pw:flat:sync`

```mermaid
flowchart TD
    Start([Commande: pw:flat:sync]) --> Check{FlatFileSync.mustImport}

    Check -->|Fichiers plus récents que DB| Import[IMPORT: Fichiers → Base de données]
    Check -->|DB plus récente ou pas de fichiers| Export[EXPORT: Base de données → Fichiers]

    %% BRANCHE IMPORT
    Import --> ImportMedia1[1. Import médias depuis mediaDir]
    ImportMedia1 --> ImportMedia1Finish[finishImport médias]
    ImportMedia1Finish --> ImportMedia2[2. Import médias depuis contentDir/media]
    ImportMedia2 --> ImportMedia2Finish[finishImport médias]
    ImportMedia2Finish --> ImportPages[3. Import pages depuis contentDir]
    ImportPages --> ImportPagesFinish[finishImport pages]

    ImportPagesFinish --> ImportProcess[Pour chaque fichier .md]
    ImportProcess --> ImportCheck{Page existe en DB?}
    ImportCheck -->|Non| ImportCreate[Créer nouvelle Page]
    ImportCheck -->|Oui| ImportCompare{Fichier plus récent?}
    ImportCompare -->|Oui| ImportUpdate[Mettre à jour Page]
    ImportCompare -->|Non| ImportSkip[Ignorer]
    ImportCreate --> ImportFlush[Flush DB]
    ImportUpdate --> ImportFlush
    ImportSkip --> ImportFlush
    ImportFlush --> ImportLinks[Convertir liens markdown relatifs en slugs]
    ImportLinks --> ImportRelations[Traiter relations parentPage, mainImage, etc.]
    ImportRelations --> ImportEnd[Import terminé]

    %% BRANCHE EXPORT
    Export --> ExportPages[1. Exporter toutes les pages]
    ExportPages --> ExportPageLoop[Pour chaque Page en DB]
    ExportPageLoop --> ExportPageCheck{Fichier existe et plus récent?}
    ExportPageCheck -->|Oui et pas --force| ExportPageSkip[Ignorer]
    ExportPageCheck -->|Non ou --force| ExportPageWrite[Écrire .md avec front matter YAML]
    ExportPageSkip --> ExportMedias
    ExportPageWrite --> ExportMedias[2. Exporter tous les médias]

    ExportMedias --> ExportMediaLoop[Pour chaque Media en DB]
    ExportMediaLoop --> ExportMediaYaml[Écrire .yaml avec métadonnées]
    ExportMediaYaml --> ExportMediaCopy{CopyMedia configuré?}
    ExportMediaCopy -->|Oui| ExportMediaCopyFile[Copier fichier média]
    ExportMediaCopy -->|Non| ExportEnd
    ExportMediaCopyFile --> ExportEnd[Export terminé]

    ImportEnd --> End([Fin])
    ExportEnd --> End

    style Start fill:#e1f5ff
    style Import fill:#fff4e1
    style Export fill:#e1ffe1
    style ImportEnd fill:#d4edda
    style ExportEnd fill:#d4edda
    style End fill:#e1f5ff
```

## Détails du processus

### Décision Import/Export

La méthode `FlatFileSync->mustImport()` détermine la direction :

- Scanne récursivement le répertoire de contenu
- Pour chaque fichier `.md`, compare `filemtime()` avec `Page->getUpdatedAt()`
- Si un fichier est plus récent que sa page en DB → **IMPORT**
- Sinon → **EXPORT**

### Import (Fichiers → DB)

1. **Import médias globaux** (`mediaDir`)
   - Parcourt récursivement le répertoire
   - Pour chaque fichier : vérifie si plus récent que `Media->getUpdatedAt()`
   - Met à jour ou crée l'entité `Media`
   - Lit les métadonnées depuis `.yaml` ou `.json` (deprecated) si présents

2. **Import médias locaux** (`contentDir/media`)
   - Même processus pour les médias spécifiques au contenu

3. **Import pages** (`contentDir`)
   - Parse chaque fichier `.md` avec front matter YAML
   - Extrait le slug (depuis front matter ou nom de fichier)
   - Met à jour ou crée l'entité `Page`
   - Convertit les liens markdown relatifs en slugs absolus
   - Traite les relations (parentPage, mainImage, translations, etc.)

### Export (DB → Fichiers)

1. **Export pages**
   - Pour chaque `Page` en DB :
     - Vérifie si le fichier `.md` existe et est plus récent (sauf avec `--force`)
     - Génère le front matter YAML avec toutes les propriétés
     - Écrit le fichier `{slug}.md`

2. **Export médias**
   - Pour chaque `Media` en DB :
     - Écrit un fichier `.yaml` avec les métadonnées
     - Si `copyMedia` est configuré, copie également le fichier média
