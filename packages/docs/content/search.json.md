---
template: none
h1: search.json
main_content_shortcode: twig
headers:
  - [Content-type, application/json]
---

{% set search_array = [] %}
{% for p in pages(apps.get().mainHost, [{'key': 'id','operator': '!=','value': page.id}]) %}
{% set search_array = search_array|merge([{'title': p.content.h1, url: page(p), slug: p.slug, content: p.content.body|u.truncate(300)}]) %}
{% endfor %}

{{ search_array|json_encode()|raw }}
