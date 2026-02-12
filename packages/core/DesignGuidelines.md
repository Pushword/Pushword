# Tailwind CSS v4 + Alpine.js — UI Design System Guidelines

> A set of opinionated rules for producing clean, consistent, and accessible user interfaces.
> These guidelines should be followed whenever generating, reviewing, or refining UI code.
> **Default personality: Neutral.** Override only when the user explicitly requests a different tone.

---

## 1. WORKFLOW

### 1.1 Feature first

- Start by building the core feature UI, not the page shell (navbar, sidebar, footer).
- Layout and navigation patterns should emerge after several features exist.

### 1.2 Grayscale first

- Build the initial version using only `text-gray-*` and `bg-gray-*`.
- If hierarchy is clear without color, it will only get better with color.
- Add `shadow-*`, `rounded-*`, and decorative classes only after structure is solid.

### 1.3 Incremental delivery

- Build the simplest useful version of each feature first.
- Handle edge cases and polish in subsequent iterations on the real UI.

### 1.4 Respect the system

- **Always prefer Tailwind's default scale values.** Avoid arbitrary values (`p-[13px]`, `text-[17px]`) unless no scale value works.
- If the scale doesn't fit your project, extend it via CSS with the `@theme` directive — don't scatter one-off values in markup.
- Constraints speed up decisions and create visual consistency.

---

## 2. TAILWIND v4 CONFIGURATION

### 2.1 CSS-first — no more `tailwind.config.js`

In Tailwind v4, all customization lives in your CSS file via the `@theme` directive. There is no JavaScript config file.

```css
/* app.css */
@import 'tailwindcss';

@theme {
  --font-sans: 'Inter', sans-serif;
  --font-display: 'Cal Sans', sans-serif;
  --color-brand-50: oklch(0.97 0.02 250);
  --color-brand-500: oklch(0.55 0.18 250);
  --color-brand-600: oklch(0.48 0.18 250);
  --color-brand-900: oklch(0.25 0.1 250);
  --spacing-18: 4.5rem;
  --radius-DEFAULT: 0.5rem;
  --radius-lg: 0.75rem;
  --shadow-soft: 0 1px 3px 0 oklch(0 0 0 / 0.04), 0 1px 2px -1px oklch(0 0 0 / 0.04);
}
```

This generates utility classes like `bg-brand-500`, `font-display`, `rounded-lg`, `shadow-soft`.

### 2.2 Key `@theme` namespaces

| Namespace        | Generates                              | Example                                   |
| ---------------- | -------------------------------------- | ----------------------------------------- |
| `--color-*`      | `bg-*`, `text-*`, `border-*`, `ring-*` | `--color-brand-500: #4f46e5;`             |
| `--font-*`       | `font-*`                               | `--font-display: 'Cal Sans', sans-serif;` |
| `--spacing-*`    | `p-*`, `m-*`, `gap-*`, `w-*`, `h-*`    | `--spacing-18: 4.5rem;`                   |
| `--radius-*`     | `rounded-*`                            | `--radius-xl: 1rem;`                      |
| `--shadow-*`     | `shadow-*`                             | `--shadow-soft: 0 1px 3px ...;`           |
| `--breakpoint-*` | `sm:`, `md:`, `lg:`, etc.              | `--breakpoint-xs: 30rem;`                 |
| `--container-*`  | `max-w-*`                              | `--container-narrow: 40rem;`              |

### 2.3 Overriding entire namespaces

Use `--color-*: initial;` inside `@theme` to strip all defaults, then define only what you need.

### 2.4 Theming with CSS variables

For multi-theme setups (dark mode, brand variants), define tokens in `@theme` referencing CSS custom properties, then override them per context in `@layer base`:

```css
@theme {
  --color-surface: var(--surface);
  --color-on-surface: var(--on-surface);
  --color-primary: var(--primary);
}

@layer base {
  :root {
    --surface: oklch(0.99 0 0);
    --on-surface: oklch(0.15 0 0);
    --primary: oklch(0.55 0.18 250);
  }
  .dark {
    --surface: oklch(0.15 0.01 250);
    --on-surface: oklch(0.95 0 0);
    --primary: oklch(0.7 0.15 250);
  }
}
```

Then use `bg-surface`, `text-on-surface`, `bg-primary` in markup — values swap automatically with the theme.

---

## 3. VISUAL HIERARCHY

### 3.1 Three tiers of content

Every screen should have a clear primary -> secondary -> tertiary structure.

**Text color tiers:**

| Tier      | Role                   | Tailwind        |
| --------- | ---------------------- | --------------- |
| Primary   | Headlines, key data    | `text-gray-900` |
| Secondary | Descriptions, metadata | `text-gray-500` |
| Tertiary  | Captions, fine print   | `text-gray-400` |

**Font weight tiers:**

| Tier       | Tailwind                                   |
| ---------- | ------------------------------------------ |
| Normal     | `font-normal` (400) or `font-medium` (500) |
| Emphasized | `font-semibold` (600) or `font-bold` (700) |

- **Never use `font-light` or `font-thin`** for interface text. To de-emphasize, use a lighter color or smaller size instead.

### 3.2 De-emphasize the surroundings

- If the main element doesn't stand out, don't keep adding emphasis to it — reduce the prominence of everything else.
- Soften inactive items (`text-gray-400`) rather than making the active one louder.

### 3.3 Let data speak for itself

- Skip labels when the format is self-evident (emails, phone numbers, prices).
- Merge label and value into a natural phrase: `"12 left in stock"` not `"In stock: 12"`.
- When labels are required, treat them as supporting content (small, uppercase, `text-gray-500`).

### 3.4 Style for the eye, not the tag

- An `<h1>` doesn't have to be the biggest thing on screen.
- Section titles often function as labels — keep them small (`text-xs font-semibold uppercase tracking-wide text-gray-500`).

### 3.5 Balance weight and contrast

- Visually heavy elements (icons, bold text) need softer colors to avoid dominating.
- Visually light elements (thin borders) may need more thickness instead of darker color.

### 3.6 Button hierarchy

Design buttons in three tiers of importance:
- **Primary:** solid background, high contrast (`bg-indigo-600 text-white`)
- **Secondary:** outline or muted (`border border-gray-300 text-gray-700`)
- **Tertiary:** link-style (`text-gray-500 hover:underline`)

Destructive actions should not automatically be `bg-red-600`. Prefer secondary styling + a confirmation step where the destructive action becomes the primary button.

---

## 4. LAYOUT & SPACING

### 4.1 Default to generous whitespace

- Start with more space than you think (`p-8`, `gap-8`, `space-y-6`), then tighten.
- Compact layouts (`p-2`, `gap-2`) should be a conscious decision (dashboards, data tables).

### 4.2 Spacing scale

Use Tailwind's built-in scale. The gaps between values grow as you go up — this is intentional.

| Class     | px      | Typical use             |
| --------- | ------- | ----------------------- |
| `1`       | 4px     | Tight inline gaps       |
| `1.5`     | 6px     | Label-to-input spacing  |
| `2`       | 8px     | Compact padding         |
| `3`       | 12px    | Button vertical padding |
| `4`       | 16px    | Standard padding/gaps   |
| `6`       | 24px    | Within-section spacing  |
| `8`       | 32px    | Between groups          |
| `12`      | 48px    | Major section gaps      |
| `16`      | 64px    | Page section separation |
| `20`-`24` | 80-96px | Hero-level spacing      |

If two spacing values look nearly identical on screen, you're choosing between options that are too close. Jump a step.

### 4.3 Constrain content width

| Class       | px     | Use for                 |
| ----------- | ------ | ----------------------- |
| `max-w-xs`  | 320px  | Small cards, modals     |
| `max-w-sm`  | 384px  | Forms, login cards      |
| `max-w-lg`  | 512px  | Medium content          |
| `max-w-2xl` | 672px  | Articles, readable text |
| `max-w-4xl` | 896px  | Wide content areas      |
| `max-w-7xl` | 1280px | Page containers         |

Custom container widths in v4: `--container-narrow: 40rem;` in `@theme` generates `max-w-narrow`.

If content only needs 400px, don't stretch it to fill 1200px.

### 4.4 Fixed widths where appropriate

Sidebars, avatars, icons, and form fields usually need fixed sizes (`w-64 shrink-0`). Let the main content area flex (`flex-1 min-w-0`).

### 4.5 Size independently per breakpoint

Don't assume proportional relationships hold across screen sizes. Padding should grow disproportionately at larger sizes.

### 4.6 Eliminate ambiguous spacing

The space within a group must be noticeably smaller than the space between groups (e.g. `space-y-1.5` within, `space-y-6` between).

---

## 5. TYPOGRAPHY

### 5.1 Type scale

Stick to Tailwind's scale. No arbitrary pixel values.

| Class       | Size  | Typical use            |
| ----------- | ----- | ---------------------- |
| `text-xs`   | 12px  | Badges, fine print     |
| `text-sm`   | 14px  | Labels, metadata       |
| `text-base` | 16px  | Body text              |
| `text-lg`   | 18px  | Lead text, card titles |
| `text-xl`   | 20px  | Section titles         |
| `text-2xl`  | 24px  | Page headings          |
| `text-3xl`  | 30px  | Hero subtitles         |
| `text-4xl`  | 36px  | Hero titles            |
| `text-5xl`+ | 48px+ | Display text           |

### 5.2 Font selection

Default to `font-sans` (system stack). Custom fonts via `@theme`: `--font-sans`, `--font-display`. Choose typefaces with 5+ weights and good UI readability.

### 5.3 Readable line length

Constrain paragraphs with `max-w-prose` (~65ch) even inside wider containers.

### 5.4 Baseline alignment for mixed sizes

Use `items-baseline` — not `items-center` — when font sizes differ on the same line.

### 5.5 Line-height by context

| Text type                    | Class                             | Ratio   |
| ---------------------------- | --------------------------------- | ------- |
| Body (`text-sm`-`text-base`) | `leading-relaxed`                 | ~1.625  |
| Large (`text-xl`+)           | `leading-snug`                    | ~1.375  |
| Headlines (`text-3xl`+)      | `leading-tight` or `leading-none` | ~1-1.25 |

Smaller text needs more line-height. Larger text needs less.

### 5.6 Link styling

- Navigation links: weight or color only (`font-medium text-gray-900`), no underline
- Ancillary links: subtle, visible on hover (`text-gray-500 hover:underline`)
- Inline prose links: bright color (reserve for this context only)

### 5.7 Alignment rules

- Default: `text-left`.
- Center only for short blocks (2-3 lines max).
- Numbers in tables: `text-right tabular-nums`.
- Justified: only with `hyphens-auto`.

### 5.8 Letter-spacing

| Class             | When to use                  |
| ----------------- | ---------------------------- |
| `tracking-tight`  | Headlines (`text-3xl`+)      |
| `tracking-normal` | Body (default, leave alone)  |
| `tracking-wide`   | Uppercase text, small labels |

---

## 6. COLOR

### 6.1 Shade roles

| Shade | Purpose                                 |
| ----- | --------------------------------------- |
| `50`  | Tinted backgrounds (alerts, highlights) |
| `100` | Hover backgrounds, subtle fills         |
| `200` | Borders, dividers                       |
| `300` | Disabled borders, muted icons           |
| `400` | Placeholder text, secondary icons       |
| `500` | Default accent (links, buttons)         |
| `600` | Hover/active accent                     |
| `700` | Dark accent, bold emphasis              |
| `800` | Text on tinted backgrounds              |
| `900` | Primary text                            |
| `950` | Near-black text                         |

### 6.2 Custom brand colors in v4

Define brand palette in `@theme` using `oklch` (perceptual uniformity). Full shade ramp from `--color-brand-50` to `--color-brand-950`. This generates `bg-brand-500`, `text-brand-900`, `border-brand-200`, etc.

### 6.3 Assign colors by role

| Role        | Example classes                                  |
| ----------- | ------------------------------------------------ |
| **Greys**   | `text-gray-900`, `bg-gray-50`, `border-gray-200` |
| **Primary** | `bg-brand-600`, `text-brand-600`, `bg-brand-50`  |
| **Success** | `bg-green-50 text-green-800`                     |
| **Warning** | `bg-amber-50 text-amber-800`                     |
| **Danger**  | `bg-red-50 text-red-800`                         |
| **Info**    | `bg-blue-50 text-blue-800`                       |

### 6.4 Text on colored backgrounds

Use same-hue lighter shade (e.g. `text-indigo-200` on `bg-indigo-600`). Never use grey text or transparent white on colored backgrounds.

### 6.5 Grey temperature

Pick **one** grey family and use it everywhere:

| Family    | Feel                                      |
| --------- | ----------------------------------------- |
| `slate`   | Cool, blue-tinted — tech, corporate       |
| `gray`    | Balanced neutral                          |
| `zinc`    | Cool but understated                      |
| `neutral` | True neutral, no tint                     |
| `stone`   | Warm, yellow-tinted — friendly, editorial |

**Default: `gray`.** Switch only when the user explicitly requests a warmer or cooler feel.

### 6.6 Never rely on color alone

Pair color with icons, text, or contrast so colorblind users can interpret the UI.

---

## 7. ACCESSIBILITY

This section is **mandatory**. Every component must follow these rules.

### 7.1 Contrast ratios (WCAG AA)

| Text size                     | Minimum ratio | Safe Tailwind colors on white |
| ----------------------------- | ------------- | ----------------------------- |
| Normal (up to `text-base`)    | **4.5:1**     | `text-gray-600` and darker    |
| Large (`text-lg`+)            | **3:1**       | `text-gray-500` and darker    |
| Decorative / placeholder only | -             | `text-gray-400`               |

Prefer light backgrounds over dark for alerts/badges (`bg-green-50 text-green-800` over `bg-green-700 text-white`).

### 7.2 Focus management

- Every interactive element must have a visible `focus-visible` ring.
- Use `focus-visible` (not `focus`) to avoid showing outlines on click.
- **Never use `outline-none` without a `focus-visible:ring-*` replacement.**

### 7.3 ARIA attributes — mandatory patterns

| Element                 | Required attributes                                                 |
| ----------------------- | ------------------------------------------------------------------- |
| Icon-only buttons       | `aria-label="..."`                                                  |
| Decorative icons/images | `aria-hidden="true"`                                                |
| Loading spinners        | `role="status"` + `<span class="sr-only">Loading...</span>`        |
| Alerts / toasts         | `role="alert"` or `role="status"`                                   |
| Navigation landmarks    | `<nav aria-label="Main navigation">`                                |
| Main content            | `<main id="main">`                                                  |
| Form errors             | `aria-describedby="..."` pointing to the error message              |
| Required fields         | `aria-required="true"` (or native `required`)                       |
| Toggle states           | `aria-expanded="true/false"`                                        |
| Current page in nav     | `aria-current="page"`                                               |
| Tabs                    | `role="tablist"`, `role="tab"`, `role="tabpanel"` + `aria-selected` |

### 7.4 Form accessibility rules

- Every `<input>` must have a `<label>` with a matching `for`/`id` pair. No exceptions.
- Error messages must be linked via `aria-describedby`.
- Invalid fields must have `aria-invalid="true"`.
- Required fields must have `aria-required="true"` or native `required`.
- Placeholder is NOT a substitute for a label.

### 7.5 Tab order

- DOM order = tab order. Don't use `tabindex` > 0.
- `tabindex="0"` for custom focusable widgets.
- `tabindex="-1"` for programmatic focus (e.g., modal title).
- Logical reading order: header -> main content -> sidebar -> footer.

### 7.6 Reduced motion

Use `motion-safe:` and `motion-reduce:` variants on all animations and transitions.

### 7.7 Images

- Informative images: always provide meaningful `alt` text.
- Decorative images: `alt=""` or `aria-hidden="true"`.
- User avatars: `object-cover` + fixed size + `ring-1 ring-black/5`.

---

## 8. DEPTH & SHADOWS

### 8.1 Shadow = elevation

| Class                      | Use                 | Perceived distance |
| -------------------------- | ------------------- | ------------------ |
| `shadow-sm`                | Buttons, inputs     | Slightly raised    |
| `shadow`                   | Cards, panels       | Resting surface    |
| `shadow-md`                | Dropdowns, popovers | Floating           |
| `shadow-lg`                | Sticky elements     | Elevated           |
| `shadow-xl` / `shadow-2xl` | Modals, dialogs     | Foreground         |

Choose shadows by purpose, not decoration. Custom shadows via `@theme`: `--shadow-soft`, `--shadow-card`.

### 8.2 Depth techniques

- **Interactive shadows:** hover lift (`hover:shadow-lg`), press down (`active:shadow-none`). Always add `motion-reduce:transition-none`.
- **Inset depth:** `shadow-inner` for recessed content, `ring-1 ring-inset ring-white/10` for subtle button depth.
- **Depth without shadows:** lighter = closer (`bg-white` on `bg-gray-100`), darker = further.
- **Layered depth:** overlapping elements with negative margin (`-mt-12`) + `shadow-lg`.

---

## 9. IMAGES & ICONS

- **Text over images:** use a dark overlay (`bg-black/40`) + `drop-shadow-lg` on text.
- **Small icons:** don't upscale — wrap in a colored container (`w-12 h-12 rounded-lg bg-indigo-100` around `w-6 h-6`).
- **User-uploaded content:** `object-cover` + fixed size + `ring-1 ring-black/5` (not `border`).

---

## 10. ALPINE.JS RULES

Use Alpine.js for UI state (toggles, dropdowns, modals, tabs, accordions). Don't use it for simple hover states or CSS-only animations.

### 10.1 Setup

- Include Alpine via CDN with `defer`, or bundle it.
- Focus plugin required for modals (`x-trap.noscroll.inert`).
- Collapse plugin optional for smooth accordions.
- Always add `[x-cloak] { display: none !important; }` in CSS.

### 10.2 Mandatory rules

| Rule                                    | Details                                                                          |
| --------------------------------------- | -------------------------------------------------------------------------------- |
| **Always pair `x-show` with `x-cloak`** | Prevents flash of content before Alpine initializes                              |
| **Always add ARIA attributes**          | `aria-expanded`, `aria-controls`, `aria-haspopup`, `role` — never skip these     |
| **Escape key closes overlays**          | `@keydown.escape.window="open = false"` on dropdowns and modals                  |
| **Click outside closes**                | `@click.outside="open = false"` on dropdown menus                                |
| **Focus trap for modals**               | Use `x-trap.noscroll.inert` — never build a modal without it                     |
| **Teleport modals**                     | `x-teleport="body"` to avoid z-index issues                                     |
| **Transitions respect motion**          | `motion-reduce:transition-none` on animated elements                             |
| **Modal accessibility**                 | `role="dialog"` + `aria-modal="true"` + `aria-labelledby` are mandatory          |
| **Tab pattern**                         | Arrow keys move between tabs, only active tab has `tabindex="0"`, rest get `-1`  |

---

## 11. POLISH & FINISHING TOUCHES

- **Upgrade default elements:** icon bullets instead of dots, branded checkboxes, decorative blockquote marks.
- **Accent borders:** card top gradient, alert left accent (`border-l-4`), page top accent.
- **Background variety:** alternate `bg-white` and `bg-gray-50` sections. Gradient hues within ~30deg of each other.
- **Empty states:** illustration + message + CTA button. Empty states are the first thing a user sees — prioritize them. Hide unused tabs/filters until content exists.
- **Reduce borders:** before `border`/`divide-y`, try: (1) shadow, (2) background contrast, (3) spacing.
- **Rethink components:** grid dropdowns with icons, selectable card radio buttons, hierarchical table cells.

---

## 12. PERSONALITY

### 12.1 Default: Neutral

**Unless the user explicitly requests a different personality, always use the Neutral profile.**

| Token       | Neutral (default)           | Playful                        | Formal                        |
| ----------- | --------------------------- | ------------------------------ | ----------------------------- |
| **Font**    | `Inter` / system stack      | `Nunito`, `Poppins`            | `DM Sans`, Serif              |
| **Grey**    | `gray`                      | `stone`                        | `slate`                       |
| **Primary** | `indigo`                    | `pink`, `teal`, `violet`       | `gray`, `amber`               |
| **Radius**  | `rounded-md` / `rounded-lg` | `rounded-full` / `rounded-2xl` | `rounded-none` / `rounded-sm` |
| **Shadows** | `shadow-sm`                 | `shadow-lg`                    | `shadow-none`                 |
| **Tone**    | Clear, straightforward      | Casual, friendly               | Professional, formal          |

### 12.2 `@theme` presets

```css
/* Neutral (default) */
@theme {
  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
  --radius-DEFAULT: 0.375rem;
  --radius-lg: 0.5rem;
}

/* Playful — only if user asks for it */
@theme {
  --font-sans: 'Nunito', ui-sans-serif, system-ui, sans-serif;
  --radius-DEFAULT: 1rem;
  --radius-lg: 1.5rem;
}

/* Formal — only if user asks for it */
@theme {
  --font-sans: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
  --radius-DEFAULT: 0;
  --radius-lg: 0.125rem;
}
```

### 12.3 When to switch personality

- "fun / playful / friendly / casual" or children's app -> **Playful**
- "professional / corporate / formal / serious" or law firm / finance -> **Formal**
- SaaS dashboard, generic app -> stay **Neutral**
- If ambiguous, ask the user. Never guess.

### 12.4 Consistency rules

- One `rounded-*` value for cards, buttons, and inputs.
- One grey family across the whole project.
- One tone of language throughout all microcopy.
- Define all design tokens in `@theme` so the system enforces consistency.

---

## CHECKLIST

Before shipping any screen:

**Visual hierarchy**

- [ ] 3-tier text hierarchy (`text-gray-900` -> `500` -> `400`)
- [ ] Max 2 font weights in use
- [ ] Consistent `rounded-*` across all components
- [ ] Single grey family used throughout
- [ ] Personality profile is Neutral unless explicitly requested otherwise

**Spacing & layout**

- [ ] All spacing from Tailwind scale (no arbitrary values)
- [ ] All font sizes from Tailwind scale
- [ ] All colors from the palette or defined in `@theme`
- [ ] Custom tokens defined in `@theme`, not as arbitrary values in markup
- [ ] Prose width <= `max-w-prose` / `max-w-2xl`
- [ ] Intra-group spacing < inter-group spacing
- [ ] Shadows match element purpose
- [ ] Borders justified — tried shadow, bg, or spacing first

**Typography**

- [ ] Mixed font sizes use `items-baseline`
- [ ] Headlines: `tracking-tight` / Uppercase: `tracking-wide`

**Accessibility (mandatory)**

- [ ] Text contrast meets WCAG AA
- [ ] Every interactive element has a visible `focus-visible` ring
- [ ] Every `<input>` has a `<label>` with matching `for`/`id`
- [ ] Icon-only buttons have `aria-label` or `sr-only` text
- [ ] Decorative icons/images have `aria-hidden="true"`
- [ ] Informative images have meaningful `alt` text
- [ ] Error messages linked via `aria-describedby`
- [ ] Color never used alone — paired with icons/text
- [ ] Empty states designed with illustration + CTA
- [ ] `motion-reduce:` variants applied to all animations/transitions
- [ ] Form validation uses `aria-invalid` and `aria-describedby`

**Alpine.js (when used)**

- [ ] `x-cloak` added to all `x-show` elements
- [ ] Dropdowns/modals have `@keydown.escape` handler
- [ ] Modals use `x-trap.noscroll.inert` from Focus plugin
- [ ] Modals have `role="dialog"` + `aria-modal="true"` + `aria-labelledby`
- [ ] Toggle buttons have `:aria-expanded` binding
- [ ] Tabs use proper `role="tablist"` / `role="tab"` / `role="tabpanel"` + arrow key navigation
- [ ] `x-teleport="body"` used for modals to avoid z-index issues

**Images**

- [ ] User images: `object-cover` + fixed size + `ring-1 ring-black/5`
- [ ] No upscaled small icons — wrapped in colored containers instead
