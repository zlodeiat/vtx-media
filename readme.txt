=== VTX Media – Media Library Manager & Optimizer ===
Contributors: vtxlabs
Tags: media library, folders, media manager, favorites
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organize, inspect and audit your WordPress media with virtual folders, favorites and transparent Media Health.

== Description ==
The Media Library includes nested shared folders, grid/list browsing, search and filters, sorting, bulk organization, personal favorites and editable attachment metadata. Files and URLs stay in WordPress. Media Health adds deterministic metadata, accessibility-review, file-integrity and image-size checks with persistent manual scans. This release does not perform image optimization or AI generation.

Open Media > VTX Media. Folder managers can create, rename, move, reorder and safely delete folders. Without a Pro permission override, authors manage their own attachments and editors/administrators can manage shared folders and other authors' media. Pro 1.5.0 adds an explicit conservative role matrix. Folder counts are direct, not recursive. Recently Added means the last 30 days. Multi-selection is scoped to the current page.

Largest/Smallest prepares a resumable size index in small foreground requests. Unknown file sizes appear last. Favorites belong to the current user.

== Installation ==
Upload the vtx-media directory and activate. Activate individually per site on multisite. Built assets ship with the plugin; Node is only needed for development.

== Data removal ==
Deactivation and uninstall retain VTX data by default. To opt into permanent removal of VTX tables/options, define VTX_MEDIA_DELETE_DATA as true in wp-config.php before uninstall. WordPress attachments, canonical metadata and files are never deleted. The Core-owned decorative-intent key is removed only with this explicit opt-in. See docs/SECURITY.md.

== Changelog ==

= 1.2.0 =
* Media Health: deterministic audit rules, transparent scoring, issue browser and inspector integration.
* Persistent manual scans with pause/resume/cancel, incremental auditing and diagnostic logs.
* Safe decorative intent, ignore/restore, rescans and validated audit thresholds.
* Core extension registries and server-only development entitlements. No licensing or Pro modules yet.

= 1.0.0 =
Initial media library milestone. See docs/CHANGELOG-DEV.md for engineering decisions and verification.

== 1.3.0 ==
Generic action/REST/job policy contracts, indexed collection query hooks and a Library sidebar extension slot. Core schema remains 3. Pro 1.5.0 owns the M7 features; Core works independently.

== 1.4.0 — M7.2 UI polish ==
Shared VTX cards, headings, metric/status patterns and responsive layout. Analytics is organized into focused module panels; existing media workflows and schemas remain unchanged. Install Core 1.4.0 before Pro 1.6.0.

== 1.5.0 — M7.4 Premium UI rollout ==
Compact status panels, visual summaries and purpose-built tables, matrices and workflows. Existing engines, metrics, schemas and destructive safeguards are unchanged. Install Core 1.5.0 before Pro 1.7.0.
