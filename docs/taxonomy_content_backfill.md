# Taxonomy Content Backfill (2026-03-04)

## Purpose

1. Fix tag/special pages that showed empty content.
2. Normalize tag slugs to stable English slugs.
3. Backfill published posts with tags and special terms.
4. Add backward-compatible 301 redirects for old tag URLs.

## Files

1. `docs/tag_slug_mapping.csv`
2. `docs/tag_301_rules.conf`
3. `docs/category_slug_mapping.csv`
4. `docs/category_301_rules.conf`

## Homepage Changes

1. `wwwroot/wp-content/themes/justnews/index.php`
2. Added more category sections and richer sidebar modules.

## Validation Snapshot

1. `post_tag` terms with non-zero count: `11`
2. `posts_without_tags`: `0`
3. `special` terms with non-zero count: `7`
4. Old category URLs: `301 -> new category slugs`
5. Old tag URLs (Chinese/encoded): `301 -> new tag slugs`

## Server Backups

1. `/var/www/your-site/_ops_backups/yyyymmdd_hhmmss`
2. Includes DB dump: `taxonomy_tables.sql`
3. Includes nginx backup: `your-site.conf.bak`
4. Includes theme backup: `justnews-index.php.bak`
