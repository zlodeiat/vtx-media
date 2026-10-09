# Milestone 2 implementation contract

Core owns deterministic audit rules, current findings, cached health, manual jobs and diagnostics. No usage detection, optimization, AI, licensing calls or distributable Pro plugin.

Rules expose validated metadata, MIME support, version, feature requirement, evaluation and localized presentation. Machine payloads are stored, never English messages. One current finding per attachment/rule, retaining resolved/ignored states. Ignore survives only identical rule version and evidence fingerprint; materially changed evidence reopens it. Removed rules are retired on re-audit.

Score = 100 minus capped category penalties from OPEN findings only. Severity weights: Critical 60, High 20, Medium 10, Low 3, Info 1. Category caps: Accessibility 30, Metadata 8, Files 80, Performance 25; additional categories cap 20. Floor 0. Optional caption/description cost one point each. Ignored/resolved findings cost zero. Finding status and decorative intent are shared attachment-level decisions; only Favorites are personal. Failed/incomplete, stale or unaudited analysis has a NULL current score. Library score averages current completed attachments; separate critical/high totals and coverage prevent averages hiding serious issues.

Attachment intent: `_vtx_media_decorative` is explicit VTX metadata. Marking decorative clears ALT only after confirmation; adding non-empty ALT removes decorative intent. No post HTML changes. Relevant WordPress metadata/post changes queue only the affected attachment; bounded shutdown/cron processing handles pending rows. Explicit rescan/actions return updated analysis. Rule/settings signatures mark prior results stale without synchronous full-library work.

Jobs snapshot a maximum attachment ID and author scope, enumerate by increasing ID and checkpoint each attempted attachment. A unique active key prevents duplicate jobs of the same type/scope. Expiring leases plus connection advisory locks serialize workers; tokens fence stale writes. Pause/cancel stop future items/batches, not an in-flight instruction. Completed attachment findings survive interruption. WP-Cron and bounded authenticated browser ticks share one runner. Browser closed: WP-Cron continues with site traffic. No external queue requirement or recursive HTTP calls.

## Implemented rules

| Rule ID | Types | Finding / default severity |
| --- | --- | --- |
| missing-alt | images | Empty ALT without decorative intent; Medium, objective fact requiring review |
| filename-as-alt | images | Case/extension/separator-normalized equality; Medium, heuristic 0.9 |
| generic-alt | images | Basic English generic label with optional numeric suffix; Medium, heuristic 0.9 |
| missing-title | all | Empty title Low; matching obviously generated filename Info/heuristic |
| missing-caption | all | Optional caption absent; Info |
| missing-description | all | Optional description absent; Info |
| poor-filename | all | Conservative camera/download/screenshot/UUID/hex patterns; Low, heuristic 0.85 |
| missing-physical-file | all | Local file or original missing Critical; unverifiable source Info and incomplete health |
| oversized-file | images | Above large threshold Medium; above very-large threshold High |
| excessive-dimensions | images | Above configured width/height; Medium |
| invalid-image-metadata | raster images | Missing/malformed dimensions or mismatch with readable file header; High. SVG excluded |
| long-alt | images | Above configured character threshold; Low, heuristic 0.8 |

Objective checks use confidence 1. Confidence expresses confidence in the mechanical observation, not visual purpose. Generic ALT currently uses a deliberately small English list; advanced language/context/duplicate/semantic analysis is deferred.

Defaults: large 3 MiB, very large 10 MiB, width 6,000 pixels, height 4,000 pixels, ALT length 250. Large range 0.25–100 MiB; very large 0.5–500 MiB and greater than large; dimensions 1,000–50,000; ALT length 100–2,000. Settings save marks prior signatures stale; it does not start a synchronous scan. Re-scan selected attachments or run the library audit. All rules re-evaluate on that attachment; selectively executing only threshold-dependent rules is deferred.

## Finding and score details

Rules have a stable unique ID and lightweight version string (currently 1). Registry definition/version/settings signatures identify stale health, including previously finding-free attachments. Per-finding versions are exposed. Re-auditing evaluates currently registered entitled rules; absent results become resolved, withdrawn rules retire as resolved, and unchanged ignored evidence stays ignored. A recurrence after resolution opens again. No permanent history snapshots are created.

Penalties are assigned in descending severity, then rule ID, consuming each category cap. The inspector shows each actual capped deduction. Total score is floored at zero. Caption plus description deduct two points. Library health is the rounded mean of current complete scores. Known current open findings on incomplete attachments remain in issue/priority totals; coverage separately reports incomplete files.

## Scan semantics

Manual snapshot scope is max attachment ID plus visible author scope. New uploads after the snapshot are handled incrementally and by future scans. Deleted or ineligible snapshot records contribute to skipped at completion. Processed includes failed attempts; failed counts incomplete attachment analysis, not the number of quality findings. A broken local file is a successful audit with a Critical finding. Rule exceptions or unverifiable storage are incomplete analysis.

Batch sizes 1–100 (UI 10/25/50/100), default 25. Each worker yields after about five seconds or the batch cap, whichever comes first; a single running rule cannot be interrupted mid-instruction. Checkpoints survive process death. At-most-one worker is enforced with an advisory lock plus lease/token; pause/cancel are checked between items. Duplicate start returns the existing manageable active job. WP-Cron dispatches up to three jobs and 25 pending attachments per event. No full scan is scheduled automatically.

Errors are isolated, logged by codes and visible in scan diagnostics/inspector. There is no infinite retry loop: failed analysis requires a relevant change or explicit re-scan; job-level exceptions mark the job failed and a new run can be started. Paused jobs require resume. Completed results are never cleared at scan start.

See EXTENSIONS.md for contracts and TESTING.md for evidence.
