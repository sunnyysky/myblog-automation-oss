# Staging Rehearsal Runbook (2026-03-07)

Scope: `https://www.skyrobot.top`

## Current Server Facts

Verified on the live server on `2026-03-07`:

- current production root: `/www/wwwroot/www.skyrobot.top`
- current production database: `www_skyrobot_top`
- current production Nginx vhost: `/www/server/panel/vhost/nginx/www.skyrobot.top.conf`
- current production PHP socket in vhost: `unix:/tmp/php-cgi-56.sock`
- current production site size: about `3.5G`
- free disk space on `/www`: about `21G`
- `rsync` is available at `/usr/bin/rsync`
- existing site roots under `/www/wwwroot`: `default`, `runtime`, `www.skyrobot.top`
- existing databases include production only; no staging DB exists yet

Important runtime fact:

- PHP `7.4` is not currently available on this server
- verified active PHP-FPM services: `php-fpm-56`, `php-fpm-80`
- verified missing service: `php-fpm-74`

This means the first staging clone should start on PHP `5.6`, and PHP `7.4` must be installed before the next runtime-step rehearsal.

## Recommended Staging Target

Use these values unless you have a stronger naming rule already:

- staging host: `staging.skyrobot.top`
- staging root: `/www/wwwroot/staging.skyrobot.top`
- staging database: `www_skyrobot_top_staging`
- staging Nginx vhost: `/www/server/panel/vhost/nginx/staging.skyrobot.top.conf`

## Executed Result

Actual execution on `2026-03-07`:

- `staging.skyrobot.top` did not exist in DNS at the time of rehearsal
- no staging site or vhost existed before the rehearsal
- the staging site was created manually on the server instead of through BT panel
- current staging root: `/www/wwwroot/staging.skyrobot.top`
- current staging database: `www_skyrobot_top_staging`
- current staging vhost: `/www/server/panel/vhost/nginx/staging.skyrobot.top.conf`
- current staging PHP socket: `unix:/tmp/php-cgi-74.sock`
- current staging URL config: `http://staging.skyrobot.top`
- current staging indexing guard:
  - WordPress `blog_public=0`
  - Nginx `X-Robots-Tag: noindex, nofollow, noarchive`

Rollback and rehearsal artifacts created during execution:

- initial clone rehearsal root: `/root/staging_rehearsal_20260307_151156`
- core upgrade snapshot root: `/root/staging_core_upgrade_20260307_154133`
- plugin upgrade snapshot root: `/root/staging_plugin_upgrade_20260307_154610`

Current rehearsal state:

- staging runs on PHP `7.4.33`
- staging WordPress core is `6.9.1`
- staging database version is `60717`
- homepage, login, `/wp-admin` redirect, one single post, one category page, `wp-json`, and Yoast sitemap all return healthy responses through server-side `curl --resolve`
- no new PHP `7.4` fatal was observed after the compatibility fix and upgrade pass

Compatibility fix applied to the active parent theme before the PHP jump:

- file: `wwwroot/wp-content/themes/justnews/functions.php`
- replaced removed PHP function `eregi()` with `stripos(...) !== false`
- skipped the empty-`USER_AGENT` crawler block for CLI only, so WordPress CLI/bootstrap scripts can run without weakening browser-side behavior

## Preflight Warnings

- The local `.env` still points at production:
  - `WP_URL=https://www.skyrobot.top`
  - `WP_PATH=/www/wwwroot/www.skyrobot.top`
  - `DB_NAME=www_skyrobot_top`
- Do not run local automation scripts against staging with the current production `.env`.
- If you need script-level staging checks later, create a separate environment file first.

## Step 1: Create The Empty Site In BT Panel

Do this in BT panel first.

Recommended settings:

- domain: `staging.skyrobot.top`
- root: `/www/wwwroot/staging.skyrobot.top`
- PHP version: `5.6` for the first clone validation
- database: do not rely on the auto-created DB if you want clean naming; the runbook below creates `www_skyrobot_top_staging` explicitly

Reason:

- BT panel will generate the basic site structure and vhost file safely
- production currently runs through the `php-cgi-56.sock` socket, so cloning on `5.6` first keeps the first validation low-risk

## Step 2: Prepare Shell Variables

Run these on the server after the empty staging site exists.

```bash
export PROD_ROOT=/www/wwwroot/www.skyrobot.top
export STAGING_HOST=staging.skyrobot.top
export STAGING_ROOT=/www/wwwroot/staging.skyrobot.top
export PROD_DB=www_skyrobot_top
export STAGING_DB=www_skyrobot_top_staging
export DB_USER=www_skyrobot_top

read -s DB_PASSWORD
read -s MYSQL_ROOT_PASSWORD

export STAMP=$(date +%Y%m%d_%H%M%S)
export REHEARSAL_ROOT=/root/staging_rehearsal_${STAMP}
mkdir -p "${REHEARSAL_ROOT}"
```

## Step 3: Freeze A Rollback Baseline

```bash
cp /www/server/panel/vhost/nginx/www.skyrobot.top.conf "${REHEARSAL_ROOT}/www.skyrobot.top.conf"
cp "${PROD_ROOT}/wp-config.php" "${REHEARSAL_ROOT}/wp-config.php"

mysqldump --single-transaction --quick \
  -u"${DB_USER}" -p"${DB_PASSWORD}" "${PROD_DB}" \
  > "${REHEARSAL_ROOT}/prod.sql"
```

If you want a file-level rollback snapshot as well:

```bash
tar -czf "${REHEARSAL_ROOT}/prod-files.tar.gz" \
  -C /www/wwwroot www.skyrobot.top
```

## Step 4: Copy Production Files Into Staging

This keeps the first copy simple and avoids cache/runtime junk.

```bash
mkdir -p "${STAGING_ROOT}"

rsync -a --delete \
  --exclude 'wp-content/cache/' \
  --exclude 'wp-content/upgrade/' \
  "${PROD_ROOT}/" "${STAGING_ROOT}/"
```

## Step 5: Create And Load The Staging Database

Use the same DB user first, then only change `DB_NAME` in the staging `wp-config.php`.

```bash
MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${STAGING_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${STAGING_DB}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
```

```bash
mysql -u"${DB_USER}" -p"${DB_PASSWORD}" "${STAGING_DB}" < "${REHEARSAL_ROOT}/prod.sql"
```

## Step 6: Repoint The Staging Config

Production already has commented `WP_HOME` and `WP_SITEURL` lines in `wp-config.php`, so use that structure instead of adding a second copy.

```bash
cp "${STAGING_ROOT}/wp-config.php" "${REHEARSAL_ROOT}/wp-config.staging.pre-edit.php"

sed -i "s/define( 'DB_NAME', 'www_skyrobot_top' );/define( 'DB_NAME', '${STAGING_DB}' );/" "${STAGING_ROOT}/wp-config.php"
sed -i "s#// define('WP_HOME', 'https://www.skyrobot.top');#define('WP_HOME', 'https://${STAGING_HOST}');#" "${STAGING_ROOT}/wp-config.php"
sed -i "s#// define('WP_SITEURL', 'WP_HOME');#define('WP_SITEURL', WP_HOME);#" "${STAGING_ROOT}/wp-config.php"
```

## Step 7: Mark Staging As Noindex

Do both of these:

1. Set WordPress visibility off:

```bash
mysql -u"${DB_USER}" -p"${DB_PASSWORD}" "${STAGING_DB}" \
  -e "UPDATE blog_options SET option_value='0' WHERE option_name='blog_public';"
```

2. Add an `X-Robots-Tag` header in the staging Nginx vhost:

```nginx
add_header X-Robots-Tag "noindex, nofollow, noarchive" always;
```

If the staging host will be publicly reachable, add Basic Auth as well before external testing.

## Step 8: Validate The Clone On PHP 5.6 First

Before changing runtime, verify that staging is a faithful copy.

Checklist:

- homepage opens
- 3 published article pages open
- 1 category page opens
- search works
- `/wp-admin` login works
- image-heavy article renders correctly
- no-image published guard still returns clean state if run against staging manually

Useful commands:

```bash
grep -nE 'server_name|root |fastcgi_pass' /www/server/panel/vhost/nginx/${STAGING_HOST}.conf
curl -I "https://${STAGING_HOST}/"
```

## Step 9: Resolve The PHP 7.4 Blocker

This server does not currently have PHP `7.4` installed as a runnable FPM service.

Before the next rehearsal stage:

- install PHP `7.4` in BT panel
- confirm the binary exists
- confirm the FPM service exists

Verification target:

```bash
ls -l /www/server/php/74/bin/php
systemctl status php-fpm-74 --no-pager -l
```

Do not switch staging to `8.0` just because it is already installed unless you intentionally accept a larger compatibility jump.

## Step 10: Move Staging To PHP 7.4 Only After The Clone Is Stable

After PHP `7.4` is installed:

- switch the staging site from `5.6` to `7.4` in BT panel
- verify the staging vhost no longer points to the PHP `5.6` socket
- reload Nginx and PHP-FPM if BT does not do it automatically

Expected config change:

- from `fastcgi_pass unix:/tmp/php-cgi-56.sock;`
- to the PHP `7.4` socket configured by BT for the staging site

## Step 11: Run The First PHP 7.4 Regression Pass

Minimum regression set:

- homepage render
- 3 single posts
- category page
- search page
- admin login
- draft save
- manual publish of a disposable draft
- sitemap and SEO meta output
- AI cases health script logic, but only with staging-safe config

## Rollback

If staging breaks, restore these first:

```bash
cp "${REHEARSAL_ROOT}/wp-config.staging.pre-edit.php" "${STAGING_ROOT}/wp-config.php"
mysql -u"${DB_USER}" -p"${DB_PASSWORD}" "${STAGING_DB}" < "${REHEARSAL_ROOT}/prod.sql"
```

If the vhost was edited manually:

```bash
cp "${REHEARSAL_ROOT}/www.skyrobot.top.conf" /www/server/panel/vhost/nginx/www.skyrobot.top.conf
nginx -t && systemctl reload nginx
```

## Notes

- A full file clone is feasible on the current server because free space is about `21G` and the production site footprint is about `3.5G`.
- The first rehearsal should prove clone fidelity, not modernization.
- Keep production and staging config clearly separated; the current automation project still defaults to production values in `.env`.
- This runbook is now partially historical: Steps `1` through `11` have been executed in substance, but public DNS and staging SSL are still pending.
