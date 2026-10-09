# Security and data lifecycle

## Authorization and inputs

All vtx-media/v1 endpoints require upload_files through Policy. Cookie authentication uses WordPress REST nonce handling; write routes additionally verify X-WP-Nonce against wp_rest, preserving M1 behavior for all callers. RouteRegistrar is shared by Core and extensions. Schema callbacks validate/sanitize IDs, enums, search lengths, thresholds and batch sizes. Settings writes require manage_options. Reads are author-scoped unless edit_others_posts permits broader access; all remediation requires per-attachment edit_post. Every bulk result reports success/failure; one inaccessible attachment is not silently skipped.

Jobs are controlled by their owner or manage_options users. Workers execute as the owner and recheck capabilities and registered job entitlement. Client values never select class names or filesystem paths. Feature/rule/job identifiers resolve through registries. SQL values are prepared, identifiers and sorts are server allowlists, and categories are escaped values. Stored scores/severity/file evidence cannot be submitted by clients.

## Metadata and files

Canonical edits use wp_update_post and post-meta APIs. Existing M1 text/HTML sanitization remains intact; React renders strings without raw HTML. Audit evidence contains bounded machine values and localized presentation is generated on read. Inspector actions reuse the existing metadata form.

Decorative intent explicitly clears ALT and stores `_vtx_media_decorative`; browser confirmation explains this. No HTML in existing posts is changed. Entering non-empty ALT removes decorative intent. Folder deletion never deletes media. No file deletion, renaming, compression, original overwrite, content rewrite or URL rewrite is implemented.

LocalSource derives paths exclusively from trusted WordPress attachment/upload configuration. It rejects stream URLs, traversal, paths outside uploads and escaping symlinks; missing paths are checked through their nearest existing real parent. Original-image names must be simple basenames. Header/stat reads occur only after confinement/readability checks. Optional thumbnails are not integrity requirements. External/unverifiable sources are unknown and prevent a current health score; no remote HTTP probe occurs. A custom trusted FileSourceInterface can support later storage providers.

## Consistency and jobs

Folders retain M1 advisory locking/transactions. Audit attachments have separate advisory locks; successful rule findings and health persist atomically. Ignore/restore reads current state under lock and commits the score with status. Evidence/version fingerprints prevent changed findings inheriting old ignores. Pending revision checks stop concurrent metadata changes from being marked freshly analyzed. Existing findings survive scan interruption and failed rules.

A unique type/scope active key prevents accidental duplicate scans. Workers use advisory locks, 90-second leases and fencing tokens. Checkpoints record each attempted attachment; retries after a killed worker are idempotent. Pause/cancel stop future work and terminal updates condition on running state. Stalled heartbeats are visible after 120 seconds and expired workers can resume. Jobs have no client-defined executable code and no recursive requests. WP-Cron requires site traffic or normal cron invocation; the Audit screen can drive bounded authenticated batches immediately.

Rule exceptions produce incomplete health and identifier-only diagnostics. Unknown/failed/stale media never receive a fabricated 100 score. Logs record event/rule/attachment IDs, not ALT content, absolute paths, SQL, stack traces or exception messages. Retention trims old events and targets about 5,000 rows; high-volume emission runs bounded maintenance.

## Development entitlement

Only literal `VTX_MEDIA_DEV_MODE === true` enables development grants. Default false; no normal option, REST setting, request parameter, domain, username or JavaScript value can enable it. Unknown features stay denied. WordPress permissions still apply. Future licensing supplies a trusted entitlement source; there are no commercial calls or keys today. See DEVELOPER-MODE.md.

## Lifecycle

Deactivation pauses active jobs, clears Core cron dispatch and retains data. Reactivation does not duplicate tables or automatically resume paused jobs. Default uninstall retains data. Deliberate `VTX_MEDIA_DELETE_DATA === true` removes eight Core tables, schema/audit-settings options, the decorative intent key and Core cron event. It never deletes attachments, canonical ALT or other metadata, or files. Multisite uninstall iterates sites in bounded pages; activate per site, not network-wide. Pro owns only its own module-specific storage and must never remove Core data.

## Review boundaries

Trusted installed PHP/JavaScript extensions can change policy or behavior; this is an extension API, not a sandbox. Outside filesystem changes without a WordPress hook require a re-scan. Local storage only is supported today. Core makes no unused-media claim. Pro Usage uses its own conservative classification/coverage boundary; AI, optimization and cleanup operations remain absent. Automated tests cover anonymous/author restrictions, nonces, malformed inputs, traversal, unknown IDs, immutable browser entitlements, lifecycle and concurrency recovery; see TESTING.md.

Core 1.1.1 additionally authorizes WorkJobInterface handlers at start/dispatch. Pro source jobs do not pass source IDs to attachment policy; their explicit handler authorization owns that boundary. Job context stays server-side; bounded diagnostic context exposes identifiers, not private source content. Shared job management checks current handler entitlement.

## M3.1 read-only diagnostics and drill-downs

Advanced endpoints require `manage_options` plus the existing all-media policy. Browser visibility is not authorization. Job types are registry-checked, pagination is bounded, logs use cursor pagination, and all dynamic SQL values use prepared parameters. Job execution context, active lock keys and lease tokens are omitted. Logs expose existing sanitized event/source/reason identifiers, never exception payloads or full site content. Support export includes version/schema/module/feature identifiers and entitlement booleans only.

Health card filters are server validated; the browser cannot submit scores. Native metadata edits and decorative/ignore workflows retain existing nonce/capability enforcement. No new deletion, rename, optimization, arbitrary filesystem or source-writing operation is added.
