# Page Workflow

Editorial workflow & pending modifications for [Pushword CMS](https://pushword.piedweb.com).

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)

## Documentation

See [pushword.piedweb.com/extension/page-workflow](https://pushword.piedweb.com/extension/page-workflow).

## What it does

- A `draft → in_review → approved` workflow on first publication, kept in a companion entity so the core `Page` row stays clean.
- A separate `PendingModification` cycle that lets editors prepare changes to **already-published** pages, get them reviewed via a side-by-side diff, and apply them atomically.
- A flat-file overlay (`{slug}.pending.md`) that activates automatically when `pushword/flat` is installed, so every proposed change is tracked by git.

## License

The MIT License (MIT). See [License File](https://pushword.piedweb.com/license#license).
