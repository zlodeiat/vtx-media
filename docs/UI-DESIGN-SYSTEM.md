# VTX UI design system — M7.4

Core owns `components/design.jsx`, `components/semantic.jsx` and the scoped `design.css` / `premium.css` layers. Pro consumes primitives through `window.vtxMediaExtensions.ui`; it does not ship another table or progress implementation. The M7.3 Analytics dashboard is the visual reference, not a universal card template.

## Identity and hierarchy

- Application background `--vm-bg` (#f0f3f7), white content surfaces, muted diagnostic groups. Use light borders and minimal elevation; never fixed empty card heights.
- Dark text `--vm-ink` (#172536), readable secondary `--vm-muted` (#586a7d), borders `--vm-line` (#e4e9f0).
- VTX orange is an eyebrow/small accent. Readable orange `--vm-accent-ink` (#a63c16) supports small text; bright brand orange is decorative.
- Blue `--vm-blue` (#3458db) identifies actions, selection, focus and informational progress. Green success, amber review, red failure/destruction, slate neutral. Every status has text.
- Page titles 30px (26px narrow), metrics 34px, section titles 18px, body 13–14px, secondary data 12px. Do not shrink important facts to fit a wide empty surface.
- Main surfaces use 12px corners, smaller groups 8–10px. Layout gaps 18–24px; compact records 10–14px cell padding. No heavy shadows or animation.

## Choose the pattern for the information

| Pattern / implementation | When to use | When not to use |
| --- | --- | --- |
| Dashboard / KPI — `MetricCard`, joined KPI strip | A few authoritative headline values with brief context and a meaningful drill-down | Every diagnostic fact, every setting, or overlapping values presented as a total |
| Section — `PageHeader`, `SectionHeader`, `Card` | Clear page/section hierarchy; a bounded group of dashboard information | Wrapping an already grouped table in multiple extra cards |
| Status panel — `.vm-status-panel` | Latest Audit, Usage, Optimization, Cleanup or AI run: status, counts and existing controls in one compact band | A gallery of historical jobs or a large empty completion announcement |
| Progress / distribution — `Progress`, `DistributionBar` | A known ratio or disjoint status partition using supplied counts | Unknown denominators, overlapping storage categories, invented eligibility or score thresholds |
| Media row — existing `Thumb` and module media rows | Recognizing media before review, restoration or metadata decisions | Generic diagnostic records; never replace visual duplicate comparison with text-only rows |
| Data table — `DataTable` | Providers, audit rules, logs and aligned comparable records | A handful of unrelated headline metrics or a settings form |
| Matrix — table with scoped headers and capability cells | Cross-role permissions; processor × format encode/decode support | One oversized card per role/format or icon-only availability |
| Activity list/table — `DataTable`, `RecordStatus`, optional `Progress` | Jobs and automation history, with existing pagination and one selected details drawer | A technical disclosure and tall card for every historical record |
| Workflow — compact `.vmi-step` fieldsets | WHEN event → IF conditions → THEN ordered actions; existing registries own choices | A node editor, duplicate condition language, or a builder repeated under each row |
| Diagnostic group — `.vm-diagnostic-grid`, `DataList` | Small related system/provider/eligibility facts | Long flat reports or giant cards with fewer than five short lines |
| Grouped form | Settings and explicit local permission edits | A card per checkbox or automatic saving introduced by presentation |
| Disclosure/drawer — existing `TechnicalDetails`, `Drawer` | Secondary nuance, individual record details, focus-managed review | Essential safety warnings hidden from the normal confirmation |

## Contracts and data truth

All shared primitives are request-free. They receive values and callbacks; they do not decide eligibility, authorization, classification or storage totals. Progress clamps visual geometry and labels its denominator. Distribution refuses an overlapping sum; unknown/zero denominators do not become fictional percentages. Health score uses informational blue because no new qualitative score bands are defined.

Usage's broken-reference **locations** remain separate from attachment classifications. Optimization coverage includes complete/partial image records, not a newly inferred eligible total. The storage comparison uses matching before/after image-family representations. Reduction is not disk savings. Cleanup distinguishes potential recovery, still-stored Quarantine and permanently removed bytes. AI counts can overlap and tokens are not monetary cost. Analytics retains genuine snapshots only.

## Tables, actions and accessibility

`DataTable` includes a caption, column/row headers, empty state and keyboard-focusable overflow region. Jobs retain 25-record pages; logs retain the existing 50-record cursor pages. New job status and log search filters explicitly apply to the loaded page; Problems only remains a server filter. No global search or synthetic level field is implied.

The permission matrix compares roles while only the selected role is editable; its existing explicit Save applies that role. Administrator protection and server capability checks remain authoritative. Unknown processor support stays Not verified; absent/unavailable/zero are distinct.

Blue filled buttons perform primary actions; neutral outline is secondary; compact table links retain button semantics for callbacks. Permanent deletion stays red, secondary to Restore and explicitly confirmed. Drawers retain Escape, focus trap and focus return. React escapes media names, rule text and diagnostics.

## Responsive and density rules

KPI strips: 4 → 2 → 1. Dashboard sections: 2 → 1 when reading space becomes tight. Tables and matrices scroll internally; permissions retain the identifying column. Primary/Advanced navigation scrolls inside its own region rather than shifting the workspace. Check 1440, 1024, 768 and 390px.

Library keeps its three-panel management layout. Inspector uses compact sections, not dashboard cards. AI Current and Suggested use distinct surfaces. Settings remain forms. No new motion or dependencies are needed. Preserve selected filters, Smart Folder preferences, job state, callbacks and capability gating.
