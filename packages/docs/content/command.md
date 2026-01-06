---
title: 'Manage your Pushword CMS with command'
h1: Command
id: 24
publishedAt: '2025-12-21 21:55'
parentPage: search.json
toc: true
---

Let's take a look at the commands available in Pushword and their purpose. Keep in mind that the exact list may vary depending on the [installed extensions](extensions).

```shell
 pushword
  pw:flat:sync                Import to database or Export to flat file
  pw:flat:import              Syncing flat file inside database
  pw:flat:export              Export database toward file (yaml+json)
  pw:message:flat             Sync conversation CSV with database (auto import/export)
  pw:message:import           Import an external conversation CSV
  pw:image:cache        Generate all images cache
  pw:image:optimize     Optimize all images cache
  pw:pdf:optimize       Optimize PDF files (compress and linearize)
  pw:page-scan          Find dead links, 404, 301 and more in your content.
  pw:static    Generate a static version for your website(s)
  pw:user:create        Create a new user
```

To get more details on each command line, just type `-h` (eg `php bin/console pw:user:create -h`)

You can also view all the available Symfony commands by running:

```bash
php bin/console list
```