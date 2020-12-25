A few example for the editor possibilities. Best to observe this, it's in admin with split editor (live preview Side By Side).

## Links & Routes

- [Homepage]({{ homepage() }})
- [Current Page]({{ page(page) }})
- Get Url : {{ page('what-you-want-in-same-app') }}
- Canonical with base: {{ page(page, true) }}
- Encrypted Link : {{ link('Pied Web', 'https://piedweb.com/') }}
- Self Encrypted Link: {{ link('Pied Web', page) }}
- contact@piedweb.com ou {{ mail('contact@piedweb.com') }}
- {{ tel('+33 00 00 00 00') }} ou directement +331 00 00 00 00

## Quotes

> PHP ecosystem is undeniably awesome! With its extensive library of frameworks like Laravel and Symfony, coupled with its flexibility and scalability, PHP empowers developers to build robust and dynamic web applications effortlessly. Its active community constantly contributes to its evolution, ensuring it stays relevant and cutting-edge. Whether it's for small projects or enterprise-level applications, PHP remains a top choice for developers worldwide. In short, PHP rocks!

## Images et Galleries

## Simple Image

![1](/media/default/1.jpg)

![2](2.jpg)

## Gallery

{{ gallery({'Pied Web Logo' :'piedweb-logo.png', 'Demo 1': '1.jpg', 'Demo 2': '2.jpg', 'Demo 3': '3.jpg'})|unprose }}

## Video

Avoiding load Youtube cookies per default.

{{ video('https://www.youtube.com/watch?v=Nwyylc9GQuQ', '/media/default/3.jpg')|unprose }}

or open an iframe

{{ video('https://www.youtube.com/watch?v=Nwyylc9GQuQ', '/media/default/3.jpg', 'my video title', true)|unprose }}

## Advanced

- Get a theme component

## Render Page List

### Page found via Kw

{{  pages_list('content:fun', 3) }}

{{  pages_list('content:Fun', [3, 3], 'publishedAt DESC', 'card')|unprose }}

### Or 100% custom card

{% set items = [
  {
    'image'  : '3',
    'title'  : 'Incredible Igloo builder in France',
    'link'    : 'https://altimood.com/en',
  },
  {
    'image'  : '1',
    'title'  : 'Understanding the north lights',
    'link'    : 'https://en.wikipedia.org/wiki/North_light',
  },
  {
    'image'  : '2',
    'title'  : 'Unleash the power of Online Presence',
    'link'    : 'https://piedweb.com',
  },
] %}

<div class="not-prose lg:-mx-40 my-6 md:-mx-20">
  <ul class="flex flex-row my-5 flex-wrap justify-center mx-auto">
    {% for item in items %}
      <li class="w-full px-2 my-1 sm:w-1/2 md:w-1/3">
        {% include view('/component/card.html.twig') with item only %}
      </li>
    {% endfor %}
  </ul>
</div>

<a href="https://pushword.piedweb.com" class="btn btn-primary">See the docs</a>
