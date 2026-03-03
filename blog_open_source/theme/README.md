# Theme Custom Functions (Sanitized)

This folder contains a reusable child-theme function bundle extracted from production and sanitized for open source.

## File

- `justnews-child-functions.php`
- `js/defer-comments.js`
- `js/infinite-scroll-auto.js`

## How to Use

1. Open your child theme `functions.php`.
2. Copy the content from `justnews-child-functions.php`.
3. Keep only the sections you need.

### Optional JS Enqueue Snippet

```php
add_action('wp_enqueue_scripts', function () {
    if (is_single() && comments_open()) {
        wp_enqueue_script(
            'defer-comments',
            get_stylesheet_directory_uri() . '/js/defer-comments.js',
            [],
            null,
            true
        );
    }
    if (is_home() || is_front_page()) {
        wp_enqueue_script(
            'infinite-scroll-auto',
            get_stylesheet_directory_uri() . '/js/infinite-scroll-auto.js',
            ['jquery'],
            null,
            true
        );
    }
});
```

## API Key for AJAX endpoint

The `delete_duplicate_posts` AJAX endpoint checks API key from:

1. `BLOG_API_KEY` environment variable
2. `BLOG_API_KEY` constant in `wp-config.php`

If not configured, endpoint returns error.
