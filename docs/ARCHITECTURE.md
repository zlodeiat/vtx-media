# Architecture

PHP 7.4+ / WordPress 6.5+. Namespace `VTX\Media`, local PSR-4-style autoloading, no runtime Composer dependency. WordPress supplies React, translation and hook runtimes; esbuild compiles the application. Assets load only on Media > VTX Media. Native media queries/uploads and attachment URL helpers are untouched.

## Composition and ownership

`Plugin` composes the existing Policy, FolderRepository, MediaRepository and FileFacts with feature/module/rule/job registries, centralized entitlements, AuditEngine, settings, query service, incremental invalidation, logger and JobRunner. Core registration precedes the extension hook, entitled module boot and `vtx_media/ready`. See EXTENSIONS.md for executable contracts.

Canonical data remains WordPress attachment posts, ALT/attachment metadata, file paths and URL helpers. VTX-owned organization is virtual. Derived audit findings, filename sort cache, health/signature and scan checkpoints are explicitly rebuildable. The inspector projects canonical data with authorized audit results; no entire attachment record is duplicated.

Folder mutations still use site-scoped advisory locks and InnoDB transactions. Delete promotes direct children and unorganizes direct media; depth/cycle checks and existing folder ordering are unchanged. Personal favorites remain keyed by user. M1 file-size indexing still processes at most 200 missing facts per request; it is separate from auditing because it supports an existing library sort. The Audit file-source interface adds original-file verification and optional header facts without changing M1 browsing.

## Audit and jobs

Rules are independent classes under Audit/Rules and implement AuditRuleInterface. Registry IDs/version/type/feature definitions drive execution; the engine does not instantiate client-selected classes. Each attachment audit isolates rule exceptions, preserves findings for failed rules, atomically updates successful results, and calculates health. Incomplete analysis has no current score. Finding wording is localized when presented; only evidence and identifiers persist.

Relevant attachment/meta hooks mark a health row pending and increment its revision. Up to ten locally changed attachments run at shutdown; remaining pending rows are processed in bounded cron batches. Explicit M1 metadata saves audit the affected attachment before their final detail response. Batch revisions are captured before priming canonical post/meta caches; standalone audits refresh their canonical cache after capturing revision. Rule/settings signatures are captured with the evaluation configuration. Revision-conditional health writes prevent a concurrent metadata change from being marked current accidentally. Rule/settings signatures expose stale data without mass synchronous updates.

Jobs use snapshot max ID + author scope and keyset enumeration. Every attempted attachment checkpoints progress. Site/attachment/job advisory locks, unique active keys, expiring leases and tokens prevent duplicate workers and recover after interruption. WP-Cron runs bounded work with site traffic; authenticated Audit screen ticks use the same runner for immediate feedback. No uncontrolled loopback recursion or external queue is required. A future queue can invoke/replace dispatch around the shared registry contracts. Pausing or cancelling stops future work; completed findings survive.

## REST and policy

`RouteRegistrar` owns namespace vtx-media/v1, upload capability, write nonce and schema conventions. The existing Controller delegates route setup to it. AuditController adds audit/settings/actions and job routes without changing M1 payloads. Shared WP_Error envelopes carry localized message/code/status. AuditQuery uses SQL aggregation, scoped joins, cache priming and bounded pages. Settings require manage_options; media remediation additionally requires edit_post for every attachment. Jobs execute as their originating user and recheck capability availability.

## Frontend

`App.jsx` preserves the M1 browser and inspector and adds manifest-driven workspace navigation. `api.js`, `library.js`, components/ui, folders, AuthorFilter and Inspector retain their responsibilities. `extensions.js` registers real components from enqueued scripts. PHP manifests contain structured data only. AuditScreen composes ScanPanel, IssueBrowser and SettingsPanel; HealthSection uses the existing inspector extension slot. Editing actions focus the existing fields. No duplicate metadata form or separate React runtime.

M1 pages are bounded to 48/100 items; findings to 25/100; bulk audit actions to 50 IDs. Folder branches stay lazy. Audit filters/search/sort run server-side. Grid/list, existing URL filters, drag/drop and accessible folder menus are preserved. Audit navigation survives refresh; findings filters are local to the Audit screen. The score formula and limitations are visible in the UI.

## Compatibility and limitations

Per-site activation, no network activation. Deactivation pauses jobs and clears dispatch, retaining data. Re-enable to resume deliberately. Uninstall defaults to retention. Local storage verification only; unknown sources produce incomplete health. Usage Intelligence is available through the separately installed Pro 1.0.0 add-on; Core remains independent. No cleanup, optimizer, AI, advanced duplicate ALT, historical warehouse or licensing system. See DATABASE.md, SECURITY.md, AUDIT-ENGINE.md and TESTING.md.

## M3 platform additions (Core 1.1.1)

Pro extends existing module, entitlement, REST, React and Inspector/settings contracts. WorkJobInterface generalizes handler-owned source cursors while retaining the existing runner, authorization, leases, checkpoints and diagnostics. Nullable job/log context is additive schema 3; Audit remains attachment-based. Type-specific latest-job queries keep Audit and Usage scan state separate. Shared browser transport/UI helpers are exposed through the existing extension object. No Usage provider/classification logic lives in Core.

## M3.1 presentation architecture (Core 1.2.0)

Simple by default, advanced on demand. Core owns the shared Advanced screen and read-only `/advanced/overview`, `/advanced/jobs`, `/advanced/logs`, `/advanced/rules` views. These reuse existing registries, permissions and job/log tables; no second engine or schema migration. Access requires `manage_options` and the existing all-media policy in addition to normal REST authentication.

`components/presentation.jsx` supplies native-dialog Drawer (focus return/Escape), TechnicalDetails, RelativeTime and Pager. `extensions.js` accepts trusted enqueued React components for Advanced sections and ordered Inspector slots (`health`, `seo`, `usage`, `organization`, `optimization`, `advanced`). No executable data arrives through PHP manifests. The legacy Inspector hook is retained. The empty optimization slot renders nothing until an actual module registers a component; future actions belong inside that component and must use server-authorized endpoints.

Health card queries use `current=true` to match dashboard signature/state scope. `group=priority|recommendations` is an indexed SQL filter; `pagination.media_total` distinguishes files from multiple findings. Optional historical/stale results remain available by disabling Current results only. Health weights, rules and mutation workflows are unchanged.

## M4 companion integration

Pro 1.2.0 adds Image Optimization through existing Core feature/module/job/REST/Inspector/Advanced contracts. Core remains 1.2.0/schema 3 and has no optimizer dependency. The companion owns its schema 2, recovery, representations and delivery. Canonical file facts/Audit remain authoritative; a smaller delivery derivative does not silently change canonical health deductions. See Pro docs/OPTIMIZATION-ENGINE.md for actual safe delivery and recovery boundaries.

## Pro M5 boundary

Cleanup is Pro-owned and consumes the existing Usage evidence/query, Optimizer ownership/shared attachment lock, Core facts/Audit and job contracts. It adds no Core runtime/schema dependency. Its nonpublic in-place Quarantine preserves attachment identity and files, with manifest-controlled explicit permanent deletion. See Pro docs/CLEANUP.md and QUARANTINE.md. Core Health scoring is unchanged.
