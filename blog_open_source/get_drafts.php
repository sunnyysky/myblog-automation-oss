<?php
/**
 * Return draft posts list.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/wp-load.php';
require_once __DIR__ . '/auth.php';

$api_key = isset($_GET['api_key']) ? $_GET['api_key'] : '';
blog_require_api_key($api_key);

$args = [
    'post_type' => 'post',
    'post_status' => 'draft',
    'posts_per_page' => 100,
    'orderby' => 'date',
    'order' => 'ASC'
];

$drafts = get_posts($args);
$result = [];

foreach ($drafts as $draft) {
    $thumbnail_id = get_post_meta($draft->ID, '_thumbnail_id', true);
    $result[] = [
        'id' => $draft->ID,
        'title' => $draft->post_title,
        'date' => $draft->post_date,
        'modified' => $draft->post_modified,
        'categories' => wp_get_post_categories($draft->ID),
        'excerpt' => $draft->post_excerpt,
        'content' => wp_trim_words($draft->post_content, 50),
        'thumbnail_id' => $thumbnail_id,
        'has_thumbnail' => !empty($thumbnail_id),
    ];
}

echo json_encode([
    'success' => true,
    'data' => $result,
    'total' => count($result),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
