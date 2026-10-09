# Translating VTX Media

Domain: `vtx-media`. `vtx-media.pot` includes PHP and source JavaScript strings. Use WordPress language packs or install locale PO/MO catalogs in this directory.

Generate browser JSON catalogs from a translated PO using:

```
wp i18n make-json languages --use-map=languages/translation-map.json --no-purge
```

The map connects source modules to the single distributed `assets/dist/admin.js` bundle. `wp_set_script_translations()` loads those catalogs for the `vtx-media` script handle. PHP uses the native plugin text domain. Regenerate the POT with:

```
wp i18n make-pot . languages/vtx-media.pot --domain=vtx-media --exclude=node_modules,tests,assets/dist,release
```
