<?php
/**
 * Sanitized child-theme function pack.
 * Compatible with JustNews child theme and generic WP child themes.
 */

// Enqueue parent style
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
});

// Content formatting
add_filter('the_content', 'wpautop', 99);
add_filter('the_excerpt', 'wpautop', 99);
remove_filter('the_content', 'shortcode_unautop');

// Basic cleanup
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
add_filter('xmlrpc_enabled', '__return_false');

// Guarded constants (avoid re-define warnings)
if (!defined('WP_POST_REVISIONS')) {
    define('WP_POST_REVISIONS', 3);
}
if (!defined('EMPTY_TRASH_DAYS')) {
    define('EMPTY_TRASH_DAYS', 7);
}
if (!defined('AUTOSAVE_INTERVAL')) {
    define('AUTOSAVE_INTERVAL', 300);
}

// Optional: stop heartbeat to reduce backend CPU
add_action('init', function () {
    wp_deregister_script('heartbeat');
}, 1);

// Add lazy loading to images
function myblog_add_lazy_loading_to_images($content) {
    return preg_replace(
        '/<img([^>]+)src=([\'"])([^\'">]+)\2([^>]*)>/i',
        '<img$1src=$2$3$2 loading="lazy"$4>',
        $content
    );
}
add_filter('the_content', 'myblog_add_lazy_loading_to_images', 10);
add_filter('post_thumbnail_html', 'myblog_add_lazy_loading_to_images', 10);

// Add resource hints (customize by your stack)
add_action('wp_head', function () {
    $hints = ['//fonts.googleapis.com', '//fonts.gstatic.com'];
    foreach ($hints as $hint) {
        echo '<link rel="preconnect" href="' . esc_url($hint) . '">' . "\n";
        echo '<link rel="dns-prefetch" href="' . esc_url($hint) . '">' . "\n";
    }
}, 1);

// Preload main stylesheet
add_action('wp_head', function () {
    echo '<link rel="preload" href="' . esc_url(get_stylesheet_directory_uri() . '/style.css') . '" as="style">' . "\n";
}, 2);

// Remove script/style query version for better CDN cache key stability
function myblog_remove_script_version($src) {
    $parts = explode('?', $src);
    return $parts[0];
}
add_filter('script_loader_src', 'myblog_remove_script_version', 15, 1);
add_filter('style_loader_src', 'myblog_remove_script_version', 15, 1);

// Auto alt text from filename
function myblog_generate_alt_from_filename($url) {
    $filename = basename($url);
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $name = preg_replace('/-\d+x\d+$/', '', $name);
    $name = preg_replace('/^(bfi_thumb|thumb|webp)_/', '', $name);
    $name = preg_replace('/-\w{32,}$/', '', $name);
    $name = str_replace(['-', '_'], ' ', $name);
    $name = ucwords(trim($name));
    return $name ?: 'Image';
}

function myblog_auto_add_image_alt_attribute($content) {
    if (is_feed() || is_admin()) {
        return $content;
    }
    return preg_replace_callback(
        '/<img([^>]*?)src=(["\'])([^"\'>]+)\2([^>]*?)>/i',
        function ($matches) {
            $img_tag = $matches[0];
            if (strpos($img_tag, 'alt=') !== false) {
                return $img_tag;
            }
            $alt_text = esc_attr(myblog_generate_alt_from_filename($matches[3]));
            return str_replace('>', ' alt="' . $alt_text . '">', $img_tag);
        },
        $content
    );
}
add_filter('the_content', 'myblog_auto_add_image_alt_attribute', 10);
add_filter('post_thumbnail_html', 'myblog_auto_add_image_alt_attribute', 10);

// Thumbnail sizes
add_action('after_setup_theme', function () {
    add_image_size('homepage-thumbnail', 480, 300, true);
    add_image_size('post-thumbnail', 480, 300, true);
});

add_filter('intermediate_image_sizes_advanced', function ($sizes, $metadata) {
    if (!isset($sizes['homepage-thumbnail'])) {
        $sizes['homepage-thumbnail'] = ['width' => 480, 'height' => 300, 'crop' => true];
    }
    if (!isset($sizes['post-thumbnail'])) {
        $sizes['post-thumbnail'] = ['width' => 480, 'height' => 300, 'crop' => true];
    }
    return $sizes;
}, 10, 2);

// AJAX: delete duplicate posts (protected by BLOG_API_KEY)
function myblog_api_key_expected() {
    $env = getenv('BLOG_API_KEY');
    if ($env && trim($env) !== '') {
        return trim($env);
    }
    if (defined('BLOG_API_KEY') && BLOG_API_KEY) {
        return BLOG_API_KEY;
    }
    return '';
}

add_action('wp_ajax_delete_duplicate_posts', 'myblog_handle_delete_duplicate_posts');
add_action('wp_ajax_nopriv_delete_duplicate_posts', 'myblog_handle_delete_duplicate_posts');

function myblog_handle_delete_duplicate_posts() {
    $provided = isset($_POST['api_key']) ? $_POST['api_key'] : '';
    $expected = myblog_api_key_expected();

    if (!$expected) {
        wp_send_json_error(['message' => 'Server API key is not configured'], 500);
    }
    if (!is_string($provided) || !hash_equals($expected, $provided)) {
        wp_send_json_error(['message' => 'Invalid API key'], 401);
    }

    $post_ids = isset($_POST['post_ids']) ? $_POST['post_ids'] : '';
    if (!is_array($post_ids)) {
        $post_ids = explode(',', $post_ids);
    }
    if (empty($post_ids)) {
        wp_send_json_error(['message' => 'No post_ids provided']);
    }

    $deleted_count = 0;
    $failed_count = 0;
    $failed_posts = [];
    $results = [];

    foreach ($post_ids as $post_id) {
        $post_id = intval($post_id);
        if ($post_id <= 0) {
            continue;
        }
        $post = get_post($post_id);
        if (!$post) {
            $failed_count++;
            $failed_posts[] = $post_id;
            continue;
        }
        if (wp_delete_post($post_id, true)) {
            $deleted_count++;
            $results[] = [
                'post_id' => $post_id,
                'title' => $post->post_title,
                'status' => 'deleted',
            ];
        } else {
            $failed_count++;
            $failed_posts[] = $post_id;
        }
    }

    wp_send_json_success([
        'deleted_count' => $deleted_count,
        'failed_count' => $failed_count,
        'failed_posts' => $failed_posts,
        'results' => $results,
    ]);
}
