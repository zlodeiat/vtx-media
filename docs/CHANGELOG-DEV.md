# Development log

## M7.4 — Core 1.5.0 / schema 3

- Premium semantic presentation layer: accessible DataTable, RecordStatus, CapabilityStatus, Progress and DistributionBar, exported through the existing extension UI contract.
- Health gains score/category visualizations and a compact latest-check band. Advanced uses job/log/rule tables and selected-record drawers; system/developer diagnostics use compact groups.
- Scoped Analytics-derived surfaces, typography and action hierarchy. Primary navigation now scrolls locally at narrow widths, preventing workspace shifts. Library structure and Inspector behavior remain intact.
- All engine/service PHP and schema definitions unchanged. No data migration. See UI-DESIGN-SYSTEM.md and TESTING.md.


## M7.2 — Core 1.4.0 / schema 3

- Shared PageHeader, SectionHeader, Card, MetricCard, DataList, StatusBadge and EmptyState exported through the existing UI extension contract.
- Scoped shared tokens and responsive panels unify action/selection blue, readable orange section accents, forms, tables, statuses and drawers. Library layout and preferences remain intact; Health uses shared summaries and sections.
- Inspector technical disclosures have specific labels. Existing handlers, capabilities, focus management and all engines remain unchanged. No schema migration.

- Focused component assertions, ten-area responsive visual pass, affected correction checks and one final navigation smoke passed. See TESTING.md; no provider/destructive calls or historical test reruns.

## M7 shared contracts — Core 1.3.0 / schema 3

- Add generic action/route/job policy hooks, server query/collection label hooks, configurable module visibility and a Library-sidebar extension slot.
- Organization/metadata services and Inspector controls honor action permissions. Audit exposes a read-only current findings projection for indexed collections. Core remains usable without Pro and imports no Pro services.
- Pro 1.5.0 owns Smart Folders, role mapping, Automation, Analytics and all new persistence. Core schema remains 3; no Audit scoring or engine rewrite.
- Core build/lint and PHP syntax passed; focused M7 permission tests and final Library/Health/Usage/Optimize/Cleanup/AI/Analytics/Advanced browser smoke passed. No historical suite or scale benchmark repeated.

## 2026-09-14 — M3.1 complete: Core 1.2.0

- Adopted “Simple by default, advanced on demand” across Library Inspector, Health and the shared application. Primary navigation is Library, Health, entitled Usage and administrator Advanced; future modules have no placeholder screens.
- Added shared TechnicalDetails, timezone-aware RelativeTime, focus-managed Drawer and Pager; trusted JavaScript Advanced and ordered Inspector section registration preserve the legacy hook. Pro extends the same application.
- Health summary/category/rule/SEO drills now use the same current-result scope as their counts, with distinct affected-file totals. Optional metadata is secondary; score deductions remain inspectable. Inspector brings together real file facts, Health, SEO & Accessibility metadata/review, Usage, Organization and contextual technical information.
- Added read-only, capability-protected Advanced overview/rules/jobs/logs over existing infrastructure. Logs are bounded and support information excludes secrets, private content and filesystem paths. Failed scan counts open that scan’s existing diagnostics.
- Core schema remains 3; no migration. Audit rules, health formula and workers remain unchanged. Extension APIs justify the minor version; Pro 1.1.0 requires this shared platform.
- Build/lint, PHP syntax, Library/Audit/entitlement, browser, pointer, lifecycle, responsive/axe and isolated scale regressions passed. M3.1 additionally verifies exact count drills, source navigation, permissions, failure states, keyboard focus and dates. See TESTING.md for actual counts, measurements and limitations.
- Security/performance review retained server-authoritative scores/classifications, prepared indexed reads, bounded pagination and capability checks. No destructive media/source actions or new commercial entitlement mechanism. Next: M4 Image Optimization, followed by M5 Cleanup/Duplicate Intelligence.

## 2026-09-13 — foundation decisions
- Empty plugin directory, no Git repository or existing project manifests/instructions on disk. WordPress 7.1, PHP 8.3, Node 22; VTX Redirect 2.1.1 and VTX AI Chat 1.0.4 active. Elementor/WooCommerce also active. References inspected without modification.
- Product roadmap and actual M1 architecture documented before implementation.
- Preserve attachment IDs/URLs and WordPress metadata ownership. Shared single-folder membership; personal favorites. Do not reverse these invariants without a migration.
- Safe delete: children move to parent; directly assigned media becomes Unorganized. No attachment deletion.
- Four purposeful indexed tables, including rebuildable byte-size facts needed for accurate global sorting. No future scanner/optimization tables or empty feature modules.

## 2026-09-13 — Milestone 1 implementation and verification
- Implemented namespaced autoloaded composition root, versioned dbDelta schema, capability policy, bounded REST v1, attachment projection, shared nested folders, memberships, personal favorites and derived file-size facts.
- Every mutation validates capabilities/nonces and attachment IDs. Folder/assignment writes use a per-site advisory lock and transaction; cycle/depth checks run inside the lock. Schema verification checks columns and required indexes before advancing its version.
- Implemented VTX family header/tokens and modular React application: lazy folder branches, folder editor/destination picker, grid/list, search/type/month/author filters, six sorts, paginated media, page selection, bulk moves/favorites, pointer drag/drop, inspector/copy URL and native metadata edits. Dirty fields alone are sent; navigation warns about unsaved changes.
- Preserve the architectural decisions above: no physical moves, no attachment deletion endpoint, no URL filters, no native Media Library query modifications, no proprietary canonical attachment store, no future-feature UI, no scheduled scanner.
- Browser review of both reference products informed the final design. Refined panel scrolling, mobile navigation, form names, contrast and reduced-motion behavior. Promotional notices from the current theme are hidden only on the VTX Media screen; errors remain visible.
- First integration run exposed missing default REST schema validation callbacks; fixed and retested. Browser runs exposed accessible names including helper text; fixed explicit control labels. Axe exposed low-contrast family text shades; corrected within the same palette.
- Final functional suite: 73 integration assertions, 26 browser checks, three real-pointer/error-recovery checks, lifecycle retention/upgrade checks, four responsive/axe widths, and isolated scale/batch-resume tests. Full evidence and untested compatibility matrix are in TESTING.md.
- WordPress native upload/attachment generation used only temporary fixtures, deleted with WordPress APIs. Existing site media and reference plugins left unchanged. Plugin remains activated. Default uninstall preserves data; opt-in removal never touches attachments.
- Shipped compiled assets, source modules, POT/template map, product/architecture/database/security/design/test documentation and a distributable archive excluding dependencies, test fixtures, browser sessions and development artifacts.
- Added a deliberate companion-plugin inspector slot using native WordPress JS hooks and an admin enqueue action. Extensions can add actual panels and authorized derived data without replacing services or editing core; the default app renders no extra section.
- Final screenshot review caught CSS Grid's automatic minimum track size expanding the desktop browser beyond its panel. Added `minmax(0, 1fr)` for the row and zero minimum child heights, plus an explicit pagination-in-viewport assertion. Rechecked responsive layouts and the complete browser suite.
- Confirmed the native WordPress attachment-details modal still opens, and tested a companion React inspector section through the public hook. Removed temporary fixtures; the existing attachment count remains 73.


## 2026-09-13 — Milestone 2: Media Audit & Health
- Preserved the owner-verified M1 design, library controls, organization schema and canonical WordPress behavior. Baseline build/lint, 73 integration assertions and 26 browser checks passed before changes.
- Released Core 1.1.0 / schema 2: additive findings, health, jobs and job_logs tables; no folder/favorite reset. Default retention remains; explicit opt-in uninstall now also removes Core audit/settings/decorative intent while retaining native media and metadata.
- Added centralized features/entitlement sources and literal server-only VTX_MEDIA_DEV_MODE. No licensing calls, production toggle, hostname/username exceptions or distributable Pro plugin. Actual module/rule/job/REST/React/inspector/settings/source contracts are documented and tested with an internal extension fixture.
- Implemented twelve versioned Core rules: missing/unreviewed ALT, filename ALT, generic ALT, missing/generated title, optional caption/description, poor filename, local/original integrity, large/very-large image, excessive dimensions, invalid image metadata and long ALT. Local files are uploads-confined; optional thumbnails do not count as broken originals. Unverified sources remain incomplete.
- Findings retain structured evidence, normalized confidence, severity, version and timestamps. One current row per rule/attachment; ignored state survives only identical version/evidence. Resolved/ignored issues remain discoverable. Shared decorative intent is explicit; adding ALT clears it. No file/post-content changes are automated.
- Transparent health uses severity weights 60/20/10/3/1 with category caps (Files 80, Accessibility 30, Performance 25, Metadata 8; others 20). Optional caption+description cost two points. Unknown/stale/pending/failed analysis has no current score. Library mean is accompanied by independent serious-issue counts and coverage.
- Added bounded persistent jobs: max-ID/author snapshots, per-item checkpoints, active uniqueness, advisory locks, leases/tokens, stale recovery, diagnostics, pause/resume/cancel, WP-Cron and authenticated browser dispatch. Failures are isolated and bounded; scans never clear previous findings at start. Deactivation pauses jobs and retains state.
- Incremental auditing captures revisions before priming canonical caches, captures settings/rule signatures with evaluation, and conditionally commits health. Fixed native attachment status validation (WordPress get_post_status resolves inherit), metadata-read races, terminal scan/control races, and deletion hooks re-queuing disappearing attachments. These decisions have regression tests and must not be reversed casually.
- Extended the existing workspace with actual Audit navigation, dashboard, coverage/category/rule/severity summaries, paginated/searchable/filterable issues, scan progress/controls/diagnostics, thresholds and safe actions. Inspector health reuses existing metadata fields. Bulk status/intent/rescan actions report each failure. Screens use existing tokens and WordPress React; no fake locked modules.
- Validation: 93 Audit assertions, 73 M1 integration assertions, 26 M1 browser checks, 20 Audit browser checks plus first-run capture, three pointer/error-recovery checks, lifecycle/cron checks, four-width axe/visual checks, default/enabled/disabled entitlement fixture, PHP syntax and build/lint. POT/source map regenerated. See TESTING.md for exact commands and compatibility limits.
- Performance: connection-local 1k/10k/50k query datasets and real 1,000-attachment rule/checkpoint execution. Bounded cache priming avoids attachment-query growth per page/batch. Dashboard aggregates combine into three queries; no full findings array is loaded. Current measured 50k dashboard ~1.18 s, final page ~0.52–0.74 s; 1,000 audits ~12.6 s/20 batches/+2 MiB measured scan memory. These are synthetic measurements, not a 50k real-image throughput guarantee.
- Deliberately deferred: Usage Intelligence, cleanup, file renaming, optimization, AI, advanced/duplicate/contextual ALT, selective per-rule threshold refresh, scheduled audit policies, history warehouse and commercial licensing. Storage is local-only; cron needs traffic; minimum-runtime/multisite/other-browser matrices remain untested. No Milestone 3 work was started.


## 2026-09-13 — Core 1.1.1 platform support for Pro Usage Intelligence
- Kept Core independent of Pro and preserved all M1/M2 behavior/design/health scoring. Added WorkJobInterface as an opt-in extension for source-centric jobs; existing Audit JobInterface and attachment authorization/cache path are unchanged.
- Schema 3 adds only nullable context fields to existing jobs/job_logs. No Core tables, folders, favorites or Audit records were reset. Shared logs accept bounded source identifiers/reason codes. Runner retains locking, leases, checkpoints and entitlement/capability checks; latest-job lookup can be scoped by type so Audit and Usage controls remain distinct.
- Exposed the existing browser transport/config/UI helpers through the extension object. Pro adds its own screen/Inspector/settings components inside the same React app. REST errors now retain status/code for explicit worker-contention handling.
- Pro 1.0.0 owns its four Usage tables/providers/aliases/classification and requires Core >=1.1.1, <2.0.0. Core remains usable without Pro or with developer entitlement disabled. Default retention and dependency failure were executed, not only reviewed.
- Baseline and final regression: build/lint, 73 Library assertions, 93 Audit assertions, 26 Library browser checks, 20 Audit browser checks, three drag/error-recovery checks, lifecycle/migration/default retention, four-width axe/keyboard checks and 24 scale-query cases plus bounded index resume passed. Pro adds 78 integration / 19 extension / 17 browser checks and measured 1k/10k-source, 50k-attachment-query benchmarks. See Core and Pro TESTING.md for evidence and limits.
- M3 is complete in the separate Pro plugin. M4 Cleanup & Duplicate Intelligence is next; Core has no dependency on Usage and no new deletion/optimization/AI workflow.

## M4 companion release — 2026-09-19

Core runtime remains 1.2.0/schema 3. Pro 1.2.0 adds the optimizer using existing extension contracts. Core documentation/roadmap now link the implementation and verification. No Core runtime/version/schema bump is needed for companion functionality. Existing 1.2.0 archive is preserved; any refreshed documentation archive has a distinct filename.
