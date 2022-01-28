---
title: Manage your Pushword CMS with command
h1: Command
toc: true
parent: homepage
---

At any moment, you can get the avalaible command from symfony by typing `php bin/console list`.

Now, let's see what command we have and how useful there are.

The list above may vary depending on [installed extensions](extensions)

To get more details on each command line, just type -h (eg `php bin/console pushword:user:create -h`)

```shell
 pushword
  pushword:flat:import                       Syncing flat file inside database
  pushword:flat:export                       Export database toward file (yaml+json)
  pushword:image:cache                       Generate all images cache
  pushword:image:optimize                    Optimize all images cache
  pushword:page:scan                         Find dead links, 404, 301 and more in your content.
  pushword:static:generate                   Generate a static version for your website(s)
  pushword:user:create                       Create a new user
```
