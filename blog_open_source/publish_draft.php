<?php
/**
 * Publish one draft post.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/wp-load.php';
require_once __DIR__ . '/auth.php';

$api_key = isset($_POST['api_key']) ? $_POST['api_key'] : '';
blog_require_api_key($api_key);

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
if (!$post_id) {
    blog_fail_json('Invalid post ID');
}

$post = get_post($post_id);
if (!$post) {
    blog_fail_json('Post not found', 404);
}
if ($post->post_status !== 'draft') {
    blog_fail_json('Post is not a draft');
}

$update_result = wp_update_post([
    'ID' => $post_id,
    'post_status' => 'publish',
    'post_date' => current_time('mysql'),
], true);

if (is_wp_error($update_result)) {
    blog_fail_json($update_result->get_error_message(), 500);
}

$updated_post = get_post($post_id);
echo json_encode([
    'success' => true,
    'post_id' => $post_id,
    'link' => get_permalink($post_id),
    'title' => $updated_post->post_title,
    'date' => $updated_post->post_date,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
