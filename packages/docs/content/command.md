---
title: Manage your Pushword CMS with command
h1: Command
toc: true
parent: homepage
---

Let's take a look at the commands available in Pushword and their purpose. Keep in mind that the exact list may vary depending on the [installed extensions](extensions).

```shell
 pushword
  pw:flat:sync                Import to database or Export to flat file
  pw:flat:import              Syncing flat file inside database
  pw:flat:export              Export database toward file (yaml+json)
  pushword:image:cache        Generate all images cache
  pushword:image:optimize     Optimize all images cache
  pw:media:update-store-in    Update media storage paths (useful for migration)
  pushword:page:scan          Find dead links, 404, 301 and more in your content.
  pushword:static:generate    Generate a static version for your website(s)
  pushword:user:create        Create a new user
```

To get more details on each command line, just type `-h` (eg `php bin/console pushword:user:create -h`)

You can also view all the available Symfony commands by running:

```bash
php bin/console list
```
