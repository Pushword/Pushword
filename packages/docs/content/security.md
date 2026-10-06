---
title: 'Security model and production hardening'
h1: Security
publishedAt: '2026-09-04 20:00'
parentPage: installation
toc: true
---

Pushword gives editors broad control over rendering, so the trust boundary is: an
account carrying `ROLE_EDITOR` is a trusted code author, not an untrusted contributor.

## Trusted editorial content

Editorial Markdown accepts raw HTML, and the editorial Twig filter is not sandboxed —
sites rely on it for galleries, includes, page lists, forms, encrypted email addresses
and more. An editor can therefore create persistent XSS and call exposed Twig functions
with the PHP process's authority. Grant `ROLE_EDITOR` only to people who could otherwise
edit templates or deploy code, and never put untrusted user-generated content in
editorial fields. Uploaded SVG files are served with a sandboxed Content Security Policy
and MIME sniffing disabled.

## Accounts and sessions

- An unattended production install creates a random temporary super-administrator
  password, prints it once, and requires its replacement before any administration
  role becomes usable. The documented `admin@example.tld` / `p@ssword` credential is
  retained only for development installs.
- Remember-me is opt-in, lasts up to one year (an accepted trade-off), and is
  invalidated by revoking the account or changing the application secret.
- Session-authenticated responses use `Cache-Control: private, no-store`, and logout asks
  the browser to clear its HTTP cache. Routes declared `stateless` are skipped — reading the
  user there would touch the session — so they keep the headers their controller set.
- Logout accepts GET. The resulting logout CSRF can only end the current session —
  an accepted trade-off.
- [Impersonation](/authentication#impersonation) is also switched with GET, so a link
  followed from a third-party page can switch a super administrator to another account.
  It grants nothing the super administrator did not already hold, but changes made
  before noticing the banner are recorded under the other account's name.
- Nothing prevents deleting or demoting the last super administrator. Recover with
  `php bin/console pw:user:create`.

## API tokens

API bearer tokens are stored in clear text, with no scope, expiry or last-use record.
Treat the database and every backup as credential material, send tokens only over TLS,
and rotate a token after suspected exposure. Flat lock, unlock and status calls also
require the token owner to hold `ROLE_EDITOR`.

## Production runtime

Run production with `APP_ENV=prod` and `APP_DEBUG=0`. The Symfony profiler and the
development `phpinfo` page exist in development only; never expose a development
instance on a production network.

The Docker configuration sets `expose_php=Off` (no `X-Powered-By` header). Elsewhere,
set it in the web SAPI's PHP configuration — a reverse proxy alone does not cover direct
access to the application server.

External link checks reject loopback, private, link-local and reserved networks,
including after DNS resolution, and retain normal TLS certificate and hostname
verification. Keep TLS verification enabled in any replacement HTTP client.

## Automated checks

The repository security workflow runs CodeQL SAST for JavaScript, Semgrep SAST for PHP,
Composer and JavaScript dependency audits, a repository secret scan, and an SPDX SBOM
build. The Docker workflow also scans the production image for high and critical
vulnerabilities on relevant changes and every week.

The JavaScript audit blocks high and critical findings, with one exception:
[GHSA-vfj7-8cjw-p6xm](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm) through
`vite-plugin-static-copy` and `vite-plugin-symfony`, build tools that only receive
repository-controlled file patterns (no patched `braces` release exists). It stays
visible in the output; other paths, advisories and critical findings still fail. Remove
the exception from `.github/scripts/js-audit.jq` once upstream ships a fix.
