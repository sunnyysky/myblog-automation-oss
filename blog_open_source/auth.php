<?php
/**
 * Shared auth helper for custom WordPress endpoints.
 */

function blog_api_key_expected() {
    $env = getenv('BLOG_API_KEY');
    if ($env && trim($env) !== '') {
        return trim($env);
    }
    if (defined('BLOG_API_KEY') && BLOG_API_KEY) {
        return BLOG_API_KEY;
    }
    return '';
}

function blog_fail_json($msg, $code = 400) {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function blog_require_api_key($provided) {
    $expected = blog_api_key_expected();
    if (!$expected) {
        blog_fail_json('Server API key is not configured', 500);
    }
    if (!is_string($provided) || !hash_equals($expected, $provided)) {
        blog_fail_json('Invalid API key', 401);
    }
}
