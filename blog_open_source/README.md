# Blog Open-Source Pack

This folder contains sanitized, reusable WordPress endpoint code extracted from production.

## Included Endpoints

- `get_drafts.php`
- `get_published_posts.php`
- `publish_draft.php`
- `wp_delete_posts.php`
- `wp_upload_image.php`
- `wp_publish_helper.php`

## Security Model

- No hardcoded API key.
- API key is read from:
  1. `BLOG_API_KEY` environment variable
  2. `BLOG_API_KEY` constant (optional, from `wp-config.php`)
- If no key is configured, request is rejected.

## Install

1. Copy these files into your WordPress web root.
2. Add an API key in server environment:
   - `BLOG_API_KEY=your-strong-key`
3. Or define in `wp-config.php`:
   - `define('BLOG_API_KEY', 'your-strong-key');`

## Notes

- These files depend on `wp-load.php` in the same root.
- Keep endpoints behind HTTPS and consider IP allowlist if exposed publicly.
