You just succeed to install **Pushword**.

You are on your future **homepage**. [Look at the docs](https://pushword.piedweb.com/configuration) to configure and customize your own Pushword website.

Have fun,<br>
[Robin](https://piedweb.com)

{% set items = [
  {
    'image'  : '3',
    'title'  : 'Configure it',
    'link'    : 'https://pushword.piedweb.com/configuration',
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
