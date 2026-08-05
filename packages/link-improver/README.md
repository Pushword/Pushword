# Pushword Link Improver

Automatic internal linking: the first mention of another page's **name** in your rendered content becomes a link to it. Opt-in per site, capped, reversible (nothing is written into your source content), and auditable — every inserted link carries a `data-auto-link` attribute and `pw:link-improver` reports what was linked where.

[![Latest Version](https://img.shields.io/github/tag/pushword/pushword.svg?style=flat&label=release)](https://github.com/Pushword/Pushword/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Pushword/Pushword/run-tests.yml?branch=main)](https://github.com/Pushword/Pushword/actions)

## Features

- **The keyword map is your content**: each line of a page's `name` field is a linkable keyword — line 1 is the displayed name, further lines are link-only variants (`*` wildcard supported). No CSV to maintain.
- **Decorates at render time** — the source Markdown stays clean; disable the option and every auto link is gone.
- **Safe by construction**: never inside a tag, a heading or code, never next to another link, never a second link to an already-linked target, capped by a word-count ratio.
- **Auditable**: `data-auto-link` attribute on every inserted link, `pw:link-improver` (with `--simulate` to preview before opting in) lists them per page.

## Installation

```shell
composer require pushword/link-improver
```

Then enable it per app:

```yaml
pushword:
  apps:
    - hosts: [example.tld]
      link_improver: true
      # link_improver_max_links: 0.01
```

## Documentation

Visit [pushword.piedweb.com/extension/link-improver](https://pushword.piedweb.com/extension/link-improver).

## Contributing

If you're interested in contributing to Pushword, please read our [contributing docs](https://pushword.piedweb.com/contribute) before submitting a pull request.

## Credits

- [PiedWeb](https://en.piedweb.com)
- [All Contributors](https://github.com/Pushword/Pushword/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://pushword.piedweb.com/license#license) for more information.
