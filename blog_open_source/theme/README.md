# Theme Custom Functions (Sanitized)

This folder contains a reusable child-theme function bundle extracted from production and sanitized for open source.

## File

- `justnews-child-functions.php`

## How to Use

1. Open your child theme `functions.php`.
2. Copy the content from `justnews-child-functions.php`.
3. Keep only the sections you need.

## API Key for AJAX endpoint

The `delete_duplicate_posts` AJAX endpoint checks API key from:

1. `BLOG_API_KEY` environment variable
2. `BLOG_API_KEY` constant in `wp-config.php`

If not configured, endpoint returns error.
