An example of an ordinary page — the shape most of your site will take.

Write to [hello@example.com]({{ mail('hello@example.com') }}), or call
{{ tel('+33 00 00 00 00') }}.

## Where we are

Replace this with your address, your opening hours, or whatever your visitors actually
need. A page is Markdown: headings, lists, links, images, tables.

- Somewhere Street, 1
- 00000 Somewhere
- Monday to Friday, 9am – 6pm

## A contact form

Pushword can render a real form here, with spam protection and the messages stored in the
admin. It comes with the `conversation` extension:

```bash
composer req pushword/conversation
```

Then drop the form into any page:

```twig
{{ conversation('contact') }}
```

See [the extension docs](https://pushword.piedweb.com/extension/conversation).
