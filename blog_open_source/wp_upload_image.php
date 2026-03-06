<?php
/**
 * Download an external image and insert it into WP media library.
 */

ini_set('memory_limit', '256M');
set_time_limit(300);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/wp-load.php';
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    blog_fail_json('Only POST is supported', 405);
}

$img_url = isset($_POST['img_url']) ? $_POST['img_url'] : '';
$api_key = isset($_POST['api_key']) ? $_POST['api_key'] : '';
blog_require_api_key($api_key);

if (!$img_url) {
    blog_fail_json('img_url is required');
}

$ch = curl_init($img_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_ENCODING, '');
$http_headers = [
    'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
    'Accept-Encoding: gzip,deflate,br',
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
$img_data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error || $http_code !== 200 || !$img_data) {
    blog_fail_json("Download failed HTTP:$http_code $error");
}

// Some sources still return gzipped bytes. Normalize to raw image bytes.
if (strncmp($img_data, "\x1f\x8b", 2) === 0) {
    $decoded = @gzdecode($img_data);
    if ($decoded !== false && strlen($decoded) > 0) {
        $img_data = $decoded;
    }
}

$path = parse_url($img_url, PHP_URL_PATH);
$filename = basename($path);
if (!pathinfo($filename, PATHINFO_EXTENSION)) {
    $filename .= '.jpg';
}

$upload_dir = wp_upload_dir();
if (!file_exists($upload_dir['path'])) {
    wp_mkdir_p($upload_dir['path']);
}

$filename = wp_unique_filename($upload_dir['path'], $filename);
$file_path = $upload_dir['path'] . '/' . $filename;
if (file_put_contents($file_path, $img_data) === false) {
    blog_fail_json('Write file failed');
}

$attachment = [
    'post_mime_type' => wp_check_filetype_and_ext($file_path, $filename, false)['type'],
    'post_title' => sanitize_file_name($filename),
    'post_content' => '',
    'post_status' => 'inherit',
];

$attach_id = wp_insert_attachment($attachment, $file_path);
if (is_wp_error($attach_id)) {
    @unlink($file_path);
    blog_fail_json('Create attachment failed: ' . $attach_id->get_error_message());
}

require_once ABSPATH . 'wp-admin/includes/image.php';
$attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
if (is_array($attach_data) && !empty($attach_data)) {
    wp_update_attachment_metadata($attach_id, $attach_data);
}

echo json_encode([
    'success' => true,
    'attachment_id' => $attach_id,
    'url' => wp_get_attachment_url($attach_id),
    'original_url' => $img_url,
    'filename' => $filename,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
