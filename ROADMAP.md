# Static rendering without full HTTP dispatch

Status: implemented for pages and page feeds.

The original proposal is partly obsolete. `PageScannerService` already calls
`PageController::showPage()` directly, and `PushwordRouteGenerator` reads the
current host from `RequestContext` rather than storing a constructor-time host.
The scanner's old 26.84-second figure does not describe its current path.

The static generator still needs an isolated render kernel: a cache refresh can
run inside a live request, and static template and routing flags must not leak
back into that request. For pages, pagers, and page feeds, `StaticPageRenderer`
now uses that kernel's controllers with a synthetic request on `RequestStack`.
It sets the matching route, site, page, pager, and locale context and prepares
the response before it is minified. It restores request-scoped state afterward.
Sitemaps, `robots.txt`, and other routes continue through `kernel->handle()`;
failed direct renders use that path for the normal HTTP error response.

An indicative SQLite benchmark of 1,000 generated pages measured 1.40 s in
direct page rendering versus 1.92 s in the previous HTTP handling. The complete
pipeline took 5.72 s versus 6.00 s, with a 148 MiB versus 154 MiB PHP peak.
The whole-pipeline difference is modest because database work, HTML minification,
and file output remain. The benchmark and a page/pager/feed HTML comparison test
are in `packages/static-generator/tests/`.
