<?php
/**
 * Category template for /category/about-me/
 * Requires: about-me-profile.php
 */
if (!function_exists('myblog_get_about_page_context')) {
    $helper = trailingslashit(get_stylesheet_directory()) . 'about-me-profile.php';
    if (file_exists($helper)) {
        require_once $helper;
    }
}

get_header();

$about_context = function_exists('myblog_get_about_page_context') ? myblog_get_about_page_context('about-me', 6) : array();
$about_profile = isset($about_context['profile']) ? (array) $about_context['profile'] : array();
$about_stats = isset($about_context['stats']) ? (array) $about_context['stats'] : array();
$about_post_ids = isset($about_context['post_ids']) ? array_map('intval', (array) $about_context['post_ids']) : array();
$about_category_desc = isset($about_context['category_desc']) ? (string) $about_context['category_desc'] : '';

$name_cn = isset($about_profile['name_cn']) ? (string) $about_profile['name_cn'] : '';
$name_en = isset($about_profile['name_en']) ? (string) $about_profile['name_en'] : '';
$headline = isset($about_profile['headline']) ? (string) $about_profile['headline'] : '';
$intro = isset($about_profile['intro']) ? (string) $about_profile['intro'] : '';
$city = isset($about_profile['city']) ? (string) $about_profile['city'] : '';
$wechat_id = isset($about_profile['wechat_id']) ? (string) $about_profile['wechat_id'] : '';
$wechat_qr = isset($about_profile['wechat_qr']) ? (string) $about_profile['wechat_qr'] : '';
$wechat_note = isset($about_profile['wechat_note']) ? (string) $about_profile['wechat_note'] : '';
$problem_points = isset($about_profile['problem_points']) ? (array) $about_profile['problem_points'] : array();
$domain_points = isset($about_profile['domain_points']) ? (array) $about_profile['domain_points'] : array();
$method_points = isset($about_profile['method_points']) ? (array) $about_profile['method_points'] : array();
$value_points = isset($about_profile['value_points']) ? (array) $about_profile['value_points'] : array();
$site_positioning = isset($about_profile['site_positioning']) ? (array) $about_profile['site_positioning'] : array();
$about_links = isset($about_profile['links']) ? (array) $about_profile['links'] : array();
$primary_cta = isset($about_profile['primary_cta']) ? (array) $about_profile['primary_cta'] : array();
$secondary_cta = isset($about_profile['secondary_cta']) ? (array) $about_profile['secondary_cta'] : array();

$primary_cta_label = isset($primary_cta['label']) ? (string) $primary_cta['label'] : '联系我';
$primary_cta_url = isset($primary_cta['url']) ? (string) $primary_cta['url'] : '#aboutme-contact';
$secondary_cta_label = isset($secondary_cta['label']) ? (string) $secondary_cta['label'] : '查看专题';
$secondary_cta_url = isset($secondary_cta['url']) ? (string) $secondary_cta['url'] : home_url('/zhuanti/');

$person_name = trim($name_cn . ' ' . $name_en);
if ($person_name === '') {
    $person_name = $name_cn ? $name_cn : $name_en;
}
$person_schema = array(
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => $name_cn ? $name_cn : $person_name,
    'alternateName' => $name_en,
    'jobTitle' => $headline,
    'description' => $intro,
    'address' => array(
        '@type' => 'PostalAddress',
        'addressLocality' => $city,
    ),
    'url' => home_url('/category/about-me/'),
);
?>
<div class="main container aboutme-card-page">
    <div class="content">
        <section class="sec-panel aboutme-hero">
            <div class="aboutme-hero-inner">
                <span class="aboutme-pill">Personal Brand</span>
                <h1>
                    <span class="name-cn"><?php echo esc_html($name_cn); ?></span>
                    <span class="name-en"><?php echo esc_html($name_en); ?></span>
                </h1>
                <?php if ($headline) { ?><p class="aboutme-headline"><?php echo esc_html($headline); ?></p><?php } ?>
                <?php if ($intro) { ?><p class="aboutme-intro"><?php echo esc_html($intro); ?></p><?php } ?>
                <?php if ($city) { ?><p class="aboutme-city"><i class="fa fa-map-marker"></i><?php echo esc_html($city); ?></p><?php } ?>
                <?php if ($about_category_desc) { ?><div class="aboutme-desc"><?php echo wp_kses_post($about_category_desc); ?></div><?php } ?>
                <div class="aboutme-cta-wrap">
                    <a class="aboutme-btn aboutme-btn-primary" href="<?php echo esc_url($primary_cta_url); ?>"><?php echo esc_html($primary_cta_label); ?></a>
                    <a class="aboutme-btn aboutme-btn-secondary" href="<?php echo esc_url($secondary_cta_url); ?>"><?php echo esc_html($secondary_cta_label); ?></a>
                </div>
            </div>
        </section>

        <?php if (!empty($problem_points)) { ?>
            <section class="sec-panel aboutme-panel">
                <div class="sec-panel-head"><h2><span class="section-title-wrap"><i class="fa fa-bullseye"></i>我能帮你解决什么</span></h2></div>
                <div class="sec-panel-body">
                    <div class="aboutme-feature-grid">
                        <?php foreach ($problem_points as $problem_item) {
                            $problem_item = trim((string) $problem_item);
                            if ($problem_item === '') { continue; }
                            ?><article class="aboutme-feature-card"><p><?php echo esc_html($problem_item); ?></p></article><?php
                        } ?>
                    </div>
                </div>
            </section>
        <?php } ?>

        <?php if (!empty($domain_points)) { ?>
            <section class="sec-panel aboutme-panel">
                <div class="sec-panel-head"><h2><span class="section-title-wrap"><i class="fa fa-cogs"></i>专业方向</span></h2></div>
                <div class="sec-panel-body">
                    <ul class="aboutme-highlight-list">
                        <?php foreach ($domain_points as $domain_item) {
                            $domain_item = trim((string) $domain_item);
                            if ($domain_item === '') { continue; }
                            ?><li><?php echo esc_html($domain_item); ?></li><?php
                        } ?>
                    </ul>
                </div>
            </section>
        <?php } ?>

        <?php if (!empty($value_points)) { ?>
            <section class="sec-panel aboutme-panel">
                <div class="sec-panel-head"><h2><span class="section-title-wrap"><i class="fa fa-star"></i>你将获得的价值</span></h2></div>
                <div class="sec-panel-body">
                    <ul class="aboutme-highlight-list">
                        <?php foreach ($value_points as $value_item) {
                            $value_item = trim((string) $value_item);
                            if ($value_item === '') { continue; }
                            ?><li><?php echo esc_html($value_item); ?></li><?php
                        } ?>
                    </ul>
                </div>
            </section>
        <?php } ?>

        <?php if (!empty($method_points)) { ?>
            <section class="sec-panel aboutme-panel">
                <div class="sec-panel-head"><h2><span class="section-title-wrap"><i class="fa fa-sitemap"></i>我的方法论（四化）</span></h2></div>
                <div class="sec-panel-body">
                    <div class="aboutme-method-grid">
                        <?php foreach ($method_points as $method_item) {
                            $method_title = isset($method_item['title']) ? trim((string) $method_item['title']) : '';
                            $method_desc = isset($method_item['desc']) ? trim((string) $method_item['desc']) : '';
                            if ($method_title === '' && $method_desc === '') { continue; }
                            ?>
                            <article class="aboutme-method-card">
                                <?php if ($method_title) { ?><h3><?php echo esc_html($method_title); ?></h3><?php } ?>
                                <?php if ($method_desc) { ?><p><?php echo esc_html($method_desc); ?></p><?php } ?>
                            </article>
                        <?php } ?>
                    </div>
                </div>
            </section>
        <?php } ?>

        <section class="sec-panel aboutme-panel aboutme-article-panel">
            <div class="sec-panel-head"><h2><span class="section-title-wrap"><i class="fa fa-book"></i>代表内容</span></h2></div>
            <div class="sec-panel-body">
                <?php if (!empty($about_post_ids)) { ?>
                    <ul class="article-list">
                        <?php
                        global $post;
                        foreach ($about_post_ids as $about_post_id) {
                            $about_post_id = (int) $about_post_id;
                            if ($about_post_id <= 0) { continue; }
                            $about_post = get_post($about_post_id);
                            if (!$about_post || $about_post->post_status !== 'publish') { continue; }

                            $post = $about_post;
                            setup_postdata($post);
                            if (locate_template('templates/list-default.php')) {
                                get_template_part('templates/list', 'default');
                            } else {
                                echo '<li><a href="' . esc_url(get_permalink($about_post_id)) . '">' . esc_html(get_the_title($about_post_id)) . '</a></li>';
                            }
                        }
                        wp_reset_postdata();
                        ?>
                    </ul>
                <?php } ?>
            </div>
        </section>
    </div>

    <aside class="sidebar aboutme-sidebar">
        <section class="sec-panel aboutme-side-panel aboutme-contact-panel" id="aboutme-contact">
            <div class="sec-panel-head"><h2><span class="panel-icon"><i class="fa fa-wechat"></i></span><span>联系我</span></h2></div>
            <div class="sec-panel-body">
                <?php if ($wechat_qr) { ?>
                    <div class="aboutme-qr-wrap"><img class="aboutme-qr" src="<?php echo esc_url($wechat_qr); ?>" alt="<?php echo esc_attr($person_name ? $person_name : '微信二维码'); ?>"></div>
                <?php } ?>
                <?php if ($wechat_id) { ?><p class="aboutme-wechat-id">微信：<strong><?php echo esc_html($wechat_id); ?></strong></p><?php } ?>
                <?php if ($wechat_note) { ?><p class="aboutme-wechat-note"><?php echo esc_html($wechat_note); ?></p><?php } ?>
            </div>
        </section>

        <section class="sec-panel aboutme-side-panel">
            <div class="sec-panel-head"><h2><span class="panel-icon"><i class="fa fa-line-chart"></i></span><span>网站概览</span></h2></div>
            <div class="sec-panel-body">
                <div class="aboutme-stat-grid">
                    <div class="stat-item"><span class="stat-num"><?php echo isset($about_stats['posts']) ? (int) $about_stats['posts'] : 0; ?></span><span class="stat-label">文章</span></div>
                    <div class="stat-item"><span class="stat-num"><?php echo isset($about_stats['categories']) ? (int) $about_stats['categories'] : 0; ?></span><span class="stat-label">分类</span></div>
                    <div class="stat-item"><span class="stat-num"><?php echo isset($about_stats['tags']) ? (int) $about_stats['tags'] : 0; ?></span><span class="stat-label">标签</span></div>
                    <div class="stat-item"><span class="stat-num"><?php echo isset($about_stats['recent_7d']) ? (int) $about_stats['recent_7d'] : 0; ?></span><span class="stat-label">7天更新</span></div>
                </div>
                <?php if (!empty($site_positioning)) { ?>
                    <ul class="aboutme-mini-list">
                        <?php foreach ($site_positioning as $positioning_item) {
                            $positioning_item = trim((string) $positioning_item);
                            if ($positioning_item === '') { continue; }
                            ?><li><?php echo esc_html($positioning_item); ?></li><?php
                        } ?>
                    </ul>
                <?php } ?>
            </div>
        </section>

        <?php if (!empty($about_links)) { ?>
            <section class="sec-panel aboutme-side-panel">
                <div class="sec-panel-head"><h2><span class="panel-icon"><i class="fa fa-link"></i></span><span>快速入口</span></h2></div>
                <div class="sec-panel-body">
                    <ul class="aboutme-link-list">
                        <?php foreach ($about_links as $about_link) {
                            $about_link_label = isset($about_link['label']) ? (string) $about_link['label'] : '';
                            $about_link_url = isset($about_link['url']) ? (string) $about_link['url'] : '';
                            if ($about_link_label === '' || $about_link_url === '') { continue; }
                            ?><li><a href="<?php echo esc_url($about_link_url); ?>"><?php echo esc_html($about_link_label); ?></a></li><?php
                        } ?>
                    </ul>
                </div>
            </section>
        <?php } ?>
    </aside>
</div>
<script type="application/ld+json"><?php echo wp_json_encode($person_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<?php get_footer(); ?>
