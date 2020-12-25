A few example for the editor possibilities. Best to observe this, it's in admin with split editor (live preview Side By Side).

## Links & Routes

- [Homepage]({{ homepage() }})
- [Current Page]({{ page(page) }})
- Get Url : {{ page('/what-you-want-in-same-app') }}
- Canonical with base: {{ page(page, true) }}
- Encrypted Link : {{ link('Pied Web', 'https://piedweb.com/') }}
- Self Encrypted Link: {{ link('Pied Web', page) }}
- contact@piedweb.com ou {{ mail('contact@piedweb.com') }}
- {{ tel('+33 00 00 00 00') }} ou directement +331 00 00 00 00 2
-

## Images et Galleries

## Simple Image

![Capture d’écran de 2020-12-12 15-33-27](/media/default/1.jpg)

## Galleries

{{ gallery(page) }}

{{ gallery(page, 1, 1) }}

{{ gallery(page,  2, 1) }}

## Video

Avoiding load Youtube cookies per default.

{{ video('https://www.youtube.com/watch?v=UeN6MAk4l5M', '/media/default/1.jpg') }}

or open an iframe

{{ video('https://www.youtube.com/watch?v=UeN6MAk4l5M', '/media/default/2.jpg', 'my video title', true) }}

## Advanced

- Get a theme component {{ view('/base.html.twig') }}

## Render Page List

### Page found via Kw

{{  list('welcome', 3) }}

{{  card_list('welcome', 3) }}

### Children Page (from parent for the case)

{{  children(page.parentPage, 3) }}

{{  card_children(page.parentPage, 3) }}
