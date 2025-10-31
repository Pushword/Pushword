---
title: Puswhord Flat File CMS - Markdown and Twig Ready
h1: Flat
toc: true
parent: extensions
---

Transform Pushword in a FlatFile CMS.

## Install

```
composer require pushword/flat-file
```

## Configure (if needed)

Globally under `pushword_static_generator` (in `config/packages`).

Or for _multi-sites_ in `config/package/pushword.yaml`.

```yaml
...:
  flat_content_dir: content #default value
```

## Usage

### Command Line

```
php bin/console pw:flat:import $host
```

Where $host is facultative.

### Write

By default, the content may be organized in `content/%main_host%/` dir and image may be in `content/%main_host%/media/default` or in `media`

Eg:

```
content
content/homepage.md
content/kitchen-skink.md
content/other-example-kitchen-sink.md
content/en/homepage.md
content/en/kitchen-skink.md
content/media/default/illustation.jpg
content/media/default/illustation.jpg.json
```

#### `kitchen-skin.md` may contain :

<!--
Add to \Pushword\Core\Entity\Page
    public function getProperties()
    {
        return array_keys(get_object_vars($this));
    }
Then
$ php -a
include 'vendor/autoload.php';
$properties = (new \Pushword\Core\Entity\Page())->getProperties();
foreach ($properties as $p) echo $p.chr(10);
-->

```yaml
---
h1: 'Welcome in Kitchen Sink'
locale: fr
translations:
  - en/kitchen-skink
main_image: media/default/illustration.jpg
images:
  - media/default/illustration.jpg
parent:
  - homepage
childrenPages:
  - other-example-kitchen-sink
metaRobots: 'no-index'
name: 'Kitchen Sink'
title: 'Kitchen Sink - best google restult'
#created_at: 'now' # see https://www.php.net/manual/fr/datetime.construct.php
#updated_at: 'now'
---
My Page content Yeah !
```

Good to know :

- **camel case** or **undescore case** work
- link to page must use **slug**
- **slug** is generate from file path (removing `.md`) and can be override by a property in _yaml front_
- `homepage` 's file could be named `index.md` or `homepage.md`
- Other properties will be added to `customProperties`

#### `illustation.jpg.json` may contain

```json
{
  "name": "My Super Kitchen Sink Illustration",
  "createdAt": "now",
  "updatedAt": "now"
}
```
