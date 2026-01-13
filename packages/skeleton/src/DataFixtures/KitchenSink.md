A few example for the editor possibilities. Best to observe this, it’s in admin with split editor (live preview Side By Side).

---

## Inline Tool Demo

See **bold** _italic_ `inline code` #[link](/kitchen-sink-block{target="_blank"}) <mark>marker</mark> ~~strikethrough~~

## Links & Routes

- [Homepage](/)
- Current Page: {{ page(page) }}
- Get Url : {{ page('what-you-want-in-same-app') }}
- Canonical with base: {{ page(page, true) }}
- Obfuscated Link : [Pied Web](https://piedweb.com) — contact@piedweb.com ou {{ mail('contact@piedweb.com') }}
- {{ tel('+33 00 00 00 00') }} ou directement +331 00 00 00 00
- [AnchorLink](#citation)

## Quotes

{#citation}
> PHP ecosystem is undeniably awesome! With its extensive library of frameworks like Laravel and Symfony, coupled with its flexibility and scalability, PHP empowers developers to build robust and dynamic web applications effortlessly. Its active community constantly contributes to its evolution, ensuring it stays relevant and cutting-edge. Whether it's for small projects or enterprise-level applications, PHP remains a top choice for developers worldwide. In short, PHP rocks!
> — <cite>Author</cite>

## Images et Galleries

### Simple Image

![Demo 3](/media/3.jpg)

### Gallery

{{ gallery({"1.jpg":"","2.jpg":"","3.jpg":""}) }}

## Advanced gallery

{{ gallery({'logo.svg': ['SVG Logo', 'https://piedweb.com', {}, false], '1.jpg': '', '2.jpg': '', '3.jpg': ''}, clickable=false) }}

## Video

{{ video('https://www.youtube.com/watch?v=Nwyylc9GQuQ', '3.jpg', 'SuperVideo') }}

## Video in a popup

{{ video('https://www.youtube.com/watch?v=Nwyylc9GQuQ', '/media/default/3.jpg', 'my video title', true) }}

## Table Example

| Fonctionnalité | Statut   | Notes                     |
| -------------- | -------- | ------------------------- |
| Table          | ✅ Testé | Nouveau dans Kitchen Sink |
| Attaches       | ✅ Testé | Gestion des fichiers      |
| Delimiter      | ✅ Testé | Séparateur de contenu     |
| linkTune       | ✅ Testé | Liens sur images          |

## Code Block

```html
<div>
  {{ hello }}
  {% include view('codeblockTest.html.twig') %}
</div>
```

<p data-attribute="this attribute permits to avoid paragraph normalization">Example Raw Html</p>

## Render Page List

### Page found via Kw

{{ pages_list('content:fun', '9', 'publishedAt ↓', 'list') }}

{{ pages_list('content:fun', '9', 'publishedAt ↓', 'card') }}

## Card List (Custom Items)

{{ card_list([{"page":"kitchen-sink-block","title":"Kitchen Sink"},{"title":"Custom Card","image":"1.jpg","link":"https://piedweb.com","description":"A custom card with **bold** and _italic_ text.","buttonLink":"https://piedweb.com","buttonLinkLabel":"Visit"}]) }}

[Continue your exploration with the docs ➜](https://pushword.piedweb.com)

## Attachments & Files

{{ attaches('Document JPG 2', '/media/2.jpg', '0' ) }}

Texte avec <u>soulignement</u>, **gras**, _italique_, <mark>surlignage</mark> et ~~barré~~ pour tester tous les outils inline.

## Image avec Lien (linkTune)

[![Image cliquable vers la documentation](/media/2.jpg)](https://pushword.piedweb.com)

## HR — separator

---

## Reviews

{{ reviews(page) }}