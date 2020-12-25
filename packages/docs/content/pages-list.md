---
title: List Pages as a pro with Pushword CMS
h1: 'Create Page List<br><small>Advanced filtering</small>'
parent: editor
raw: true
toc: true
see:
---

You can do advanced filtering toward the twig `pages_list` like in the [admin-block-editor](/extension/admin-block-editor).

| Value                            | Expected behavior                                                                                               |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `children`                       | filter children pages (case insensitive)                                                                        |
| `grandchildren`                  | filter grandchildren pages's pages (case insensitive)                                                           |
| `children_children`              | alias for `grandchildren` (**deprecated**)                                                                      |
| `sisters`                        | filter sister pages (case insensitive)                                                                          |
| `parent_children`                | alias for `sisters` (**deprecated**)                                                                            |
| `related`                        | if there is a parent page, filter results with sister pages with a _pageId_ inferior to _currentPageId_ + **3** |
| `comment:`_exampleValue_         | filter rpages containing in _mainContent_ `<!--exampleValue-->` (**deprecated**, prefer tags)                   |
| `related:comment:`_exampleValue_ | same as `related` but instead of _sister pages_, it's pages containing the comment                              |
| `title:`_exampleValue_           | filter pages containing in title (**seo title** or **h1**) the _exampleValue_                                   |
| `content:`_exampleValue_         | same than `title` + searching in _mainContent_ too                                                              |
| `slug:`_exampleValue_            | filter pages with the exact slug (useful only with **OR**)                                                      |
| `slug:`_%exampleValue%_          | same than `slug:` with **%** ➜ filter page with the slug containing _exampleValue_.                             |
| _exampleValue_                   | filter _tag_ (exact match only !)                                                                               |

## Using Operators `OR` or `AND`

For now, you can only use `OR` or `AND` operator in the same query

Examples :

- ✔ `related:comment:blog OR related`
- ✔ `parent_children OR related OR page:custom-slug`
- ✔ `parent_children AND related AND page:custom-slug` (this one will output only 1 result)
- ✗ `parent_children AND related OR page:custom-slug` (this one will output only 1 result)
- ✗ `(parent_children AND related) OR page:custom-slug` (this one will output only 1 result)
