<?php
/**
 * Publish helper endpoint:
 * - create post draft/publish
 * - optional delete_posts action
 */

error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(300);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/wp-load.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    blog_fail_json('Only POST is supported', 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST;
}
if (empty($data)) {
    parse_str($raw, $data);
}

$api_key = isset($data['api_key']) ? $data['api_key'] : '';
blog_require_api_key($api_key);

if (isset($data['action']) && $data['action'] === 'delete_posts') {
    $post_ids = isset($data['post_ids']) ? $data['post_ids'] : [];
    if (!is_array($post_ids)) {
        $post_ids = explode(',', $post_ids);
    }
    if (empty($post_ids)) {
        blog_fail_json('No post_ids provided');
    }

    $deleted = 0;
    foreach ($post_ids as $post_id) {
        $post_id = intval($post_id);
        if ($post_id > 0 && wp_delete_post($post_id, true)) {
            $deleted++;
        }
    }
    echo json_encode(['success' => true, 'deleted_count' => $deleted], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($data['title']) || empty($data['content'])) {
    blog_fail_json('Missing required fields: title, content');
}

$post_data = [
    'post_title' => $data['title'],
    'post_content' => $data['content'],
    'post_status' => isset($data['status']) ? $data['status'] : 'draft',
    'post_excerpt' => isset($data['excerpt']) ? $data['excerpt'] : '',
    'post_author' => isset($data['author_id']) ? intval($data['author_id']) : 1,
];

if (isset($data['categories'])) {
    $cat_ids = [];
    $cats = is_array($data['categories']) ? $data['categories'] : [$data['categories']];
    foreach ($cats as $cat_name) {
        if (is_numeric($cat_name)) {
            $cat_ids[] = intval($cat_name);
            continue;
        }
        $cat = get_category_by_slug(sanitize_title($cat_name));
        if ($cat) {
            $cat_ids[] = $cat->term_id;
        } else {
            $new_cat = wp_insert_term($cat_name, 'category', ['slug' => sanitize_title($cat_name)]);
            if (!is_wp_error($new_cat)) {
                $cat_ids[] = $new_cat['term_id'];
            }
        }
    }
    if (!empty($cat_ids)) {
        $post_data['post_category'] = $cat_ids;
    }
}

if (isset($data['tags']) && is_array($data['tags'])) {
    $post_data['tags_input'] = $data['tags'];
}

$post_id = wp_insert_post($post_data);
if (!$post_id || is_wp_error($post_id)) {
    $msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'Create post failed';
    blog_fail_json($msg, 500);
}

if (isset($data['seo_title']) && $data['seo_title'] !== '') {
    update_post_meta($post_id, '_yoast_wpseo_title', $data['seo_title']);
}
if (isset($data['seo_description']) && $data['seo_description'] !== '') {
    update_post_meta($post_id, '_yoast_wpseo_metadesc', $data['seo_description']);
}
if (isset($data['seo_keywords']) && $data['seo_keywords'] !== '') {
    update_post_meta($post_id, '_yoast_wpseo_focuskw', $data['seo_keywords']);
}

if (isset($data['featured_image']) && intval($data['featured_image']) > 0) {
    update_post_meta($post_id, '_thumbnail_id', intval($data['featured_image']));
}

echo json_encode([
    'success' => true,
    'post_id' => $post_id,
    'url' => get_permalink($post_id),
    'message' => 'Post created',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
