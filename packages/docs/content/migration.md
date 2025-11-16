---
title: Migration and media path updates
h1: Migration
toc: true
parent: homepage
---

This page documents migration procedures.

## Updating media storage paths

When you move your Pushword project or modify the directory structure, the media storage paths (`storeIn`) stored in the database may become obsolete. The `pw:media:update-store-in` command allows you to update these paths automatically.

```bash
php bin/console pw:media:update-store-in
```

### When to use this command?

Use this command in the following cases:

- After moving your project to a new directory
- After migrating to a new server
- If you have modified your project's directory structure
- If media paths in the database no longer match the current structure

### How it works

The command:

1. Automatically detects the old storage path by analyzing existing media
2. Compares it with the expected new path
3. Updates all media if a difference is detected
4. Verifies that each media file exists before proceeding with the update

### Usage

#### Dry-run mode (test)

Before applying changes, it is recommended to use the `--dry-run` mode to see what will be modified without making any changes:

```bash
php bin/console pw:media:update-store-in --dry-run
```

This command will display:

- The number of media that will be updated
- Any missing files (with an error message)
- A preview of the changes without applying them

#### Applying changes

Once you have verified with dry-run mode, run the command without the `--dry-run` option to apply the changes:

```bash
php bin/console pw:media:update-store-in
```

The command will display a progress bar and update all media storage paths in the database.

### Example output

```
5 media found to update.
5/5 [████████████████████████████] 100% 00:00/00:00
 Updating: image1.jpg
5 media updated successfully.
```

### Error handling

If a media file is not found at the new path, the command:

- In `--dry-run` mode: displays an error message but continues the analysis
- In normal mode: throws an exception and stops execution

Make sure all your media files are present in the `media/` directory before running the command.

### Important notes

- The command does not physically move files, it only updates paths in the database
- If you have moved media files, make sure they are in the correct directory before running the command
- The command uses the `%kernel.project_dir%` placeholder to store paths in a portable way
