<?php
/**
 * Return published posts list for de-duplication.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/wp-load.php';
require_once __DIR__ . '/auth.php';

$api_key = isset($_GET['api_key']) ? $_GET['api_key'] : '';
blog_require_api_key($api_key);

$args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 500,
    'fields' => 'ids',
];

$published_ids = get_posts($args);
$result = [];

foreach ($published_ids as $post_id) {
    $result[] = [
        'id' => $post_id,
        'title' => get_the_title($post_id),
    ];
}

echo json_encode([
    'success' => true,
    'data' => $result,
    'total' => count($result),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
