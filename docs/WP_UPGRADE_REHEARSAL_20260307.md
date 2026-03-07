# WordPress Upgrade Rehearsal Plan (2026-03-07)

Scope: `https://www.skyrobot.top`

## Baseline Confirmed

- Production WordPress: `5.2.4`
- Production PHP: `5.6.40`
- Production MySQL: `5.7.44`
- Production theme: `justnews`
- Production front page mode: `show_on_front=posts`
- Production homepage size driver:
  - `posts_per_page=18`
  - homepage category sections: up to `12`
  - posts per category section: `6`

## Active Plugins Confirmed From Production DB

- `QAPress/index.php`
- `classic-editor/classic-editor.php`
- `smartideo/smartideo.php`
- `updraftplus/updraftplus.php`
- `wordpress-importer/wordpress-importer.php`
- `wordpress-seo/wp-seo.php`
- `wp-postviews/wp-postviews.php`

## Constraints

- The current local repo is not a full mirror of production plugins.
- The inactive `justnews-child` theme is not part of the live request path.
- `wordpress-seo` version `14.9` already declares `Requires at least: 5.4`, while production WordPress is `5.2.4`.
- Official references checked on 2026-03-07 indicate:
  - WordPress now recommends PHP `8.3+` and MySQL `8.0+` or MariaDB `10.6+`: `https://wordpress.org/about/requirements/`
  - WordPress PHP compatibility guidance is tracked here: `https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/`
  - Yoast plugin requirements are here: `https://yoast.com/help/plugin-requirements/`
  - PHP supported branches are here: `https://www.php.net/supported-versions`

## Phase 0 Status

Completed in the local workspace on `2026-03-07`:

- synced these previously missing production plugin directories into `wwwroot/wp-content/plugins`
  - `Basic-Auth`
  - `autoptimize`
  - `updraftplus`
  - `wordpress-seo`
  - `wp-smushit`
- recorded the sync result in `docs/PRODUCTION_PLUGIN_SYNC_20260307.md`

Still important:

- the synced plugin directories are available locally for testing
- they are not yet tracked by Git because of the repository allowlist in `.gitignore`

## Current Server Runtime Constraint

Verified on the live server on `2026-03-07`:

- PHP `7.4` was not installed at the start of the rehearsal
- PHP `7.4` was installed during the rehearsal and the staging site is now running through `unix:/tmp/php-cgi-74.sock`
- the BT-generated service status for `php-fpm-74` is unreliable, but the `php-fpm` master process and socket are active

See `docs/STAGING_REHEARSAL_RUNBOOK_20260307.md` for the concrete staging procedure and the executed result.

## Phase 1 Status

Completed on staging on `2026-03-07`:

- created staging root: `/www/wwwroot/staging.skyrobot.top`
- created staging database: `www_skyrobot_top_staging`
- added a dedicated staging vhost: `/www/server/panel/vhost/nginx/staging.skyrobot.top.conf`
- set staging `siteurl` and `home` to `http://staging.skyrobot.top`
- set `blog_public=0` and added `X-Robots-Tag: noindex, nofollow, noarchive`
- fixed staging `.user.ini` `open_basedir` to the staging root

Compatibility issue found and fixed during the PHP `7.4` move:

- active parent theme `justnews` used removed PHP function `eregi()` in `wwwroot/wp-content/themes/justnews/functions.php`
- replaced that call with `stripos(...) !== false`
- kept the anti-crawler behavior for web traffic, but skipped it for CLI to allow rehearsal scripts and future `wp-cli` usage

Current staging result:

- PHP: `7.4.33`
- WordPress core: `6.9.1`
- WordPress DB version: `60717`
- smoke tests passed for homepage, login, `/wp-admin` redirect, one post, one category, `wp-json`, and Yoast sitemap

Active plugin result on staging after upgrade:

- `QAPress/index.php` -> `2.3.1` (no upstream update detected during rehearsal)
- `classic-editor/classic-editor.php` -> `1.6.7`
- `smartideo/smartideo.php` -> `2.8.1`
- `updraftplus/updraftplus.php` -> `1.26.2`
- `wordpress-importer/wordpress-importer.php` -> `0.9.5`
- `wordpress-seo/wp-seo.php` -> `27.1.1`
- `wp-postviews/wp-postviews.php` -> `1.78`

Still not production-ready:

- `staging.skyrobot.top` has not been added to public DNS yet
- staging is HTTP-only right now and has no SSL certificate
- production is still on `PHP 5.6.40` and `WordPress 5.2.4`
- `QAPress` remains the oldest active plugin and should get targeted manual checks before production cutover

Update after cutover on `2026-03-07`:

- production was subsequently moved to `PHP 7.4.33`
- production core was upgraded to `WordPress 6.9.1`
- production active plugin versions were aligned with the validated staging set
- see `docs/PRODUCTION_CUTOVER_20260307.md` for the exact backup paths, applied changes, and post-cutover smoke checks

## Recommended Sequence

### 1. Freeze a trustworthy baseline

- export a full database backup
- archive the full production `wp-content` directory
- record the live plugin inventory and active plugin list
- keep a copy of the current Nginx vhost and PHP-FPM settings

Success criteria:

- you can rebuild the current site exactly
- rollback can be done without guessing

### 2. Build staging from production, not from the current repo alone

- copy production files into a staging path or subdomain
- restore a fresh production DB snapshot into a staging DB
- set staging URLs
- block indexing on staging

Important:

- sync missing production plugin directories before testing
- do not assume the current repo represents the exact live plugin set

### 3. Validate staging on the current legacy stack first

- keep WordPress `5.2.4`
- keep PHP `5.6.40`
- load homepage, several single posts, category pages, search, login, and publish flow

Goal:

- prove staging is a correct copy before changing anything

### 4. Move staging PHP to `7.4` first

- switch staging PHP from `5.6` to `7.4`
- clear opcode/cache layers
- review fatal errors, warnings, login, editor, homepage, and publishing

Why this step exists:

- it reduces the size of the compatibility jump
- it is safer than jumping from PHP `5.6` directly to PHP `8.x`

### 5. Update WordPress core and active plugins on staging

- update WordPress core on staging
- update the active plugin set on staging
- keep notes on any plugin that must be replaced, pinned, or removed
- test theme templates with the upgraded core before considering PHP `8.x`

Priority attention items:

- `wordpress-seo`
- `QAPress`
- `smartideo`
- any customizations inside `justnews`

### 6. Move staging PHP to `8.2` or newer

- preferred landing target: `8.2+`
- do not use PHP `8.1` as the long-term target because PHP `8.1` security support ended on `2025-12-31`, per `php.net`
- if the panel provides a stable `8.3` package and theme/plugin tests pass, `8.3` is closer to current WordPress recommendations

This target is an engineering inference from the sources above plus the current age of the theme/plugin stack.

### 7. Run regression checks before any production change

- homepage render and navigation
- article pages with images, embeds, and author info
- AI cases collection and publish path
- image guard cron path
- sitemap and SEO metadata
- backup/restore plugin behavior
- comment or account flows if still used

### 8. Production cutover

- take a final backup immediately before cutover
- switch PHP and deploy updated code during a low-traffic window
- verify homepage, several article pages, admin login, publishing, and cron jobs
- keep rollback artifacts ready until the site is stable

## Rollback Triggers

Rollback immediately if any of these appear after a staging or production jump:

- front page or single post fatals
- broken editor or publish flow
- missing thumbnails or content images
- sitemap or meta output failures
- PHP fatal logs repeating after cache clear

## Low-Risk Wins To Apply Before Or Alongside The Upgrade

- reduce homepage latest posts from `18` to `12`
- reduce homepage category sections from `12` to `8`
- suppress avatar output on homepage list cards to cut repeated image output
- sync production plugins into version control or a private artifact store
