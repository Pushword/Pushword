---
title: 'Security model and production hardening'
h1: Security
publishedAt: '2026-09-04 20:00'
parentPage: installation
toc: true
---

Pushword gives editors unusually broad control over rendering. Production security
therefore starts with a clear trust boundary: an account carrying `ROLE_EDITOR` is a
trusted code author, not an untrusted content contributor.

## Trusted editorial content

Editorial Markdown deliberately accepts raw HTML, and the editorial Twig filter is
deliberately not sandboxed. Real Pushword sites use that Twig surface extensively for
galleries, includes, page lists, attachments, prices, forms, reviews, encrypted email
addresses and telephone links. Removing functions or includes would break normal site
content.

This flexibility also means an editor can create persistent XSS and can invoke exposed
Twig functions with the authority of the PHP process. Grant `ROLE_EDITOR` only to
people who could otherwise edit the site's templates or deploy code. Do not use the
editorial fields for untrusted user-generated content. Public uploads of SVG files are
served with a sandboxed Content Security Policy and MIME sniffing disabled.

## Accounts and sessions

- An unattended production install creates a random temporary super-administrator
  password, prints it once, and requires its replacement before any administration
  role becomes usable. The documented `admin@example.tld` / `p@ssword` credential is
  retained only for development installs.
- Remember-me is opt-in. When selected, its maximum lifetime remains one year; this is
  an accepted convenience trade-off. Revoking the account or changing the application
  secret invalidates it.
- Authenticated responses use `Cache-Control: private, no-store`, and logout asks the
  browser to clear its HTTP cache.
- Logout remains callable with GET. This permits logout CSRF, whose only effect is to
  end the current session; Pushword accepts that availability trade-off.
- There is no last-super-administrator deletion or demotion guard. This avoids special
  persistence rules around administrators. Recover locally with
  `php bin/console pw:user:create` if every administrator has been removed.

## API tokens

API bearer tokens are stored in clear text, have no scope or expiry, and do not record
their last use. This is an accepted compatibility constraint of the current API.
Treat the database and every backup as credential material, transmit tokens only over
TLS, restrict backup access, and rotate a token after suspected exposure. Flat lock,
unlock and status calls additionally require the token owner to reach `ROLE_EDITOR`.

## Production runtime

Run production with `APP_ENV=prod` and `APP_DEBUG=0`. The Symfony profiler and the
development `phpinfo` surface are intentionally available in development and must
never be exposed on a production network. Their presence in a development instance is
an identified and accepted risk.

The Docker configuration sets `expose_php=Off`, which removes PHP's `X-Powered-By`
header. On a non-Docker deployment, add the same directive to the PHP configuration
used by the web SAPI and restart PHP. A reverse proxy may also remove the header, but
disabling it in PHP covers direct access to the application server.

External link checks reject loopback, private, link-local and reserved networks,
including after DNS resolution, and retain normal TLS certificate and hostname
verification. Keep TLS verification enabled in any replacement HTTP client.

## Automated checks

The repository security workflow runs CodeQL SAST for JavaScript, Semgrep SAST for PHP,
Composer and JavaScript dependency audits, a repository secret scan, and an SPDX SBOM
build. The Docker workflow also scans the production image for high and critical
vulnerabilities on relevant changes and every week.
