# Tailwind CSS v4 + Alpine.js — UI Design System Guidelines for AI Agents

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
  /* Custom fonts */
  --font-sans: 'Inter', sans-serif;
  --font-display: 'Cal Sans', sans-serif;

  /* Custom colors */
  --color-brand-50: oklch(0.97 0.02 250);
  --color-brand-100: oklch(0.93 0.04 250);
  --color-brand-500: oklch(0.55 0.18 250);
  --color-brand-600: oklch(0.48 0.18 250);
  --color-brand-700: oklch(0.4 0.16 250);
  --color-brand-900: oklch(0.25 0.1 250);

  /* Custom spacing (if defaults don't fit) */
  --spacing-18: 4.5rem;

  /* Custom border radius */
  --radius-DEFAULT: 0.5rem;
  --radius-lg: 0.75rem;
  --radius-xl: 1rem;

  /* Custom shadows */
  --shadow-soft: 0 1px 3px 0 oklch(0 0 0 / 0.04), 0 1px 2px -1px oklch(0 0 0 / 0.04);
}
```

This generates utility classes like `bg-brand-500`, `font-display`, `rounded-xl`, `shadow-soft` — usable directly in markup.

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

To completely replace a default set (e.g., strip all default colors and use only yours):

```css
@theme {
  --color-*: initial; /* removes all default colors */
  --color-white: #fff;
  --color-black: #000;
  --color-brand-500: #4f46e5;
  /* ... define only what you need */
}
```

### 2.4 Theming with CSS variables

For multi-theme setups (dark mode, brand variants), define tokens in `@theme` and override them per context in `@layer base`:

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

Every screen should have a clear primary → secondary → tertiary structure.

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
- Example: soften inactive items (`text-gray-400`) rather than making the active one louder.

### 3.3 Let data speak for itself

- Skip labels when the format is self-evident (emails, phone numbers, prices).
- Merge label and value into a natural phrase: `"12 left in stock"` not `"In stock: 12"`.
- When labels are required, treat them as supporting content:

```html
<p class="text-xs font-medium uppercase tracking-wide text-gray-500">Revenue</p>
<p class="text-2xl font-semibold text-gray-900">$42,500</p>
```

### 3.4 Style for the eye, not the tag

- An `<h1>` doesn't have to be the biggest thing on screen.
- Section titles often function as labels — keep them small:

```html
<h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
  Account Settings
</h2>
```

### 3.5 Balance weight and contrast

- Visually heavy elements (icons, bold text) need softer colors to avoid dominating.
- Visually light elements (thin borders) may need more thickness instead of darker color.

### 3.6 Button hierarchy

Design buttons by importance, not just semantic meaning:

```html
<!-- Primary: solid, high contrast -->
<button
  class="bg-indigo-600 text-white font-medium px-4 py-2 rounded-lg hover:bg-indigo-700
  focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
>
  Confirm
</button>

<!-- Secondary: outline or muted -->
<button
  class="border border-gray-300 text-gray-700 font-medium px-4 py-2 rounded-lg hover:bg-gray-50
  focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
>
  Cancel
</button>

<!-- Tertiary: link-style -->
<button
  class="text-gray-500 font-medium hover:text-gray-700 underline-offset-2 hover:underline
  focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded"
>
  Reset
</button>
```

- Destructive actions should not automatically be `bg-red-600`. Prefer secondary styling + a confirmation step where the destructive action becomes the primary button.

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
| `20`–`24` | 80–96px | Hero-level spacing      |

In v4, you can also use **dynamic arbitrary spacing** like `p-17` (= `calc(var(--spacing) * 17)`) without config — but prefer named scale values for consistency.

If two spacing values look nearly identical on screen, you're choosing between options that are too close. Jump a step.

### 4.3 Constrain content width

```html
<div class="max-w-2xl mx-auto"><!-- 672px max --></div>
```

| Class       | px     | Use for                 |
| ----------- | ------ | ----------------------- |
| `max-w-xs`  | 320px  | Small cards, modals     |
| `max-w-sm`  | 384px  | Forms, login cards      |
| `max-w-lg`  | 512px  | Medium content          |
| `max-w-2xl` | 672px  | Articles, readable text |
| `max-w-4xl` | 896px  | Wide content areas      |
| `max-w-7xl` | 1280px | Page containers         |

To add custom container widths in v4:

```css
@theme {
  --container-narrow: 40rem; /* generates max-w-narrow */
  --container-wide: 80rem; /* generates max-w-wide */
}
```

If content only needs 400px, don't stretch it to fill 1200px.

### 4.4 Fixed widths where appropriate

Not everything should be percentage-based.

```html
<div class="flex">
  <aside class="w-64 shrink-0"><!-- fixed sidebar --></aside>
  <main class="flex-1 min-w-0"><!-- flexible main --></main>
</div>
```

Sidebars, avatars, icons, and form fields usually need fixed sizes. Let the main content area flex.

### 4.5 Size independently per breakpoint

Don't assume proportional relationships hold across screen sizes.

```html
<h1 class="text-2xl md:text-4xl lg:text-5xl font-bold">Heading</h1>
```

Padding should grow disproportionately at larger sizes:

```html
<button class="text-xs px-2.5 py-1.5 rounded">Small</button>
<button class="text-sm px-4 py-2 rounded-md">Medium</button>
<button class="text-base px-6 py-3 rounded-lg">Large</button>
```

### 4.6 Eliminate ambiguous spacing

The space within a group must be noticeably smaller than the space between groups:

```html
<div class="space-y-6">
  <!-- 24px between groups -->
  <div class="space-y-1.5">
    <!-- 6px within group -->
    <label class="text-sm font-medium text-gray-700" for="email">Email</label>
    <input id="email" class="border border-gray-300 rounded-md px-3 py-2 w-full" />
  </div>
  <div class="space-y-1.5">
    <label class="text-sm font-medium text-gray-700" for="password">Password</label>
    <input
      id="password"
      type="password"
      class="border border-gray-300 rounded-md px-3 py-2 w-full"
    />
  </div>
</div>
```

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

Default to `font-sans` (system stack). To add a custom font in v4:

```css
@theme {
  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
  --font-display: 'Cal Sans', 'Inter', sans-serif;
}
```

Then use `font-sans` for body and `font-display` for headlines. Choose typefaces with 5+ weights. Use fonts designed for UI readability (wide letter-spacing, tall x-height) at body sizes.

### 5.3 Readable line length

```html
<p class="max-w-prose"><!-- ~65ch, ideal for reading --></p>
```

Constrain paragraphs even inside wider containers.

### 5.4 Baseline alignment for mixed sizes

```html
<div class="flex items-baseline">
  <h2 class="text-2xl font-bold">Dashboard</h2>
  <a href="#" class="ml-4 text-sm text-indigo-600">View all</a>
</div>
```

Use `items-baseline` — not `items-center` — when font sizes differ on the same line.

### 5.5 Line-height by context

| Text type                    | Class                             | Ratio   |
| ---------------------------- | --------------------------------- | ------- |
| Body (`text-sm`–`text-base`) | `leading-relaxed`                 | ~1.625  |
| Large (`text-xl`+)           | `leading-snug`                    | ~1.375  |
| Headlines (`text-3xl`+)      | `leading-tight` or `leading-none` | ~1–1.25 |

Smaller text needs more line-height. Larger text needs less.

### 5.6 Link styling in link-heavy UIs

```html
<!-- Subtle: weight or color only -->
<a href="#" class="font-medium text-gray-900 hover:text-gray-600">Dashboard</a>

<!-- Ancillary: visible on hover -->
<a href="#" class="text-gray-500 hover:text-gray-900 hover:underline">Settings</a>
```

Reserve bright link colors for inline links within prose.

### 5.7 Alignment rules

- Default: `text-left`.
- Center only for short blocks (≤2–3 lines).
- Numbers in tables: `text-right tabular-nums`.
- Justified: only with `hyphens-auto`.

### 5.8 Letter-spacing

```html
<h1 class="text-5xl font-bold tracking-tight">Headline</h1>
<span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Label</span>
```

| Class             | When to use                  |
| ----------------- | ---------------------------- |
| `tracking-tight`  | Headlines (`text-3xl`+)      |
| `tracking-normal` | Body (default, leave alone)  |
| `tracking-wide`   | Uppercase text, small labels |

---

## 6. COLOR

### 6.1 Use the palette as your system

Tailwind v4 ships with a comprehensive palette. Each color has 11 shades (50–950). This IS your design system.

**Shade roles:**

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

Define your brand palette in CSS:

```css
@theme {
  --color-brand-50: oklch(0.97 0.01 250);
  --color-brand-100: oklch(0.93 0.03 250);
  --color-brand-200: oklch(0.87 0.06 250);
  --color-brand-300: oklch(0.78 0.1 250);
  --color-brand-400: oklch(0.68 0.15 250);
  --color-brand-500: oklch(0.55 0.18 250);
  --color-brand-600: oklch(0.48 0.18 250);
  --color-brand-700: oklch(0.4 0.16 250);
  --color-brand-800: oklch(0.32 0.12 250);
  --color-brand-900: oklch(0.25 0.1 250);
  --color-brand-950: oklch(0.18 0.07 250);
}
```

This generates `bg-brand-500`, `text-brand-900`, `border-brand-200`, etc.

**Tip:** Tailwind v4 defaults to `oklch` for its built-in palette. Prefer `oklch` or `hsl` over hex when defining custom colors — perceptual uniformity makes it easier to create consistent shade ramps.

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

```html
<!-- BAD: grey or transparent white text -->
<div class="bg-indigo-600">
  <p class="text-gray-400">Muddy</p>
  <p class="text-white/50">Washed out</p>
</div>

<!-- GOOD: same-hue lighter shade -->
<div class="bg-indigo-600">
  <p class="text-indigo-200">Harmonious</p>
</div>
```

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

```html
<!-- BAD -->
<span class="text-green-600">+12%</span>

<!-- GOOD: color + icon -->
<span class="text-green-600 flex items-center gap-1">
  <svg aria-hidden="true"><!-- ↑ arrow --></svg>
  <span>+12%</span>
  <span class="sr-only">increase</span>
</span>
```

Pair color with icons, text, or contrast so colorblind users can interpret the UI.

---

## 7. ACCESSIBILITY

This section is **mandatory**. Every component the agent generates must follow these rules.

### 7.1 Contrast ratios (WCAG AA)

| Text size                     | Minimum ratio | Safe Tailwind colors on white |
| ----------------------------- | ------------- | ----------------------------- |
| ≤ `text-base` (normal)        | **4.5:1**     | `text-gray-600` and darker ✅ |
| ≥ `text-lg` (large)           | **3:1**       | `text-gray-500` and darker ✅ |
| Decorative / placeholder only | —             | `text-gray-400` ⚠️            |

**Prefer light backgrounds over dark for alerts/badges:**

```html
<!-- Accessible, less dominant -->
<div class="bg-green-50 text-green-800" role="status">Success</div>

<!-- Rather than -->
<div class="bg-green-700 text-white">Success</div>
```

### 7.2 Focus management

**Every interactive element must have a visible focus indicator.** Use `focus-visible` (not `focus`) to avoid showing outlines on click.

```html
<!-- Standard focus ring — apply to ALL buttons, links, inputs -->
<button
  class="... focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
>
  Action
</button>

<!-- Inputs: border change + ring -->
<input
  class="... focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
/>
```

**Never use `outline-none` without a `focus-visible:ring-*` replacement.**

### 7.3 Screen reader utilities

Use `sr-only` to provide context that is visible to assistive technology but hidden visually:

```html
<!-- Icon-only button: ALWAYS needs sr-only label -->
<button
  class="p-2 rounded-lg hover:bg-gray-100
  focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
  aria-label="Close"
>
  <svg class="w-5 h-5 text-gray-500" aria-hidden="true"><!-- X icon --></svg>
</button>

<!-- Or with sr-only span -->
<button class="p-2 rounded-lg hover:bg-gray-100">
  <svg class="w-5 h-5 text-gray-500" aria-hidden="true"><!-- X icon --></svg>
  <span class="sr-only">Close</span>
</button>

<!-- Status indicator with text for screen readers -->
<span class="inline-block w-2 h-2 rounded-full bg-green-500" aria-hidden="true"></span>
<span class="sr-only">Online</span>

<!-- Skip to main content link -->
<a
  href="#main"
  class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4
  focus:z-50 focus:bg-white focus:px-4 focus:py-2 focus:rounded-md focus:shadow-lg"
>
  Skip to main content
</a>
```

### 7.4 ARIA attributes — mandatory patterns

**Always include these ARIA attributes where applicable:**

| Element                 | Required attributes                                                 |
| ----------------------- | ------------------------------------------------------------------- |
| Icon-only buttons       | `aria-label="..."`                                                  |
| Decorative icons/images | `aria-hidden="true"`                                                |
| Loading spinners        | `role="status"` + `<span class="sr-only">Loading...</span>`         |
| Alerts / toasts         | `role="alert"` or `role="status"`                                   |
| Navigation landmarks    | `<nav aria-label="Main navigation">`                                |
| Main content            | `<main id="main">`                                                  |
| Form errors             | `aria-describedby="..."` pointing to the error message              |
| Required fields         | `aria-required="true"` (or native `required`)                       |
| Toggle states           | `aria-expanded="true/false"`                                        |
| Current page in nav     | `aria-current="page"`                                               |
| Tabs                    | `role="tablist"`, `role="tab"`, `role="tabpanel"` + `aria-selected` |

### 7.5 Form accessibility

```html
<div class="space-y-6">
  <div class="space-y-1.5">
    <label for="email" class="block text-sm font-medium text-gray-700">
      Email <span class="text-red-500" aria-hidden="true">*</span>
    </label>
    <input
      id="email"
      type="email"
      required
      aria-required="true"
      aria-describedby="email-error"
      aria-invalid="true"
      class="block w-full rounded-md border border-red-300 px-3 py-2 text-gray-900
        placeholder:text-gray-400
        focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
    />
    <p id="email-error" class="text-sm text-red-600" role="alert">
      Please enter a valid email address.
    </p>
  </div>
</div>
```

**Rules:**

- Every `<input>` must have a `<label>` with a matching `for`/`id` pair. No exceptions.
- Error messages must be linked via `aria-describedby`.
- Invalid fields must have `aria-invalid="true"`.
- Required fields must have `aria-required="true"` or the native `required` attribute.
- Placeholder is NOT a substitute for a label.

### 7.6 Tab order

- DOM order = tab order. Don't use `tabindex` > 0 (it breaks natural flow).
- `tabindex="0"` to make non-interactive elements focusable when needed (e.g., a custom widget).
- `tabindex="-1"` to make elements programmatically focusable but not in the tab sequence (e.g., modal title for focus restoration).
- Ensure logical reading order: header → main content → sidebar → footer.

### 7.7 Reduced motion

Respect the user's system preference:

```html
<!-- Apply transitions only when motion is allowed -->
<div class="transition-shadow duration-200 motion-reduce:transition-none">...</div>

<!-- Or in CSS -->
<style>
  @media (prefers-reduced-motion: reduce) {
    * {
      animation-duration: 0.01ms !important;
      transition-duration: 0.01ms !important;
    }
  }
</style>
```

Use `motion-safe:` and `motion-reduce:` variants in Tailwind.

### 7.8 Images and media

```html
<!-- Informative image: always provide alt text -->
<img src="chart.png" alt="Revenue grew 23% from Q1 to Q2 2025" class="rounded-lg" />

<!-- Decorative image: empty alt or aria-hidden -->
<img src="pattern.svg" alt="" class="absolute inset-0" aria-hidden="true" />

<!-- User avatar with name context -->
<img
  src="avatar.jpg"
  alt="Jane Doe's profile photo"
  class="w-10 h-10 rounded-full object-cover"
/>
```

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

Choose shadows by purpose, not decoration.

To define custom shadows in v4:

```css
@theme {
  --shadow-soft: 0 1px 3px oklch(0 0 0 / 0.04), 0 1px 2px oklch(0 0 0 / 0.03);
  --shadow-card: 0 2px 8px oklch(0 0 0 / 0.06), 0 1px 2px oklch(0 0 0 / 0.04);
}
```

### 8.2 Interactive shadows

```html
<!-- Hover lift -->
<div class="shadow hover:shadow-lg transition-shadow motion-reduce:transition-none">
  Card
</div>

<!-- Press down -->
<button
  class="shadow-sm active:shadow-none transition-shadow motion-reduce:transition-none"
>
  Click
</button>
```

### 8.3 Inset depth

```html
<div class="bg-gray-100 shadow-inner rounded-lg p-4">Recessed content</div>

<button class="bg-indigo-600 text-white shadow-sm ring-1 ring-inset ring-white/10">
  Save
</button>
```

### 8.4 Depth without shadows

```html
<!-- Raised feel -->
<div class="bg-gray-100 p-8">
  <div class="bg-white rounded-lg p-6">Lighter = closer</div>
</div>

<!-- Inset feel -->
<div class="bg-white p-8">
  <div class="bg-gray-100 rounded-lg p-6">Darker = further</div>
</div>
```

### 8.5 Layered depth via overlap

```html
<div class="relative bg-indigo-600 pb-20">
  <h2 class="text-white text-3xl font-bold px-8 pt-8">Section</h2>
</div>
<div class="relative -mt-12 px-8">
  <div class="bg-white rounded-xl shadow-lg p-6">Overlapping card</div>
</div>
```

---

## 9. IMAGES & ICONS

### 9.1 Text over images

```html
<div class="relative">
  <img src="..." alt="" aria-hidden="true" class="w-full h-64 object-cover" />
  <div class="absolute inset-0 bg-black/40"></div>
  <h2 class="absolute bottom-4 left-4 text-white text-2xl font-bold drop-shadow-lg">
    Title
  </h2>
</div>
```

### 9.2 Don't upscale small icons — wrap them

```html
<div class="flex items-center justify-center w-12 h-12 rounded-lg bg-indigo-100">
  <svg class="w-6 h-6 text-indigo-600" aria-hidden="true">
    <!-- icon at intended size -->
  </svg>
</div>
```

### 9.3 User-uploaded content

```html
<div class="w-16 h-16 rounded-full overflow-hidden ring-1 ring-black/5">
  <img src="user-photo.jpg" alt="User avatar" class="w-full h-full object-cover" />
</div>
```

Use `ring-1 ring-black/5` instead of `border` — it won't clash with image colors.

---

## 10. ALPINE.JS INTERACTIVE PATTERNS

Use Alpine.js for UI behaviors that don't justify a full framework: toggles, dropdowns, modals, tabs, accordions, and form interactions. Always pair Alpine behavior with proper ARIA attributes and focus management.

### 10.1 Setup

```html
<!-- CDN (include before closing </body> or with defer in <head>) -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- With Focus plugin (required for modals) -->
<script
  defer
  src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.x.x/dist/cdn.min.js"
></script>

<!-- With Collapse plugin (optional, for smooth accordions) -->
<script
  defer
  src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"
></script>
```

**Always add `x-cloak` + CSS to prevent flash of unstyled content:**

```css
[x-cloak] {
  display: none !important;
}
```

### 10.2 Toggle / Disclosure

```html
<div x-data="{ open: false }">
  <button
    @click="open = !open"
    :aria-expanded="open"
    aria-controls="details-panel"
    class="flex items-center gap-2 text-sm font-medium text-gray-700
      focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded"
  >
    <svg
      class="w-4 h-4 transition-transform motion-reduce:transition-none"
      :class="open && 'rotate-90'"
      aria-hidden="true"
    >
      <!-- chevron-right icon -->
    </svg>
    Show details
  </button>

  <div
    x-show="open"
    x-transition
    x-cloak
    id="details-panel"
    class="mt-2 text-sm text-gray-600"
  >
    Hidden content revealed on click.
  </div>
</div>
```

### 10.3 Dropdown menu

```html
<div x-data="{ open: false }" class="relative">
  <!-- Trigger -->
  <button
    @click="open = !open"
    @keydown.escape.window="open = false"
    :aria-expanded="open"
    aria-haspopup="true"
    class="flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700
      rounded-lg hover:bg-gray-50
      focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
  >
    Options
    <svg class="w-4 h-4 text-gray-400" aria-hidden="true"><!-- chevron-down --></svg>
  </button>

  <!-- Menu -->
  <div
    x-show="open"
    x-cloak
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    @click.outside="open = false"
    role="menu"
    class="absolute right-0 z-10 mt-2 w-48 origin-top-right
      rounded-lg bg-white shadow-md ring-1 ring-gray-950/5
      py-1"
  >
    <a
      href="#"
      role="menuitem"
      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
      >Edit</a
    >
    <a
      href="#"
      role="menuitem"
      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
      >Duplicate</a
    >
    <a
      href="#"
      role="menuitem"
      class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50"
      >Delete</a
    >
  </div>
</div>
```

### 10.4 Modal / Dialog (requires Focus plugin)

```html
<div x-data="{ open: false }">
  <!-- Trigger -->
  <button
    @click="open = true"
    class="bg-indigo-600 text-white font-medium px-4 py-2 rounded-lg hover:bg-indigo-700
      focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
  >
    Open dialog
  </button>

  <!-- Backdrop + Dialog -->
  <template x-teleport="body">
    <div
      x-show="open"
      x-cloak
      class="fixed inset-0 z-50 flex items-center justify-center"
    >
      <!-- Backdrop -->
      <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false"
        class="absolute inset-0 bg-black/40"
        aria-hidden="true"
      ></div>

      <!-- Panel -->
      <div
        x-show="open"
        x-trap.noscroll.inert="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @keydown.escape.window="open = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
        class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
      >
        <!-- Close button -->
        <button
          @click="open = false"
          class="absolute top-4 right-4 p-1 rounded-md text-gray-400 hover:text-gray-600
            focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
          aria-label="Close dialog"
        >
          <svg class="w-5 h-5" aria-hidden="true"><!-- X icon --></svg>
        </button>

        <!-- Content -->
        <h2 id="modal-title" class="text-lg font-semibold text-gray-900">Dialog title</h2>
        <p class="mt-2 text-sm text-gray-500">Dialog description goes here.</p>

        <!-- Actions -->
        <div class="mt-6 flex justify-end gap-3">
          <button
            @click="open = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 rounded-lg border border-gray-300
              hover:bg-gray-50
              focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
          >
            Cancel
          </button>
          <button
            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg
              hover:bg-indigo-700
              focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
          >
            Confirm
          </button>
        </div>
      </div>
    </div>
  </template>
</div>
```

**Key accessibility points:**

- `x-trap.noscroll.inert="open"` traps focus, prevents scroll, and makes background inert.
- `role="dialog"` + `aria-modal="true"` + `aria-labelledby` are mandatory.
- Escape key closes the dialog.
- `x-teleport="body"` prevents z-index stacking issues.
- Focus returns to the trigger button automatically when `x-trap` releases.

### 10.5 Tabs

```html
<div x-data="{ activeTab: 'general' }">
  <!-- Tab list -->
  <div role="tablist" class="flex gap-1 border-b border-gray-200">
    <button
      role="tab"
      :id="'tab-general'"
      :aria-selected="activeTab === 'general'"
      :aria-controls="'panel-general'"
      :tabindex="activeTab === 'general' ? 0 : -1"
      @click="activeTab = 'general'"
      @keydown.arrow-right.prevent="activeTab = 'notifications'; $nextTick(() => $el.nextElementSibling.focus())"
      :class="activeTab === 'general'
        ? 'border-indigo-500 text-indigo-600'
        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
      class="px-4 py-2 text-sm font-medium border-b-2 -mb-px
        focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t"
    >
      General
    </button>
    <button
      role="tab"
      :id="'tab-notifications'"
      :aria-selected="activeTab === 'notifications'"
      :aria-controls="'panel-notifications'"
      :tabindex="activeTab === 'notifications' ? 0 : -1"
      @click="activeTab = 'notifications'"
      @keydown.arrow-left.prevent="activeTab = 'general'; $nextTick(() => $el.previousElementSibling.focus())"
      :class="activeTab === 'notifications'
        ? 'border-indigo-500 text-indigo-600'
        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
      class="px-4 py-2 text-sm font-medium border-b-2 -mb-px
        focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded-t"
    >
      Notifications
    </button>
  </div>

  <!-- Tab panels -->
  <div
    x-show="activeTab === 'general'"
    id="panel-general"
    role="tabpanel"
    aria-labelledby="tab-general"
    class="py-4"
  >
    General settings content.
  </div>
  <div
    x-show="activeTab === 'notifications'"
    x-cloak
    id="panel-notifications"
    role="tabpanel"
    aria-labelledby="tab-notifications"
    class="py-4"
  >
    Notifications settings content.
  </div>
</div>
```

**Key accessibility points:**

- Arrow keys move between tabs (not Tab key — Tab should go to the panel content).
- Only the active tab has `tabindex="0"`, others have `tabindex="-1"`.
- `aria-selected` reflects the active state.
- Each panel has `aria-labelledby` pointing to its tab.

### 10.6 Accordion (requires Collapse plugin)

```html
<div class="divide-y divide-gray-200">
  <div x-data="{ open: false }">
    <button
      @click="open = !open"
      :aria-expanded="open"
      aria-controls="accordion-1"
      class="flex w-full items-center justify-between py-4 text-left text-sm font-medium text-gray-900
        focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded"
    >
      <span>What is your refund policy?</span>
      <svg
        class="w-5 h-5 text-gray-400 transition-transform motion-reduce:transition-none"
        :class="open && 'rotate-180'"
        aria-hidden="true"
      >
        <!-- chevron-down -->
      </svg>
    </button>
    <div x-show="open" x-collapse x-cloak id="accordion-1" role="region">
      <p class="pb-4 text-sm text-gray-500">
        We offer a 30-day money-back guarantee on all plans.
      </p>
    </div>
  </div>
</div>
```

### 10.7 Alpine.js rules for the agent

| Rule                                    | Details                                                                                                         |
| --------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| **Always pair `x-show` with `x-cloak`** | Prevents flash of content before Alpine initializes                                                             |
| **Always add ARIA attributes**          | `aria-expanded`, `aria-controls`, `aria-haspopup`, `role` — never skip these                                    |
| **Escape key closes overlays**          | Add `@keydown.escape.window="open = false"` on dropdowns and modals                                             |
| **Click outside closes**                | Add `@click.outside="open = false"` on dropdown menus                                                           |
| **Focus trap for modals**               | Use `x-trap.noscroll.inert` from the Focus plugin — never build a modal without it                              |
| **Teleport modals**                     | Use `x-teleport="body"` to avoid z-index issues inside stacked containers                                       |
| **Transitions respect motion**          | Add `motion-reduce:transition-none` on animated elements                                                        |
| **Don't overuse Alpine**                | Simple hover states and CSS-only animations don't need Alpine. Use it for state (open/closed, active tab, etc.) |

---

## 11. POLISH & FINISHING TOUCHES

### 11.1 Upgrade default elements

```html
<!-- Icon bullets instead of dots -->
<ul class="space-y-3">
  <li class="flex items-start gap-3">
    <svg class="w-5 h-5 text-green-500 mt-0.5 shrink-0" aria-hidden="true">
      <!-- ✓ -->
    </svg>
    <span class="text-gray-700">Feature benefit here</span>
  </li>
</ul>

<!-- Branded checkbox -->
<input
  type="checkbox"
  class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
/>

<!-- Decorative quote mark -->
<blockquote class="relative pl-8">
  <span
    class="absolute left-0 top-0 text-5xl text-indigo-200 leading-none"
    aria-hidden="true"
    >"</span
  >
  <p class="text-lg text-gray-700 italic">Testimonial text...</p>
</blockquote>
```

### 11.2 Accent borders

```html
<!-- Card top accent -->
<div class="bg-white rounded-lg shadow overflow-hidden">
  <div
    class="h-1 bg-gradient-to-r from-indigo-500 to-purple-500"
    aria-hidden="true"
  ></div>
  <div class="p-6">Content</div>
</div>

<!-- Alert left accent -->
<div class="border-l-4 border-yellow-500 bg-yellow-50 p-4" role="alert">
  <p class="text-yellow-800">Warning message</p>
</div>

<!-- Page top accent -->
<body class="border-t-4 border-indigo-600"></body>
```

### 11.3 Background variety

```html
<section class="bg-white py-16">...</section>
<section class="bg-gray-50 py-16">...</section>

<section class="bg-gradient-to-br from-indigo-50 to-white py-16">...</section>
```

Keep gradient hues within ~30° of each other.

### 11.4 Empty states

```html
<div class="text-center py-12" role="status">
  <svg class="mx-auto w-12 h-12 text-gray-400" aria-hidden="true">
    <!-- illustration -->
  </svg>
  <h3 class="mt-4 text-lg font-semibold text-gray-900">No projects yet</h3>
  <p class="mt-2 text-sm text-gray-500">Get started by creating your first project.</p>
  <button
    class="mt-6 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700
    focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
  >
    New Project
  </button>
</div>
```

Empty states are the first thing a user sees. Prioritize them. Hide unused tabs/filters until content exists.

### 11.5 Reduce borders

Before adding `border` or `divide-y`, try in order:

1. **Shadow:** `shadow-sm` or `ring-1 ring-gray-950/5`
2. **Background contrast:** `bg-gray-50` next to `bg-white`
3. **Spacing:** increase `gap-*` or `space-y-*`

### 11.6 Rethink standard components

- Dropdowns: `grid grid-cols-2 gap-4 p-4` with icons and descriptions.
- Radio buttons: selectable cards with `peer-checked:border-indigo-600`.
- Tables: merge related columns, add images, use hierarchy within cells.

---

## 12. PERSONALITY

### 12.1 Default: Neutral

**Unless the user explicitly requests a different personality, always use the Neutral profile.** This ensures consistency and professional results without requiring user input on style questions.

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

Switch from Neutral only when the user says something like:

- "Make it fun / playful / friendly / casual" → **Playful**
- "Make it professional / corporate / formal / serious" → **Formal**
- "I want a SaaS dashboard" → stay **Neutral**
- "I want a children's app / game" → **Playful**
- "I want a law firm / finance site" → **Formal**

If ambiguous, ask the user. Never guess.

### 12.4 Consistency rules

- One `rounded-*` value for cards, buttons, and inputs.
- One grey family across the whole project.
- One tone of language throughout all microcopy.
- Define all design tokens in `@theme` so the system enforces consistency.

---

## 13. REUSABLE PATTERNS

### Card

```html
<div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-950/5 p-6">
  <h3 class="text-lg font-semibold text-gray-900">Title</h3>
  <p class="mt-2 text-sm text-gray-500 leading-relaxed">Description.</p>
</div>
```

### Alert

```html
<div class="rounded-lg bg-blue-50 border-l-4 border-blue-500 p-4" role="alert">
  <div class="flex items-start gap-3">
    <svg class="w-5 h-5 text-blue-600 mt-0.5 shrink-0" aria-hidden="true">
      <!-- icon -->
    </svg>
    <div>
      <p class="text-sm font-semibold text-blue-800">Info</p>
      <p class="mt-1 text-sm text-blue-700">Your account has been updated.</p>
    </div>
  </div>
</div>
```

### Form group

```html
<div class="space-y-6">
  <div class="space-y-1.5">
    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
    <input
      id="name"
      type="text"
      class="block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900
        shadow-inner placeholder:text-gray-400
        focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
    />
  </div>
</div>
```

### Page section

```html
<section class="bg-gray-50 py-16">
  <div class="max-w-4xl mx-auto px-6">
    <h2 class="text-3xl font-bold tracking-tight text-gray-900">Title</h2>
    <p class="mt-4 max-w-prose text-gray-500 leading-relaxed">Content.</p>
  </div>
</section>
```

### Loading spinner

```html
<div role="status">
  <svg
    class="w-6 h-6 animate-spin text-indigo-600 motion-reduce:animate-none"
    aria-hidden="true"
  >
    <!-- spinner SVG -->
  </svg>
  <span class="sr-only">Loading…</span>
</div>
```

---

## CHECKLIST

Before shipping any screen:

**Visual hierarchy**

- [ ] 3-tier text hierarchy (`text-gray-900` → `500` → `400`)
- [ ] Max 2 font weights in use
- [ ] Consistent `rounded-*` across all components
- [ ] Single grey family used throughout
- [ ] Personality profile is Neutral unless explicitly requested otherwise

**Spacing & layout**

- [ ] All spacing from Tailwind scale (no arbitrary values)
- [ ] All font sizes from Tailwind scale
- [ ] All colors from the palette or defined in `@theme`
- [ ] Custom tokens defined in `@theme`, not as arbitrary values in markup
- [ ] Prose width ≤ `max-w-prose` / `max-w-2xl`
- [ ] Intra-group spacing < inter-group spacing
- [ ] Shadows match element purpose
- [ ] Borders justified — tried shadow, bg, or spacing first

**Typography**

- [ ] Mixed font sizes use `items-baseline`
- [ ] Headlines: `tracking-tight` / Uppercase: `tracking-wide`

**Accessibility (mandatory)**

- [ ] Text contrast meets WCAG AA (≥ `text-gray-600` for body text on white)
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
