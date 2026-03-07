# Site Optimization Audit (2026-03-07)

Scope: `https://www.skyrobot.top`

## Current State

- Homepage HTTP status: `200`
- Homepage response time: about `0.13s`
- Homepage HTML size: about `264KB`
- Homepage effective image count: `153`
- Homepage unique effective images: `71`
- Repeated homepage avatar image: `70` occurrences
- Homepage mode: `show_on_front=posts`
- Homepage theme/template: `template=justnews`, `stylesheet=justnews`
- Homepage `posts_per_page`: `18`
- Published posts: `112`
- Published posts missing content images: `0`
- Uploads size: `3.4G`
- Upload files count: `22298`
- Database size: about `28.54MB`
- Post revisions: `722`

## Runtime Versions

- PHP: `5.6.40`
- WordPress: `5.2.4`
- Nginx: `1.24.0`
- MySQL: `5.7.44`
- Theme: `JustNews 5.2.3`

## Active Plugins

- `QAPress 2.3.1`
- `classic-editor`
- `smartideo 2.6.0`
- `updraftplus 1.22.15`
- `wordpress-importer 0.6.4`
- `Yoast SEO 14.9`
- `wp-postviews 1.76.1`

## Key Findings

### 1. Runtime is far behind current WordPress recommendations

Official WordPress requirements now recommend:

- PHP `8.3+`
- MySQL `8.0+` or MariaDB `10.6+`

The live site is still on:

- PHP `5.6.40`
- WordPress `5.2.4`
- MySQL `5.7.44`

This is the highest-priority technical debt item.

Notes:

- Official WordPress compatibility tables show WordPress `5.2` aligned with PHP `7.1-7.3`.
- Official PHP support tables show only `8.2+` branches are still supported on 2026-03-07.
- `Yoast SEO 14.9` declares `Requires at least: 5.4`, while the live site is `5.2.4`.

### 2. Homepage is not slow, but it is heavier than it needs to be

- The homepage is already cached and fast, so this is not an emergency.
- The bigger issue is repeated rendering:
  - `153` image tags on the homepage
  - `70` of them are the same avatar image
- This is now traceable to the live template path:
  - live homepage is rendered by `wp-content/themes/justnews/index.php`
  - it renders `18` latest posts
  - it then renders up to `12` category sections
  - each category section renders `6` posts
  - this means the homepage can render about `90` post cards before sidebar and partner blocks
- The repeated avatar output comes from `wp-content/themes/justnews/templates/list-default-sticky.php`, where each card renders `get_avatar()` when author display is enabled.
- This is primarily a DOM/output problem, not a raw query problem. The homepage helper blocks in `wp-content/themes/justnews/functions.php` already use transients for hot posts, spotlight posts, random posts, tags, specials, and site stats.

Recommended optimization direction:

- first reduce repeated avatar output on homepage list cards
- then reduce homepage section count from `12` to `8`
- then lower homepage latest-post count from `18` to `12`
- only after that consider moving lower-value sidebar blocks below the fold or into secondary pages

### 3. Local repository is not a full mirror of production

The local project is missing some plugin directories that exist on the live server. On 2026-03-07 the live plugin directory includes:

- `autoptimize`
- `Basic-Auth`
- `classic-editor`
- `QAPress`
- `smartideo`
- `updraftplus`
- `wordpress-importer`
- `wordpress-seo`
- `wp-postviews`
- `wp-smushit`

The active plugin list stored in the live database currently contains:

- `QAPress/index.php`
- `classic-editor/classic-editor.php`
- `smartideo/smartideo.php`
- `updraftplus/updraftplus.php`
- `wordpress-importer/wordpress-importer.php`
- `wordpress-seo/wp-seo.php`
- `wp-postviews/wp-postviews.php`

This matters because any upgrade rehearsal that starts from the current local repo alone will not fully reproduce production behavior.

### 4. Parent theme is live; child theme is currently inactive

- Production is running the parent theme directly: `justnews`
- `justnews-child` exists locally and on the server, but it is not the active stylesheet/template
- Changes placed only in the child theme will not affect the live site right now

This also avoids a second trap during upgrades: the inactive child theme contains several broad performance tweaks that should be reviewed before it is ever activated, including global heartbeat deregistration, script deferral, and static asset URL rewriting.

### 5. Security headers were incomplete and have now been improved

Headers now verified on the live homepage:

- `Strict-Transport-Security`
- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`

Not yet added:

- `Content-Security-Policy`
- `Permissions-Policy`

These should be added only after compatibility testing.

### 6. SSL renewal looks healthy

- Current certificate `notAfter`: `2026-04-23`
- BT panel service is running
- BT panel renewal config reports successful renewal for `www.skyrobot.top`

This is worth monitoring, but it is not currently broken.

### 7. Operations hardening still has room to improve

Observed or reported by BT panel data:

- MySQL `3306` appears externally reachable
- BT panel risk data reports scheduled database backup is not fully configured for all databases
- SSH idle timeout hardening is not configured
- PHP display errors risk exists on some installed PHP runtimes according to panel warning data

## Recommended Order

### Phase 0: Sync production inventory into the working copy

Goal: make staging and regression testing credible.

Tasks:

- sync live plugin directories missing from the repo
- capture the live active-plugin list before any upgrade rehearsal
- keep note that the live homepage uses the parent `justnews` theme, not the child theme

### Phase 1: Upgrade readiness audit

Goal: reduce upgrade risk before changing runtime.

Tasks:

- clone the live site into a staging copy
- inventory theme/plugin compatibility against the real live plugin set
- test PHP `7.4` first on staging only
- update WordPress core into a modern supported branch
- only then evaluate PHP `8.2+`

Suggested migration path:

1. current production -> staging clone
2. PHP `5.6` -> `7.4`
3. WordPress `5.2.4` -> current supported WordPress branch on staging
4. plugin/theme regression test
5. evaluate PHP `8.2+`

### Phase 2: Homepage slimming

Goal: improve UX and reduce repeated render cost.

Tasks:

- suppress author avatar output on homepage list cards
- reduce homepage section count from `12` to `8`
- reduce homepage latest-post count from `18` to `12`
- keep helper queries cached, but lower raw card count and DOM size
- verify mobile first-screen density after the trim

### Phase 3: Ops hardening

Tasks:

- restrict MySQL port exposure
- confirm scheduled DB backup for `www_skyrobot_top`
- set SSH idle timeout
- trim old revisions

## Live Checks Performed

- verified published-post image guard works and current no-image count is `0`
- verified AI cases pipeline health is `ok`
- verified Nginx config test and reload after security-header changes
- verified homepage security headers on live traffic
- verified live homepage routing from database options: `show_on_front=posts`, `posts_per_page=18`, `template=justnews`, `stylesheet=justnews`
- verified live active plugin list directly from the production database
