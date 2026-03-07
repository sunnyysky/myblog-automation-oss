<?php get_header();
$slider_items = function_exists('skyrobot_get_home_slider_items') ? skyrobot_get_home_slider_items(4) : [];
$slider_post_ids = [];
foreach ($slider_items as $slider_item) {
    if (!empty($slider_item['post_id'])) {
        $slider_post_ids[] = (int) $slider_item['post_id'];
    }
}
$feature_items = function_exists('skyrobot_get_home_feature_items') ? skyrobot_get_home_feature_items(3, $slider_post_ids) : [];
$is_fea_img = !empty($feature_items);
$hot_post_ids = function_exists('skyrobot_get_home_hot_post_ids') ? skyrobot_get_home_hot_post_ids(8) : [];
$spotlight_post_ids = function_exists('skyrobot_get_home_spotlight_post_ids') ? skyrobot_get_home_spotlight_post_ids(6) : [];
$hot_special_term_ids = function_exists('skyrobot_get_home_hot_special_term_ids') ? skyrobot_get_home_hot_special_term_ids(8) : [];
$hot_tag_term_ids = function_exists('skyrobot_get_home_hot_tag_term_ids') ? skyrobot_get_home_hot_tag_term_ids(16) : [];
$resolve_link_target = function ($url) {
    return function_exists('skyrobot_get_link_target') ? skyrobot_get_link_target($url) : '_self';
};
$resolve_link_rel = function ($url) {
    return function_exists('skyrobot_get_link_rel') ? skyrobot_get_link_rel($url) : '';
};
$home_section_limit = 8;
$home_section_post_limit = 6;
$home_latest_post_limit = min(12, max(1, (int) get_option('posts_per_page')));

$fixed_sections = [
    ['slug' => 'prompt-tutorials', 'fallback_name' => 'Prompt教程'],
    ['slug' => 'ai-tools', 'fallback_name' => 'AI工具'],
    ['slug' => 'ai-writing', 'fallback_name' => 'AI写作'],
    ['slug' => 'word-tutorials', 'fallback_name' => 'Word教程'],
    ['slug' => 'creative-design', 'fallback_name' => '创意设计'],
    ['slug' => 'marketing-growth', 'fallback_name' => '营销推广'],
    ['slug' => 'excel-tutorials', 'fallback_name' => 'Excel教程'],
    ['slug' => 'ppt-design', 'fallback_name' => 'PPT制作'],
    ['slug' => 'video-production', 'fallback_name' => '视频制作'],
    ['slug' => 'ai-fundamentals', 'fallback_name' => 'AI基础入门'],
    ['slug' => 'tool-recommendations', 'fallback_name' => '工具推荐'],
    ['slug' => 'business', 'fallback_name' => '创业'],
];
$section_icons = [
    'prompt-tutorials' => 'fa-magic',
    'ai-tools' => 'fa-cube',
    'ai-writing' => 'fa-pencil-square-o',
    'word-tutorials' => 'fa-file-word-o',
    'creative-design' => 'fa-paint-brush',
    'marketing-growth' => 'fa-line-chart',
    'excel-tutorials' => 'fa-table',
    'ppt-design' => 'fa-object-group',
    'video-production' => 'fa-video-camera',
    'ai-fundamentals' => 'fa-graduation-cap',
    'tool-recommendations' => 'fa-star',
    'business' => 'fa-rocket',
];
$resolved_sections = [];
$resolved_section_ids = [];
foreach ($fixed_sections as $section) {
    $category = get_category_by_slug($section['slug']);
    if (!$category && !empty($section['fallback_name'])) {
        $category = get_term_by('name', $section['fallback_name'], 'category');
    }
    if (!$category || is_wp_error($category) || (int) $category->count === 0) {
        continue;
    }

    $category_id = (int) $category->term_id;
    if ($category_id <= 0 || isset($resolved_section_ids[$category_id])) {
        continue;
    }

    $resolved_section_ids[$category_id] = true;
    $section_slug = (string) $section['slug'];
    $resolved_sections[] = [
        'category' => $category,
        'slug' => $section_slug,
        'icon' => isset($section_icons[$section_slug]) ? $section_icons[$section_slug] : 'fa-folder-open-o',
        'anchor' => 'home-cat-' . sanitize_title($category->slug),
    ];
}

if (count($resolved_sections) < $home_section_limit) {
    $fallback_categories = get_categories([
        'taxonomy' => 'category',
        'hide_empty' => true,
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 40,
    ]);
    if (!is_wp_error($fallback_categories) && !empty($fallback_categories)) {
        foreach ($fallback_categories as $fallback_category) {
            $fallback_category_id = isset($fallback_category->term_id) ? (int) $fallback_category->term_id : 0;
            if ($fallback_category_id <= 0 || isset($resolved_section_ids[$fallback_category_id])) {
                continue;
            }

            $resolved_section_ids[$fallback_category_id] = true;
            $fallback_slug = isset($fallback_category->slug) ? (string) $fallback_category->slug : '';
            $resolved_sections[] = [
                'category' => $fallback_category,
                'slug' => $fallback_slug,
                'icon' => isset($section_icons[$fallback_slug]) ? $section_icons[$fallback_slug] : 'fa-folder-open-o',
                'anchor' => 'home-cat-' . sanitize_title($fallback_slug ? $fallback_slug : (string) $fallback_category_id),
            ];
            if (count($resolved_sections) >= $home_section_limit) {
                break;
            }
        }
    }
}

$rendered_sections = [];
$site_stats = function_exists('skyrobot_get_site_stat_snapshot') ? skyrobot_get_site_stat_snapshot() : [];
$random_post_ids = function_exists('skyrobot_get_home_random_post_ids') ? skyrobot_get_home_random_post_ids(5) : [];
$focus_post_ids = [];
$focus_seen_ids = [];
$focus_sources = array_merge((array) $hot_post_ids, (array) $spotlight_post_ids, (array) $slider_post_ids, (array) $random_post_ids);
foreach ($focus_sources as $focus_source_id) {
    $focus_source_id = (int) $focus_source_id;
    if ($focus_source_id <= 0 || isset($focus_seen_ids[$focus_source_id])) {
        continue;
    }
    $focus_post = get_post($focus_source_id);
    if (!$focus_post || $focus_post->post_status !== 'publish') {
        continue;
    }
    $focus_seen_ids[$focus_source_id] = true;
    $focus_post_ids[] = $focus_source_id;
    if (count($focus_post_ids) >= 4) {
        break;
    }
}
if (count($focus_post_ids) < 4) {
    $fallback_focus_ids = get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 4,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
        'suppress_filters' => false,
    ]);
    foreach ((array) $fallback_focus_ids as $fallback_focus_id) {
        $fallback_focus_id = (int) $fallback_focus_id;
        if ($fallback_focus_id <= 0 || isset($focus_seen_ids[$fallback_focus_id])) {
            continue;
        }
        $focus_seen_ids[$fallback_focus_id] = true;
        $focus_post_ids[] = $fallback_focus_id;
        if (count($focus_post_ids) >= 4) {
            break;
        }
    }
}
?>
    <div class="main container home-main-modern">
        <div class="content">
            <?php if ($slider_items) { ?>
                <div class="slider-wrap clearfix">
                    <div class="main-slider flexslider<?php echo $is_fea_img ? ' pull-left' : ' slider-full'; ?>">
                        <ul class="slides">
                            <?php foreach ($slider_items as $k => $slider_item) {
                                $img = isset($slider_item['image']) ? $slider_item['image'] : '';
                                $slide_title = isset($slider_item['title']) ? $slider_item['title'] : '';
                                $slide_url = isset($slider_item['url']) ? $slider_item['url'] : '';
                                $slide_alt = isset($slider_item['alt']) && $slider_item['alt'] ? $slider_item['alt'] : $slide_title;
                                $slide_thumb_id = isset($slider_item['thumbnail_id']) ? (int) $slider_item['thumbnail_id'] : 0;
                                $slide_target = $resolve_link_target($slide_url);
                                $slide_rel = $resolve_link_rel($slide_url);
                                if (!$img) {
                                    continue;
                                }
                                $slide_image_html = '';
                                if ($slide_thumb_id > 0) {
                                    $slide_img_attrs = [
                                        'alt' => $slide_alt,
                                        'decoding' => 'async',
                                    ];
                                    if ($k === 0) {
                                        $slide_img_attrs['fetchpriority'] = 'high';
                                        $slide_img_attrs['data-no-lazy'] = '1';
                                    } else {
                                        $slide_img_attrs['loading'] = 'lazy';
                                    }
                                    $slide_image_html = wp_get_attachment_image($slide_thumb_id, 'large', false, $slide_img_attrs);
                                }
                                if (!$slide_image_html) {
                                    $slide_image_html = '<img src="' . esc_url($img) . '" alt="' . esc_attr($slide_alt) . '"' . ($k === 0 ? ' fetchpriority="high" decoding="async"' : ' loading="lazy" decoding="async"') . '>';
                                }
                                ?>
                                <li class="slide-item">
                                    <?php if ($slide_url) { ?>
                                        <a href="<?php echo esc_url($slide_url); ?>"<?php if ($slide_target === '_blank') { ?> target="_blank" rel="<?php echo esc_attr($slide_rel); ?>"<?php } ?>>
                                            <?php echo $slide_image_html; ?>
                                        </a>
                                        <?php if ($slide_title) { ?>
                                            <h3 class="slide-title">
                                                <a href="<?php echo esc_url($slide_url); ?>"<?php if ($slide_target === '_blank') { ?> target="_blank" rel="<?php echo esc_attr($slide_rel); ?>"<?php } ?>><?php echo esc_html($slide_title); ?></a>
                                            </h3>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <?php echo $slide_image_html; ?>
                                        <?php if ($slide_title) { ?>
                                            <h3 class="slide-title"><?php echo esc_html($slide_title); ?></h3>
                                        <?php } ?>
                                    <?php } ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>

                    <?php if ($is_fea_img) { ?>
                        <ul class="feature-post pull-right">
                            <?php foreach ($feature_items as $feature_item) {
                                $feature_img = isset($feature_item['image']) ? $feature_item['image'] : '';
                                $feature_title = isset($feature_item['title']) ? $feature_item['title'] : '';
                                $feature_url = isset($feature_item['url']) ? $feature_item['url'] : '';
                                $feature_thumb_id = isset($feature_item['thumbnail_id']) ? (int) $feature_item['thumbnail_id'] : 0;
                                $feature_target = $resolve_link_target($feature_url);
                                $feature_rel = $resolve_link_rel($feature_url);
                                if (!$feature_img) {
                                    continue;
                                }
                                $feature_image_html = '';
                                if ($feature_thumb_id > 0) {
                                    $feature_image_html = wp_get_attachment_image($feature_thumb_id, 'medium_large', false, [
                                        'alt' => $feature_title,
                                        'loading' => 'eager',
                                        'decoding' => 'async',
                                        'data-no-lazy' => '1',
                                    ]);
                                }
                                if (!$feature_image_html) {
                                    $feature_image_html = '<img src="' . esc_url($feature_img) . '" alt="' . esc_attr($feature_title) . '" loading="eager" decoding="async">';
                                }
                                ?>
                                <li>
                                    <?php if ($feature_url) { ?>
                                        <a href="<?php echo esc_url($feature_url); ?>"<?php if ($feature_target === '_blank') { ?> target="_blank" rel="<?php echo esc_attr($feature_rel); ?>"<?php } ?>>
                                            <?php echo $feature_image_html; ?>
                                        </a>
                                        <?php if ($feature_title) { ?>
                                            <span><?php echo esc_html($feature_title); ?></span>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <?php echo $feature_image_html; ?>
                                        <?php if ($feature_title) { ?>
                                            <span><?php echo esc_html($feature_title); ?></span>
                                        <?php } ?>
                                    <?php } ?>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php do_action('wpcom_echo_ad', 'ad_home_1'); ?>

            <?php if (!empty($focus_post_ids)) { ?>
                <div class="sec-panel home-main-panel home-focus-panel">
                    <div class="sec-panel-head">
                        <h2><span class="section-title-wrap"><i class="fa fa-bolt"></i>快速开始</span></h2>
                    </div>
                    <div class="sec-panel-body">
                        <div class="home-focus-grid">
                            <?php foreach ($focus_post_ids as $focus_post_id) {
                                $focus_title = get_the_title((int) $focus_post_id);
                                $focus_url = get_permalink((int) $focus_post_id);
                                $focus_thumb = get_the_post_thumbnail_url((int) $focus_post_id, 'post-thumbnail');
                                if (!$focus_thumb) {
                                    $focus_thumb = get_the_post_thumbnail_url((int) $focus_post_id, 'medium_large');
                                }
                                $focus_cats = get_the_category((int) $focus_post_id);
                                $focus_cat_name = (!empty($focus_cats) && isset($focus_cats[0]->name)) ? $focus_cats[0]->name : '推荐';
                                $focus_date = get_the_date('m-d', (int) $focus_post_id);
                                ?>
                                <a class="focus-card" href="<?php echo esc_url($focus_url); ?>">
                                    <?php if ($focus_thumb) { ?>
                                        <span class="focus-thumb">
                                            <img src="<?php echo esc_url($focus_thumb); ?>" alt="<?php echo esc_attr($focus_title); ?>" loading="lazy" decoding="async">
                                        </span>
                                    <?php } ?>
                                    <span class="focus-body">
                                        <span class="focus-kicker"><?php echo esc_html($focus_cat_name); ?></span>
                                        <span class="focus-title"><?php echo esc_html($focus_title); ?></span>
                                        <span class="focus-meta"><?php echo esc_html($focus_date); ?></span>
                                    </span>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($resolved_sections)) { ?>
                <div class="sec-panel home-main-panel home-directory-panel">
                    <div class="sec-panel-head">
                        <h2><span class="section-title-wrap"><i class="fa fa-th-large"></i>内容目录</span></h2>
                    </div>
                    <div class="sec-panel-body">
                        <div class="home-directory-grid">
                            <?php foreach (array_slice($resolved_sections, 0, $home_section_limit) as $directory_section) {
                                if (!isset($directory_section['category']) || !($directory_section['category'] instanceof WP_Term)) {
                                    continue;
                                }
                                $directory_category = $directory_section['category'];
                                $directory_anchor = isset($directory_section['anchor']) ? (string) $directory_section['anchor'] : '';
                                $directory_icon = isset($directory_section['icon']) ? (string) $directory_section['icon'] : 'fa-folder-open-o';
                                ?>
                                <a class="directory-chip" href="#<?php echo esc_attr($directory_anchor); ?>">
                                    <span class="chip-icon"><i class="fa <?php echo esc_attr($directory_icon); ?>"></i></span>
                                    <span class="chip-label"><?php echo esc_html($directory_category->name); ?></span>
                                    <span class="chip-count"><?php echo (int) $directory_category->count; ?></span>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="sec-panel main-list home-main-panel home-main-latest">
                <div class="sec-panel-head">
                    <h2><?php _e('Latest Posts', 'wpcom'); ?></h2>
                </div>
                <ul class="article-list">
                    <?php
                    $latest_args = [
                        'posts_per_page' => $home_latest_post_limit,
                        'ignore_sticky_posts' => 0,
                        'post_type' => 'post',
                        'post_status' => ['publish'],
                        'category__not_in' => isset($options['newest_exclude']) ? $options['newest_exclude'] : [],
                        'no_found_rows' => true,
                    ];
                    $latest_posts = new WP_Query($latest_args);
                    if ($latest_posts->have_posts()) {
                        while ($latest_posts->have_posts()) {
                            $latest_posts->the_post();
                            get_template_part('templates/list', 'default-sticky');
                        }
                    }
                    wp_reset_postdata();
                    ?>
                </ul>
            </div>

            <?php do_action('wpcom_echo_ad', 'ad_home_2'); ?>

            <?php foreach (array_slice($resolved_sections, 0, $home_section_limit) as $resolved_section) {
                $category = $resolved_section['category'];
                if (!$category || is_wp_error($category)) {
                    continue;
                }

                $section_query = new WP_Query([
                    'post_type' => 'post',
                    'post_status' => ['publish'],
                    'cat' => (int) $category->term_id,
                    'posts_per_page' => $home_section_post_limit,
                    'ignore_sticky_posts' => 1,
                    'no_found_rows' => true,
                ]);

                if (!$section_query->have_posts()) {
                    wp_reset_postdata();
                    continue;
                }
                ?>
                <div id="<?php echo esc_attr(isset($resolved_section['anchor']) ? $resolved_section['anchor'] : ''); ?>" class="sec-panel main-list home-main-panel home-main-category">
                    <div class="sec-panel-head">
                        <h2>
                            <?php
                            $section_icon = isset($resolved_section['icon']) ? (string) $resolved_section['icon'] : 'fa-folder-open-o';
                            ?>
                            <span class="section-title-wrap"><i class="fa <?php echo esc_attr($section_icon); ?>"></i><?php echo esc_html($category->name); ?></span>
                            <span class="section-count"><?php echo (int) $category->count; ?></span>
                            <a href="<?php echo esc_url(get_category_link((int) $category->term_id)); ?>" class="more"><?php esc_html_e('View all', 'wpcom'); ?></a>
                        </h2>
                    </div>
                    <ul class="article-list">
                        <?php
                        while ($section_query->have_posts()) {
                            $section_query->the_post();
                            get_template_part('templates/list', 'default-sticky');
                        }
                        ?>
                    </ul>
                </div>
                <?php
                $rendered_sections[] = $category;
                wp_reset_postdata();
            } ?>
        </div>
        <aside class="sidebar home-sidebar-modern">
            <div class="sec-panel home-side-panel">
                <div class="sec-panel-head">
                    <h2><span class="panel-icon"><i class="fa fa-search"></i></span><span>站内搜索</span></h2>
                </div>
                <div class="sec-panel-body">
                    <?php get_search_form(); ?>
                </div>
            </div>

            <?php if (!empty($site_stats)) { ?>
                <div class="sec-panel home-side-panel">
                    <div class="sec-panel-head">
                        <h2><span class="panel-icon"><i class="fa fa-signal"></i></span><span>站点概览</span></h2>
                    </div>
                    <div class="sec-panel-body">
                        <div class="site-stat-grid">
                            <div class="stat-item">
                                <span class="stat-num"><?php echo (int) (isset($site_stats['posts']) ? $site_stats['posts'] : 0); ?></span>
                                <span class="stat-label">文章</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-num"><?php echo (int) (isset($site_stats['categories']) ? $site_stats['categories'] : 0); ?></span>
                                <span class="stat-label">分类</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-num"><?php echo (int) (isset($site_stats['tags']) ? $site_stats['tags'] : 0); ?></span>
                                <span class="stat-label">标签</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-num"><?php echo (int) (isset($site_stats['specials']) ? $site_stats['specials'] : 0); ?></span>
                                <span class="stat-label">专题</span>
                            </div>
                        </div>
                        <p class="site-stat-desc">近 7 天更新 <?php echo (int) (isset($site_stats['recent_7d']) ? $site_stats['recent_7d'] : 0); ?> 篇内容</p>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($hot_post_ids)) { ?>
                <div class="sec-panel home-side-panel">
                    <div class="sec-panel-head">
                        <h2><span class="panel-icon"><i class="fa fa-fire"></i></span><span>热门文章</span></h2>
                    </div>
                    <div class="sec-panel-body">
                        <ul class="list">
                            <?php
                            $hot_rank = 1;
                            foreach ($hot_post_ids as $hot_post_id) {
                                $hot_post = get_post((int) $hot_post_id);
                                if (!$hot_post || $hot_post->post_status !== 'publish') {
                                    continue;
                                }
                                $hot_url = get_permalink((int) $hot_post_id);
                                $hot_title = get_the_title((int) $hot_post_id);
                                $hot_views = (int) get_post_meta((int) $hot_post_id, 'views', true);
                                $hot_target = $resolve_link_target($hot_url);
                                $hot_rel = $resolve_link_rel($hot_url);
                                ?>
                                <li class="hot-item">
                                    <span class="hot-rank"><?php echo str_pad((string) $hot_rank, 2, '0', STR_PAD_LEFT); ?></span>
                                    <a href="<?php echo esc_url($hot_url); ?>"<?php if ($hot_target === '_blank') { ?> target="_blank" rel="<?php echo esc_attr($hot_rel); ?>"<?php } ?>><?php echo esc_html($hot_title); ?></a>
                                    <?php if ($hot_views > 0) { ?>
                                        <span class="hot-meta"><?php echo esc_html(function_exists('skyrobot_format_compact_number') ? skyrobot_format_compact_number($hot_views) : (string) $hot_views); ?></span>
                                    <?php } ?>
                                </li>
                            <?php
                                $hot_rank++;
                            } ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($random_post_ids)) { ?>
                <div class="sec-panel home-side-panel">
                    <div class="sec-panel-head">
                        <h2><span class="panel-icon"><i class="fa fa-random"></i></span><span>随机探索</span></h2>
                    </div>
                    <div class="sec-panel-body">
                        <ul class="list">
                            <?php foreach ($random_post_ids as $random_post_id) {
                                $random_post = get_post((int) $random_post_id);
                                if (!$random_post || $random_post->post_status !== 'publish') {
                                    continue;
                                }
                                $random_url = get_permalink((int) $random_post_id);
                                $random_title = get_the_title((int) $random_post_id);
                                ?>
                                <li class="spotlight-item">
                                    <a href="<?php echo esc_url($random_url); ?>">
                                        <span class="spotlight-title"><?php echo esc_html($random_title); ?></span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($spotlight_post_ids)) { ?>
                <div class="sec-panel home-side-panel">
                    <div class="sec-panel-head">
                        <h2><span class="panel-icon"><i class="fa fa-lightbulb-o"></i></span><span>精选阅读</span></h2>
                    </div>
                    <div class="sec-panel-body">
                        <ul class="list spotlight-list">
                            <?php foreach ($spotlight_post_ids as $spotlight_post_id) {
                                $spotlight_post = get_post((int) $spotlight_post_id);
                                if (!$spotlight_post || $spotlight_post->post_status !== 'publish') {
                                    continue;
                                }
                                $spotlight_url = get_permalink((int) $spotlight_post_id);
                                $spotlight_title = get_the_title((int) $spotlight_post_id);
                                $spotlight_target = $resolve_link_target($spotlight_url);
                                $spotlight_rel = $resolve_link_rel($spotlight_url);
                                $spotlight_categories = get_the_category((int) $spotlight_post_id);
                                $spotlight_category = (!empty($spotlight_categories) && isset($spotlight_categories[0]->name)) ? $spotlight_categories[0]->name : '推荐';
                                ?>
                                <li class="spotlight-item">
                                    <a href="<?php echo esc_url($spotlight_url); ?>"<?php if ($spotlight_target === '_blank') { ?> target="_blank" rel="<?php echo esc_attr($spotlight_rel); ?>"<?php } ?>>
                                        <span class="spotlight-title"><?php echo esc_html($spotlight_title); ?></span>
                                        <span class="spotlight-meta"><?php echo esc_html($spotlight_category); ?></span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($rendered_sections)) { ?>
                <div class="sec-panel home-side-panel">
                    <div class="sec-panel-head">
                        <h2><span class="panel-icon"><i class="fa fa-compass"></i></span><span>分类导航</span></h2>
                    </div>
                    <div class="sec-panel-body">
                        <ul class="list">
                            <?php foreach (array_slice($rendered_sections, 0, $home_section_limit) as $rendered_category) { ?>
                                <li class="category-nav-item">
                                    <?php $category_url = get_category_link((int) $rendered_category->term_id); ?>
                                    <a href="<?php echo esc_url($category_url); ?>">
                                        <span><?php echo esc_html($rendered_category->name); ?></span>
                                        <span class="count-badge"><?php echo (int) $rendered_category->count; ?></span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>

            <?php
            $hot_specials = [];
            if (!empty($hot_special_term_ids)) {
                $hot_specials = get_terms([
                    'taxonomy' => 'special',
                    'hide_empty' => true,
                    'include' => $hot_special_term_ids,
                    'orderby' => 'include',
                ]);
            }
            if (!is_wp_error($hot_specials) && !empty($hot_specials)) { ?>
                <div class="sec-panel home-side-panel">
                    <div class="sec-panel-head">
                        <h2><span class="panel-icon"><i class="fa fa-diamond"></i></span><span>热门专题</span></h2>
                    </div>
                    <div class="sec-panel-body">
                        <ul class="list">
                            <?php foreach ($hot_specials as $special_term) { ?>
                                <li class="category-nav-item">
                                    <a href="<?php echo esc_url(get_term_link((int) $special_term->term_id, 'special')); ?>">
                                        <span><?php echo esc_html($special_term->name); ?></span>
                                        <span class="count-badge"><?php echo (int) $special_term->count; ?></span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            <?php } ?>

            <?php
            $hot_tags = [];
            if (!empty($hot_tag_term_ids)) {
                $hot_tags = get_terms([
                    'taxonomy' => 'post_tag',
                    'hide_empty' => true,
                    'include' => $hot_tag_term_ids,
                    'orderby' => 'include',
                ]);
            }
            if (!is_wp_error($hot_tags) && !empty($hot_tags)) { ?>
                <div class="sec-panel home-side-panel">
                    <div class="sec-panel-head">
                        <h2><span class="panel-icon"><i class="fa fa-tags"></i></span><span>热门标签</span></h2>
                    </div>
                    <div class="sec-panel-body">
                        <div class="tag-chip-cloud">
                            <?php foreach ($hot_tags as $hot_tag) {
                                if (!$hot_tag || !isset($hot_tag->term_id)) {
                                    continue;
                                }
                                $chip_color = function_exists('skyrobot_tag_color_index') ? skyrobot_tag_color_index($hot_tag->slug) : 1;
                                ?>
                                <a class="tag-chip sky-tag-color-<?php echo (int) $chip_color; ?>" href="<?php echo esc_url(get_tag_link((int) $hot_tag->term_id)); ?>">
                                    <span class="chip-name"><?php echo esc_html($hot_tag->name); ?></span>
                                    <span class="chip-count"><?php echo (int) $hot_tag->count; ?></span>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="sec-panel home-side-panel">
                <div class="sec-panel-head">
                    <h2><span class="panel-icon"><i class="fa fa-bell-o"></i></span><span>订阅更新</span></h2>
                </div>
                    <div class="sec-panel-body">
                        <ul class="list subscribe-list">
                        <li><a href="/feed/"><i class="fa fa-rss"></i> RSS 订阅</a></li>
                        <li><a href="/category/about-me/"><i class="fa fa-info-circle"></i> 关于我</a></li>
                        <li><a href="/wp-login.php?action=register"><i class="fa fa-user-plus"></i> 注册账号</a></li>
                    </ul>
                </div>
            </div>

            <?php
            ob_start();
            get_sidebar();
            $sidebar_html = ob_get_clean();
            $patterns = [
                '#<section[^>]*class="[^"]*widget[^"]*"[^>]*>.*?暂无内容.*?</section>#isu',
                '#<aside[^>]*class="[^"]*widget[^"]*"[^>]*>.*?暂无内容.*?</aside>#isu',
                '#<div[^>]*class="[^"]*widget[^"]*"[^>]*>.*?暂无内容.*?</div>#isu',
            ];
            $clean_sidebar = preg_replace($patterns, '', $sidebar_html);
            echo $clean_sidebar ? $clean_sidebar : $sidebar_html;
            ?>
        </aside>
    </div>

<?php
$partners = isset($options['pt_img']) && $options['pt_img'] ? $options['pt_img'] : [];
$link_cat = isset($options['link_cat']) && $options['link_cat'] ? $options['link_cat'] : '';
$bookmarks = get_bookmarks(['limit' => -1, 'category' => $link_cat, 'category_name' => '', 'hide_invisible' => 1, 'show_updated' => 0]);
if (($partners && isset($partners[0]) && $partners[0]) || $bookmarks) {
    ?>
    <div class="container hidden-xs j-partner">
        <div class="sec-panel">
            <?php if ($partners && isset($partners[0]) && $partners[0]) {
                if (isset($options['partner_title']) && $options['partner_title']) {
                    ?>
                    <div class="sec-panel-head">
                        <h2><?php echo $options['partner_title']; ?> <small><?php echo $options['partner_desc']; ?></small> <a href="<?php echo esc_url($options['partner_more_url']); ?>" target="_blank" class="more"><?php echo $options['partner_more_title']; ?></a></h2>
                    </div>
                <?php } ?>
                <div class="sec-panel-body">
                    <ul class="list list-partner">
                <?php
                        $width = isset($options['partner_img_width']) && $options['partner_img_width'] ? $options['partner_img_width'] . 'px' : 'auto';
                        foreach ($partners as $x => $pt) {
                            $url = $options['pt_url'] && $options['pt_url'][$x] ? $options['pt_url'][$x] : '';
                            if (function_exists('skyrobot_sanitize_home_link')) {
                                $url = skyrobot_sanitize_home_link($url);
                            }
                            $partner_target = function_exists('skyrobot_get_link_target') ? skyrobot_get_link_target($url) : '_blank';
                            $partner_rel = 'nofollow';
                            if ($partner_target === '_blank') {
                                $partner_rel = 'nofollow noopener';
                            }
                            $alt = $options['pt_title'] && $options['pt_title'][$x] ? $options['pt_title'][$x] : '';
                            ?>
                            <li>
                                <?php if ($url) { ?><a<?php if ($partner_target === '_blank') { ?> target="_blank"<?php } ?> title="<?php echo esc_attr($alt); ?>" href="<?php echo esc_url($url); ?>" rel="<?php echo esc_attr($partner_rel); ?>"><?php } ?><?php echo wpcom_lazyimg($pt, $alt, $width); ?><?php if ($url) { ?></a><?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php }
            if ($bookmarks) {
                if (isset($options['link_title']) && $options['link_title']) {
                    ?>
                    <div class="sec-panel-head">
                        <h2><?php echo $options['link_title']; ?> <small><?php echo $options['link_desc']; ?></small> <a href="<?php echo esc_url($options['link_more_url']); ?>" target="_blank" class="more"><?php echo $options['link_more_title']; ?></a></h2>
                    </div>
                <?php } ?>

                <div class="sec-panel-body">
                    <div class="list list-links">
                        <?php foreach ($bookmarks as $link) {
                            if ($link->link_visible == 'Y') { ?>
                                <a <?php if ($link->link_target) { ?>target="<?php echo $link->link_target; ?>" <?php } ?><?php if ($link->link_description) { ?>title="<?php echo esc_attr($link->link_description); ?>" <?php } ?>href="<?php echo $link->link_url; ?>"><?php echo $link->link_name; ?></a>
                            <?php }
                        } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>
<?php get_footer(); ?>
