---
title: 'Pushword - The Modern CMS for Developers (ready for AI era)'
h1: search.json
publishedAt: '2025-12-21 21:55'
name: Pushword
template: none
main_content_filters: twig
headers:
  - [Content-type, application/json]
---

{% set search_array = [] %}
{% for p in pages(apps.get().mainHost, [{'key': 'id','operator': '!=','value': page.id}]) %}
{% set search_array = search_array|merge([{'title': pw(p).h1, url: page(p), slug: p.slug, content: pw(p).mainContent.body|u.truncate(300)}]) %}
{% endfor %}
{{ search_array|json_encode()|raw }}