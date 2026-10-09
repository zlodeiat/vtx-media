# Database (Core schema 3)

`vtx_media_schema_version` is non-autoloaded. dbDelta adds/upgrades InnoDB tables using the site's prefix/collation; verification checks columns and required indexes before advancing the version. Version 2 added four tables; version 3 adds nullable job context and diagnostic context without new Core tables; all version 1 tables and their organization/favorite data remain intact. Repeated activation/installation is idempotent. No destructive reset or foreign-key migration.

| Table suffix (after vtx_media_) | Data | Keys / indexes |
| --- | --- | --- |
| folders | id, parent_id, name(191), position, created_at, updated_at | PK id; parent_order(parent_id,position,id); name |
| memberships | attachment_id, folder_id | PK attachment_id; folder_media(folder_id,attachment_id) |
| favorites | user_id, attachment_id, created_at | PK(user_id,attachment_id); attachment_id |
| facts | attachment_id, nullable bytes, checked_at | PK attachment_id; size_order(bytes,attachment_id) |
| findings **new** | id, attachment_id, rule_id(64), rule_version(32), category(40), severity(12), numeric severity_rank, confidence decimal(4,3), kind(16), fingerprint(64), JSON evidence in data, status(12), audited_at, updated_at | PK id; UNIQUE attachment_rule(attachment_id,rule_id); attachment_status; rule_status; category_status; severity_status(status,severity_rank,id) |
| health **new** | attachment_id, cached basename filename(255), state, nullable score, signature(64), revision, JSON error codes, audited_at | PK attachment_id; state_attachment(state,attachment_id); signature_state; score_order |
| jobs **new** | id, registered type, owner_id, scope_author, max_id, last_id, total, processed, failed, skipped, status, nullable active_key, batch_size, lease_token, lease_until, heartbeat, created_at, updated_at, completed_at, nullable JSON context | PK id; UNIQUE active_key; status_updated; owner_id(owner_id,id) |
| job_logs **new** | id, job_id, attachment_id, rule_id, event code, nullable bounded JSON context, created_at | PK id; job_event(job_id,id); retention(created_at) |

All timestamps are UTC. Categories/statuses use validated string identifiers rather than database enums. Findings store one current record per rule/attachment; resolved rows may later reopen. English labels/descriptions do not persist. JSON evidence is bounded to 12 KB per finding. Fingerprints encode rule version and relevant evidence; ignored state survives only identical fingerprints. Health is derived from authoritative findings, never client-submitted scores. Filename cache exists only for issue search/sort, refreshed with each audit.

No health row means not analyzed. Pending revision invalidates the current score. Failed analysis carries diagnostic rule IDs/codes. A rule/settings signature mismatch means stale even if the stored prior score remains. Dashboard health averages current complete rows, while current known open findings (including partially analyzed attachments) remain visible in issue counts.

Jobs checkpoint each attempted attachment. Failed is a subset of processed. Skipped counts snapshot media no longer eligible when the scan finishes. Null active keys allow retained terminal job records; active uniqueness prevents duplicate type/scope scans. Logs contain identifiers and event codes, not paths or exception traces; maintenance removes records older than 30 days and trims toward 5,000 records in bounded deletes. High-volume event emission invokes maintenance every 100 events. Job records persist as small manual scan records; no history warehouse.

## Canonical versus VTX data

WordPress attachment posts/meta/files remain canonical. Folders and favorites are Core organization data. `_vtx_media_decorative` is a Core-owned post-meta intent flag; it does not modify HTML. Marking decorative clears WordPress ALT explicitly; entering ALT removes this intent. Findings, health, facts and job state are derived Core data. No URLs or attachment paths are rewritten.

Direct folder counts include visible non-trash media, not descendants. Native attachment deletion removes memberships/favorites/facts/findings/health. User deletion removes favorites. Core job/diagnostic records can retain numeric references to removed attachments without retaining their content.

## Retention and ownership

Deactivation retains all data and pauses active jobs. Default uninstall retains data. Explicit `VTX_MEDIA_DELETE_DATA === true` removes these eight Core tables, schema/audit-settings options, the Core decorative-intent key and cron event. It preserves WordPress ALT, all other attachment metadata, attachments and files. Multisite opt-in uninstall visits sites in bounded pages. Network activation remains unsupported; activate per site.

Pro owns independently versioned module-specific tables/options. Pro uninstall must never remove Core folders, favorites, audit, health, jobs or settings. Usage indexes now live exclusively in Pro. Hash inventories, optimization attempts, AI provider data and historical analytics remain future designs, not installed Core tables.

## Schema 3 / Pro integration

Job `context` contains handler-generated snapshot boundaries for non-attachment source jobs and is not included in public job responses. `job_logs.context` contains only source type/ID and a reason code. Existing attachment checkpoints and all M1/M2 storage remain compatible. Pro owns its four usage tables and independent schema option; see the sibling Pro docs/DATABASE.md. Core uninstall remains independent of Pro storage.

## M3.1

Core schema remains **3**. This release adds presentation/read query capabilities only. No tables, columns or indexes change. Pro schema remains **1** and stays Pro-owned. Shared diagnostics read the existing Core job history; uninstall ownership and retention remain unchanged.
