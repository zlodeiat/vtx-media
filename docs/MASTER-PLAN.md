# VTX Media product plan

## Product and invariants

VTX Media – Media Library Manager & Optimizer addresses large-library organization, metadata quality, usage uncertainty, storage waste and repetitive editorial work. WordPress attachment IDs are the integration key and canonical source of truth. Virtual organization never changes URLs, files, attachment parent or existing content. Deterministic observations and heuristic recommendations are explicitly distinguished. Unknown analysis is not healthy or unused.

Milestone 1 is preserved: grid/list, server-side search/filter/sort, pagination, nested virtual folders, drag/drop and accessible menu alternatives, personal favorites, Unorganized and editable native metadata inspector. Milestone 2 adds deterministic Audit/Media Health and reusable manual job infrastructure. No later module is presented as available before it exists.

## Commercial split

Core/Free remains useful: Media Library, nested folders, favorites, Unorganized, Inspector, metadata editing, basic deterministic Audit, transparent health, manual full-library scans and safe review workflows.

`vtx-media-pro` 1.5.0 requires Core 1.3.0 and adds Usage Intelligence, Image Optimization, recoverable Cleanup, AI Media Assistant, Smart Folders, native role permissions, safe Automation and factual Analytics. Advanced Audit and agency workflows may extend these contracts later. This split can evolve; pricing does not belong in domain code. Pro must not copy Core classes, React application, REST plumbing, engine or tables. Feature entitlements are centralized; licensing is a future source, not scattered business-logic conditions. DEVELOPMENT ONLY owner entitlement grants all registered features when explicitly configured server-side. See DEVELOPER-MODE.md.

## Module dependencies and strategies

- **Organize / Inspect:** canonical attachment projection, virtual folders and personal favorites. Future collections are separate many-to-many relations; smart folders consume indexes and saved policies.
- **Audit / Health:** versioned rules, structured evidence, confidence, severity, issue lifecycle and transparent scoring. Core performs mechanical metadata/filename, local integrity and image size checks. Optional metadata has a very small penalty. Decorative intent is deliberate. Advanced language/context/semantic checks and audit history belong to future Pro extensions.
- **Usage Intelligence (M3):** independent providers enumerate post content, Gutenberg blocks, featured images, galleries, post meta, ACF, WooCommerce, Elementor, options/theme mods, widgets, CSS/backgrounds and serialized structures without executing serialized objects. Index references to attachment IDs and normalized original/variant URLs. Record coverage, provider failures and generation. Absence of evidence is not proof of non-use. `post_parent` and folder assignment are never unused detection.
- **Smart Cleanup:** depends on Usage coverage, integrity facts and audit. Separate unused, possibly unused, orphaned attachments, missing files, broken references and duplicate content. Lifecycle: detection → review → recoverable trash/quarantine → restore or explicit permanent deletion through WordPress APIs. No cleanup deletion in M2 or implicit M3 scanning.
- **Optimization:** depends on facts/jobs and server codec capabilities. Preserve originals, stage and verify outputs, then publish atomically through safe native metadata/reference handling. JPEG/PNG, WebP/AVIF, resizing and thumbnail policies require srcset/offload compatibility tests. Savings are measured; large-file findings remain independent of optimizer implementation.
- **AI:** combines deterministic findings, future usage context and image input to propose reviewable metadata. Never replaces deterministic auditing. External transmission is explicit, keys remain server-side, provider adapters and cost limits remain separate from Core. Future included credits, packs or bring-your-own-key are commercial/provider concerns, not Audit dependencies.
- **Permissions:** centralized current policy; future private/shared folders, user/role policies and storage restrictions must apply consistently to queries and mutations. Folder organization alone is not access control.
- **Analytics:** SQL aggregates and derived snapshots for health, storage distribution, measured savings and trends. Core stores current findings and job timestamps, not a history warehouse.
- **Automation:** future opt-in policies build on current persistent checkpoints, job registry, locks, diagnostics and pause/resume/cancel. Periodic queue dispatch today is infrastructure, not scheduled full-audit policy.

## Performance and safety

No whole-library browser payloads, attachment arrays or synchronous settings-triggered full scans. Current media/findings endpoints paginate; folder branches are lazy; Audit uses scoped SQL aggregation and attachment-ID keyset batches. Relevant metadata changes queue/re-audit only the changed attachment. Signature mismatches mark stale results without rewriting 50,000 rows during settings save. Leading-wildcard search and offset findings pagination retain measurable deep-page costs; future search indexes/keyset UI can extend v1 compatibly.

Benchmarks use connection-local temporary tables for 1k/10k/50k datasets and execute actual rules/checkpoints for 1,000 synthetic local-missing attachments. These establish query shape, bounded payloads and memory behavior, not a universal 50k throughput guarantee. See TESTING.md for measured evidence and limitations.

No file renaming, deletion, compression, original overwrite, URL rewrite or post-content mutation in M2. Local file checks stay within configured uploads; unverifiable/offloaded sources are unknown. Preserve native Media Library, Gutenberg, featured images, galleries, WooCommerce and REST attachment behavior. Core uninstall defaults to retention; explicit opt-in deletes only Core-owned data. Pro uninstall must never delete Core data.

## Roadmap

1. Complete media-library foundation — delivered and owner verified.
2. Extensible deterministic Audit/Media Health, safe actions, persistent manual scans, incremental auditing and development entitlements — complete in Core 1.1.0.
3. Usage Intelligence providers, coverage and reference index — complete in Pro 1.0.0 with Core 1.1.1 platform additions. Provider, integration, extension, browser, regression and scale checks passed; runtime/coverage boundaries are documented in Pro docs/TESTING.md.
4. M3.1 Product UX & Usability — COMPLETE — Core 1.2.0 / Pro 1.1.0. Simple by default, advanced on demand; no engine rewrite.
5. M4 Image Optimization — COMPLETE in Pro 1.2.0 / schema 2. Validated outputs, mandatory encrypted recovery/restore, native fallback delivery, generated-size families, non-destructive delivery resize, Core jobs/upload queue, Inspector/Optimize/Advanced and measured metrics. Actual server supports GD JPEG/PNG/WebP; AVIF unavailable. Canonical/CSS/Cover/variable-product delivery boundaries and tested compatibility are documented in Pro docs/TESTING.md.
6. M4.1 Optimization UX & Metrics — COMPLETE in Pro 1.2.1; full-size/family/recovery metrics remain distinct.
7. M5 Cleanup & Duplicate Intelligence — COMPLETE in Pro 1.3.0 / schema 3: current Usage eligibility, exact hashes, in-place recoverable Quarantine, explicit permanent deletion, accurate accounting and bounded Core jobs. Focused tests/browser/security review passed; boundaries are documented in Pro CLEANUP.md/QUARANTINE.md.
8. M5.1 Cleanup Visual & UX Polish — COMPLETE in Pro 1.3.1 / schema 3.
9. M6 AI Media Assistant — COMPLETE in Pro 1.4.0 / schema 4. Live connection and controlled vision/structured suggestion acceptance passed; no automatic metadata save; normalized usage recorded. See Pro docs/AI-TESTING.md and AI-RELEASE.md.
10. M7 Smart Folders / Permissions / Automation / Analytics — COMPLETE — Core 1.3.0/schema 3; Pro 1.5.0/schema 5. Targeted, browser and final smoke acceptance passed. See Pro SMART-FOLDERS.md, PERMISSIONS.md, AUTOMATION.md and ANALYTICS.md for actual boundaries.
11. Commercial licensing — deferred until Pro functionality stabilizes.

Extension contracts are implemented only around current responsibilities: features/modules, rules, REST, React screens/inspector/settings, job handlers and storage facts. No empty future provider hierarchy or speculative Pro database tables.
