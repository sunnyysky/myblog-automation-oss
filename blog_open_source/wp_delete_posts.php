<?php
/**
 * Batch delete posts by IDs.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/wp-load.php';
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    blog_fail_json('Only POST is supported', 405);
}

$api_key = isset($_POST['api_key']) ? $_POST['api_key'] : '';
blog_require_api_key($api_key);

$post_ids = isset($_POST['post_ids']) ? $_POST['post_ids'] : [];
if (!is_array($post_ids)) {
    $post_ids = explode(',', $post_ids);
}
if (empty($post_ids)) {
    blog_fail_json('No post_ids provided');
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
        $results[] = ['post_id' => $post_id, 'status' => 'not_found'];
        continue;
    }

    if (wp_delete_post($post_id, true)) {
        $deleted_count++;
        $results[] = ['post_id' => $post_id, 'title' => $post->post_title, 'status' => 'deleted'];
    } else {
        $failed_count++;
        $failed_posts[] = $post_id;
        $results[] = ['post_id' => $post_id, 'status' => 'failed'];
    }
}

echo json_encode([
    'success' => true,
    'deleted_count' => $deleted_count,
    'failed_count' => $failed_count,
    'failed_posts' => $failed_posts,
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
