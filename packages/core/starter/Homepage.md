**Pushword is installed.** This is your future homepage — edit it from
[the admin](/admin), or replace it with your own.

Three pages came with it: [Getting started](/getting-started) explains how to work,
[What you can write](/examples) shows what the editor can do, and [Contact](/contact) is
an example of a real page.

{% set items = [
  {
    'image'  : '3',
    'title'  : 'Configure it',
    'link'    : 'https://pushword.piedweb.com/installation',
  },
  {
    'image'  : '1',
    'title'  : 'Template it',
    'link'    : 'https://pushword.piedweb.com/themes',
  },
  {
    'image'  : '2',
    'title'  : 'Extend it',
    'link'    : 'https://pushword.piedweb.com/extensions',
  },
] %}
<div class="not-prose lg:-mx-40 my-6 md:-mx-20">
  <ul class="flex flex-row my-5 flex-wrap justify-center mx-auto">
    {% for item in items %}
      <li class="w-full px-1 my-1 sm:w-1/2 md:w-1/3">
        {% include view('/component/card.html.twig') with item only %}
      </li>
    {% endfor %}
  </ul>
</div>

## Done looking?

These four demo pages are tagged `demo`. Remove them in one go:

```bash
php bin/console pw:page:delete --tag=demo
```
