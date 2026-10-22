---
target: Newsletter statistics dashboard and campaign report
total_score: 23
p0_count: 0
p1_count: 5
p2_count: 10
p3_count: 2
timestamp: 2026-10-22T20-33-27Z
slug: newsletter-src-templates-admin-statistics-html-twig
---
# Newsletter statistics UI review

Scope: the statistics dashboard and campaign report, including filters, empty states,
long content, touch and keyboard interaction at 390, 768 and 1440 CSS pixels.
Reference: Adam Wathan and Steve Schoger, Refactoring UI, supplied locally by the user.
Product register: a marketer checking mailing results at a desk in daylight, and on a
phone between meetings. Preserve the existing light admin theme and restrained accent.

## Overall assessment

Manual Nielsen score: 23/40. Cognitive-load checklist: 3/8 failures, moderate
(chunking, hierarchy, progressive disclosure). No blocking decorative anti-patterns.
The current interface is credible but presents a uniform inventory before answering
which campaigns engaged readers and what needs attention.

## Heuristics

| Heuristic | Score / 4 | Evidence |
| --- | --- | --- |
| System status | 3 | Active navigation clear; sending status buried in metadata |
| Match to real world | 2 | Technical methodology and ambiguous unique-click label |
| Control and freedom | 2 | Back preserves filters; no quick range/reset |
| Consistency | 3 | Familiar admin vocabulary and controls |
| Error prevention | 1 | Stale end-date minimum rejects a valid earlier range |
| Recognition | 3 | Visible field labels and metrics |
| Efficiency | 2 | Key comparison columns offscreen on mobile |
| Minimalist design | 2 | Six equal metrics and long methodological notes |
| Error recovery | 2 | Invalid filters leave the dashboard for an error page |
| Help | 3 | Honest measurement limits, excessive initial emphasis |

## Findings and implementation decisions

1. P1, date range: changing the start to 2026-01-01 leaves the end minimum at
   2026-09-23, rejecting a valid 2026-01-31 end date. Synchronize the minimum and
   validate on the server with translated inline errors and preserved fields.
2. P1, contrast: the primary action is white on #0284c7 at 14px, measured at
   4.095:1. Use a scoped solid primary shade with AA contrast in all states.
3. P1, mobile comparison: 907px of table content occupies a 343px region. Keep
   campaign, sends, engagement and attention signals visible in stacked rows.
4. P1, hierarchy: six equal KPI columns compete. Prioritize sends, unique readers
   and observed click rate; make total clicks and delivery signals secondary.
5. P2, explanatory copy: three scoped paragraphs reach about 103 characters per
   line. Keep short cohort/measurement context visible, move details into native
   disclosure and limit prose to 65-75 characters. Tailor the report explanation.
6. P2, empty state: guidance follows six placeholders and long notes, below the
   first mobile screen. Show it immediately after filters, with recovery actions;
   suppress meaningless metrics and the empty comparison export.
7. P2, export scope: the export action is inside the pending filter form but uses
   applied query values. Put it in the results heading, clearly tied to the report.
8. P2, filter efficiency: add last 7/30/90 day links and a reset, retaining the
   audience for period changes. Preserve the custom range and cohort context.
9. P2, delivery details: eight equally styled rows mix counts, ratios and actions.
   Separate sending states from feedback rates and emphasize measured nonzero
   failures/pending work with text plus restrained semantic styling.
10. P2, status and long metadata: give Sending its own textual badge, separate
    audience/date, make the return route prominent and keep full titles available.
11. P2, touch: actions measure 32px and linked labels 19px. Use 44px action targets
    and complete linked rows without inflating table typography.
12. P2, long content: the audience select expands to 719px and moves actions onto
    another desktop row. Constrain its flexible width; safely wrap titles, numbers
    and URLs. Preserve semantic headings and full accessible text.
13. P2, unavailable data: distinguish no sends, unavailable tracking and a measured
    zero. Never fabricate zero rates or hide measurement limitations.
14. P2, link comparison: make destinations scannable with host/path hierarchy,
    complete selectable URLs, unique-reader emphasis and count-backed indicators.
15. P3, number language: use locale-aware numeric formatting and singular/plural
    campaign labels, retaining right alignment and tabular figures.
16. P3, meter styling: browser-green 64x14px meters imply a success judgement that
    is not measured. Use the existing neutral accent and visible numeric values.
17. P1, zoom: the inherited admin viewport disables scaling. Allow pinch zoom on
    statistics pages while preserving the shared metadata and CSP nonce.

These decisions apply Schoger's hierarchy, action priority, grouping/spacing,
line-length, alignment, color-role, empty-state and table-composition principles.
They do not add new tracking, fabricated delivery metrics, thresholds or revenue data.

## Preserved strengths

- Familiar admin components, restrained palette and readable typography.
- Semantic headings, captions, scoped table headers and right-aligned numbers.
- Active navigation, visible keyboard focus and preserved dashboard back filters.
- Honest consent and transport-handoff limitations; escaped long URLs.
- No document-level horizontal overflow at any of the three measured viewports.

## Independent automated assessment

Twig is not in the detector's supported extensions; its initial empty result is
not a clean measurement. Scanning rendered HTML with inline styles returned 14
flags, mostly global-style/jsdom false positives. Browser detection returned 16
flags: three valid long-line findings, ten Symfony toolbar findings, two acceptable
product-font findings and one inherited-cyan false positive. The contrast failure
was established separately by measuring the actual button colors.

Secondary note text measured 4.759:1 on white, and focus was a visible 2px outline.
The links table measured 405px in 343px on mobile. Chromium could focus the scroll
region and ArrowRight moved it by 40px, so there is no evidenced keyboard trap.
Document titles and heading order passed the live checks.

The visual detection overlay was left in the isolated [Human] Statistics review tab.
The temporary detector server was stopped after collection. Raw detector results
remain outside the repository in the browser tool's temporary directory.

## User impact

The analyst has to scan too many equal metrics before comparing engagement. The
keyboard or low-vision user encounters small targets and quiet delivery problems.
The mobile user reaches no-campaign guidance too late. The intended result is a
shorter path from selecting a period to understanding engagement and inspecting
its links or sending failures, with recoverable errors and clear measurement scope.

## Implementation verification

All 17 findings were addressed. Two independent Chromium reviews covered the
dashboard, sending and completed campaign reports, empty periods, zero sends,
disabled tracking and long titles/URLs at 390, 768 and 1440 CSS pixels.

- Manual reassessment: 34/40; cognitive-load failures: 0/8.
- Primary action contrast: 6.53:1 normal, 8.80:1 hover/focus. The lowest measured
  action contrast was 4.76:1 for inactive period shortcuts. Dark-mode primary
  action contrast was 10.01:1 normal and 12.18:1 hover/focus.
- Action targets: at least 44px. Focus outlines: 2px, offset 2-3px.
- Explanatory prose: maximum 70ch. Pinch zoom remains enabled.
- After refreshing the served assets, mobile row headings occupy 343px of their
  343px row in both tables. No document or table overflow was measured.
- Historical dates, invalid-range recovery, retained audience, reset, CSV scope
  and keyboard disclosure interaction passed live checks.
- The date regression guard passes with the implementation and fails on a copy
  with its input/change synchronization removed.

Browser validation is limited to Chromium. No test emails were sent.
