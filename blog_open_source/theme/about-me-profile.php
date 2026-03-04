<?php
/**
 * Reusable profile data + helpers for /category/about-me/
 * Safe for open-source: no personal/private hardcoded data.
 */

if (!function_exists('myblog_get_about_profile_config')) {
    function myblog_get_about_profile_config() {
        $config = array(
            'category_slug' => 'about-me',
            'name_cn' => '你的姓名',
            'name_en' => 'Your Name',
            'headline' => '一句话定位：你是谁、你擅长什么',
            'intro' => '在这里写 2-3 句简介，强调你的专业方向和可提供价值。',
            'city' => '你的城市',
            'wechat_id' => 'your_wechat_id',
            'wechat_qr' => get_stylesheet_directory_uri() . '/images/about/wechat-qr.jpg',
            'wechat_note' => '添加时请备注来源，便于快速通过。',
            'primary_cta' => array(
                'label' => '联系我',
                'url' => '#aboutme-contact',
            ),
            'secondary_cta' => array(
                'label' => '查看专题',
                'url' => home_url('/zhuanti/'),
            ),
            'problem_points' => array(
                '你能帮用户解决的典型问题 1',
                '你能帮用户解决的典型问题 2',
                '你能帮用户解决的典型问题 3',
            ),
            'domain_points' => array(
                '你的专业方向 1',
                '你的专业方向 2',
                '你的专业方向 3',
            ),
            'method_points' => array(
                array(
                    'title' => '自动化',
                    'desc' => '减少重复劳动，提升效率。',
                ),
                array(
                    'title' => '模板化',
                    'desc' => '沉淀可复用资产，降低成本。',
                ),
                array(
                    'title' => '流程化',
                    'desc' => '建立稳定交付闭环。',
                ),
                array(
                    'title' => '工具化',
                    'desc' => '让经验变成可执行能力。',
                ),
            ),
            'value_points' => array(
                '用户可获得的价值 1',
                '用户可获得的价值 2',
                '用户可获得的价值 3',
            ),
            'site_positioning' => array(
                '站点定位 1',
                '站点定位 2',
                '站点定位 3',
            ),
            'links' => array(
                array(
                    'label' => '首页',
                    'url' => home_url('/'),
                ),
                array(
                    'label' => '专题页',
                    'url' => home_url('/zhuanti/'),
                ),
                array(
                    'label' => '标签页',
                    'url' => home_url('/tags/'),
                ),
            ),
        );

        return apply_filters('myblog_about_profile_config', $config);
    }
}

if (!function_exists('myblog_get_about_page_posts')) {
    function myblog_get_about_page_posts($category_slug = 'about-me', $limit = 6) {
        $category_slug = sanitize_title((string) $category_slug);
        if ($category_slug === '') {
            $category_slug = 'about-me';
        }
        $limit = max(3, min(12, (int) $limit));

        $post_ids = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'ignore_sticky_posts' => true,
            'category_name' => $category_slug,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'suppress_filters' => false,
        ));
        $post_ids = array_map('intval', (array) $post_ids);

        if (count($post_ids) < $limit) {
            $fallback_ids = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => $limit - count($post_ids),
                'ignore_sticky_posts' => true,
                'post__not_in' => $post_ids,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
                'suppress_filters' => false,
            ));
            $post_ids = array_merge($post_ids, array_map('intval', (array) $fallback_ids));
        }

        return array_values(array_filter(array_unique($post_ids)));
    }
}

if (!function_exists('myblog_get_about_site_stats')) {
    function myblog_get_about_site_stats() {
        $post_counts = wp_count_posts('post');
        $published_posts = isset($post_counts->publish) ? (int) $post_counts->publish : 0;

        $category_count = wp_count_terms('category', array(
            'taxonomy' => 'category',
            'hide_empty' => true,
        ));
        $tag_count = wp_count_terms('post_tag', array(
            'taxonomy' => 'post_tag',
            'hide_empty' => true,
        ));

        $recent_query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => array('publish'),
            'posts_per_page' => 1,
            'ignore_sticky_posts' => 1,
            'date_query' => array(
                array(
                    'after' => '7 days ago',
                )
            ),
            'fields' => 'ids',
            'no_found_rows' => false,
        ));
        $recent_count = isset($recent_query->found_posts) ? (int) $recent_query->found_posts : 0;
        wp_reset_postdata();

        return array(
            'posts' => max(0, $published_posts),
            'categories' => is_wp_error($category_count) ? 0 : (int) $category_count,
            'tags' => is_wp_error($tag_count) ? 0 : (int) $tag_count,
            'recent_7d' => max(0, $recent_count),
        );
    }
}

if (!function_exists('myblog_get_about_page_context')) {
    function myblog_get_about_page_context($category_slug = 'about-me', $limit = 6) {
        $profile = myblog_get_about_profile_config();
        if (isset($profile['category_slug']) && $profile['category_slug']) {
            $category_slug = sanitize_title((string) $profile['category_slug']);
        }

        $category = get_category_by_slug($category_slug);
        $category_desc = $category && !is_wp_error($category) && isset($category->term_id)
            ? term_description((int) $category->term_id, 'category')
            : '';

        return array(
            'profile' => $profile,
            'category_slug' => $category_slug,
            'category_desc' => $category_desc,
            'stats' => myblog_get_about_site_stats(),
            'post_ids' => myblog_get_about_page_posts($category_slug, $limit),
        );
    }
}
