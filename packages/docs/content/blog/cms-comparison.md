---
title: 'CMS Comparison - Pushword vs WordPress, Statamic and Sulu'
h1: 'Choosing the Right CMS: Pushword vs WordPress, Statamic & Sulu'
publishedAt: '2025-12-28 17:25'
parentPage: blog
template: /page/blog.html.twig
---

Pushword, WordPress, Statamic and Sulu solve different publishing problems. This comparison covers their editing workflows, hosting requirements, extensions and costs.

## The short answer

Start with who will publish and what your developers already know.

Three of these CMSs fit a specific brief. This page treats Pushword as the starting point
when none of those briefs applies:

- **WordPress** if non-technical people must run the site alone, you need a specific plugin (WooCommerce above all), or you want to hire from its large talent pool. Its tutorials and plugin directory can shorten launch time.
- **Sulu** if you have Symfony expertise and need workflows, granular permissions or audit trails.
- **Statamic** if your team is Laravel-native and visual editing matters most. Its Control Panel and Live Preview are its main advantages here, and the Pro licence buys commercial support. Price it per site before committing: multi-site is a Pro feature at $349 per site, with $99/year for updates after the first year if you renew.
- **Pushword** if none of those briefs applies. It has no licence fee, can serve several sites and locales from one install, includes SEO tooling, and can sync Markdown to your own git for developers and agents.

The sections below explain where those choices differ.

## Quick overview

| CMS           | Best For                                              | Philosophy                               | Community Size                   |
| ------------- | ----------------------------------------------------- | ---------------------------------------- | -------------------------------- |
| **Pushword**  | Developers wanting modern PHP + flat-file flexibility | Symfony bundles; SEO tooling built in   | Small, shipping since Dec 2020   |
| **WordPress** | Non-technical users, plugin ecosystem                 | Accessibility, large community           | Very large                        |
| **Statamic**  | Laravel developers, content-focused sites             | Flat-file default; paid Pro tier        | Medium (growing)                 |
| **Sulu**      | Enterprise, complex content structures                | Headless-first, enterprise features      | Small/Niche (enterprise-focused) |

## Technical stack

| Aspect              | Pushword                  | WordPress                  | Statamic                     | Sulu                   |
| ------------------- | ------------------------- | -------------------------- | ---------------------------- | ---------------------- |
| **PHP Version**     | 8.5                       | 8.3+ recommended; 7.4+ still runs | 8.2+                  | 8.2 to 8.5            |
| **Current version** | 1.0                       | 7.1 (August 2026)          | 6.x                          | 3.0.x                  |
| **Framework**       | Symfony 8                 | Custom                     | Laravel (Laravel-native)     | Symfony 6.4 / 7.x / 8  |
| **Database**        | SQLite / PostgreSQL / MariaDB | MySQL / MariaDB (required) | Flat-file / SQL (optional)   | Doctrine-supported DB  |
| **Templating**      | Twig                      | PHP / Blade (themes)       | Antlers / Blade              | Twig                   |
| **Frontend Stack**  | Tailwind, Alpine.js       | Gutenberg (React)          | Tailwind, Alpine.js          | Custom (flexible)      |
| **ORM**             | Doctrine 3                | wpdb (custom)              | Eloquent                     | Doctrine               |
| **Content Storage** | Database + Markdown sync via Flat | Relational DB      | Flat-file or DB              | Doctrine ORM + JSON    |

### Hosting and framework choices

**Pushword** requires PHP 8.5 and uses Symfony 8 with Doctrine 3. That permits property hooks and asymmetric visibility, but limits older hosting environments.

**WordPress** maintains broad backward compatibility. Its [hosting recommendations](https://wordpress.org/about/requirements/) are PHP 8.3+, MariaDB 10.11+ or MySQL 8.0+, and HTTPS; the same page says WordPress still runs on PHP 7.4+ and MySQL 5.5.5+, although those old versions are end-of-life and should not be a new site's target. [WordPress 7.1](https://wordpress.org/news/2026/08/mary-lou/) shipped in August 2026. Broad hosting compatibility remains a real advantage.

**Statamic** uses Laravel and has been Laravel-native for eight years. Teams can use a Laravel version that Statamic supports.

**Sulu** shares Symfony foundations with Pushword. [Sulu 3.0](https://sulu.io/blog/sulu-3-0-released) shipped in November 2025, moved content from PHPCR to Doctrine ORM with JSON fields, and supports Symfony 6.4, 7.x and 8.x on PHP 8.2 to 8.5. Its structured content and editorial tooling serve a different brief from Pushword's Markdown workflow.

## Content management features

| Feature                 | Pushword                                  | WordPress                  | Statamic             | Sulu                 |
| ----------------------- | ----------------------------------------- | -------------------------- | -------------------- | -------------------- |
| **Block Editor**        | Optional EditorJS extension              | Gutenberg (React-based)    | Bard + Peak          | Content blocks       |
| **Flat-file Support**   | Via Flat extension                       | Via plugins               | Native               | No native mode       |
| **Multi-site**          | Native                                    | Multisite network          | Pro addon            | Native (Webspaces)   |
| **i18n / Multilingual** | Native                                    | Plugins (WPML, Polylang)   | Native               | Native               |
| **Page Versioning**     | Extension (diff/restore/timeline)         | Revisions (basic)          | Revisions            | Native               |
| **Publication control** | Per-page publication hold + version diffs | Plugins (PublishPress)     | Workflow addon       | Native (workflows)   |
| **REST API**            | Token + OpenAPI (extension)               | REST core / GraphQL plugin | REST + GraphQL (Pro) | REST (GraphQL ready) |
| **Media Management**    | Auto-optimization (WebP)                  | Basic + plugins            | Asset manager        | Media bundles        |
| **Custom Fields**       | Custom properties                         | ACF / Meta Box             | Fieldsets            | Content types        |
| **Git Integration**     | Via Flat extension                       | Requires workarounds       | Full support         | Developer-dependent  |
| **AI Editing**          | Flat files or REST API                    | REST API or tooling        | Flat files or API    | API or custom tooling |
| **Bulk Operations**     | Flat files, CLI or API                    | WP-CLI, API or SQL         | CLI/scripts          | API, SQL or custom code |

### Editing workflows

**Pushword** edits Markdown in its admin and offers EditorJS blocks through an optional extension. AI coding assistants can work with the Markdown mirror when Flat is enabled; that is a file workflow, not a special capability of EditorJS. Multi-site and i18n capabilities are built in.

**WordPress** pioneered block editing with Gutenberg (2018+). While powerful, Gutenberg is React-based and can feel heavyweight in browser. The ecosystem provides extensive field and multilingual plugins, including both paid and free options. Multi-site mode is available, though its workflow differs from CMSs designed around multiple sites from the start.

**Statamic** offers Bard for writing, Live Preview across device sizes and Peak for drag-and-drop layouts. Flat-file storage lets teams review content changes in Git. Native multi-site requires a Pro licence ($349/site).

A per-page **publication hold** saves edits to the database while the public keeps seeing the previous static file until you release the hold and regenerate it. The Page "hold publication" switch and API `holdPublication: true` control it. The **version** extension adds side-by-side diffs, restore and a timeline. The **api** extension exposes a token-authenticated REST API for Page, Media and redirections, with OpenAPI documentation and revision guards for scripted edits.

**Sulu** enforces structured content modeling through content types and blocks. This prevents content anarchy but requires upfront planning. Block definitions ensure responsive rendering (developers control rendering complexity). Media management integrates tightly with content, supporting enterprise workflows with roles/permissions.

### Multilingual support

Pushword, Statamic and Sulu include multilingual support natively. WordPress uses plugins for this, such as:

- **WPML**: Commercial multilingual tooling
- **Polylang**: A free edition and paid upgrades

For projects with three or more languages, native support avoids a separate multilingual plugin.

## SEO and performance

| Feature                 | Pushword                        | WordPress                    | Statamic                            | Sulu                     |
| ----------------------- | ------------------------------- | ---------------------------- | ----------------------------------- | ------------------------ |
| **Static Generation**   | Static Generator extension      | Plugins (WP2Static, etc.)    | First-party SSG addon               | Custom implementation    |
| **SEO Tools**           | Built-in (meta, schema, robots) | Core basics; plugins for more | SEO Pro addon                     | SEO fields and templates |
| **Image Optimization**  | Auto WebP conversion            | Plugins (Smush, Imagify)     | Transform API                       | Manual                   |
| **HTTP Caching**        | Symfony HTTP Cache              | Plugins (WP Super Cache)     | Static caching                      | Symfony Cache            |
| **Dead Link Detection** | Page Scanner extension          | Plugins                      | Addon or custom check               | Custom check             |
| **Schema Markup**       | Native support                  | Plugins (Yoast, RankMath)    | SEO Pro addon                       | Developer-dependent      |

### SEO and caching in practice

**Pushword** was built by an SEO/GEO consultant, with content optimization baked into core. Meta management, schema generation, and clean URL structures require no third-party plugins. The Page Scanner extension audits internal links and detects broken links. The Static Generator extension can export HTML for static hosting; actual response and paint times depend on the site, host, network and browser.

**WordPress** includes basic SEO controls; teams often add plugins for more advanced workflows:

- **Yoast SEO**: Metadata and content analysis
- **Rank Math**: Metadata and structured-data tooling

Plugins can add maintenance work and, depending on their implementation, runtime overhead. Caching plugins are one common way to improve performance, but their need depends on the hosting and site.

**Statamic** offers static caching and an SEO Pro addon for metadata workflows. Compare deployed sites under the same hosting and content conditions before treating one CMS as inherently faster: flat-file storage alone does not determine page speed.

**Sulu** includes SEO fields and Symfony caching infrastructure. Projects can extend its SEO output in templates or bundles when their requirements go beyond the defaults.

## Editor experience

| Aspect                    | Pushword                         | WordPress                        | Statamic                 | Sulu                      |
| ------------------------- | -------------------------------- | -------------------------------- | ------------------------ | ------------------------- |
| **Admin UI Style**        | Clean, minimal                   | Familiar, feature-rich           | Modern, elegant          | Enterprise-focused        |
| **Editor Learning Curve** | Low to medium                    | Very low                         | Low                      | Medium to high            |
| **Content Editing**       | Markdown in Monaco + EditorJS blocks | Gutenberg (mature blocks)    | Bard (live preview)      | Content blocks            |
| **Media Upload**          | Drag & drop, auto-optimize       | Drag & drop                      | Drag & drop              | Structured upload         |
| **Preview / Draft**       | Yes                              | Yes                              | Live preview (real-time) | Preview mode              |
| **Collaborative Editing** | Publication hold + version diffs | Real-time (plugins)              | Basic                    | Advanced (workflows)      |
| **Mobile Admin**          | Responsive                       | Native apps (Jetpack)            | Responsive               | Responsive                |
| **Onboarding**            | Documented                       | Extensive tutorials              | Excellent                | Complex (requires setup)  |
| **Documentation**         | Growing                          | Extensive (tutorials everywhere) | Excellent                | Good (enterprise-focused) |
| **Support Community**     | GitHub, small community          | Forums, agencies, huge           | Laravel community        | Professional services     |

### Learning the admin

**Pushword** edits Markdown in Monaco and offers EditorJS blocks as an optional extension. Editors moving from WordPress may need time to learn that workflow. Documentation and community support are available on GitHub.

**WordPress** has more than 20 years of tutorials and extensive agency support. Gutenberg offers more than 100 block types, which gives editors choice but can take time to learn. Plugin quality varies.

**Statamic**'s Control Panel includes Live Preview across mobile, tablet and desktop sizes. Its documentation and the Laravel community give teams places to look for help.

**Sulu** targets enterprise users comfortable with structured workflows. The admin is powerful (workflows, versioning, advanced permissions) but assumes technical familiarity or dedicated training. Setup requires developer involvement before editorial team productivity.

## Developer experience

| Aspect                 | Pushword                   | WordPress                      | Statamic                   | Sulu                     |
| ---------------------- | -------------------------- | ------------------------------ | -------------------------- | ------------------------ |
| **Learning Curve**     | Medium (Symfony knowledge) | Low (hooks/filters)            | Medium (Laravel knowledge) | High (Symfony expertise) |
| **Extensibility**      | Symfony bundles            | Plugins / hooks                | Addons / Laravel services  | Symfony bundles          |
| **CLI Tools**          | Symfony Console            | WP-CLI                         | Artisan                    | Symfony Console          |
| **Testing**            | PHPUnit, PHPStan           | PHPUnit                        | Pest / PHPUnit             | PHPUnit                  |
| **API**                | REST API (token + OpenAPI) | REST (core) / GraphQL (plugin) | REST + GraphQL (Pro)       | REST (GraphQL ready)     |
| **Type Safety**        | Strong (strict types)     | Weak (legacy PHP)              | Good (Laravel types)       | Good (Symfony types)     |
| **Code Quality Tools** | PHPStan, Rector            | Basic                          | Pint, Larastan             | PHPStan, Rector          |
| **Framework Maturity** | Symfony 8                 | Custom (legacy)                | Laravel 11+ (mature)       | Symfony 6+ (stable)      |

### Working in each codebase

**Pushword** uses Symfony dependency injection and events. Its first-party extensions share a monorepo and run through CI together. PHPStan checks types, Rector supports refactoring, and PHP 8.5 provides property hooks and asymmetric visibility. A token-authenticated REST API covers Page, Media and redirections, with OpenAPI documentation and revision guards for scripted editing.

Trade-off: Requires Symfony knowledge. Developers from WordPress/custom PHP backgrounds face moderate learning curve.

**WordPress** has the lowest barrier to entry for beginners. Hook-based plugin system is simple but can lead to spaghetti code in complex projects. WP-CLI provides command-line tooling. PHPUnit testing is supported but not enforced. Code quality varies widely; legacy PHP patterns (procedural, global functions) are common. Ecosystem is vast but lacks standardization.

**Statamic** uses Laravel's Artisan CLI, Eloquent ORM, dependency injection and Pest/PHPUnit testing. Laravel developers already know those tools. Pro includes GraphQL for headless deployments.

**Sulu** requires deep Symfony knowledge. Services, events, subscribers follow enterprise patterns. Extremely flexible but steep learning curve. Best suited for teams already invested in Symfony.

### Git workflow integration

- **Pushword**: The Flat extension syncs database content with Markdown files that can be committed alongside code.
- **Statamic**: Flat-file content can be versioned alongside code in Git.
- **WordPress**: Content is stored in the database, outside Git unless exported or synchronized with additional tooling.
- **Sulu**: Database-backed = Git integration requires custom implementation.

Pushword with Flat and Statamic's flat-file mode both let teams review content changes in Git.

### AI-assisted editing and bulk operations

A key differentiator for technical teams: **direct file access**.

**Pushword** serves content from a database and can sync it to Markdown files with YAML frontmatter through the Flat extension. In a flat-file workflow, this means:

- **AI coding assistants** (Cursor, Claude Code, Copilot, Windsurf) can read, understand, and edit content directly alongside your code
- **Bulk operations** are trivial: `grep`, `sed`, `find/replace` across hundreds of pages in seconds
- **Content refactoring** (rename a term site-wide, update URLs, fix typos) requires no admin interface
- **Scripting** content changes is straightforward: any language that reads and writes files works

Example bulk operations:

```bash
# Find all pages mentioning "old-product"
grep -r "old-product" content/

# Replace across all markdown files
sed -i 's/old-product/new-product/g' content/**/*.md

# Update all meta descriptions
find content/ -name "*.md" -exec sed -i 's/metaDescription: old/metaDescription: new/' {} \;
```

**Statamic** shares similar flat-file advantages, though its YAML structure can be more complex for AI tools to parse reliably.

**WordPress/Sulu** store content in databases. AI tools can edit through their APIs, CLI or custom tooling, but those workflows need credentials and an understanding of each CMS's content model rather than direct Markdown edits.

With Flat enabled, an AI coding assistant can edit content files alongside code.

## Ecosystem and community

| Aspect                       | Pushword                | WordPress                     | Statamic                   | Sulu                     |
| ---------------------------- | ----------------------- | ----------------------------- | -------------------------- | ------------------------ |
| **Community Size**           | Small, since Dec 2020   | Very large                    | Medium                    | Smaller, enterprise-focused |
| **Extensions / Plugins**     | 18 bundles in 25 packages | 71,000+ plugins (.org)     | Addon marketplace          | Bundles and integrations |
| **Extension risk model**     | First-party concentration | Third-party maintenance     | Paid-addon trade-offs      | Bundle compatibility     |
| **Commercial Support**       | Consulting (small team) | Thousands of agencies         | Official support available | Professional services    |
| **License**                  | MIT (open-source)       | GPL v2 (open-source)          | Core free, Pro paid        | MIT (open-source)        |
| **Hosting Options**          | PHP 8.5 host           | Broad PHP hosting support     | Compatible Laravel host    | Compatible Symfony host  |
| **Job Market**               | Minimal                 | Huge (highest demand)         | Growing                    | Niche (enterprise)       |
| **Third-party Integrations** | Symfony ecosystem       | Native integrations + plugins | Laravel ecosystem          | Symfony ecosystem        |

### Three ways an extension ecosystem can hurt you

"Ecosystem size" is often reported as a single number, but it hides different maintenance risks:

- **Entropy (WordPress).** Plugins can affect the public site, the admin or both, and are often installed and updated from the dashboard. A site's chosen plugins need regular compatibility and security reviews; abandoned ones can be risky to keep and work to replace. The breadth of choice creates maintenance decisions as well as opportunities.
- **Selection (Statamic).** Its addon marketplace is smaller than WordPress's plugin directory. Check each addon for current compatibility, maintenance and licence terms rather than assuming marketplace presence guarantees support.
- **Concentration (Pushword).** Its 18 Symfony bundles ship and are tested together, so compatibility among first-party packages is checked in CI. The mirror-image cost: if something is missing, you may have to build it, and the smaller maintainer base is a real risk. Sulu has a separate third-party bundle ecosystem, so its risk profile is not identical.

None of these is the "right" model. Pick the failure mode you would rather manage.

### Why Pushword has a smaller audience

Pushword was built for its authors' client sites and has run them since December 2020. It now has 25 packages (18 Symfony bundles) and over 3,000 test methods. It had no launch campaign or growth team; features arrived when those sites needed them.

The smaller audience means fewer tutorials and fewer people to call for help. It does not mean the project is new: its first release was in 2020. The roadmap follows the sites its authors maintain, not adoption targets.

## Each CMS in detail

### Pushword

**Philosophy**: A Symfony-bundle CMS built by an SEO professional. Developers can use Flat for a Markdown workflow; editors can work in the admin.

**Architecture**: Page-oriented with Symfony DI, services, events. Flat-file content (YAML/markdown) version-controllable. Optional block editor via extensions. Headless-capable through API.

**Strengths**:

- Modern PHP 8.5 / Symfony 8 stack
- **Flat-file AI editing**: With the Flat extension, AI tools can edit Markdown files directly and sync them to the database
- **Bulk operations for power users**: grep, sed and find/replace across content files without opening the admin
- Native multi-site and i18n without plugins
- **Publication hold**: stage edits to a published page while the static site keeps serving the previous version until you release the hold and regenerate
- **Page & snippet versioning**: side-by-side diff, restore, and time-slider timeline
- **Token-authenticated REST API** (Page, Media, redirections) with OpenAPI docs and optimistic concurrency for scripted editing
- SEO-first design (built by SEO consultant)
- Static site generation built-in extension
- MIT license, fully open-source
- Git-friendly content workflows
- Full Symfony ecosystem available

**Limitations**:

- Smaller community than competitors
- Fewer ready-made themes/extensions
- Requires Symfony knowledge for deep customization
- Less beginner-friendly than WordPress
- PHP 8.5 requirement limits shared hosting options (requires modern infrastructure)
- Documentation still growing

**Ideal for**:

- Developers who value modern PHP practices
- Teams using flat-file Git workflows
- SEO-critical projects and content-driven sites
- Symfony-based stacks
- Documentation websites
- Performance-critical applications
- **AI-assisted editing workflows** (Cursor, Claude Code, Copilot work directly on content)
- **Technical teams needing bulk content operations** (mass updates via scripts/CLI)

**Not ideal for**:

- Non-technical users without developer support
- Projects requiring specific WordPress plugins
- Teams without PHP/Symfony expertise

### WordPress

**Philosophy**: Make publishing accessible to people without development skills.

**Architecture**: Monolithic PHP codebase with relational database (MySQL/MariaDB required). Post/Page paradigm with Custom Post Types. Plugin-based extension system.

**Strengths**:

- Massive ecosystem: [more than 71,000 free plugins](https://wordpress.org/plugins/), plus themes
- Extremely beginner-friendly with extensive tutorials
- Runs on any hosting (PHP 7.4+, though 8.3+ recommended)
- Huge job market and agency support worldwide
- Extensive documentation, tutorials, and community knowledge
- Proven on a very large range of sites
- Gutenberg block editor is mature and powerful
- REST API core feature (GraphQL via plugins)

**Limitations**:

- Poorly chosen plugins can add runtime overhead
- Popular target for attacks; plugin and theme maintenance matters
- Plugin quality varies widely, no quality guarantee
- Legacy codebase (20+ years) uses older PHP patterns
- Multilingual publishing usually requires a plugin; free and paid options differ
- Updates can break plugin compatibility
- Premium plugins, themes and specialist maintenance can increase total cost
- Database-bound = Git workflow challenges

**Ideal for**:

- Non-technical users with developer support
- Blogs and marketing websites
- Projects requiring specific WordPress plugins
- Teams needing immediate freelancer/agency availability
- Shared hosting budget constraints
- Rapid deployment (hours vs. days)

**Not ideal for**:

- Performance-critical applications
- Complex multilingual sites (without paid plugins)
- Teams valuing clean architecture
- Git-based content workflows
- Static site generation needs

### Statamic

**Philosophy**: A Laravel CMS with flat-file content by default and a paid Pro tier for multi-site and collaboration.

**Architecture**: Flat-file CMS (YAML/markdown) by default with optional database (MySQL/PostgreSQL). Laravel framework with Eloquent ORM. Headless-capable. Extensible via Laravel ecosystem.

**Core Pricing**:

- **Solo (Free)**: Single admin, development use, basic features
- **Pro ($349/site, including one year of updates; $99/year for updates after that)**: Multi-site, collaborators, extended features, REST + GraphQL. See [current pricing](https://statamic.com/pricing) for volume and platform plans.

**Strengths**:

- Native flat-file CMS with optional database upgrade path
- Excellent Bard editor with live preview (devices, responsive)
- Laravel ecosystem access (Eloquent, Artisan, testing tools)
- Beautiful, modern Control Panel
- Strong documentation and passionate community
- Headless-capable with built-in API
- Free core for simple projects
- Option to move content from flat files to a database without changing templates

**Limitations**:

- Pro features require a paid licence, with optional paid update renewals after the first year
- Multi-site requires Pro tier
- Smaller addon ecosystem than WordPress
- GraphQL only in Pro tier
- Laravel knowledge expected for customization
- Community smaller than WordPress

**Ideal for**:

- Laravel developers
- Content-focused marketing sites
- Agencies building multiple projects (curated addons)
- Performance-critical applications
- Flat-file workflows preferred
- Teams valuing modern architecture
- Projects with 3+ languages (native i18n)

**Not ideal for**:

- Budget-constrained projects (Pro license required at scale)
- Non-technical users without developer support
- Projects requiring specific WordPress plugins
- Teams without Laravel experience

#### Choosing between Pushword and Statamic

Pushword and Statamic are the closest pair here. Both can put content changes in git and support i18n and static output. Neither CMS can be replaced by copying a directory alone.

They are not the same architecture, though, and the difference can show up on invoices. **Multi-site is core in Pushword and a paid Pro feature in Statamic.** One Pushword installation can serve multiple hosts and locales; Statamic licenses Pro per site. Pushword's Flat extension synchronizes Markdown and the database in both directions, so admin edits and file edits can be reconciled. Neither side is unconditionally authoritative in every conflict.

Their editing workflows, licence costs and agent interfaces differ.

**The editors suit different habits.** Statamic offers Bard and Live Preview across device widths. For someone who wants to watch a page assemble while typing, that may be the more comfortable tool.

Pushword edits Markdown in Monaco. Writers comfortable with Markdown may prefer its keyboard-driven workflow; [EditorJS blocks](/extension/admin-block-editor) are available for pages that need them. With Flat enabled, bulk edits can also run outside the admin through a `sed` command, a branch or an agent.

So: if a responsive live preview decides it, buy Statamic. If Markdown and bulk file edits suit your team, try Pushword's workflow.

**Pushword can win fleet economics.** Statamic Pro costs $349 per site, including the first year of updates, and $99 per site per year for updates after that if you renew. For fifteen sites, that is $5,235 initially and up to $1,485 per renewal year at [today's prices](https://statamic.com/pricing). Pushword has no licence fee, but hosting, development and maintenance still cost money. Price the whole roster and the support each team needs.

**Pushword offers a specific agent surface.** [Agent-optimized output](/agent-output) on supported `pw:*` commands, an [OpenAPI-described REST API](/extension/api), `pw:schema:dump` handing an agent the content model, and instructions shipped to the agent working on your site. Whether that matters depends entirely on whether agents are in your content loop.

Statamic offers visual editing and commercial support. Pushword offers built-in multi-site, optional Markdown sync and agent-oriented interfaces without a per-site licence. Try both editors with a real page, then price the support your team needs.

### Sulu CMS

**Philosophy**: A Symfony CMS for structured content and editorial governance, with headless delivery options.

**Architecture**: Symfony CMS with structured content stored as JSON in relational tables through Doctrine ORM since Sulu 3.0. Multi-site via Webspaces (site + language + domain combinations). See [Sulu's 3.0 storage explanation](https://sulu.io/blog/sulu-3-0-released).

**Pricing**: Free and open-source (MIT license). Professional services and support available.

**Strengths**:

- Enterprise features: workflows, advanced permissions, audit trails
- Powerful content type system (structured content-first)
- Native multi-site and multilingual (Webspaces concept)
- Headless/API-first architecture
- Media management and versioning
- Uses Symfony services and conventions
- Suitable for complex content structures
- MIT license, fully open-source
- Professional services available
- Type-safe (Symfony 6+)

**Limitations**:

- Steep learning curve (Symfony expertise required)
- Complex setup process (requires developer time)
- Overkill for simple sites
- Smaller community and less public documentation
- Admin UI less polished than Statamic
- Content modeling requires planning before launch
- Less suitable for rapid prototyping

**Ideal for**:

- Enterprise projects with complex requirements
- Headless applications (multi-channel delivery)
- Teams with deep Symfony expertise
- Complex multilingual platforms
- Projects needing advanced permissions/workflows
- Large organizations with dedicated dev teams
- Content-rich platforms (news, e-commerce, intranets)

**Not ideal for**:

- Rapid prototyping or MVP development
- Non-technical editorial teams
- Small projects with limited budget
- Teams without Symfony experience
- Projects requiring quick time-to-market

## Comparison by use case

### Blog or documentation site

| Criterion         | Winner            | Notes                    |
| ----------------- | ----------------- | ------------------------ |
| Setup speed       | WordPress         | Hours vs. days           |
| SEO readiness     | Pushword          | Built-in, no plugins     |
| Performance       | Pushword/Statamic | Static generation option |
| Visual editing    | Statamic          | Live Preview             |
| Editing throughput | Pushword         | Markdown, keyboard, bulk-editable |
| Cost              | Pushword/Sulu     | Zero licensing, no per-site fee |

**Recommendation**:

- **Quick launch with non-tech editor**: WordPress (accept plugin complexity)
- **Clean, modern stack**: Pushword or Statamic
- **Performance-critical (high traffic)**: Pushword (static generation)

### Small-business marketing site

| Criterion              | Winner            | Notes                         |
| ---------------------- | ----------------- | ----------------------------- |
| Agency availability    | WordPress         | Easiest to find freelancers   |
| Multi-language support | Pushword/Statamic | Native, no plugins            |
| Total cost (5 years)   | Pushword          | No plugin licensing           |
| Maintenance burden     | Pushword          | Fewer plugins = fewer updates |
| Design flexibility     | All tied          | All support custom themes     |

**Recommendation**:

- **Budget, no development team**: WordPress (hire agency)
- **In-house developers**: Pushword or Statamic
- **Multiple languages/sites**: Pushword (multi-site and i18n built in)

### E-commerce platform

| Criterion               | Winner                  | Notes                            |
| ----------------------- | ----------------------- | -------------------------------- |
| Plugin ecosystem        | WordPress + WooCommerce | Extensive commerce integrations  |
| Headless support        | Sulu / Statamic         | API-first better for mobile apps |
| Structured product data | Sulu                    | Content types/modeling           |
| Multilingual products   | Pushword / Sulu         | Native i18n                      |
| Performance at scale    | Measure per deployment | Hosting, cache and implementation matter |

**Recommendation**:

- **Rapid WooCommerce site**: WordPress + WooCommerce
- **Custom e-commerce platform**: Sulu or Statamic (headless + API)
- **Omnichannel (web + mobile + voice)**: Sulu

### Enterprise intranet or portal

| Criterion             | Winner          | Notes                           |
| --------------------- | --------------- | ------------------------------- |
| Permissions/workflows | Sulu            | Enterprise features built-in    |
| Customization         | Sulu            | Deeply configurable             |
| Editorial governance | Sulu            | Granular permissions and workflows |
| Support/SLAs          | Sulu            | Professional services available |
| Licence cost          | Pushword / Sulu | No licence fee; support costs differ |

**Recommendation**: **Sulu** (professional services justify investment)

## When to choose each CMS

### Choose Pushword when:

- Modern PHP (8.5) and Symfony 8 architecture appeal to you
- Flat-file / Git-based workflows matter for your team
- **AI-assisted editing is important**: Your team uses Cursor, Claude Code, Copilot or similar tools to edit content files directly
- **Bulk content operations are needed**: Mass find/replace, scripted updates, CLI-based content management
- SEO is a primary concern
- Multi-site or i18n required without plugins
- Controlled publishing is needed (per-page publication hold plus version diffs)
- Headless or scripted editing via a token REST API (CI, external tooling)
- Static site generation needed
- Lightweight, maintainable code is priority
- Team has Symfony experience or wants to learn
- Long-term maintenance with minimal plugin overhead matters

### Choose WordPress when:

- Non-technical users will manage content independently
- Specific plugin ecosystem requirement exists (e.g., WooCommerce for e-commerce)
- Shared hosting with PHP 7.4+ is a constraint
- Budget requires hiring non-specialized freelancers
- Project is standard blog, portfolio, or brochure site
- Speed-to-launch is critical (hours, not days)
- Massive community resources and tutorials needed
- Team lacks PHP development expertise

### Choose Statamic when:

- Team is invested in Laravel ecosystem
- Flat-file with commercial support appeals to you
- Content editing experience is paramount
- Budget available for a Pro licence ($349/site, with optional $99/year update renewals after year one)
- Project is content-focused marketing site
- Static caching and Laravel-based development appeal to you
- Multi-language support needed natively
- Beautiful admin interface is priority
- Live preview and responsive editing matter

### Choose Sulu when:

- Enterprise requirements (workflows, permissions, audit trails)
- Complex, structured content models needed
- Headless / API-first is the priority
- Multi-site, multi-language platforms required
- Team has deep Symfony expertise
- Professional services and support needed
- Large-scale projects with dedicated dev teams
- Content governance and compliance important
- Complex editorial permissions and workflows are required

## Cost of ownership

| CMS | Licence and service costs to budget | Work to budget |
| --- | --- | --- |
| **Pushword** | No licence fee; hosting and support are your responsibility | Symfony development, operations and upgrades |
| **WordPress** | Core is free; premium themes, plugins and managed hosting vary by site | Plugin selection, updates, security and custom development |
| **Statamic** | [Pro is $349 per site](https://statamic.com/pricing), including one year of updates; later updates cost $99 per site per year if renewed | Laravel development, hosting and any paid addons |
| **Sulu** | Open-source core; professional services are optional | Content modelling, Symfony development and operations |

Compare a real feature list and five-year maintenance plan before treating a licence price as total cost.

## Migration considerations

### From WordPress to Pushword/Statamic

- **Complexity**: Depends on custom post types, blocks, media and plugin data
- **Content export**: WordPress export or API data must be mapped to the target content model
- **Potential benefit**: A Git-based content workflow and less plugin maintenance; measure performance on the migrated site

### From Pushword/Statamic to WordPress

- **Complexity**: Depends on content relationships and Pushword/Statamic-specific features
- **Benefit**: Larger ecosystem, easier freelancer hiring
- **Cost**: Depends on the plugins, themes, hosting and support selected

### From WordPress to Sulu

- **Complexity**: High (structured content modeling required)
- **Content rearchitecture**: Significant planning needed
- **Time estimate**: Scope after auditing content models, integrations and editorial workflows
- **Benefit**: Enterprise features, permissions, scalability

### What it costs to be wrong

Adopting a smaller CMS should include an exit-cost assessment, alongside everyday use and support needs:

| | Content lives as | Getting it out |
| --- | --- | --- |
| **Pushword** | Your database, with Markdown + YAML mirrored to git when Flat is enabled | Export content; rework templates, media and integrations for the target |
| **Statamic** | Flat-file content by default, or a database | Export content; rework templates and addons for the target |
| **WordPress** | Rows in MySQL, plus per-plugin tables and serialised meta | Export and convert; plugin data often needs bespoke work |
| **Sulu** | Doctrine ORM entities with JSON content fields | Export and re-model |

For Pushword specifically: templates are Twig, the app uses Symfony, media are ordinary files on disk, and the monorepo is MIT-licensed and public. If the project stopped tomorrow, a PHP team could maintain it, but would inherit responsibility for security, upgrades and Pushword-specific behaviour. That is a meaningful counterweight to its smaller community, not a guarantee of a cheap exit.

The reasonable way to test any of this is an afternoon with a real page:

```shell
composer create-project pushword/new pushword "^1.0"
```

## Questions to settle before choosing

Before choosing, evaluate your project across these dimensions:

1. **Team technical capability**: Do they have Symfony/Laravel expertise? PHP fundamentals?
2. **Content complexity**: Simple posts or structured content with relationships?
3. **Scale**: Single site or multi-site/multi-language platform?
4. **Performance requirements**: Traffic volumes, response time targets?
5. **Editing experience**: How many non-technical editors? What's their comfort level?
6. **Budget**: Total cost of ownership vs. vendor licensing vs. agency support?
7. **Long-term vision**: Rapid prototype vs. 5+ year platform?
8. **Team size**: Solo developer vs. distributed team?
9. **Specific features**: E-commerce, headless, static generation, multilingual?

## Resources

- **Pushword**: [pushword.piedweb.com](https://pushword.piedweb.com) · [getting help and paid support](/pro)
- **WordPress**: [wordpress.org](https://wordpress.org)
- **Statamic**: [statamic.com](https://statamic.com)
- **Sulu**: [sulu.io](https://sulu.io)

> [!note] About this comparison
>
> This page was written by the Pushword author (and Claude). Product and pricing claims were checked against official sources in September 2026. The qualitative comparisons are the author's assessment, not benchmark results. The author builds Pushword and favours modern PHP architecture.
>
> Found an error? [Let us know on GitHub](https://github.com/Pushword/Pushword/issues). We welcome corrections and improvements to this analysis.

> [!warning] Version
>
> Last updated: September 2026. Reflects WordPress 7.1, Statamic's published Pro pricing, Sulu 3.0 and Pushword 1.0. Features and pricing change often; corrections are welcome via GitHub issues. See also our [Astro vs Pushword](/blog/astro-vs-pushword) comparison.
