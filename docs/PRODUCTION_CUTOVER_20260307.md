# Production Cutover Record (2026-03-07)

Scope: `https://www.skyrobot.top`

## Backup And Rollback Artifacts

- cutover snapshot root: `/root/prod_cutover_20260307_160036`
- production DB dump: `/root/prod_cutover_20260307_160036/prod_before_upgrade.sql`
- production core snapshot: `/root/prod_cutover_20260307_160036/core_before_upgrade.tar.gz`
- production `wp-content` snapshot: `/root/prod_cutover_20260307_160036/wp-content_before_upgrade.tar.gz`
- production vhost backup: `/root/prod_cutover_20260307_160036/www.skyrobot.top.conf`

## Applied Changes

- switched production Nginx PHP socket from `unix:/tmp/php-cgi-56.sock` to `unix:/tmp/php-cgi-74.sock`
- upgraded production WordPress core from `5.2.4` to `6.9.1`
- upgraded production database to WordPress DB version `60717`
- copied the PHP `7.x` compatibility fix for the active parent theme `justnews`
- upgraded these active plugins using the versions already validated on staging:
  - `classic-editor` -> `1.6.7`
  - `smartideo` -> `2.8.1`
  - `updraftplus` -> `1.26.2`
  - `wordpress-importer` -> `0.9.5`
  - `wordpress-seo` -> `27.1.1`
  - `wp-postviews` -> `1.78`
- left `QAPress` at `2.3.1` because no upstream update was detected during the rehearsal

## Post-Cutover State

- production PHP runtime: `7.4.33`
- production WordPress version: `6.9.1`
- production WordPress DB version option: `60717`
- active plugins:
  - `QAPress/index.php` -> `2.3.1`
  - `classic-editor/classic-editor.php` -> `1.6.7`
  - `smartideo/smartideo.php` -> `2.8.1`
  - `updraftplus/updraftplus.php` -> `1.26.2`
  - `wordpress-importer/wordpress-importer.php` -> `0.9.5`
  - `wordpress-seo/wp-seo.php` -> `27.1.1`
  - `wp-postviews/wp-postviews.php` -> `1.78`

## Smoke Tests Passed

- homepage `200`
- login page `200`
- `/wp-admin` redirects to login instead of `upgrade.php`
- one published post `200`
- one category page `200`
- `wp-json` `200`
- Yoast sitemap `200`
- homepage cache returned `MISS` then `HIT`

## Residual Risks

- `php-fpm-74` is serving requests correctly, but the BT-generated service status is still not a clean `active (running)` under `systemctl`
- `QAPress` remains the oldest active plugin and still deserves focused functional checks in admin/publish flows
- staging still has no public DNS or SSL, so future rehearsals still rely on server-side `curl --resolve`

## Post-Cutover Incident

Homepage blank output was reported after the upgrade on `2026-03-07`.

Root cause:

- the classic `justnews` theme ships a placeholder file at `wp-content/themes/justnews/templates/index.html`
- on WordPress `6.9.1`, that file was treated as a block template and routed through `wp-includes/template-canvas.php`
- because the file was empty, the frontend body rendered as an empty canvas instead of the classic `index.php` layout

Fix applied:

- removed `wp-content/themes/justnews/templates/index.html` on both staging and production
- cleared FastCGI cache after the production fix

Verification after the fix:

- homepage body rendered through `wp-content/themes/justnews/index.php` again
- public homepage, login page, category page, and sitemap all returned healthy responses
