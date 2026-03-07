# Production Plugin Sync Snapshot (2026-03-07)

Scope: `https://www.skyrobot.top`

## Purpose

This snapshot records the plugin directories that existed on production but were missing from the local working copy before upgrade rehearsal work started.

## Missing From Local Before Sync

- `Basic-Auth`
- `autoptimize`
- `updraftplus`
- `wordpress-seo`
- `wp-smushit`

## Production Plugin Directory Inventory

- `autoptimize`
- `Basic-Auth`
- `classic-editor`
- `index.php`
- `QAPress`
- `smartideo`
- `updraftplus`
- `wordpress-importer`
- `wordpress-seo`
- `wp-postviews`
- `wp-smushit`

## Active Plugins Confirmed From Production DB

- `QAPress/index.php`
- `classic-editor/classic-editor.php`
- `smartideo/smartideo.php`
- `updraftplus/updraftplus.php`
- `wordpress-importer/wordpress-importer.php`
- `wordpress-seo/wp-seo.php`
- `wp-postviews/wp-postviews.php`

## Sync Result

Local working copy now matches production for the previously missing directories below:

- `Basic-Auth`: `31` files, `39911` bytes
- `autoptimize`: `80` files, `931358` bytes
- `updraftplus`: `1504` files, `26722419` bytes
- `wordpress-seo`: `1179` files, `11307317` bytes
- `wp-smushit`: `584` files, `13140760` bytes

## Local Artifact

- archive used for the final sync pass:
  - `D:/02_work/04_MyBlog/backups/prod_plugins_sync_20260307144427.tar.gz`

## Important Note About Git

The repository currently uses an allowlist-style `.gitignore`.

- `docs/**` is tracked
- most of `wwwroot/**` is ignored unless explicitly whitelisted

That means the synced plugin directories now exist in the local workspace and can be used for staging or rehearsal work, but they are not currently tracked by Git.
