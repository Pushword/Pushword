# Pushword Admin Block Editor

Supercharge the Pushword admin with a Gutenberg-like block editor (Editor.js) while storing content as clean **Markdown**.

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

The same Playwright suite runs in CI on Google Chrome (`chrome`), Microsoft Edge
(`msedge`) and Playwright's Firefox build. Chrome and Edge use their actual branded
binaries, not a changed user-agent string. Each browser has its own CI job; a failure
does not cancel the other browsers, and no retry hides the first failure.

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

The suite starts its own test app on port 9079, with a temporary database, media,
sessions and content. It refuses to reuse an existing server. It does not reset the
development app. Each browser signs in through the real login form and uses a separate
page fixture. The server removes its temporary runtime when it stops.

The native keyboard journeys cover list indentation at several levels, all inline
formatting tools, undo/redo, immediate saving, hard breaks, rich-text copy/paste,
table navigation and editing, and Markdown/JSON mode round trips. They verify the
saved content by reloading the admin. The existing in-page checks also exercise every
configured block and tune, selection preservation and incremental rendering; they
are shared with Panther rather than copied. Uncaught browser errors and console errors fail the tests.

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
uses the host's exact Playwright version and opens an isolated Edge in the Windows
desktop through a temporary interactive scheduled task. The test app stays on Linux;
SSH forwards its port 9079 and Edge's ports 19880/19881 over loopback. The launcher
closes its browser, task and tunnels on completion; it leaves the VM running.

This runs the shared journeys, including system-clipboard Office HTML fixtures,
nested lists, accented text and selection replacement. Windows-only journeys use
physical AZERTY scan codes through Windows `SendInput`, including circumflex and
diaeresis dead keys, with browser-default suggestions and with suggestions disabled,
then save and reload the result. Reports, Windows/Edge versions,
screenshots and traces are separate in `playwright-report-windows/` and
`test-results-windows/`. This local run complements Linux CI. Office fixtures exercise
representative clipboard payloads; they do not launch Word or Outlook. Browser writing
assistants still need comparison on the affected user's profile.

### Reporting an editor problem

Open **Report a problem** above the editor and download the diagnostic file to attach
to a support message. It includes the installed version, loaded asset version, browser
and OS metadata, the last 60 editing actions and at most 20 errors. Caught copy,
history, save and tool failures show a visible message and enter the same report as
uncaught failures. A failed conversion leaves the form open and its last valid source
intact so the author can retry.

Typing over selected blocks replaces the selection with one paragraph and one
character, with undo/redo preserving the replaced blocks. Ctrl+U switches to and from
Markdown before the browser handles its own source-view shortcut.
Windows HTML clipboard context outside `StartFragment`/`EndFragment` is discarded
before inline insertion, so its CRLF wrapper cannot become extra paragraphs on save.

The report omits page text, clipboard payloads, field values, URLs and exception
messages. It stays in memory until downloaded; nothing is sent automatically. The
**Disable browser writing suggestions for this session** checkbox allows comparison
with and without inline predictions and rewriting (`writingsuggestions="false"`). It
applies only to the current editor session and leaves spellcheck available. Record
whether the symptom changes and include a diagnostic for each setting.

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/Pushword/Pushword/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
