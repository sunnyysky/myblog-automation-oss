<?php
/**
 * Plugin Name: MyBlog Tools
 * Description: Automation endpoints for draft/publish/content/image workflows.
 * Version: 0.1.0
 * Author: MyBlog OSS
 */

if (!defined('ABSPATH')) {
    exit;
}

function myblog_tools_expected_api_key() {
    $env = getenv('BLOG_API_KEY');
    if ($env && trim($env) !== '') {
        return trim($env);
    }
    if (defined('BLOG_API_KEY') && BLOG_API_KEY) {
        return BLOG_API_KEY;
    }
    return '';
}

function myblog_tools_parse_request_data($request) {
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = [];
    }
    $body_params = $request->get_body_params();
    if (is_array($body_params)) {
        $params = array_merge($body_params, $params);
    }
    $query_params = $request->get_query_params();
    if (is_array($query_params)) {
        $params = array_merge($query_params, $params);
    }
    return $params;
}

function myblog_tools_require_api_key($request) {
    $expected = myblog_tools_expected_api_key();
    if (!$expected) {
        return new WP_Error('server_key_missing', 'Server API key is not configured', ['status' => 500]);
    }

    $params = myblog_tools_parse_request_data($request);
    $provided = isset($params['api_key']) ? (string) $params['api_key'] : '';

    if (!hash_equals($expected, $provided)) {
        return new WP_Error('invalid_api_key', 'Invalid API key', ['status' => 401]);
    }
    return true;
}

function myblog_tools_ok($data = []) {
    return new WP_REST_Response(array_merge(['success' => true], $data), 200);
}

function myblog_tools_fail($msg, $status = 400) {
    return new WP_REST_Response(['success' => false, 'error' => $msg], $status);
}

function myblog_tools_route_drafts($request) {
    $auth = myblog_tools_require_api_key($request);
    if (is_wp_error($auth)) return $auth;

    $drafts = get_posts([
        'post_type' => 'post',
        'post_status' => 'draft',
        'posts_per_page' => 100,
        'orderby' => 'date',
        'order' => 'ASC',
    ]);

    $result = [];
    foreach ($drafts as $draft) {
        $thumb = get_post_meta($draft->ID, '_thumbnail_id', true);
        $result[] = [
            'id' => $draft->ID,
            'title' => $draft->post_title,
            'date' => $draft->post_date,
            'thumbnail_id' => $thumb,
            'has_thumbnail' => !empty($thumb),
        ];
    }

    return myblog_tools_ok(['data' => $result, 'total' => count($result)]);
}

function myblog_tools_route_published($request) {
    $auth = myblog_tools_require_api_key($request);
    if (is_wp_error($auth)) return $auth;

    $ids = get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 500,
        'fields' => 'ids',
    ]);

    $rows = [];
    foreach ($ids as $post_id) {
        $rows[] = ['id' => $post_id, 'title' => get_the_title($post_id)];
    }
    return myblog_tools_ok(['data' => $rows, 'total' => count($rows)]);
}

function myblog_tools_route_publish_draft($request) {
    $auth = myblog_tools_require_api_key($request);
    if (is_wp_error($auth)) return $auth;
    $p = myblog_tools_parse_request_data($request);
    $post_id = isset($p['post_id']) ? intval($p['post_id']) : 0;
    if (!$post_id) return myblog_tools_fail('Invalid post_id');

    $post = get_post($post_id);
    if (!$post) return myblog_tools_fail('Post not found', 404);
    if ($post->post_status !== 'draft') return myblog_tools_fail('Post is not a draft');

    $res = wp_update_post([
        'ID' => $post_id,
        'post_status' => 'publish',
        'post_date' => current_time('mysql'),
    ], true);
    if (is_wp_error($res)) return myblog_tools_fail($res->get_error_message(), 500);

    return myblog_tools_ok([
        'post_id' => $post_id,
        'title' => get_the_title($post_id),
        'link' => get_permalink($post_id),
    ]);
}

function myblog_tools_route_delete_posts($request) {
    $auth = myblog_tools_require_api_key($request);
    if (is_wp_error($auth)) return $auth;
    $p = myblog_tools_parse_request_data($request);

    $post_ids = isset($p['post_ids']) ? $p['post_ids'] : [];
    if (!is_array($post_ids)) $post_ids = explode(',', (string)$post_ids);
    if (empty($post_ids)) return myblog_tools_fail('No post_ids provided');

    $deleted = 0; $failed = 0; $failed_ids = [];
    foreach ($post_ids as $id) {
        $id = intval($id);
        if ($id <= 0) continue;
        if (wp_delete_post($id, true)) $deleted++;
        else { $failed++; $failed_ids[] = $id; }
    }

    return myblog_tools_ok([
        'deleted_count' => $deleted,
        'failed_count' => $failed,
        'failed_posts' => $failed_ids,
    ]);
}

function myblog_tools_route_create_post($request) {
    $auth = myblog_tools_require_api_key($request);
    if (is_wp_error($auth)) return $auth;
    $p = myblog_tools_parse_request_data($request);

    if (empty($p['title']) || empty($p['content'])) {
        return myblog_tools_fail('Missing required fields: title, content');
    }

    $post_data = [
        'post_title' => $p['title'],
        'post_content' => $p['content'],
        'post_status' => !empty($p['status']) ? $p['status'] : 'draft',
        'post_excerpt' => !empty($p['excerpt']) ? $p['excerpt'] : '',
        'post_author' => !empty($p['author_id']) ? intval($p['author_id']) : 1,
    ];

    if (!empty($p['categories'])) {
        $cat_ids = [];
        $cats = is_array($p['categories']) ? $p['categories'] : [$p['categories']];
        foreach ($cats as $cat_name) {
            if (is_numeric($cat_name)) { $cat_ids[] = intval($cat_name); continue; }
            $cat = get_category_by_slug(sanitize_title($cat_name));
            if ($cat) $cat_ids[] = $cat->term_id;
            else {
                $new_cat = wp_insert_term($cat_name, 'category', ['slug' => sanitize_title($cat_name)]);
                if (!is_wp_error($new_cat)) $cat_ids[] = $new_cat['term_id'];
            }
        }
        if (!empty($cat_ids)) $post_data['post_category'] = $cat_ids;
    }

    if (!empty($p['tags']) && is_array($p['tags'])) {
        $post_data['tags_input'] = $p['tags'];
    }

    $post_id = wp_insert_post($post_data);
    if (!$post_id || is_wp_error($post_id)) {
        $msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'Create post failed';
        return myblog_tools_fail($msg, 500);
    }

    if (!empty($p['seo_title'])) update_post_meta($post_id, '_yoast_wpseo_title', $p['seo_title']);
    if (!empty($p['seo_description'])) update_post_meta($post_id, '_yoast_wpseo_metadesc', $p['seo_description']);
    if (!empty($p['seo_keywords'])) update_post_meta($post_id, '_yoast_wpseo_focuskw', $p['seo_keywords']);
    if (!empty($p['featured_image']) && intval($p['featured_image']) > 0) {
        update_post_meta($post_id, '_thumbnail_id', intval($p['featured_image']));
    }

    return myblog_tools_ok([
        'post_id' => $post_id,
        'url' => get_permalink($post_id),
    ]);
}

function myblog_tools_route_upload_image($request) {
    $auth = myblog_tools_require_api_key($request);
    if (is_wp_error($auth)) return $auth;
    $p = myblog_tools_parse_request_data($request);
    $img_url = isset($p['img_url']) ? (string)$p['img_url'] : '';
    if (!$img_url) return myblog_tools_fail('img_url is required');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($img_url, 30);
    if (is_wp_error($tmp)) return myblog_tools_fail($tmp->get_error_message(), 500);

    $filename = basename(parse_url($img_url, PHP_URL_PATH));
    if (!$filename) $filename = 'image.jpg';

    $file_array = [
        'name' => sanitize_file_name($filename),
        'tmp_name' => $tmp,
    ];

    $attach_id = media_handle_sideload($file_array, 0);
    if (is_wp_error($attach_id)) {
        @unlink($tmp);
        return myblog_tools_fail($attach_id->get_error_message(), 500);
    }

    return myblog_tools_ok([
        'attachment_id' => $attach_id,
        'url' => wp_get_attachment_url($attach_id),
    ]);
}

add_action('rest_api_init', function () {
    register_rest_route('myblog/v1', '/drafts', [
        'methods' => 'GET',
        'callback' => 'myblog_tools_route_drafts',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('myblog/v1', '/published', [
        'methods' => 'GET',
        'callback' => 'myblog_tools_route_published',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('myblog/v1', '/publish-draft', [
        'methods' => 'POST',
        'callback' => 'myblog_tools_route_publish_draft',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('myblog/v1', '/delete-posts', [
        'methods' => 'POST',
        'callback' => 'myblog_tools_route_delete_posts',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('myblog/v1', '/posts', [
        'methods' => 'POST',
        'callback' => 'myblog_tools_route_create_post',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('myblog/v1', '/upload-image', [
        'methods' => 'POST',
        'callback' => 'myblog_tools_route_upload_image',
        'permission_callback' => '__return_true',
    ]);
});
