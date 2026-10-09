# M4 companion regression — 2026-09-19

## M7.2 — shared UI and product polish (Core 1.4.0 / Pro 1.6.0)

- Core `node tests/design-system.mjs` passed focused value/action, heading, status, empty-state and responsive/focus/destructive-style contracts. Primitives contain no request/effect code.
- Both production builds and configured ESLint checks passed; changed PHP bootstrap/dependency files passed `php -l`. No historical engine suites or benchmarks were repeated.
- Pro `tests/m72-browser.mjs` performed the visual pass through Library, Health, Usage, Optimize, Cleanup, AI, Analytics, Advanced, Permissions and Automation at 1440 / 1024 / 390px. No page overflow or scoped WCAG A/AA findings. Read-only filters, settings, candidate/Usage drawers, role selection and the Automation builder retained their handlers. One final eight-module navigation smoke passed with zero runtime errors.
- The pass resumed at Usage after fixing its missing presentation import; already completed Library/Health checks were not repeated. Inspector disclosures were relabeled by context. An Advanced test expected an encoder format before a manual self-test; its expectation was corrected to the actual diagnostics heading. No encoder test was needed for this UI change.
- `tests/m72-corrections.mjs` checked only affected areas: specific Inspector disclosures, AI's single eyebrow and fully loaded list, five existing suggestion cards at desktop/narrow widths, readable Advanced facts, Automation checkbox alignment, Cleanup search alignment and narrow Analytics accessibility. The existing accepted image was selected from All images rather than Needs ALT. Zero runtime/console errors in the corrected checks; no generation, acceptance, deletion or other mutating REST requests.
- Focused consistency/security/performance review: scoped tokens and styles; unchanged capability gates/confirmation callbacks; React-escaped text; no new diagnostic fields or secrets; no per-card requests, new image loading, domain calculations or whole-library filtering. Compared backend files to the preceding archives: Core app files identical; Pro differs only in the minimum Core version check. Schemas remain 3 / 5.
- Evidence: Pro `tests/artifacts/m72-*` (screenshots and JSON reports), excluded from release. Test authentication is revoked after validation. No disposable attachments or new provider calls were required.

Limitations: browser evidence is Chromium on the existing WordPress installation; no new cross-browser matrix. Current history contains one genuine daily observation, so the honest early-history state is shown. Long existing tables keep bounded internal scrolling. Existing data errors and stale AI suggestions remain visible rather than being cosmetically hidden.


Core remains 1.2.0/schema 3. Pro 1.2.0/schema 2 adds the optimizer. Core build/lint, 73 Library and 93 Audit assertions, entitlement, 26 Library/20 Audit browser checks, three pointer checks, four-width visual/axe/keyboard, and lifecycle/index repair passed again. All 76 non-doc/catalog files match the existing Core 1.2.0 archive byte for byte. Pro's full image/recovery/jobs/browser/security/performance evidence is in its docs/TESTING.md. No Core engine or health formula change was needed.

# Milestone 3 platform regression — Core 1.1.1

## M3.1 acceptance — 2026-09-14, Core 1.2.0 / Pro 1.1.0

Baseline tests ran before changes; all final suites below ran against the existing development WordPress installation, sequentially. Evidence is in tests/artifacts/m31-* (not distributed).

| Executed check | Result |
| --- | --- |
| Core and Pro npm run build / npm run lint | Passed |
| Runtime PHP syntax (both app trees) | 70 files passed |
| Core tests/integration.php | 73 assertions |
| Core tests/audit.php, developer entitlement explicitly false in CLI process | 93 assertions |
| Core tests/entitlements.php | Enabled-development fixture passed |
| Core tests/browser.mjs | 26 checks |
| Core tests/audit-browser.mjs | 20 checks, including metadata/decorative/ignore/settings/scan regression |
| Core tests/drag.mjs | Three real pointer/retry checks |
| Core tests/lifecycle.mjs | Retention/reactivation/schema-index repair passed |
| Core tests/visual.mjs | Inspector at 1440/1024/768/390, keyboard/Escape and axe passed |
| Pro existing integration/contracts/browser/lifecycle | 78 / 19 / 17 checks and dependency/entitlement/retention passed |
| Pro tests/ux.php | 113 real REST/query/presentation/permissions assertions |
| Pro tests/ux-browser.mjs | 32 checks, zero runtime errors |
| Pro tests/failure-browser.mjs | Seven checks using a temporary malformed Elementor source, cleaned afterward |

The new browser suite covers count-to-result agreement, paginated 17-location details, real source navigation, grouping, Advanced sources/jobs/logs/rules/developer/copy, Health and SEO drills, Inspector, exact/relative dates including UTC and +05:30, and keyboard Enter/Escape/focus return. Usage/Health/Advanced each passed axe and overflow checks at 1440/1024/768/390. Mobile locations and last-checked cells remain visible; the drawer explicitly traps Tab and Shift+Tab. A real provider failure showed source-specific problems, escaped malicious title text and withheld Not in use. Screenshots were visually reviewed against the established VTX interface.

Core audit-scale.php measured 1,000 real fixture audits in 20 batches: 13.141s, zero failed, 2 MiB memory delta. At 50,000 derived rows, dashboard aggregation was 1.2118s/three queries; last-page findings sorts were 0.732–1.037s/three queries (~18 KB payload). Fixtures use connection-local temporary tables, not a seeded production library. Pro measurements are in its TESTING.md.

Protected hashes verified 31 engine files unchanged; the Pro classification base query is byte-identical to the previous release. Schemas remain Core 3 / Pro 1. Security checks cover unauthenticated/restricted-role reads, server entitlement, invalid filters/IDs/pagination, same-site source actions, executable/external URL rejection, escaped titles and sanitized bounded diagnostics. No arbitrary SQL/path/class input or media deletion/rename/URL mutation was introduced.

### Manual owner acceptance

1. Keep both plugins active. Confirm Core 1.2.0 and Pro 1.1.0 in Advanced → Developer. Owner full access uses `define( 'VTX_MEDIA_DEV_MODE', true );` in wp-config.php before WordPress loads; remove it or set false to disable. There is no browser toggle or license service.
2. Open Usage; run Scan again if coverage is stale. Check the three file totals and separately counted broken locations. Click every summary card, then clear filters.
3. Click a file’s locations, paginate and expand Technical details. Use View/Edit to inspect the trusted source. Open Scan coverage, then View usages or View problems for an area.
4. Open Advanced → Usage Sources, Scans & Jobs, Audit Rules, Logs and Developer. Verify detailed data remains available; these views require administrator capabilities.
5. Open Health; click attention, ALT review and filename/category counts. Open an attachment and Why this score? Edit ALT/save, mark an appropriate empty-ALT image decorative, ignore/restore and re-scan using existing controls.
6. Check the Inspector’s File, Health, SEO & Accessibility, Usage and Organization. Recheck Library folders, favorites, drag/drop and native WordPress media editing.
7. At a narrow admin width, use Tab/Enter, open locations, Shift+Tab and Escape. Confirm focus returns to the opener. Pause/resume/refresh a running scan to confirm persisted progress.

### Limits

Actual environment remains WP 7.1/PHP 8.3/MariaDB 10.11/Chromium. ACF is absent and tested with schema fixtures; this is not a full ACF editor compatibility claim. No Safari/Firefox, physical screen-reader, multisite or minimum-version runtime matrix. Scale timings are local fixture measurements, not universal throughput guarantees or a full 50k-file scan. Index-based results require supported storage and current scans; direct DB/filesystem changes can remain stale. Source groups are paginated and can continue on another page. No destructive Cleanup/Optimization/AI/licensing functionality is present.

Pro 1.0.0 extends Core with Usage Intelligence; its implementation, provider coverage, classifications and benchmark evidence are documented in `../vtx-media-pro/docs/TESTING.md`. Core remains independent of Pro.

Before changes, build/lint, 73 Library assertions, 93 Audit assertions, entitlement checks and 26 Library browser checks passed. After the additive schema-3/job/browser extension changes, these Core checks ran successfully again:

- Build and ESLint; 58 runtime/test PHP files passed syntax checks; POT regenerated without warnings.
- 73 Library integration assertions and 93 Audit assertions.
- 26 Library and 20 Audit browser checks, preserving the native Media Library, metadata, folders, favorites, Unorganized and Audit workflows.
- Three real-pointer drag/error-recovery checks; visual/axe/keyboard checks at 1440/1024/768/390.
- Lifecycle retention and migration/index repair; 24 M1 query-scale cases plus bounded size-index resume.
- Pro lifecycle fixture exercised Core with Pro deactivated and with developer entitlement disabled. Pro activation without Core failed safely; default Pro uninstall retained data.

Evidence: `tests/artifacts/m3-baseline-*`, `m3-final-*`, `final-php-lint.txt`; Pro artifacts include 78 provider/REST/job assertions, 19 extension/classification assertions, browser/lifecycle results and connection-local 1k/10k-source / 50k-attachment-query benchmarks. Live suites ran sequentially and temporary fixtures were removed. No WordPress core, unrelated plugin/theme or wp-config.php source was changed.

M1/M2 historical results and their compatibility limitations remain below. The M3 pass does not newly claim untested browsers, multisite, minimum PHP/WordPress versions, all integration editors or a 50k physical-file throughput guarantee. Audit Health formula and organization semantics are unchanged.

---

# Milestone 2 verification — 2026-09-13

Environment remains WordPress 7.1 / PHP 8.3.22 / Node 22.21 / Chromium. The owner-verified M1 implementation was inspected and documented before edits. Baseline build/lint, 73 integration assertions and 26 browser checks passed before implementation. Site/reference plugins/core/wp-config were not modified.

## Commands and actual results

| Check | Result |
| --- | --- |
| `npm run build`; `npm run lint` | Passed; WordPress React stays external |
| PHP syntax checks across runtime/tests | Passed (58 PHP files at the syntax pass; source targets PHP 7.4) |
| `wp eval-file tests/audit.php --allow-root` | 93 assertions passed |
| `npm run test:integration` | All 73 M1 assertions passed after M2 |
| `npm run test:browser` | All 26 M1 browser checks passed after M2 |
| `node tests/audit-browser.mjs` | 20 Audit workflow/responsive/axe checks passed; independent initial run also verified genuine first-run state |
| `npm run test:drag` | Three real-pointer drag/error-recovery checks passed |
| `npm run test:visual` | M1 layout/inspector at 1440/1024/768/390, no axe violations; keyboard dialog checks passed |
| `npm run test:lifecycle` | Deactivate/reactivate, default uninstall retention and missing-index repair passed |
| `npm run test:scale` | 24 M1 query cases at 1k/10k/50k plus 200+50 size-index resume passed |
| `wp eval-file tests/audit-scale.php --allow-root` | 1k/10k/50k aggregate/pagination benchmark and real 1,000-attachment batch audit passed |
| `wp cron event run vtx_media_job_tick --allow-root` | Core dispatch event executed successfully; audit suite also verifies processing of 12 pending uploads through the cron hook |
| Entitlement fixture, separate default / literal true / literal false processes | Free always available; registered paid feature available only when enabled; unknown denied |
| `wp i18n make-pot ...` | PHP/JS POT regenerated without extraction warnings after translator-comment fixes |

## Audit coverage

Rules: empty/decorative ALT, separator/extension/case normalization, basic generic ALT, valid ALT, empty/generated titles, optional captions/descriptions, camera/download/screenshot/UUID/hash filenames and a descriptive counterexample, valid/missing/unverifiable physical files, optional thumbnail exclusion, missing original, normal/large/very-large bytes, excessive/normal dimensions, malformed/header-mismatched metadata and long ALT.

Health/status: 100 for a complete healthy attachment; optional two-point cost; category caps; strong critical penalty; ignored versus resolved; restore; evidence changes reopen ignores; rule-version changes mark stale/reopen; settings signatures; unaudited/incomplete scores withheld; real coverage partition. Metadata changes during the canonical snapshot cannot be marked current; targeted retry succeeds. Native attachment status and deletion hooks, durable pending work beyond the shutdown limit, and cron queue processing have explicit regressions.

Jobs: duplicate active prevention, one-item checkpoints, pause/resume/cancel, completion, persisted progress, stale heartbeat/lease recovery, per-attachment exceptions, batch-level exceptions and diagnostics. The engine isolates throwing extension rules and does not fabricate healthy scores.

Extensions/security: test-only module/rule/feature registration, paid denial/internal entitlement, module boot, actual rule execution, removal without breaking Core, executable manifest value rejection, nonce and anonymous/author restrictions, per-media authorization, invalid IDs/rules/jobs/actions/settings, traversal and frontend health/developer-setting rejection. Developer mode tests do not modify wp-config.php.

Browser: real persisted full-library progress, refresh while paused, resume/cancel/re-run/complete, issue search/filter, bulk ignore/restore, opening the existing inspector, metadata re-audit, decorative add/remove, focus existing ALT field, threshold persistence, and real visual/accessibility checks at four widths. Additional M1 regression covers the native WordPress Media Library/attachment modal and REST; no duplicate admin application was introduced.

## Measured scale

All large datasets use connection-local temporary clones of WordPress/VTX tables; no synthetic library is inserted into live tables. The final Audit benchmark at 50,000 attachments measured:

- Dashboard: three SQL queries, approximately 1.18 seconds.
- Final findings page (25 items): about 0.52–0.74 seconds across severity/health/filename/recent sorts, approximately 18 KB JSON. Cold pages include two bounded cache-prime queries; warm pages used two queries total.
- Real evaluation/checkpoint run: 1,000 attachments, 20 batches, approximately 12.6 seconds, zero failed analyses. About 2 MiB additional measured memory during scanning; total PHP peak about 148 MiB including this installation's WordPress/plugin stack and benchmark setup.
- EXPLAIN confirms attachment_rule and state_attachment indexes for targeted/pending access. Deep offset sorting/search has measured costs; no throughput SLA is implied.

Artifacts: `tests/artifacts/m2-*.txt`, `audit-scale.json`, `audit-visual.json`, `audit-1440.png` through `audit-390.png`, initial `audit-inspect.png`, and retained M1 artifacts. Full browser screenshots were visually inspected, including first-run and populated Audit screens. Findings now scroll inside a bounded table region; the header, tokens and existing library layout are preserved.

## Remaining verification limits

The synthetic 50k benchmark tests query/index/pagination/memory shape, not a full 50k real-image file scan or concurrent multi-browser stress campaign. Recovery is tested with persisted expired leases and injected failures, not an actual server restart. No multisite, PHP 7.4/WP 6.5 runtime matrix, Safari/Firefox, offload provider, physical assistive-technology session or every WooCommerce/Elementor/Gutenberg editing workflow was available. Destructive uninstall opt-in was reviewed, not executed on this site. Default retention was executed.

WP-Cron continues with site traffic; the authenticated Audit screen also drives work. File changes bypassing WordPress metadata hooks require a re-scan. Threshold changes invalidate the whole audit signature rather than selectively rerunning just affected rules. Duplicate ALT/context/usage/optimization/AI and historical analytics are deliberately deferred.

Run suites sequentially on a development installation. Browser fixtures share artifact filenames and lifecycle tests change plugin activation. Temporary uploads use native WordPress APIs and are cleaned in finally; deliberately unsafe test paths are removed before attachment cleanup even on failure. Failed-run orphan derived rows were cleaned without touching attachments. Live media count returned to 73 after fixture cleanup. Authentication uses a temporary administrator session and must be revoked with `tests/revoke-auth.php` after testing; no passwords are changed. Test/session artifacts are excluded from release archives.

---

# Milestone 1 verification (retained baseline evidence)

Environment: WordPress 7.1, PHP CLI 8.3.22, Node 22.21.0, Chromium through Playwright. The existing site had 73 attachments. VTX Redirect 2.1.1, VTX AI Chat 1.0.4, WooCommerce 10.1.4, Elementor 3.31.3 and the Neve theme were active. No Git repository existed in this directory.

## Commands actually executed
- `npm install`, `npm run build`, `npm run lint`.
- `php -l` for runtime and test PHP files.
- `wp plugin activate vtx-media`, deactivate and reactivate.
- `npm run test:integration`: 73 assertions against real WordPress/REST with temporary test attachments/users/folders, cleaned in finally.
- `npm run test:browser`: 26 browser checks; real database state, no mocked application data.
- `node tests/drag.mjs`: actual pointer drag of selected media and folders, plus injected HTTP 503 and Retry recovery (three checks).
- `npm run test:lifecycle`: persisted folder survives deactivation/reactivation and default uninstall; migration restores a dropped size index and schema version.
- `npm run test:scale`: 24 query cases across 1k/10k/50k synthetic attachments in connection-local temporary tables. Live WordPress tables are never seeded. Additional assertion verifies size indexing processes 200, checkpoints through existing rows, then resumes remaining 50.
- `npm run test:visual`: application-scoped axe WCAG 2 A/AA + 2.1 AA at 1440/1024/768/390; focus enters dialog, Tab stays inside, Escape closes. No axe violations or browser JS errors in the final run.
- `wp i18n make-pot`: regenerated complete PHP/JS translation template without extraction warnings.

## Coverage
Integration: install/upgrade idempotence and four tables; nested folders, rename/order/move/delete, direct counts, self/descendant cycles, missing parents and maximum/subtree depth; paginated folder search/order; single/bulk assignments and explicit Unorganized; personal favorites and user isolation; search, all media types, dates, authors, recent cutoff, six sorts and disjoint pages; real inspector facts and missing-file state; metadata persistence/sanitization/slashes and untouched unrelated metadata/URL; nonce, anonymous/subscriber/author enforcement, mixed-author atomic rejection, invalid IDs/enums/dates/batches/field types and SQL metacharacters; native deletion cleanup and native wp/v2/media.

Browser: existing media, search, type/name/size filters, pagination, grid/list and URL/preference persistence, folder create/nest/edit/reorder/move/delete, lazy expansion, bulk moves, drag/drop highlights and events, real pointer drag, Unorganized, favorites, inspector metadata save/reload, clipboard copy, unsaved edit guard, non-image inspector, mobile navigation, no page horizontal overflow, native Media Library and plugin asset isolation. Test fixtures exercise `wp_upload_bits`, attachment creation and native metadata generation with a real tiny PNG; cleanup uses `wp_delete_attachment` for that test file only.

Visual review: inspected both VTX reference pages and actual media page screenshots. Refinement pass introduced bounded scrolling panels, corrected accessible filter/ALT labels and improved text contrast. Screenshots and machine-readable results are under `tests/artifacts/` (excluded from distribution). Final screenshots are `final-1440.png`, `final-1024.png`, `final-768.png`, `final-390.png`.

## Scale measurements and limits
The initial isolated repository benchmark used five SQL queries per media page and returned at most 48 records. At 50,000 attachments, tested query cases measured approximately 52–333 ms on this server (newest, name, largest, search, Unorganized, folder, favorites and final page). These are synthetic repository timings, not browser/HTTP SLAs, and exclude indexing time and realistic large postmeta distributions. Exact rerun results are in `tests/artifacts/scale-results.json`. Size indexing is bounded to 200 attachments per request, not a full-memory scan.

## Not exercised / genuine limits
- No multisite installation available: per-site behavior and network-activation rejection reviewed in code, not runtime-tested. The destructive uninstall opt-in was not run on this site; default retention was tested.
- PHP 7.4 / minimum WordPress 6.5 not available for a runtime matrix. Actual runtime verification is PHP 8.3 / WP 7.1. Source targets PHP 7.4.
- Chromium was tested; no Firefox, Safari, physical touch device or assistive-technology user session. Axe is an automated check, not an accessibility certification.
- Native Media Library and REST were tested. Complete Gutenberg, featured-image, gallery, WooCommerce and Elementor editing workflows were not all exercised end-to-end; VTX does not hook native media queries, URLs, upload processing or editor selectors.
- Offload/CDN integrations not installed. Local stat is confined to uploads; inaccessible files display an explicit missing/offloaded state and metadata-derived size when available. Files changed externally without WordPress metadata events can leave cached size stale.
- Shared folders are not privacy controls. Authors see their own media; folder names remain shared. Folder depth limit is 32; media batches max 100, folder pages 50, normal media pages 48. Selection is page-scoped. Full-text filename search and deep offset pagination still have linear-cost aspects.

Tests are intended for a development/test WordPress installation. Run suites sequentially, since browser fixtures and lifecycle tests share the installation. Browser authentication uses a short-lived administrator session stored with mode 0600; revoke via `tests/revoke-auth.php` after testing. Existing passwords are never changed.

Additional final checks: native WordPress grid attachment-details modal opened successfully; a browser-only companion inspector section registered through the public hook rendered correctly. Desktop pagination is explicitly asserted to remain within the workspace and viewport after fixing the CSS Grid automatic minimum-size behavior. `npm audit --audit-level=high` reported zero vulnerabilities in the installed development dependency tree.

M3.1 release check: both PHP/JavaScript POT catalogs were regenerated without extraction warnings; translation maps include every current source module and its production bundle.

## M7.4 presentation acceptance — 2026-09-20

Core 1.5.0 / schema 3 and Pro 1.7.0 / schema 5. Pro requires Core 1.5.0.

- Focused `node tests/premium-ui.mjs` (Core): progress zero/one/large/unknown cases, partition guard, textual status/capability states, accessible table headers, row callback, empty state, responsive contracts and request-free primitives. Passed. A whitespace-sensitive CSS assertion was corrected after formatting; no engine test was involved.
- `node tests/ui-preservation.mjs` (Core): parsed request arguments match the prior validated archives across Health, Usage, Optimization, Cleanup, AI, Permissions, Automation and source controls. Passed. No historical engine suite rerun.
- One screen-by-screen Chromium pass (`Pro tests/m74-browser.mjs`): Health, Usage, Optimize, Cleanup, AI and all eleven Advanced tabs at 1440; Health/Optimize/Jobs/Permissions at 1024 and 768; Health/Cleanup/workflow/Jobs at 390. Real content only. Existing provider locations, job/rule detail drawers, local permission toggles, automation draft builder, Library Inspector, optimization settings, Cleanup candidate and AI review opened successfully.
- One navigation smoke across Library, Health, Usage, Optimize, Cleanup, AI, Analytics and Advanced passed. No JavaScript/console errors and no mutating VTX requests in that pass. No media or permission data was saved; no AI/provider request, scan, optimization or destructive lifecycle was initiated.
- Actual screenshots were inspected. Refinements corrected Health-score inherited typography, capability truthy-value display, AI action alignment, duplicate automation empty states, a small-text contrast failure and narrow navigation shifting the workspace. `Pro tests/m74-refinements.mjs` checked affected layouts plus Library/Analytics compatibility: no remaining scoped axe WCAG A/AA findings, page overflow or workspace offset. No broad browser matrix rerun.
- Both package builds and configured ESLint passed; changed PHP bootstrap/compatibility files passed `php -l`. Browser PHP server log contained no PHP errors/warnings. Schemas and all engine PHP remain byte-identical to prior archives; the only Pro service-file change is its minimum Core UI version.

Focused preservation review: capabilities/entitlements and permanent-delete confirmation are untouched; existing request argument contracts are retained; no arbitrary actions or HTML injection were introduced. Diagnostics show existing sanitized fields, never credentials. Focused performance review: no API-per-card, new scans or large images; request-free primitives, existing 25-job/50-log pagination, seven provider rows and selected detail drawers bound rendering. No benchmarks or fixtures.

Limits: logs search and job status filters cover the current bounded page and are labeled accordingly. Complex tables use internal horizontal scrolling. The real site has no configured automation/history, so visual acceptance covered its honest empty state and unsaved workflow builder; execution engines were not rerun. Visual owner review remains the next product step.
