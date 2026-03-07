<?php
/**
 * Guard published posts against missing content images.
 *
 * Usage:
 *   php guard_published_images.php --dry-run
 *   php guard_published_images.php --apply --limit=300
 *   php guard_published_images.php --apply --category-slug=ai-cases
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SERVER['HTTP_USER_AGENT']) || !$_SERVER['HTTP_USER_AGENT']) {
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; PublishedImageGuard/1.0)';
}
if (!isset($_SERVER['REMOTE_ADDR']) || !$_SERVER['REMOTE_ADDR']) {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

define('PIG_DEFAULT_LIMIT', 300);

require_once __DIR__ . '/wp-load.php';

global $wpdb;

$options = parse_options($argv);
$candidates = find_candidates($options, $wpdb);

$stats = array(
    'mode' => $options['apply'] ? 'apply' : 'dry-run',
    'limit' => $options['limit'],
    'category_slug' => $options['category_slug'],
    'default_image_url' => $options['default_image_url'],
    'total_candidates' => count($candidates),
    'fixed' => 0,
    'skipped_already_has_img' => 0,
    'skipped_no_image_source' => 0,
    'failed' => 0,
    'items' => array(),
);

foreach ($candidates as $post_id_raw) {
    $post_id = (int) $post_id_raw;
    if ($post_id <= 0) {
        continue;
    }

    $post_content = (string) get_post_field('post_content', $post_id);
    if (stripos($post_content, '<img') !== false) {
        $stats['skipped_already_has_img'] += 1;
        $stats['items'][] = array(
            'id' => $post_id,
            'status' => 'skip_has_img',
        );
        continue;
    }

    $image_url = (string) get_the_post_thumbnail_url($post_id, 'full');
    if ($image_url === '' && $options['default_image_url'] !== '') {
        $image_url = $options['default_image_url'];
    }

    if ($image_url === '') {
        $stats['skipped_no_image_source'] += 1;
        $stats['items'][] = array(
            'id' => $post_id,
            'status' => 'skip_no_image_source',
        );
        continue;
    }

    if (!$options['apply']) {
        $stats['fixed'] += 1;
        $stats['items'][] = array(
            'id' => $post_id,
            'status' => 'would_fix',
            'image_url' => $image_url,
        );
        continue;
    }

    $img_html = '<p><img src="' . esc_url($image_url) . '" alt="" loading="lazy" referrerpolicy="no-referrer"></p>';
    $new_content = $img_html . "\n" . $post_content;

    $result = wp_update_post(
        array(
            'ID' => $post_id,
            'post_content' => $new_content,
        ),
        true
    );

    if (is_wp_error($result)) {
        $stats['failed'] += 1;
        $stats['items'][] = array(
            'id' => $post_id,
            'status' => 'error',
            'message' => $result->get_error_message(),
        );
        continue;
    }

    $stats['fixed'] += 1;
    $stats['items'][] = array(
        'id' => $post_id,
        'status' => 'fixed',
        'image_url' => $image_url,
    );
}

$payload = array(
    'timestamp' => date('c'),
    'stats' => $stats,
);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

if ($options['apply'] && (int) $stats['failed'] > 0) {
    exit(2);
}
exit(0);

function parse_options($argv)
{
    $out = array(
        'apply' => false,
        'limit' => PIG_DEFAULT_LIMIT,
        'category_slug' => '',
        'default_image_url' => '',
    );

    if (!is_array($argv)) {
        return $out;
    }

    foreach ($argv as $idx => $arg) {
        if ($idx === 0 || !is_string($arg)) {
            continue;
        }
        if ($arg === '--apply') {
            $out['apply'] = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $out['apply'] = false;
            continue;
        }
        if (strpos($arg, '--limit=') === 0) {
            $limit_raw = trim(substr($arg, 8));
            $limit = (int) $limit_raw;
            if ($limit > 0) {
                $out['limit'] = $limit;
            }
            continue;
        }
        if (strpos($arg, '--category-slug=') === 0) {
            $out['category_slug'] = sanitize_title(trim(substr($arg, 16)));
            continue;
        }
        if (strpos($arg, '--default-image-url=') === 0) {
            $out['default_image_url'] = esc_url_raw(trim(substr($arg, 20)));
            continue;
        }
    }

    return $out;
}

function find_candidates($options, $wpdb)
{
    $limit = (int) $options['limit'];
    if ($limit <= 0) {
        $limit = PIG_DEFAULT_LIMIT;
    }

    $category_slug = isset($options['category_slug']) ? (string) $options['category_slug'] : '';

    if ($category_slug !== '') {
        $sql = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'category'
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
            WHERE p.post_type = 'post'
              AND p.post_status = 'publish'
              AND t.slug = %s
              AND COALESCE(pm.meta_value, '') <> ''
              AND (p.post_content IS NULL OR p.post_content = '' OR p.post_content NOT LIKE '%<img%')
            ORDER BY p.ID DESC
            LIMIT %d
        ";
        return $wpdb->get_col($wpdb->prepare($sql, $category_slug, $limit));
    }

    $sql = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
        WHERE p.post_type = 'post'
          AND p.post_status = 'publish'
          AND COALESCE(pm.meta_value, '') <> ''
          AND (p.post_content IS NULL OR p.post_content = '' OR p.post_content NOT LIKE '%<img%')
        ORDER BY p.ID DESC
        LIMIT %d
    ";
    return $wpdb->get_col($wpdb->prepare($sql, $limit));
}
