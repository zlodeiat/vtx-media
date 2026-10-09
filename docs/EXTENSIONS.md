# Core extension contracts (1.1.1)

Core is the platform; a future `vtx-media-pro` plugin requires it. VTX Media Pro 1.0.0 is the first add-on and implements Usage Intelligence (Core >=1.1.1, <2.0.0). Add-ons must never duplicate Core, edit its files, replace its admin application or drop its tables.

## Registration lifecycle

Core composes services on WordPress `init`. Register a callback during plugin loading, before `init`:

```php
add_action('vtx_media/register_extensions', function (array $services) {
    $services['features']->register('vendor-feature'); // Paid by default.
    $services['modules']->register(
        'vendor-module',
        'vendor-feature',
        ['label' => __('Example', 'vendor-domain'), 'icon' => 'chart-bar', 'position' => 30],
        function (array $services) {
            // Called only when the module's feature is entitled.
            // Register actual functionality here.
        }
    );
    // Register AuditRuleInterface implementations with $services['rules'].
    // Register JobInterface implementations with $services['jobRegistry'].
});
```

Available services: policy, folders, media, facts, features, entitlements, modules, rules, settings, logger, engine, jobRegistry, runner, routes. `vtx_media/ready` remains a post-registration notification. Feature, module, rule and job registries support explicit removal. Source objects implement `FeatureEntitlementInterface`; add/remove them through the entitlement service. Business logic asks for features, never license state.

Rule registration occurs after Core rules exist and before scans execute. Definitions contain id/version/category/severity/types/feature. `evaluate(Context): ?array` returns null or `['data'=>[...], 'severity'=>..., 'confidence'=>0..1, 'kind'=>'objective'|'heuristic']`. Evidence is bounded to 12 KB per rule; one finding per attachment/rule. `present(array)` returns localized title/explanation/recommendation/remediation IDs and must also accept an empty array for the rule catalog. Rules declare MIME families (`image`, `audio`, etc.), exact MIME strings or `*`. Definitions and result evidence are server-authoritative. IDs and versions must be stable. See AUDIT-ENGINE.md.

## REST

`vtx_media/register_routes` receives the shared `RouteRegistrar` and services on `rest_api_init`. Use `register('/vendor/path', 'POST', $callback, $schemas, $additionalPermission, 'vendor-feature')`. Namespace remains `vtx-media/v1`; use module-prefixed paths to avoid collisions. Shared upload capability, write nonce, validation/sanitization, entitlement checks and WP REST error envelopes apply. Add-ons must additionally authorize each attachment and any stronger module-specific capability. Additive response fields are compatible within v1; breaking request/response changes require an API version transition.

## Browser application

`ModuleRegistry` validates navigation data: id, translated plain-text label, icon identifier and integer position. Only entitled registered modules appear in the boot manifest. Callbacks, classes, source code and license details never enter the manifest.

Enqueue a real extension script from `vtx_media/admin_enqueue` with `vtx-media`, `wp-element`, `wp-hooks` as dependencies. Register a component through:

```js
window.vtxMediaExtensions.registerScreen('vendor-module', Component);
```

Only manifest entries with registered components appear. `unregisterScreen(id)` removes a component. The shared header and WordPress React runtime remain Core-owned. Components receive `onOpen(attachmentId)` to reuse the library inspector.

Inspector: `wp.hooks.addFilter('vtx_media.inspectorSections', 'vendor/module', (sections, item, context) => [...sections, element])`. Context exposes `onSaved`, `dirty` and `focusField('alt'|'title'|'caption'|'description')`. Data can be added through authorized PHP `vtx_media/inspector`. Existing two-argument filters remain compatible. `vtx_media.auditSettingsSections` accepts React sections and `{ onSaved }`; an add-on's own section must enforce its feature/capability in its API. No PHP-provided JavaScript is evaluated.

## Jobs and sources

`JobInterface` defines id, required feature, snapshot(author), next(state, limit) and process(attachmentId, jobId). The shared runner owns bounded execution, checkpoints, statuses, leases, diagnostics and execution as the originating user. Existing handlers continue to operate on canonical attachment IDs. The additive `WorkJobInterface extends JobInterface` supplies `authorize(): bool` and `prepare(array $ids): void` for handler-owned positive source cursors. The runner calls handler authorization at start and execution; it bypasses attachment authorization/cache priming only for this explicit contract. Source handlers remain responsible for source policy and bounded enumeration. A snapshot may provide a small server-generated JSON `context` stored in the shared job row; it is withheld from the public state. Existing AuditJob is unchanged. `JobRunner::latest(?string $type)` supports type-scoped screens; Audit explicitly requests media-audit. Pro requests usage-scan. Core exposes namespaced `VTX\Media\VERSION` for compatibility checks. No job class can be selected or instantiated by a request; only registry IDs are accepted.

`vtx_media/file_source` can supply a `Media\FileSourceInterface` before Core composition. Return available/missing/missing-original/unknown, nullable byte size and nullable dimensions. The local implementation is uploads-confined and makes no network requests. Future storage integrations must implement safe verification themselves. No remote integration ships today.

Other preserved hooks: capabilities, folder_changed, media_moved, metadata_updated, audited, deactivated. Extension code is trusted server/browser code, not a sandbox. Entitlement does not substitute for authorization.

## Proof and ownership

`tests/fixtures/AuditExtension.php` supplies a test-only rule. `tests/audit.php` registers a paid feature/module, supplies an internal entitlement, executes the extension rule, removes it and proves Core continues. `tests/entitlements.php` tests the server-only development source in separate PHP processes.

Core owns organization, favorites, basic audit, health, settings, jobs and diagnostics. Future Pro modules own independently versioned module-specific tables/options and migrations. Uninstalling Pro must not drop Core data. No speculative AI, cleanup or optimization tables are installed. Usage contracts/storage belong to Pro; Core never imports Pro classes.

## Additive browser helpers

`window.vtxMediaExtensions` additionally exposes Core's `api`, `query`, `config`, and frozen `ui` helpers (Button, Icon, Thumb, bytes, date, Dialog). Add-on bundles use the WordPress React runtime and these shared controls/transport rather than copying the application. API errors retain normal messages and add status/code fields for handling retryable worker contention.

`Logger::event` accepts optional bounded diagnostic context: source_type, source_id and reason. It stores identifiers only; clients cannot submit logs. Job snapshots/context are trusted handler output, never browser-provided executable data.

## M3.1 shared UI additions

Core 1.2.0 exports these methods on `window.vtxMediaExtensions`:

```js
registerAdvancedSection("module-diagnostics", {
  label: wp.i18n.__("Module diagnostics", "module-domain"),
  position: 15,
  component: Diagnostics,
});
registerInspectorSection("module-optimization", "optimization", OptimizationSection);
// Rendered only after a real entitled module registers; no placeholder UI.
navigate("advanced", "module-diagnostics");
```

Components receive `item` and `context` in Inspector (`onSaved`, `dirty`, `focusField`). Advanced sections are sorted by position and mount on demand. IDs and callable component types are validated; PHP never supplies JavaScript. Existing `registerScreen`, legacy Inspector/settings hooks remain compatible. Pro 1.1.0 requires Core >=1.2.0 and <2.0.0 for these UI contracts.

`ui` also exposes `TechnicalDetails`, `RelativeTime`, `Drawer`, `Pager`. Drawer is a native dialog and restores the triggering element on close. `vtx_media/support_versions` accepts a bounded label/version string mapping for sanitized support diagnostics. Do not return credentials, filesystem paths or content.

## M4 exercised extension contracts

Pro registers feature `optimizer`, screen `optimize`, job `image-optimization`, Inspector slot `optimization`, a Health performance action and Advanced Optimization using the existing APIs. No second React app, job/log table, entitlement or Core engine is introduced. Shared drawers/technical details retain keyboard/focus behavior. Pro extension hooks `vtx_media_pro/register_optimizers`, `vtx_media_pro/optimizer_ready` and validated `vtx_media_pro/attachment_aliases` keep processor, state and alias integration separately extensible. Future Cleanup consumes optimizer service facts; AI can continue using canonical Core media facts.

## Core 1.3.0 contracts

`Policy::can(action)` supplies generic action defaults with `vtx_media/action_allowed`; `vtx_media/route_permission` runs after authentication/entitlement; `vtx_media/job_allowed` protects job creation, management and execution. `vtx_media/admin_config` and `vtx_media/module_manifest` align UI visibility. Trusted PHP extensions may filter `vtx_media/media_where` and `vtx_media/collection_label` for the validated Library `collection` parameter. `AuditQuery::collectionQuery()` exposes the current open-finding projection. JavaScript `registerLibrarySidebar(id, component)` inserts a scoped Library sidebar component. These are trusted extension APIs, never browser-supplied SQL, capability decisions or code.
