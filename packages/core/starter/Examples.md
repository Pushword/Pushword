What the editor can do. Open this page in the admin with the split editor to read the
source and the result side by side.

---

## Inline tools

**bold** _italic_ `inline code` <mark>highlight</mark> ~~strikethrough~~ and a
#[link](/getting-started){target="_blank"}.

## Links and routes

- [Homepage](/)
- Link to a page by slug: {{ page('getting-started') }}
- Absolute URL of the current page: {{ page(page, true) }}
- Obfuscated email: {{ mail('hello@example.com') }}
- Phone: {{ tel('+33 00 00 00 00') }}
- [Jump to the quote below](#quote)

Writing a slug rather than a URL means the link follows the page if it moves.

## Quote

{#quote}

> Content is Markdown, templates are Twig, and the database is a file. There is no
> proprietary field builder to learn — if you know Symfony, you already know Pushword.
> — <cite>The docs</cite>

## Images

![Demo 3](/media/3.jpg)

### Gallery

{{ gallery({"1.jpg":"","2.jpg":"","3.jpg":""}) }}

### Image as a link

[![A clickable image](/media/2.jpg)](https://pushword.piedweb.com)

## Video

{{ video('https://www.youtube.com/watch?v=Nwyylc9GQuQ', '3.jpg', 'A video') }}

Add `true` as the fourth argument to open it in a popup instead:

{{ video('https://www.youtube.com/watch?v=Nwyylc9GQuQ', '/media/default/3.jpg', 'A video in a popup', true) }}

## Table

| Feature   | Status | Notes                        |
| --------- | ------ | ---------------------------- |
| Tables    | ✅     | Plain Markdown               |
| Galleries | ✅     | A Twig call, see above       |
| Files     | ✅     | Attach any media             |
| Twig      | ✅     | Available anywhere in a page |

## Code

```html
<div>
  {{ hello }}
  {% include view('my-component.html.twig') %}
</div>
```

<p data-attribute="this attribute keeps the paragraph as written">Raw HTML works too.</p>

## Listing other pages

Pull in other pages by tag — these are the demo pages, which all carry the `demo` tag:

{{ pages_list('demo', '9', 'publishedAt ↓', 'list') }}

The same list rendered as cards:

{{ pages_list('demo', '9', 'publishedAt ↓', 'card') }}

## Cards you write yourself

{{ card_list([{"page":"getting-started","title":"Getting started"},{"id":"custom-card","title":"A custom card","image":"1.jpg","link":"https://pushword.piedweb.com","description":"With **bold** and _italic_ text.","showInfoButton":true,"infoLinkLabel":"Discover","buttonLink":"https://pushword.piedweb.com","buttonLinkLabel":"Visit"}]) }}

## Attachments

{{ attaches('A JPEG file', '/media/2.jpg', '0' ) }}

## Separator

---

[Continue with the documentation ➜](https://pushword.piedweb.com)
