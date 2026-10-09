# Development entitlement — DEVELOPMENT ONLY

Core functionality is Free and does not require developer mode. The development override is **off by default** and is controlled only by server-side PHP configuration.

To enable owner access to every registered feature on this development installation, add this above the “stop editing” line in `wp-config.php`:

```php
define( 'VTX_MEDIA_DEV_MODE', true );
```

To disable, remove the definition or set it to the literal boolean `false`:

```php
define( 'VTX_MEDIA_DEV_MODE', false );
```

Core does not modify wp-config.php. This task leaves the installation's configuration unchanged. Do not enable the override on customer production sites. There is no hostname, username, URL parameter, REST parameter, JavaScript switch, option, secret key or hidden administrator toggle that enables it. Strings such as `'true'` and integer `1` do not enable it.

`EntitlementService::hasFeature($id)` first requires a registered feature. Free features are always available; the explicit development constant grants every registered feature. Otherwise registered server-side entitlement sources decide. Unknown features remain unavailable even in development mode. Future commercial licensing will supply an entitlement source; there are no license calls today. This mechanism does not install or fabricate future modules.

The Audit screen shows a subtle DEVELOPMENT ONLY label when the override is enabled. Permission checks still apply: developer entitlement never grants WordPress capabilities or another user's media access.

Verification without changing site configuration:

```sh
wp eval-file tests/entitlements.php --allow-root
wp --exec='define("VTX_MEDIA_DEV_MODE", true);' eval-file tests/entitlements.php --allow-root
wp --exec='define("VTX_MEDIA_DEV_MODE", false);' eval-file tests/entitlements.php --allow-root
```

These separate CLI processes prove default/disabled behavior and registered paid-feature access when enabled. Test fixtures are never loaded by production code.

## Usage Intelligence

Install/activate compatible VTX Media Pro alongside Core. With the literal development constant enabled, Pro registers Usage inside the existing app. Without entitlement its operational module/REST providers do not boot; Core Library and Audit remain available. Pro does not create a license or alter this constant. Re-enabling Pro/entitlement after an offline period requires fresh coverage before Unused can be asserted.
