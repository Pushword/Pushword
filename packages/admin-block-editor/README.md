# Pushword Admin Block Editor

An Editor.js block editor for the Pushword admin, with content stored as **Markdown**.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

[![Code Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2FPushword%2FPushword%2Fbadges%2Fcoverage.json)](https://github.com/Pushword/Pushword/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/pushword/admin-block-editor.svg?style=flat)](https://packagist.org/packages/pushword/admin-block-editor)

## Features

- **Block (Editor.js) editing** inside the admin, toggleable per app.
- Content stored as **Markdown** — blocks are converted to/from Markdown transparently.
- **Extensible**: add your own Editor.js plugins / custom blocks.
- **Pages-list block** with a powerful search syntax (`CHILDREN`, `slug:…`, `OR`).

## Installation

```shell
composer require pushword/admin-block-editor
```

## Documentation

Visit [pushword.piedweb.com/extension/admin-block-editor](https://pushword.piedweb.com/extension/admin-block-editor).

### Site-provided tools

Load a site's tool script before the block editor initializes, then add its constructor
directly to the widget's `editorjsConfig.tools`. Native Editor.js constructors and
Pushword's built-in `className` entries can be used together:

```javascript
editorjsConfig.tools.promotion = {
  class: PromotionTool,
  config: { preview: '/admin/promotion/preview' },
}
```

To preserve Markdown storage, a custom tool also implements the static
`isItMarkdownExported(markdown)`, `importFromMarkdown(editor, markdown)` and
`exportToMarkdown(data, tunes)` methods. Claim only syntax the tool can read and
write without losing information; leave unsupported expressions to the Raw tool.

## The Pushword ecosystem

Pushword is a modular CMS — one [Symfony](https://symfony.com) bundle for the core and one bundle per feature. See the full list and guides at [pushword.piedweb.com/extensions](https://pushword.piedweb.com/extensions).

## Contributing

If you're interested in contributing to Pushword, please read our [contributing docs](https://pushword.piedweb.com/contribute) before submitting a pull request.

### Browser integration tests

The Playwright suite runs in separate CI jobs on Google Chrome (`chrome`), Microsoft
Edge (`msedge`) and Playwright's Firefox build, with no retries.

From the repository root on Linux, with Composer dependencies installed:

```shell
yarn --cwd packages/admin-block-editor install --frozen-lockfile
yarn --cwd packages/admin-block-editor playwright install --with-deps chrome msedge firefox
yarn --cwd packages/admin-block-editor build
yarn --cwd packages/admin-monaco-editor install --frozen-lockfile
yarn --cwd packages/admin-monaco-editor build
yarn --cwd packages/admin-block-editor test:browser
# Run one browser or one journey while investigating:
yarn --cwd packages/admin-block-editor test:browser --project=msedge --grep 'checklist:'
```

The suite starts a test app on port 9079 with a temporary database, media, sessions
and content, removed when the server stops. It refuses to reuse an existing server
and leaves the development app's content intact. Each browser signs in through the
login form and uses a separate page fixture.

Keyboard journeys cover nested lists, inline formatting, undo/redo, immediate saving,
hard breaks, rich-text copy/paste, tables and Markdown/JSON round trips. Saved content
is checked after reloading the admin. In-page checks shared with Panther cover the
configured blocks and tunes, selection preservation and incremental rendering.
Uncaught browser errors and console errors fail the tests.

CI uploads the HTML/JSON reports, browser versions, individual in-page outcomes,
server log, failure screenshots and Playwright traces. Local reports are in `playwright-report/`
and `test-results/` inside this package. Add new user regressions to
`tests/browser/*.spec.ts`: every scenario then runs on all three browsers.

### Microsoft Edge on the local Windows VM

Start the existing Windows 11 VM with its usual VM launcher. It needs Edge, Node.js,
OpenSSH and an active desktop session. Run from the Linux host after building the
editor and Monaco as above:

```shell
yarn --cwd packages/admin-block-editor test:windows
# Narrow a diagnostic run:
yarn --cwd packages/admin-block-editor test:windows --grep 'physical keys|Office'
```

The default SSH target is `Administrator@127.0.0.1:2223`, with the key at
`~/.local/share/ga-windows11-vm/cle`. Override `PW_WINDOWS_SSH_HOST`,
`PW_WINDOWS_SSH_PORT` or `PW_WINDOWS_SSH_KEY` for another existing VM. The launcher
uses the host's Playwright version and opens an isolated Edge through a temporary
interactive scheduled task. SSH forwards the Linux test app's port 9079 and Edge's
ports 19880/19881 over loopback. The launcher closes its browser, task and tunnels
on completion and leaves the VM running.

This runs the shared journeys and Windows-only AZERTY keyboard tests, including
circumflex and diaeresis dead keys with default writing suggestions and with them
disabled. Reports, Windows/Edge versions, screenshots and traces are in
`playwright-report-windows/` and `test-results-windows/`. Office clipboard fixtures
use representative HTML payloads without launching Word or Outlook.
Writing-assistant problems still need comparison on the affected user's profile.

### Reporting an editor problem

Use **Report a problem** above the editor to download a diagnostic and compare browser
writing suggestions. See [Reporting an editor problem](https://pushword.piedweb.com/extension/admin-block-editor#reporting-an-editor-problem)
for the steps and the report's contents.

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/Pushword/Pushword/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
