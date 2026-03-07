<?php
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$now_ua = array('FeedDemon ','ZmEu','Indy Library','oBot','jaunty'); //将恶意USER_AGENT存入数组
$is_cli_request = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' || (defined('WP_CLI') && WP_CLI);
if(!$is_cli_request) {
    if(!$ua) { //禁止空USER_AGENT，dedecms等主流采集程序都是空USER_AGENT，部分sql注入工具也是空USER_AGENT
    header("Content-type: text/html; charset=utf-8");
    wp_die('请勿采集本站，因为采集的站长木JJ！');
    }else{
        foreach($now_ua as $value )
        if(stripos($ua, $value) !== false) {
        header("Content-type: text/html; charset=utf-8");
        wp_die('请勿采集本站，因为采集的站长木JJ！');
        }
    }
}

define( 'THEME_ID', '5b4220be66895b87' ); // 主题ID，请勿修改！！！
define( 'THEME_VERSION', '5.2.3' ); // 主题版本号，请勿修改！！！

// Themer 框架路径信息常量，请勿修改，框架会用到
define( 'FRAMEWORK_PATH', is_dir($framework_path = get_template_directory() . '/themer') ? $framework_path : get_theme_root() . '/Themer/themer' );
define( 'FRAMEWORK_URI', is_dir($framework_path) ? get_template_directory_uri() . '/themer' : get_theme_root_uri() . '/Themer/themer' );

require FRAMEWORK_PATH .'/load.php';

function add_menu(){
    return array(
        'primary'   => '导航菜单',
        'footer'   => '页脚菜单'
    );
}
add_filter('wpcom_menus', 'add_menu');

// sidebar
if ( ! function_exists( 'wpcom_widgets_init' ) ) :
    function wpcom_widgets_init() {
        register_sidebar( array(
            'name'          => '首页边栏',
            'id'            => 'home',
            'description'   => '用户首页显示的边栏',
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>'
        ) );
    }
endif;
add_action( 'wpcom_sidebar', 'wpcom_widgets_init' );

add_filter('wpcom_image_sizes', 'justnews_image_sizes', 20);
function justnews_image_sizes($image_sizes){
    $image_sizes['post-thumbnail'] = array(
        'width' => 480,
        'height' => 300
    );
    return $image_sizes;
}

function skyrobot_get_home_og_image_url() {
    $site_icon = get_site_icon_url(512);
    if ($site_icon) {
        return esc_url_raw($site_icon);
    }

    global $options;
    $configured_logo = isset($options['logo']) ? (string) $options['logo'] : '';
    if ($configured_logo !== '') {
        return esc_url_raw($configured_logo);
    }

    $default_logo_path = get_template_directory() . '/images/logo.png';
    if (file_exists($default_logo_path)) {
        return esc_url_raw(get_template_directory_uri() . '/images/logo.png');
    }

    return '';
}

function skyrobot_wpseo_default_og_image($image) {
    if (!is_home() && !is_front_page()) {
        return $image;
    }
    if (!empty($image)) {
        return $image;
    }
    $fallback = skyrobot_get_home_og_image_url();
    return $fallback ? $fallback : $image;
}
add_filter('wpseo_opengraph_image', 'skyrobot_wpseo_default_og_image', 10, 1);
add_filter('wpseo_twitter_image', 'skyrobot_wpseo_default_og_image', 10, 1);

function skyrobot_add_home_og_image($image_container) {
    if (!is_home() && !is_front_page()) {
        return $image_container;
    }
    $fallback = skyrobot_get_home_og_image_url();
    if (!$fallback || !is_object($image_container)) {
        return $image_container;
    }
    if (method_exists($image_container, 'add_image_by_url')) {
        $image_container->add_image_by_url($fallback);
    }
    return $image_container;
}
add_filter('wpseo_add_opengraph_images', 'skyrobot_add_home_og_image', 10, 1);

// Excerpt length
if ( ! function_exists( 'wpcom_excerpt_length' ) ) :
    function wpcom_excerpt_length( $length ) {
        return 90;
    }
endif;
add_filter( 'excerpt_length', 'wpcom_excerpt_length', 999 );

// 左右边栏设置
function sidebar_position($echo){
    global $options;
    if(isset($options['sidebar_left']) && $options['sidebar_left']==0){
        $echo .= '<style>.main{float: left;}.sidebar{float:right;}</style>'."\n";
    }
    return $echo;
}
add_filter( 'wpcom_head', 'sidebar_position' );

function format_date($time){
    global $options;
    if(isset($options['time_format']) && $options['time_format']=='0'){
        return date(get_option('date_format').(is_single()?' '.get_option('time_format'):''), $time);
    }
    $t = current_time('timestamp') - $time;
    $f = array(
        '86400'=>'天',
        '3600'=>'小时',
        '60'=>'分钟',
        '1'=>'秒'
    );
    if($t==0){
        return '1秒前';
    }else if( $t >= 604800 || $t < 0){
        return date(get_option('date_format').(is_single()?' '.get_option('time_format'):''), $time);
    }else{
        foreach ($f as $k=>$v)    {
            if (0 !=$c=floor($t/(int)$k)) {
                return $c.$v.'前';
            }
        }
    }
}

add_action('wp_ajax_wpcom_like_it', 'wpcom_like_it');
add_action('wp_ajax_nopriv_wpcom_like_it', 'wpcom_like_it');
function wpcom_like_it(){
    $data = $_POST;
    $res = array();
    if(isset($data['id']) && $data['id'] && $post = get_post($data['id'])){
        $cookie = isset($_COOKIE["wpcom_liked_".$data['id']])?$_COOKIE["wpcom_liked_".$data['id']]:0;
        if(isset($cookie) && $cookie=='1'){
            $res['result'] = -2;
        }else{
            $res['result'] = 0;
            $likes = get_post_meta($data['id'], 'wpcom_likes', true);
            $likes = $likes ? $likes : 0;
            $res['likes'] = $likes + 1;
            // 数据库增加一个喜欢数量
            update_post_meta( $data['id'], 'wpcom_likes', $res['likes'] );
            //cookie标记已经给本文点赞过了
            setcookie('wpcom_liked_'.$data['id'], 1, time()+3600*24*365, '/');
        }
    }else{
        $res['result'] = -1;
    }
    echo wp_json_encode($res);
    die();
}

add_action('wp_ajax_wpcom_heart_it', 'wpcom_heart_it');
add_action('wp_ajax_nopriv_wpcom_heart_it', 'wpcom_heart_it');
function wpcom_heart_it(){
    $data = $_POST;
    $res = array();
    $current_user = wp_get_current_user();
    if($current_user->ID){
        if(isset($data['id']) && $data['id'] && $post = get_post($data['id'])){
            // 用户关注的文章
            $u_favorites = get_user_meta($current_user->ID, 'wpcom_favorites', true);
            $u_favorites = $u_favorites ? $u_favorites : array();
            // 文章关注人数
            $p_favorite = get_post_meta($data['id'], 'wpcom_favorites', true);
            $p_favorite = $p_favorite ? $p_favorite : 0;
            if(in_array($data['id'], $u_favorites)){ // 用户是否关注本文
                $res['result'] = 1;
                $nu_favorites = array();
                foreach($u_favorites as $uf){
                    if($uf != $data['id']){
                        $nu_favorites[] = $uf;
                    }
                }
                $p_favorite -= 1;
            }else{
                $res['result'] = 0;
                $u_favorites[] = $data['id'];
                $nu_favorites = $u_favorites;
                $p_favorite += 1;
            }
            $p_favorite = $p_favorite<0 ? 0 : $p_favorite;
            update_user_meta($current_user->ID, 'wpcom_favorites', $nu_favorites);
            update_post_meta($data['id'], 'wpcom_favorites', $p_favorite);
            $res['favorites'] = $p_favorite;
        }else{
            $res['result'] = -2;
        }
    }else{ // 未登录
        $res['result'] = -1;
    }
    echo wp_json_encode($res);
    die();
}

add_filter( 'wpcom_profile_tabs_posts_class', 'justnews_profile_posts_class' );
function justnews_profile_posts_class(){
    return 'profile-posts-list article-list clearfix';
}

add_filter( 'wpcom_profile_tabs', 'wpcom_add_profile_tabs' );
function wpcom_add_profile_tabs( $tabs ){
    global $options, $current_user, $profile;
    $tabs += array(
        30 => array(
            'slug' => 'favorites',
            'title' => __( 'Favorites', 'wpcom' )
        )
    );

    if( isset($current_user->ID) && isset($profile->ID) && $profile->ID === $current_user->ID && isset($options['tougao_on']) && $options['tougao_on']=='1') {
        $tabs += array(
            40 => array(
                'slug' => 'addpost',
                'title' => __('Add post', 'wpcom')
            )
        );
    }

    return $tabs;
}

add_action('wpcom_profile_tabs_favorites', 'wpcom_favorites');
function wpcom_favorites() {
    global $profile, $post;
    $favorites = get_user_meta($profile->ID, 'wpcom_favorites', true);

    if($favorites) {
        add_filter('posts_orderby', 'favorites_posts_orderby');
        $args = array(
            'post_type' => 'post',
            'post__in' => $favorites,
            'posts_per_page' => get_option('posts_per_page'),
            'ignore_sticky_posts' => 1
        );
        $posts = new WP_Query($args);
        if ( $posts->have_posts() ) {
            echo '<ul class="profile-posts-list profile-favorites-list article-list clearfix" data-user="'.$profile->ID.'">';
            while ($posts->have_posts()) : $posts->the_post();
                get_template_part('templates/list', 'default');
            endwhile;
            echo '</ul>';
            if ($posts->max_num_pages > 1) { ?>
                <div class="load-more-wrap"><a href="javascript:;" class="load-more j-user-favorites"><?php _e('Load more posts', 'wpcom'); ?></a></div><?php }
        } else {
            if (get_current_user_id() == $profile->ID) {
                echo '<div class="profile-no-content">' . __('You have no favorite posts.', 'wpcom') . '</span></div>';
            } else {
                echo '<div class="profile-no-content">' . __('This user has no favorite posts.', 'wpcom') . '</span></div>';
            }
        }
        wp_reset_query();
    }else{
        if( get_current_user_id()==$profile->ID ) {
            echo '<div class="profile-no-content">' . __('You have no favorite posts.', 'wpcom') . '</span></div>';
        } else {
            echo '<div class="profile-no-content">' . __('This user has no favorite posts.', 'wpcom') . '</span></div>';
        }
    }
}

add_action( 'wp_ajax_wpcom_user_favorites', 'wpcom_profile_tabs_favorites' );
add_action( 'wp_ajax_nopriv_wpcom_user_favorites', 'wpcom_profile_tabs_favorites' );
function wpcom_profile_tabs_favorites(){
    if( isset($_POST['user']) && is_numeric($_POST['user']) && $user = get_user_by('ID', $_POST['user'] ) ){
        $favorites = get_user_meta($user->ID, 'wpcom_favorites', true);

        if($favorites) {
            add_filter('posts_orderby', 'favorites_posts_orderby');

            $per_page = get_option('posts_per_page');
            $page = $_POST['page'];
            $page = $page ? $page : 1;
            $arg = array(
                'post_type' => 'post',
                'posts_per_page' => $per_page,
                'post__in' => $favorites,
                'paged' => $page,
                'ignore_sticky_posts' => 1
            );
            $posts = new WP_Query($arg);

            if ($posts->have_posts()) {
                while ($posts->have_posts()) : $posts->the_post();
                    get_template_part('templates/list', 'default');
                endwhile;
                wp_reset_postdata();
            } else {
                echo 0;
            }
        }
    }
    exit;
}

function favorites_posts_orderby( $orderby ){
    global $wpdb, $profile;
    if( !isset($profile) ) return $orderby;

    $favorites = get_user_meta( $profile->ID, 'wpcom_favorites', true );
    if($favorites) $orderby = "FIELD(".$wpdb->posts.".ID, ".implode(',', $favorites).") DESC";

    return $orderby;
}

add_filter( 'wpcom_profile_tab_url', 'add_post_tab_link', 10, 3 );
function add_post_tab_link( $tab_html, $tab, $url ){
    if( $tab['slug'] == 'addpost' ){
        $tab_html = '<a target="_blank" href="' . wpcom_addpost_url() . '">'.$tab['title'].'</a>';
    }
    return $tab_html;
}

function wpcom_addpost_url(){
    global $options;
    if( isset($options['tougao_page']) && $options['tougao_page'] ){
        return get_permalink( $options['tougao_page'] );
    }
}

function post_editor_settings($args = array()){
    $img = current_user_can('upload_files');
    return array(
        'textarea_name' => $args['textarea_name'],
        //'textarea_rows' => $args['textarea_rows'],
        'media_buttons' => false,
        'quicktags' => false,
        'tinymce'       => array(
            'height'        => 350,
            'toolbar1' => 'formatselect,bold,underline,blockquote,forecolor,alignleft,aligncenter,alignright,link,unlink,bullist,numlist,'.($img?'wpcomimg,':'image,').'undo,redo,fullscreen,wp_help',
            'toolbar2' => '',
            'toolbar3' => '',
        )
    );
}

add_filter( 'mce_external_plugins', 'wpcom_mce_plugin');
function wpcom_mce_plugin($plugin_array){
    global $is_submit_page;
    if ( $is_submit_page ) {
        wp_enqueue_media();
        wp_enqueue_script('jquery.taghandler', get_template_directory_uri() . '/js/jquery.taghandler.min.js', array('jquery'), THEME_VERSION, true);
        wp_enqueue_script('edit-post', get_template_directory_uri() . '/js/edit-post.js', array('jquery'), THEME_VERSION, true);

        $plugin_array['wpcomimg'] = admin_url('admin-ajax.php?action=wpcomimg');
    }
    return $plugin_array;
}

add_action('wp_ajax_wpcomimg', 'wpcom_img');
function wpcom_img(){
    header("Content-type: text/javascript");
    echo '(function($) {
            tinymce.create("tinymce.plugins.wpcomimg", {
                init : function(ed, url) {
                    ed.addButton("wpcomimg", {
                        icon: "image",
                        tooltip : "添加图片",
                        onclick: function(){
                            var uploader;
                            if (uploader) {
                                uploader.open();
                            }else{
                                uploader = wp.media.frames.file_frame = wp.media({
                                    title: "选择图片",
                                    button: {
                                        text: "插入图片"
                                    },
                                    library : {
                                        type : "image"
                                    },
                                    multiple: true
                                });
                                uploader.on("select", function() {
                                    var attachments = uploader.state().get("selection").toJSON();
                                    var img = "";
                                    for(var i=0;i<attachments.length;i++){
                                        img += "<img src=\""+attachments[i].url+"\" width=\""+attachments[i].width+"\" height=\""+attachments[i].height+"\" alt=\""+(attachments[i].alt?attachments[i].alt:attachments[i].title)+"\">";
                                    }
                                    tinymce.activeEditor.execCommand("mceInsertContent", false, img)
                                });
                                uploader.open();
                            }
                        }
                    });
                }
        });
        // Register plugin
        tinymce.PluginManager.add("wpcomimg", tinymce.plugins.wpcomimg);
        })(jQuery);';
    exit;
}

add_action('pre_get_posts','wpcom_restrict_media_library');
function wpcom_restrict_media_library( $wp_query_obj ) {
    global $current_user, $pagenow;
    if( ! $current_user instanceof WP_User )
        return;
    if( 'admin-ajax.php' != $pagenow || $_REQUEST['action'] != 'query-attachments' )
        return;
    if( !current_user_can('edit_others_posts') )
        $wp_query_obj->set('author', $current_user->ID );
    return;
}

function wpcom_tougao_tinymce_style($content) {
    if ( ! is_admin() ) {
        global $editor_styles, $stylesheet;
        $editor_styles = (array) $editor_styles;
        $stylesheet    = (array) $stylesheet;
        $stylesheet[] = 'css/editor-style.css';
        $editor_styles = array_merge( $editor_styles, $stylesheet );
    }
    return $content;
}

add_filter('wpcom_update_post','wpcom_update_post');
function wpcom_update_post($res){

    add_filter('the_editor_content', "wpcom_tougao_tinymce_style");

    if(isset($_POST['post-title'])){ // 只处理post请求
        $nonce = $_POST['wpcom_update_post_nonce'];
        if ( wp_verify_nonce( $nonce, 'wpcom_update_post' ) ){
            $post_id = isset($_GET['post_id'])?$_GET['post_id']:'';

            $post_title = $_POST['post-title'];
            $post_excerpt = $_POST['post-excerpt'];
            $post_content = $_POST['post-content'];
            $post_category = isset($_POST['post-category'])?$_POST['post-category']:array();
            $post_tags = $_POST['post-tags'];
            $_thumbnail_id = $_POST['_thumbnail_id'];

            if($post_id){ // 编辑文章
                $post = get_post($post_id);
                if(isset($post->ID)) { // 文章要存在
                    $p = array(
                        'ID' => $post_id,
                        'post_type' => 'post',
                        'post_title' => $post_title,
                        'post_excerpt' => $post_excerpt,
                        'post_content' => $post_content,
                        'post_category' => $post_category,
                        'tags_input' => $post_tags
                    );
                    if($post->post_status=='draft' && trim($post_title)!='' && trim($post_content)!=''){
                        $p['post_status'] = current_user_can( 'publish_posts' ) ? 'publish' : 'pending';
                    }
                    $pid = wp_update_post($p, true);
                    if ( !is_wp_error( $pid ) ) {
                        update_post_meta($pid, '_thumbnail_id', $_thumbnail_id);
                    }
                }
            }else{ // 新建文章
                if(trim($post_title)=='' && trim($post_content)==''){
                    return array();
                }else if(trim($post_title)=='' || trim($post_content)=='' || empty($post_category)){
                    $post_status = 'draft';
                }else{
                    $post_status = current_user_can( 'publish_posts' ) ? 'publish' : 'pending';
                }
                $p = array(
                    'post_type' => 'post',
                    'post_title' => $post_title,
                    'post_excerpt' => $post_excerpt,
                    'post_content' => $post_content,
                    'post_status' => $post_status,
                    'post_category' => $post_category,
                    'tags_input' => $post_tags
                );
                $pid = wp_insert_post($p, true);
                if ( !is_wp_error( $pid ) ) {
                    update_post_meta($pid, '_thumbnail_id', $_thumbnail_id);
                    update_post_meta($pid, 'wpcom_copyright_type', 'copyright_tougao');
                    wp_redirect(get_edit_link($pid).'&submit=true');
                }
            }
        }
    }
    return $res;
}

function get_edit_link($id){
    $url = wpcom_addpost_url();
    $url =  add_query_arg( 'post_id', $id, $url );
    return $url;
}

function max_page(){
    global $wp_query;
    return $wp_query->max_num_pages;
}

add_action('wp_ajax_wpcom_load_posts', 'wpcom_load_posts');
add_action('wp_ajax_nopriv_wpcom_load_posts', 'wpcom_load_posts');
function wpcom_load_posts(){
    $id = isset($_POST['id']) ? $_POST['id'] : '';
    $page = isset($_POST['page']) ? $_POST['page'] : '';
    $page = $page ? $page : 1;
    $per_page = get_option('posts_per_page');
    if($id){
        $posts = new WP_Query(array(
            'posts_per_page' => $per_page,
            'paged' => $page,
            'cat' => $id,
            'post_type' => 'post',
            'post_status' => array( 'publish' ),
            'ignore_sticky_posts' => 0
        ));
    }else{
        global $options;
        $arg = array(
            'posts_per_page' => $per_page,
            'paged' => $page,
            'ignore_sticky_posts' => 0,
            'post_type' => 'post',
            'post_status' => array( 'publish' ),
            'category__not_in' => isset($options['newest_exclude']) ? $options['newest_exclude'] : array()
        );
        $posts = new WP_Query($arg);

    }
    if($posts->have_posts()) {
        while ( $posts->have_posts() ) : $posts->the_post();
            get_template_part('templates/list', 'default-sticky');
        endwhile;
        wp_reset_postdata();
        if($id && $page==1 && get_category($id)->count>$per_page){
            echo '<li class="load-more-wrap"><a class="load-more j-load-more" data-id="'.$id.'" href="javascript:;">'.__('Load more posts', 'wpcom').'</a></li>';
        }
    }else{
        echo 0;
    }
    exit;
}

add_action( 'init', 'wpcom_create_special' );
function wpcom_create_special(){
    global $options;
    if(!isset($options['special_on']) || $options['special_on']=='1') { //是否开启专题功能
        $slug = isset($options['special_slug']) && $options['special_slug'] ? $options['special_slug'] : 'special';
        $labels = array(
            'name' => '专题',
            'singular_name' => '专题',
            'search_items' => '搜索专题',
            'all_items' => '所有专题',
            'parent_item' => '父级专题',
            'parent_item_colon' => '父级专题',
            'edit_item' => '编辑专题',
            'update_item' => '更新专题',
            'add_new_item' => '添加专题',
            'new_item_name' => '新专题名',
            'not_found' => '暂无专题',
            'menu_name' => '专题',
        );

        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => $slug),
            'show_in_rest' => true
        );
        register_taxonomy('special', 'post', $args);
    }
}

function get_special_list($num=10, $paged=1){
    $special = get_terms( array(
        'taxonomy' => 'special',
        'orderby' => 'id',
        'order' => 'DESC',
        'number' => $num,
        'hide_empty' => false,
        'offset' => $num*($paged-1)
    ) );
    return $special;
}

// 优化专题排序支持 Simple Custom Post Order 插件
add_filter( 'get_terms_orderby', 'wpcom_get_terms_orderby', 20, 3 );
function wpcom_get_terms_orderby($orderby, $args, $tax){
    if(class_exists('SCPO_Engine') && $tax && count($tax)==1 && $tax[0]=='special'){
        $orderby = 't.term_order, t.term_id';
    }
    return $orderby;
}

add_action('wp_ajax_wpcom_load_special', 'wpcom_load_special');
add_action('wp_ajax_nopriv_wpcom_load_special', 'wpcom_load_special');
function wpcom_load_special(){
    global $options, $post;
    $page = $_POST['page'];
    $page = $page ? $page : 1;
    $per_page = isset($options['special_per_page']) && $options['special_per_page'] ? $options['special_per_page'] : 10;

    $special = get_special_list($per_page, $page);
    if($special){
    foreach($special as $sp){
        $thumb = get_term_meta( $sp->term_id, 'wpcom_thumb', true );
        $link = get_term_link($sp->term_id);
        ?>
        <div class="col-md-6 col-xs-12 special-item-wrap">
            <div class="special-item">
                <div class="special-item-top">
                    <div class="special-item-thumb">
                        <a href="<?php echo $link;?>" target="_blank"><img src="<?php echo esc_url($thumb);?>" alt="<?php echo esc_attr($sp->name);?>"></a>
                    </div>
                    <div class="special-item-title">
                        <h2><a href="<?php echo $link;?>" target="_blank"><?php echo $sp->name;?></a></h2>
                        <?php echo category_description($sp->term_id);?>
                    </div>
                    <a class="special-item-more" href="<?php echo $link;?>"><?php echo _x('Read More', 'topic', 'wpcom');?></a>
                </div>
                <ul class="special-item-bottom">
                    <?php
                    $args = array(
                        'posts_per_page' => 3,
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'special',
                                'field' => 'term_id',
                                'terms' => $sp->term_id
                            )
                        )
                    );
                    $postslist = get_posts( $args );
                    foreach($postslist as $post) {
                        setup_postdata($post);?>
                        <li><a title="<?php echo esc_attr(get_the_title());?>" href="<?php the_permalink();?>" target="_blank"><?php the_title();?></a></li>
                    <?php } wp_reset_postdata(); ?>
                </ul>
            </div>
        </div>
    <?php }
    } else {
        echo 0;
    }
    exit;
}

function wpcom_post_copyright() {
    global $post, $options;
    $copyright = '';

    $copyright_type = get_post_meta($post->ID, 'wpcom_copyright_type', true);
    if(!$copyright_type){
        $copyright = isset($options['copyright_default']) ? $options['copyright_default'] : '';
    }else if($copyright_type=='copyright_tougao'){
        $copyright = isset($options['copyright_tougao']) ? $options['copyright_tougao'] : '';;
    }else if($copyright_type){
        if(isset($options['copyright_id']) && $options['copyright_id']) {
            foreach ($options['copyright_id'] as $i => $id) {
                if($copyright_type == $id && $options['copyright_text'][$i]) {
                    $copyright = $options['copyright_text'][$i];
                }
            }
        }
    }

    if(preg_match('%SITE_NAME%', $copyright)) $copyright = str_replace('%SITE_NAME%', get_bloginfo('name'), $copyright);
    if(preg_match('%SITE_URL%', $copyright)) $copyright = str_replace('%SITE_URL%', get_bloginfo('url'), $copyright);
    if(preg_match('%POST_TITLE%', $copyright)) $copyright = str_replace('%POST_TITLE%', get_the_title(), $copyright);
    if(preg_match('%POST_URL%', $copyright)) $copyright = str_replace('%POST_URL%', get_permalink(), $copyright);
    if(preg_match('%AUTHOR_NAME%', $copyright)) $copyright = str_replace('%AUTHOR_NAME%', get_the_author(), $copyright);
    if(preg_match('%AUTHOR_URL%', $copyright)) $copyright = str_replace('%AUTHOR_URL%', get_author_posts_url(get_the_author_meta( 'ID' )), $copyright);
    if(preg_match('%ORIGINAL_NAME%', $copyright)) $copyright = str_replace('%ORIGINAL_NAME%', get_post_meta($post->ID, 'wpcom_original_name', true), $copyright);
    if(preg_match('%ORIGINAL_URL%', $copyright)) $copyright = str_replace('%ORIGINAL_URL%', get_post_meta($post->ID, 'wpcom_original_url', true), $copyright);

    echo $copyright ? '<div class="entry-copyright">'.$copyright.'</div>' : '';
}

add_filter('comment_reply_link', 'wpcom_comment_reply_link', 10, 1);
function wpcom_comment_reply_link($link){
    if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
        $link = '<a rel="nofollow" class="comment-reply-login" href="javascript:;">回复</a>';
    }
    return $link;
}

add_action('init', 'wpcom_allow_contributor_uploads');
function wpcom_allow_contributor_uploads() {
    $user = wp_get_current_user();
    if( isset($user->roles) && $user->roles && $user->roles[0] == 'contributor' ){
        global $options;
        $allow = isset($options['tougao_upload']) && $options['tougao_upload']=='0' ? 0 : 1;
        $can_upload = isset($user->allcaps['upload_files']) ? $user->allcaps['upload_files'] : 0;

        if ( $allow && !$can_upload ) {
            $contributor = get_role('contributor');
            $contributor->add_cap('upload_files');
        } else if(!$allow && $can_upload){
            $contributor = get_role('contributor');
            $contributor->remove_cap('upload_files');
        }
    }
}

add_theme_support( 'wc-product-gallery-lightbox' );

add_action( 'wpcom_echo_ad', 'wpcom_echo_ad', 10, 1);
function wpcom_echo_ad( $id ){
    if(defined('DOING_AJAX') && DOING_AJAX) return false;
    if($id && $id=='ad_flow'){
        global $wp_query;
        if(!isset($wp_query->ad_index)) $wp_query->ad_index = rand(1, $wp_query->post_count-2);
        $current_post = $wp_query->current_post;
        if(isset($wp_query->posts->current_post)) $current_post = $wp_query->posts->current_post;
        if($current_post==$wp_query->ad_index) echo wpcom_ad_html($id);
    }else if($id) {
        echo wpcom_ad_html($id);
    }
}

function wpcom_ad_html($id){
    if($id) {
        global $options;
        $html = '';
        if( wp_is_mobile() && isset($options[$id.'_mobile']) && $options[$id.'_mobile'] ) {
            $html = '<div class="wpcom_ad_wrap">';
            $html .= $options[$id.'_mobile'];
            $html .= '</div>';
        } else if ( isset($options[$id]) && $options[$id] ) {
            $html = '<div class="wpcom_ad_wrap">';
            $html .= $options[$id];
            $html .= '</div>';
        }

        if($html && $id=='ad_flow') $html = '<li class="item item-ad">'.$html.'</li>';
        return $html;
    }
}

add_action( 'wp_head', 'wpcom_style_output', 20 );
if ( ! function_exists( 'wpcom_style_output' ) ) :
    function wpcom_style_output(){
        global $options; ?>
        <style>
            <?php
            $theme_color = WPCOM::color($options['theme_color']?$options['theme_color']:'#3ca5f6');
            $theme_color_hover = WPCOM::color($options['theme_color_hover']?$options['theme_color_hover']:'#4285f4');
            $sticky_color1 = WPCOM::color(isset($options['sticky_color1'])?$options['sticky_color1']:'');
            $sticky_color2 = WPCOM::color(isset($options['sticky_color2'])?$options['sticky_color2']:'');
            if( $theme_color!='#3ca5f6' || $theme_color_hover!='#4285f4' ) include get_template_directory() . '/css/color.php';
            if( function_exists('is_woocommerce') ) include get_template_directory() . '/css/woo-color.php';
            if(isset($options['bg_color']) && ($options['bg_color'] || $options['bg_image'])){ ?>@media (min-width: 992px){
                body{  <?php if($options['bg_color']) {echo 'background-color: '.WPCOM::color($options['bg_color']).';';};?> <?php if($options['bg_image']) {echo 'background-image: url('.$options['bg_image'].');';};?><?php if($options['bg_image_repeat']) {echo 'background-repeat: '.$options['bg_image_repeat'].';';};?><?php if($options['bg_image_position']) {echo 'background-position: '.$options['bg_image_position'].';';};?><?php if($options['bg_image_attachment']=='1') {echo 'background-attachment: fixed;';};?>}
                <?php if($options['special_title_color']){?>.special-head .special-title,.special-head p{color:<?php echo WPCOM::color($options['special_title_color']);?>;}.special-head .page-description:before{background:<?php echo WPCOM::color($options['special_title_color']);?>;}<?php } ?>
                .special-head .page-description:before,.special-head p{opacity: 0.5;}
            }<?php } if( isset($options['member_login_bg']) && $options['member_login_bg'] !='' ) { ?>
            .page-template-page-fullnotitle.member-login #wrap,.page-template-page-fullnotitle.member-register #wrap{ background-image: url('<?php echo esc_url($options['member_login_bg']);?>');}
            <?php } ?>.j-share{position: fixed!important;top: <?php echo $options['action_top']?$options['action_top']:'50%'?>!important;}
            <?php if(isset($options['logo-height']) && $logo_height = intval($options['logo-height'])){
            $logo_height = $logo_height>50 ? 50 : $logo_height;
            ?>
            .header .logo img{max-height: <?php echo $logo_height;?>px;}
            <?php } if(isset($options['logo-height-mobile']) && $mob_logo_height = intval($options['logo-height-mobile'])){
            $mob_logo_height = $mob_logo_height>40 ? 40 : $mob_logo_height;
            ?>
            @media (max-width: 767px){
                .header .logo img{max-height: <?php echo $mob_logo_height;?>px;}
            }
            <?php }
            $video_height = intval(isset($options['post_video_height']) && $options['post_video_height'] ? $options['post_video_height'] : 482);?>
            .entry .entry-video{ height: <?php echo $video_height ?>px;}
            @media (max-width: 1219px){
                .entry .entry-video{ height: <?php echo $video_height * (688/858) ?>px;}
            }
            @media (max-width: 991px){
                .entry .entry-video{ height: <?php echo $video_height * (800/858) ?>px;}
            }
            @media (max-width: 767px){
                .entry .entry-video{ height: <?php echo $video_height/1.4 ?>px;}
            }
            @media (max-width: 500px){
                .entry .entry-video{ height: <?php echo $video_height/2 ?>px;}
            }
            <?php if(get_locale()!='zh_CN'){ ?>
            .action .a-box:hover:after{padding: 0;font-family: "FontAwesome";font-size: 20px;line-height: 40px;}
            .action .contact:hover:after{content:'\f0e5';}
            .action .wechat:hover:after{content:'\f029';}
            .action .share:hover:after{content:'\f045';}
            .action .gotop:hover:after{content:'\f106';font-size: 36px;}
            <?php }
            if($sticky_color1 && $sticky_color2){ ?>
            @media screen and (-webkit-min-device-pixel-ratio: 0) {
                .article-list .item-sticky .item-title a{-webkit-background-clip: text;-webkit-text-fill-color: transparent;}
                .article-list .item-sticky .item-title a, .article-list .item-sticky .item-title a .sticky-post {
                    background-image: -webkit-linear-gradient(0deg, <?php echo $sticky_color1;?> 0%, <?php echo $sticky_color2;?> 100%);
                    background-image: linear-gradient(90deg, <?php echo $sticky_color1;?> 0%, <?php echo $sticky_color2;?> 100%);
                }
            }
            <?php } echo $options['custom_css'];?>
        </style>
    <?php }
endif;

function is_multimage( $post_id = '' ){
    global $post, $options;
    if($post_id==''){
        $post_id = $post->ID;
    }
    $multimage = get_post_meta($post_id, 'wpcom_multimage', true);
    $multimage = $multimage=='' ? (isset($options['list_multimage']) ? $options['list_multimage'] : 0) : $multimage;
    return $multimage;
}


// 老版用户中心头像、封面图片迁移
add_filter( 'get_avatar_url', 'um_to_wpcom_member_avatar', 20, 2 );
function um_to_wpcom_member_avatar( $url, $id_or_email ){
    global $avatar_checked, $current_user, $options;
    if( !(isset($options['member_enable']) && $options['member_enable']=='1') || preg_match('/\/member\/avatars\//i', $url ) ){
        return $url;
    }

    if( !isset($avatar_checked) ) $avatar_checked = array();

    $uploads = wp_upload_dir();
    $dir = $uploads['basedir'];

    if ( is_multisite() ) {
        if ( get_current_blog_id() != '1' ) {

            $split = explode('sites/', $dir);
            $dir = $split[0] . 'ultimatemember/';
        }
    }else{
        $dir = $dir . '/ultimatemember/';
    }

    $user_id = 0;
    if ( is_numeric( $id_or_email ) ) {
        $user_id = absint( $id_or_email );
    } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
        $user = get_user_by( 'email', $id_or_email );
        if( isset($user->ID) && $user->ID ) $user_id = $user->ID;
    } elseif ( $id_or_email instanceof WP_User ) {
        $user_id = $id_or_email->ID;
    } elseif ( $id_or_email instanceof WP_Post ) {
        $user_id = $id_or_email->post_author;
    } elseif ( $id_or_email instanceof WP_Comment ) {
        $user_id = $id_or_email->user_id;
        if( !$user_id ){
            $user = get_user_by( 'email', $id_or_email->comment_author_email );
            if( isset($user->ID) && $user->ID ) $user_id = $user->ID;
        }
    }

    if( ( current_user_can( 'edit_users' ) || (isset($current_user->ID) && $current_user->ID == $user_id) ) && !preg_match('/\/member\/avatars\//i', $url ) ) {

        if (in_array($user_id, $avatar_checked)) return $url;

        $profile_photo = get_user_meta($user_id, 'profile_photo', true);

        if ($profile_photo) {
            $file = $dir . $user_id . '/' . $profile_photo;
            if (file_exists($file)) {
                $file_content = file_get_contents($file);

                $GLOBALS['image_type'] = 0;
                $file_exp = explode('.', $file);
                $ext = end($file_exp);
                $filename = substr(md5($user_id), 5, 16) . '.' . time() . '.' . $ext;

                $mirror = wp_upload_bits($filename, '', $file_content, '1234/06');

                if (!$mirror['error']) {
                    update_user_meta($user_id, 'wpcom_avatar', $mirror['url']);
                    $url = $mirror['url'];
                    @unlink($file);
                }
            }
        }

        $avatar_checked[] = $user_id;
    }

    return $url;
}

add_filter( 'wpcom_member_user_cover', 'um_to_wpcom_member_cover', 20, 2 );
function um_to_wpcom_member_cover( $cover, $user_id ){
    global $cover_checked, $current_user, $options;
    if( isset($options['member_enable']) && $options['member_enable']=='1' && ( ( current_user_can( 'edit_users' ) || (isset($current_user->ID) && $current_user->ID == $user_id) ) && !preg_match('/\/member\/covers\//i', $cover ) ) ) {

        if (!isset($cover_checked)) $cover_checked = array();

        if (in_array($user_id, $cover_checked)) return $cover;

        $uploads = wp_upload_dir();
        $dir = $uploads['basedir'];

        if (is_multisite()) {
            if (get_current_blog_id() != '1') {

                $split = explode('sites/', $dir);
                $dir = $split[0] . 'ultimatemember/';
            }
        } else {
            $dir = $dir . '/ultimatemember/';
        }

        $cover_photo = get_user_meta($user_id, 'cover_photo', true);

        if ($cover_photo) {
            $file = $dir . $user_id . '/' . $cover_photo;
            if (file_exists($file)) {
                $file_content = file_get_contents($file);

                $GLOBALS['image_type'] = 1;
                $file_exp = explode('.', $file);
                $ext = end($file_exp);
                $filename = substr(md5($user_id), 5, 16) . '.' . time() . '.' . $ext;

                $mirror = wp_upload_bits($filename, '', $file_content, '1234/06');

                if (!$mirror['error']) {
                    update_user_meta($user_id, 'wpcom_cover', $mirror['url']);
                    $cover = $mirror['url'];
                    @unlink($file);
                }
            }
        }

        $cover_checked[] = $user_id;
    }

    return $cover;
}

// 未批准用户数据迁移
add_action( '_admin_menu', 'wpcom_filter_unapproved_users' );
function wpcom_filter_unapproved_users(){
    global $pagenow, $options;
    if( isset($options['member_enable']) && $options['member_enable']=='1' && is_admin() && 'users.php' == $pagenow ) {
        $users_query = new WP_User_Query( array(
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'account_status',
                    'value' => 'awaiting_email_confirmation',
                    'compare' => '=',
                ),
                array(
                    'key' => 'account_status',
                    'value' => 'awaiting_admin_review',
                    'compare' => '=',
                ),
                array(
                    'key' => 'account_status',
                    'value' => 'inactive',
                    'compare' => '=',
                ),
                array(
                    'key' => 'account_status',
                    'value' => 'rejected',
                    'compare' => '=',
                )
            )
        ));

        $users = $users_query->get_results();
        if($users){
            foreach ($users as $user){
                if(update_user_meta( $user->ID, 'wpcom_approve', 0 ))
                    delete_user_meta( $user->ID, 'account_status' );
            }
        }
    }
}

add_action('init', 'wpcom_kx_init');
if ( ! function_exists( 'wpcom_kx_init' ) ) :
    function wpcom_kx_init(){
        global $options;
        if(isset($options['kx_on']) && $options['kx_on']=='1') {
            $slug = isset($options['kx_slug']) && $options['kx_slug'] ? $options['kx_slug'] : 'kuaixun';
            $labels = array(
                'name' => '快讯',
                'singular_name' => '快讯',
                'add_new' => '添加',
                'add_new_item' => '添加',
                'edit_item' => '编辑',
                'new_item' => '添加',
                'view_item' => '查看',
                'search_items' => '查找',
                'not_found' => '没有内容',
                'not_found_in_trash' => '回收站为空',
                'parent_item_colon' => ''
            );
            $args = array(
                'labels' => $labels,
                'public' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'query_var' => true,
                'capability_type' => 'post',
                'hierarchical' => true,
                'menu_position' => null,
                'rewrite' => array('slug' => $slug),
                'show_in_rest' => true,
                'supports' => array('title', 'excerpt', 'thumbnail', 'comments')
            );
            register_post_type('kuaixun', $args);


            // add post meta
            add_filter( 'wpcom_post_metas', 'wpcom_add_kx_metas' );
        }
    }
endif;

add_action( 'pre_get_posts', 'wpcom_kx_orderby' );
function wpcom_kx_orderby( $query ){
    if( function_exists('get_current_screen') && $query->is_admin ) {
        $screen = get_current_screen();
        if ( isset($screen->base) && isset($screen->post_type) && 'edit' == $screen->base && 'kuaixun' == $screen->post_type && !isset($_GET['orderby'])) {
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
        }
    }
}

if ( ! function_exists( 'wpcom_add_kx_metas' ) ) :
    function wpcom_add_kx_metas( $metas ){
        $metas['kuaixun'] = array(
            array(
                "title" => "快讯设置",
                "option" => array(
                    array(
                        'name' => 'kx_url',
                        'title' => '快讯来源',
                        'desc' => '快讯来源链接地址',
                        'type' => 'text'
                    )
                )
            )
        );
        return $metas;
    }
endif;

add_filter( 'get_the_excerpt', 'wpcom_kx_excerpt', 20, 2 );
if ( ! function_exists( 'wpcom_kx_excerpt' ) ) :
    function wpcom_kx_excerpt( $excerpt, $post ) {
        if( $post->post_type == 'kuaixun' && $url = get_post_meta($post->ID, 'wpcom_kx_url', true ) ){
            $excerpt .= ' <a class="kx-more" href="'.esc_url($url).'" target="_blank" rel="nofollow">[原文链接]</a>';
        }
        return $excerpt;
    }
endif;

add_action( 'init', 'wpcom_kx_rewrite' );
function wpcom_kx_rewrite() {
    global $wp_rewrite, $options, $permalink_structure;
    if(!isset($permalink_structure)) $permalink_structure = get_option('permalink_structure');
    if($permalink_structure){
        $slug = isset($options['kx_slug']) && $options['kx_slug'] ? $options['kx_slug'] : 'kuaixun';

        $queryarg = 'post_type=kuaixun&p=';
        $wp_rewrite->add_rewrite_tag( '%kx_id%', '([^/]+)', $queryarg );
        $wp_rewrite->add_permastruct( 'kuaixun', $slug.'/%kx_id%.html', false );
    }
}

add_filter('post_type_link', 'wpcom_kx_permalink', 5, 2);
function wpcom_kx_permalink( $post_link, $id ) {
    global $wp_rewrite, $permalink_structure;
    if(!isset($permalink_structure)) $permalink_structure = get_option('permalink_structure');
    if($permalink_structure) {
        $post = get_post($id);
        if (!is_wp_error($post) && $post->post_type == 'kuaixun') {
            $newlink = $wp_rewrite->get_extra_permastruct('kuaixun');
            $newlink = str_replace('%kx_id%', $post->ID, $newlink);
            $newlink = home_url(untrailingslashit($newlink));
            return $newlink;
        }
    }
    return $post_link;
}

// 旧版快讯链接兼容跳转新链接
add_action( 'template_redirect', 'wpcom_kx_old_link', 1 );
function wpcom_kx_old_link(){
    global $wp, $options;
    $slug = isset($options['kx_slug']) && $options['kx_slug'] ? $options['kx_slug'] : 'kuaixun';
    $url = untrailingslashit(home_url( $wp->request ));
    if( preg_match('/\/'.$slug.'\/\d+$/i', $url) ){
        wp_redirect( $url . '.html', 301 );
        exit;
    }
}

add_action('wp_ajax_wpcom_load_kuaixun', 'wpcom_load_kuaixun');
add_action('wp_ajax_nopriv_wpcom_load_kuaixun', 'wpcom_load_kuaixun');
if ( ! function_exists( 'wpcom_load_kuaixun' ) ) :
    function wpcom_load_kuaixun(){
        global $options;
        $page = $_POST['page'];
        $page = $page ? $page : 1;
        $per_page = get_option('posts_per_page');

        $arg = array(
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => array( 'publish' ),
            'post_type' => 'kuaixun'
        );
        $posts = new WP_Query($arg);

        if($posts->have_posts()) {
            $cur_day = '';
            $weekarray = array("日","一","二","三","四","五","六");
            while ( $posts->have_posts() ) : $posts->the_post();
                if($cur_day != $date = get_the_date(get_option('date_format'))){
                    $cur_day = $date;
                    $pre_day = '';
                    $week = $weekarray[date('w', strtotime(get_the_date('c')) )];
                    if(date(get_option('date_format'), time()) == $date) {
                        $pre_day = '今天 • ';
                    }else if(date(get_option('date_format'), strtotime("-1 day")) == $date){
                        $pre_day = '昨天 • ';
                    }else if(date(get_option('date_format'), strtotime("-2 day")) == $date){
                        $pre_day = '前天 • ';
                    }
                    echo '<div class="kx-date">'. $pre_day .$date . ' • 星期' . $week.'</div>';
                } ?>
                <div class="kx-item" data-id="<?php the_ID();?>">
                    <span class="kx-time"><?php the_time(get_option('time_format'));?></span>
                    <div class="kx-content">
                        <h2><?php if(isset($options['kx_url_enable']) &&  $options['kx_url_enable'] == '1'){ ?>
                                <a href="<?php the_permalink();?>" target="_blank"><?php the_title();?></a>
                            <?php } else{ the_title(); } ?></h2>
                        <?php the_excerpt();?>
                        <?php if(get_the_post_thumbnail()){ ?>
                            <?php if(isset($options['kx_url_enable']) &&  $options['kx_url_enable'] == '1'){ ?>
                                <a class="kx-img" href="<?php the_permalink();?>" title="<?php echo esc_attr(get_the_title());?>" target="_blank"><?php the_post_thumbnail('full'); ?></a>
                            <?php }else{ ?>
                                <div class="kx-img"><?php the_post_thumbnail('full'); ?></div>
                            <?php } ?>
                        <?php } ?>
                    </div>

                    <div class="kx-meta hidden-sm hidden-md hidden-lg clearfix">
                        <span class="j-mobile-share" data-id="<?php the_ID();?>">
                            <i class="fa fa-share-alt"></i> 生成分享图片
                        </span>
                    </div>
                    <div class="kx-meta hidden-xs clearfix" data-url="<?php echo urlencode(get_permalink());?>">
                        <span>分享到</span>
                        <span class="share-icon wechat">
                            <i class="fa fa-wechat"></i>
                            <span class="wechat-img">
                                <span class="j-qrcode" data-text="<?php the_permalink();?>"></span>
                            </span>
                        </span>
                        <span class="share-icon weibo" href="javascript:;"><i class="fa fa-weibo"></i></span>
                        <span class="share-icon qq" href="javascript:;"><i class="fa fa-qq"></i></span>
                        <span class="share-icon copy"><i class="fa fa-file-text"></i></span>
                    </div>
                </div>
            <?php endwhile;
            wp_reset_postdata();
        }else{
            echo 0;
        }
        exit;
    }
endif;

add_action('wp_ajax_wpcom_new_kuaixun', 'wpcom_new_kuaixun');
add_action('wp_ajax_nopriv_wpcom_new_kuaixun', 'wpcom_new_kuaixun');
function wpcom_new_kuaixun(){
    $id = isset($_POST['id']) && $_POST['id'] ? $_POST['id'] : '';
    if($post = get_post($id)){
        $time = get_the_time('U', $post->ID);
        $args = array(
            'post_status' => array( 'publish' ),
            'post_type' => 'kuaixun',
            'date_query' => array(
                array(
                    'after'    => array(
                        'year'   => date('Y', $time),
                        'month'  => date('m', $time),
                        'day'    => date('d', $time),
                        'hour'   => date('H', $time),
                        'minute' => date('i', $time),
                        'second' => date('s', $time),
                    ),
                    'inclusive' => false
                )
            ),
            'posts_per_page' => -1,
        );
        $my_date_query = new WP_Query( $args );
        echo $my_date_query->found_posts;
    }
    exit;
}

add_action('wp_loaded', 'wpcom_tinymce_replace_start');
if ( ! function_exists( 'wpcom_tinymce_replace_start' ) ) {
    function wpcom_tinymce_replace_start() {
        if(!is_admin()) {
            global $is_IE;
            if (!$is_IE) return false;
            ob_start("wpcom_tinymce_replace_url");
        }
    }
}

add_action('shutdown', 'wpcom_tinymce_replace_end');
if ( ! function_exists( 'wpcom_tinymce_replace_end' ) ) {
    function wpcom_tinymce_replace_end() {
        if(!is_admin()) {
            global $is_IE;
            if (!$is_IE) return false;
            if (ob_get_level() > 0) ob_end_flush();
        }
    }
}

if ( ! function_exists( 'wpcom_tinymce_replace_url' ) ) {
    function wpcom_tinymce_replace_url( $str ){
        $regexp = "/\/wp-includes\/js\/tinymce/i";
        $path = get_template_directory_uri();
        $path = str_replace(get_option( 'siteurl' ), '', $path);
        $str = preg_replace( $regexp, $path . '/js/tinymce', $str );
        $str = preg_replace( '/tinymce\.Env\.ie \< 11/i', 'tinymce.Env.ie < 8', $str );
        $str = preg_replace( '/wp-editor-wrap html-active/i', 'wp-editor-wrap tmce-active', $str );
        return $str;
    }
}

add_filter( 'user_can_richedit', 'wpcom_can_richedit' );
if ( ! function_exists( 'wpcom_can_richedit' ) ) {
    function wpcom_can_richedit( $wp_rich_edit ){
        global $is_IE;
        if( !$wp_rich_edit && $is_IE && !is_admin() ){
            $wp_rich_edit = 1;
        }
        return $wp_rich_edit;
    }
}

function wpcom_post_metas( $key = '' ){
    $html = '';
    if($key){
        global $post;
        switch ($key){
            case 'h':
                $fav = get_post_meta($post->ID, 'wpcom_favorites', true);
                $fav = $fav ? $fav : 0;
                $html = '<span class="item-meta-li hearts" title="喜欢数"><i class="fa fa-heart"></i> '.$fav.'</span>';
                break;
            case 'z':
                $likes = get_post_meta($post->ID, 'wpcom_likes', true);
                $likes = $likes ? $likes : 0;
                $html = '<span class="item-meta-li likes" title="点赞数"><i class="fa fa-thumbs-up"></i> '.$likes.'</span>';
                break;
            case 'v':
                if( function_exists('the_views') ) {
                    $views = $post->views ? $post->views : 0;
                    if ($views >= 1000) $views = sprintf("%.2f", $views / 1000) . 'K';
                    $html = '<span class="item-meta-li views" title="阅读数"><i class="fa fa-eye"></i> ' . $views . '</span>';
                }
                break;
            case 'c':
                $comments = get_comments_number();
                $html = '<a class="item-meta-li comments" href="'.get_permalink($post->ID).'#comments" target="_blank" title="评论数"><i class="fa fa-comments"></i> '.$comments.'</a>';
                break;
        }
    }
    return $html;
}

// 把 <div data-line="true">...</div> 转成 <p>...</p>
add_filter('the_content', function($content) {
    // 仅在前台执行，避免影响后台编辑器/REST 等
    if (is_admin()) { return $content; }

    // 正则：匹配带 data-line="true" 的 div（大小写不敏感，跨行匹配）
    $pattern = '#<div[^>]*\bdata-line\s*=\s*("|\')true\1[^>]*>(.*?)</div>#si';

    // 替换为 <p>...</p>；即使内容为空也输出 <p></p>，依赖主题 p 的 margin 形成“空行”
    $content = preg_replace($pattern, '<p>$2</p>', $content);

    return $content;
}, 20);

// 自动把飞书/Notion复制来的 <div class="ace-line"> 转成 <p>
add_filter('the_content', function($content) {
    $content = preg_replace('/<div[^>]*class="[^"]*ace-line[^"]*"[^>]*>(.*?)<\/div>/is', '<p>$1</p>', $content);
    return $content;
}, 20);

// 自动把飞书/微信编辑器的 <div>/<section> 转成 <p>
add_filter('the_content', function($content) {
    // 替换 ace-line
    $content = preg_replace('/<div[^>]*class="[^"]*ace-line[^"]*"[^>]*>(.*?)<\/div>/is', '<p>$1</p>', $content);
    // 替换 eye-protector-processed
    $content = preg_replace('/<section[^>]*class="[^"]*eye-protector-processed[^"]*"[^>]*>(.*?)<\/section>/is', '<p>$1</p>', $content);
    return $content;
}, 20);

// 前端导航中隐藏空分类菜单项，减少无内容入口
add_filter('wp_nav_menu_objects', function($items) {
    if (is_admin()) {
        return $items;
    }

    $removed_ids = [];
    foreach ($items as $item) {
        if ($item->type !== 'taxonomy' || $item->object !== 'category') {
            continue;
        }

        $term = get_term((int) $item->object_id, 'category');
        if (is_wp_error($term) || !($term instanceof WP_Term) || (int) $term->count === 0) {
            $removed_ids[(int) $item->ID] = true;
        }
    }

    if (!$removed_ids) {
        return $items;
    }

    $parent_map = [];
    foreach ($items as $item) {
        $parent_map[(int) $item->ID] = (int) $item->menu_item_parent;
    }

    $filtered = [];
    foreach ($items as $item) {
        $current_id = (int) $item->ID;
        if (isset($removed_ids[$current_id])) {
            continue;
        }

        $parent_id = isset($parent_map[$current_id]) ? $parent_map[$current_id] : 0;
        while ($parent_id > 0) {
            if (isset($removed_ids[$parent_id])) {
                continue 2;
            }
            $parent_id = isset($parent_map[$parent_id]) ? $parent_map[$parent_id] : 0;
        }

        $filtered[] = $item;
    }

    return $filtered;
}, 10, 2);

// 首页/标签页视觉增强样式
add_action('wp_enqueue_scripts', 'skyrobot_enqueue_home_ui_assets', 30);
function skyrobot_enqueue_home_ui_assets() {
    $is_special_hub_page = is_page('zhuanti') || is_page('tags') || is_page_template('pages/zhuanti.php') || is_page_template('pages/tags.php');
    if (!(is_home() || is_front_page() || is_tag() || is_category() || is_single() || is_tax('special') || $is_special_hub_page)) {
        return;
    }

    $css_file = get_template_directory() . '/css/skyrobot-home.css';
    if (!file_exists($css_file)) {
        return;
    }

    wp_enqueue_style(
        'skyrobot-home-ui',
        get_template_directory_uri() . '/css/skyrobot-home.css',
        array(),
        (string) filemtime($css_file)
    );

    $js_file = get_template_directory() . '/js/skyrobot-home.js';
    if (file_exists($js_file)) {
        wp_enqueue_script(
            'skyrobot-home-ui-js',
            get_template_directory_uri() . '/js/skyrobot-home.js',
            array(),
            (string) filemtime($js_file),
            true
        );
    }
}

function skyrobot_get_header_logo_url($configured_logo = '') {
    $configured_logo = trim((string) $configured_logo);
    $theme_logo = trailingslashit(get_template_directory_uri()) . 'images/logo.svg';
    $theme_logo_file = trailingslashit(get_template_directory()) . 'images/logo.svg';
    if (file_exists($theme_logo_file)) {
        $theme_logo = add_query_arg('v', (string) filemtime($theme_logo_file), $theme_logo);
    }

    // Prefer theme-tracked logo so multi-server deployments keep a consistent brand.
    $prefer_theme_logo = apply_filters('skyrobot_prefer_theme_logo', true, $configured_logo, $theme_logo);
    if ($prefer_theme_logo) {
        return esc_url($theme_logo);
    }

    if ($configured_logo !== '') {
        return esc_url($configured_logo);
    }

    return esc_url($theme_logo);
}

add_filter('wp_nav_menu_objects', 'skyrobot_inject_about_menu_item', 12, 2);
function skyrobot_inject_about_menu_item($menu_items, $args) {
    if (!is_object($args) || !is_array($menu_items)) {
        return $menu_items;
    }
    if (!isset($args->theme_location) || $args->theme_location !== 'primary') {
        return $menu_items;
    }

    $about_url = home_url('/category/about-me/');
    $about_url_norm = untrailingslashit($about_url);
    foreach ($menu_items as $item) {
        if (!is_object($item) || !isset($item->url)) {
            continue;
        }
        $url_norm = untrailingslashit((string) $item->url);
        if ($url_norm === $about_url_norm || strpos($url_norm, '/category/about-me') !== false) {
            return $menu_items;
        }
    }

    $anchor_index = -1;
    $anchor_id = 0;
    for ($i = 0; $i < count($menu_items); $i++) {
        $item = $menu_items[$i];
        if (!is_object($item)) {
            continue;
        }
        $is_root = isset($item->menu_item_parent) && (int) $item->menu_item_parent === 0;
        if (!$is_root) {
            continue;
        }

        $title = isset($item->title) ? (string) $item->title : '';
        $url = isset($item->url) ? (string) $item->url : '';
        if (strpos($title, '生活与阅读') !== false || strpos($url, '/category/life-reading/') !== false) {
            $anchor_index = $i;
            $anchor_id = isset($item->ID) ? (int) $item->ID : 0;
            break;
        }
    }

    if ($anchor_index < 0) {
        for ($j = count($menu_items) - 1; $j >= 0; $j--) {
            $item = $menu_items[$j];
            if (is_object($item) && isset($item->menu_item_parent) && (int) $item->menu_item_parent === 0) {
                $anchor_index = $j;
                $anchor_id = isset($item->ID) ? (int) $item->ID : 0;
                break;
            }
        }
    }

    if ($anchor_index < 0) {
        return $menu_items;
    }

    // Insert after the anchor item and its descendants.
    $insert_at = $anchor_index + 1;
    if ($anchor_id > 0) {
        $descendant_ids = array($anchor_id => true);
        while ($insert_at < count($menu_items)) {
            $current = $menu_items[$insert_at];
            if (!is_object($current)) {
                $insert_at++;
                continue;
            }
            $parent_id = isset($current->menu_item_parent) ? (int) $current->menu_item_parent : 0;
            $current_id = isset($current->ID) ? (int) $current->ID : 0;
            if ($parent_id > 0 && isset($descendant_ids[$parent_id])) {
                if ($current_id > 0) {
                    $descendant_ids[$current_id] = true;
                }
                $insert_at++;
                continue;
            }
            break;
        }
    }

    $about_item = new stdClass();
    $about_item->ID = -999001;
    $about_item->db_id = 0;
    $about_item->menu_item_parent = 0;
    $about_item->object_id = 0;
    $about_item->object = 'custom';
    $about_item->type = 'custom';
    $about_item->type_label = __('Custom Link');
    $about_item->title = '关于我';
    $about_item->url = $about_url;
    $about_item->target = '';
    $about_item->attr_title = '';
    $about_item->description = '';
    $about_item->classes = array('menu-item', 'menu-item-type-custom', 'menu-item-object-custom', 'skyrobot-menu-about');
    $about_item->xfn = '';
    $about_item->status = 'publish';
    $about_item->current = false;
    $about_item->current_item_ancestor = false;
    $about_item->current_item_parent = false;
    $about_item->menu_order = 0;

    array_splice($menu_items, $insert_at, 0, array($about_item));
    return $menu_items;
}

function skyrobot_sanitize_home_link($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    $parsed = wp_parse_url($url);
    if (!is_array($parsed)) {
        return '';
    }

    $host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
    if ($host !== '') {
        $blocked_hosts = array('taobao.com', 'tmall.com');
        foreach ($blocked_hosts as $blocked_host) {
            if ($host === $blocked_host || substr($host, -strlen('.' . $blocked_host)) === '.' . $blocked_host) {
                return home_url('/category/ai-tools/');
            }
        }
    }

    return esc_url_raw($url);
}

function skyrobot_is_internal_link($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return true;
    }

    $parsed = wp_parse_url($url);
    if (!is_array($parsed)) {
        return false;
    }

    if (!isset($parsed['host']) || $parsed['host'] === '') {
        return true;
    }

    $home_parts = wp_parse_url(home_url('/'));
    $home_host = is_array($home_parts) && isset($home_parts['host']) ? strtolower($home_parts['host']) : '';
    $host = strtolower($parsed['host']);

    if ($home_host === '') {
        return false;
    }

    if ($host === $home_host) {
        return true;
    }

    return substr($host, -strlen('.' . $home_host)) === '.' . $home_host;
}

function skyrobot_get_link_target($url) {
    return skyrobot_is_internal_link($url) ? '_self' : '_blank';
}

function skyrobot_get_link_rel($url) {
    return skyrobot_is_internal_link($url) ? '' : 'noopener nofollow external';
}

function skyrobot_format_compact_number($number) {
    $number = (int) $number;
    if ($number >= 1000000) {
        return sprintf('%.1fM', $number / 1000000);
    }
    if ($number >= 10000) {
        return sprintf('%.1fW', $number / 10000);
    }
    if ($number >= 1000) {
        return sprintf('%.1fK', $number / 1000);
    }
    return (string) $number;
}

function skyrobot_get_home_slider_items($limit = 4) {
    global $options;

    $limit = max(1, min(8, (int) $limit));
    $items = array();
    $seen_ids = array();

    $collect_posts = function ($query_posts) use (&$items, &$seen_ids, $limit) {
        if (empty($query_posts)) {
            return;
        }

        foreach ($query_posts as $post_obj) {
            if (count($items) >= $limit) {
                break;
            }

            $post_id = (int) $post_obj->ID;
            if (isset($seen_ids[$post_id])) {
                continue;
            }

            $thumb = get_the_post_thumbnail_url($post_id, 'post-thumbnail');
            if (!$thumb) {
                $thumb = get_the_post_thumbnail_url($post_id, 'large');
            }
            if (!$thumb) {
                continue;
            }

            $seen_ids[$post_id] = true;
            $items[] = array(
                'image' => $thumb,
                'title' => get_the_title($post_id),
                'url' => get_permalink($post_id),
                'alt' => get_the_title($post_id),
                'post_id' => $post_id,
                'thumbnail_id' => (int) get_post_thumbnail_id($post_id),
            );
        }
    };

    $sticky_ids = get_option('sticky_posts');
    if (is_array($sticky_ids) && !empty($sticky_ids)) {
        $sticky_query = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'post__in' => $sticky_ids,
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $limit * 2,
            'suppress_filters' => false,
        ));
        $collect_posts($sticky_query);
    }

    if (count($items) < $limit) {
        $latest_query = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit * 3,
            'ignore_sticky_posts' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => false,
        ));
        $collect_posts($latest_query);
    }

    if (count($items) < $limit && isset($options['slider_img']) && is_array($options['slider_img'])) {
        $fallback_images = array_slice($options['slider_img'], 0, $limit);
        foreach ($fallback_images as $i => $img) {
            if (!$img || count($items) >= $limit) {
                continue;
            }

            $items[] = array(
                'image' => $img,
                'title' => isset($options['slider_title'][$i]) ? (string) $options['slider_title'][$i] : '',
                'url' => isset($options['slider_url'][$i]) ? (string) $options['slider_url'][$i] : '',
                'alt' => isset($options['slider_title'][$i]) ? (string) $options['slider_title'][$i] : '',
                'post_id' => 0,
                'thumbnail_id' => 0,
            );
        }
    }

    foreach ($items as $i => $item) {
        $items[$i]['url'] = skyrobot_sanitize_home_link(isset($item['url']) ? $item['url'] : '');
    }

    return array_slice($items, 0, $limit);
}

function skyrobot_get_home_feature_items($limit = 3, $exclude_post_ids = array()) {
    global $options;

    $limit = max(1, min(6, (int) $limit));
    $exclude_post_ids = array_map('intval', (array) $exclude_post_ids);

    $items = array();
    $posts = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => $limit * 3,
        'post__not_in' => $exclude_post_ids,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => false,
    ));

    foreach ($posts as $post_obj) {
        if (count($items) >= $limit) {
            break;
        }

        $post_id = (int) $post_obj->ID;
        $thumb = get_the_post_thumbnail_url($post_id, 'post-thumbnail');
        if (!$thumb) {
            $thumb = get_the_post_thumbnail_url($post_id, 'large');
        }
        if (!$thumb) {
            continue;
        }

        $items[] = array(
            'image' => $thumb,
            'title' => get_the_title($post_id),
            'url' => get_permalink($post_id),
            'alt' => get_the_title($post_id),
            'post_id' => $post_id,
            'thumbnail_id' => (int) get_post_thumbnail_id($post_id),
        );
    }

    if (count($items) < $limit && isset($options['fea_img']) && is_array($options['fea_img'])) {
        $fallback_images = array_slice($options['fea_img'], 0, $limit);
        foreach ($fallback_images as $i => $img) {
            if (count($items) >= $limit) {
                break;
            }
            if (!$img) {
                continue;
            }

            $items[] = array(
                'image' => $img,
                'title' => isset($options['fea_title'][$i]) ? (string) $options['fea_title'][$i] : '',
                'url' => isset($options['fea_url'][$i]) ? (string) $options['fea_url'][$i] : '',
                'alt' => isset($options['fea_title'][$i]) ? (string) $options['fea_title'][$i] : '',
                'post_id' => 0,
                'thumbnail_id' => 0,
            );
        }
    }

    foreach ($items as $i => $item) {
        $items[$i]['url'] = skyrobot_sanitize_home_link(isset($item['url']) ? $item['url'] : '');
    }

    return array_slice($items, 0, $limit);
}

function skyrobot_get_home_hot_post_ids($limit = 8) {
    $limit = max(1, min(20, (int) $limit));
    $cache_key = 'skyrobot_home_hot_' . $limit;
    $cached_ids = get_transient($cache_key);
    if (is_array($cached_ids)) {
        return array_map('intval', $cached_ids);
    }

    $query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => array('publish'),
        'posts_per_page' => $limit,
        'ignore_sticky_posts' => 1,
        'meta_key' => 'views',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'date_query' => array(
            array(
                'after' => '90 days ago',
            )
        ),
        'meta_query' => array(
            array(
                'key' => 'views',
                'compare' => 'EXISTS',
            )
        ),
        'fields' => 'ids',
        'no_found_rows' => true,
    ));

    $ids = array();
    if (!empty($query->posts)) {
        $ids = array_map('intval', $query->posts);
    }
    wp_reset_postdata();

    if (empty($ids)) {
        $fallback = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'ignore_sticky_posts' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'suppress_filters' => false,
        ));
        $ids = array_map('intval', (array) $fallback);
    }

    set_transient($cache_key, $ids, 10 * MINUTE_IN_SECONDS);
    return $ids;
}

function skyrobot_get_home_spotlight_post_ids($limit = 6) {
    $limit = max(1, min(20, (int) $limit));
    $cache_key = 'skyrobot_home_spotlight_' . $limit;
    $cached_ids = get_transient($cache_key);
    if (is_array($cached_ids)) {
        return array_map('intval', $cached_ids);
    }

    $pool = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => max(24, $limit * 4),
        'ignore_sticky_posts' => true,
        'orderby' => 'comment_count',
        'order' => 'DESC',
        'date_query' => array(
            array(
                'after' => '30 days ago',
            )
        ),
        'suppress_filters' => false,
    ));

    if (empty($pool)) {
        $pool = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => max(24, $limit * 4),
            'ignore_sticky_posts' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => false,
        ));
    }

    $ids = array();
    foreach ((array) $pool as $post_obj) {
        $post_id = (int) $post_obj->ID;
        if ($post_id <= 0 || isset($ids[$post_id])) {
            continue;
        }
        $ids[$post_id] = $post_id;
        if (count($ids) >= $limit) {
            break;
        }
    }

    $ids = array_values($ids);
    set_transient($cache_key, $ids, 10 * MINUTE_IN_SECONDS);
    return $ids;
}

function skyrobot_get_home_hot_special_term_ids($limit = 8) {
    $limit = max(1, min(20, (int) $limit));
    $cache_key = 'skyrobot_home_hot_specials_' . $limit;
    $cached_ids = get_transient($cache_key);
    if (is_array($cached_ids)) {
        return array_map('intval', $cached_ids);
    }

    $terms = get_terms(array(
        'taxonomy' => 'special',
        'hide_empty' => true,
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => $limit,
    ));

    $ids = array();
    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $term_id = isset($term->term_id) ? (int) $term->term_id : 0;
            if ($term_id > 0) {
                $ids[] = $term_id;
            }
        }
    }

    set_transient($cache_key, $ids, 30 * MINUTE_IN_SECONDS);
    return $ids;
}

function skyrobot_get_home_hot_tag_term_ids($limit = 18) {
    $limit = max(1, min(40, (int) $limit));
    $cache_key = 'skyrobot_home_hot_tags_' . $limit;
    $cached_ids = get_transient($cache_key);
    if (is_array($cached_ids)) {
        return array_map('intval', $cached_ids);
    }

    $terms = get_terms(array(
        'taxonomy' => 'post_tag',
        'hide_empty' => true,
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => $limit,
    ));

    $ids = array();
    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $term_id = isset($term->term_id) ? (int) $term->term_id : 0;
            if ($term_id > 0) {
                $ids[] = $term_id;
            }
        }
    }

    set_transient($cache_key, $ids, 30 * MINUTE_IN_SECONDS);
    return $ids;
}

function skyrobot_get_site_stat_snapshot() {
    $cache_key = 'skyrobot_site_stat_snapshot';
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

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
    $special_count = 0;
    if (taxonomy_exists('special')) {
        $special_count = wp_count_terms('special', array(
            'taxonomy' => 'special',
            'hide_empty' => true,
        ));
    }

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

    $stats = array(
        'posts' => max(0, $published_posts),
        'categories' => is_wp_error($category_count) ? 0 : (int) $category_count,
        'tags' => is_wp_error($tag_count) ? 0 : (int) $tag_count,
        'specials' => is_wp_error($special_count) ? 0 : (int) $special_count,
        'recent_7d' => max(0, $recent_count),
    );

    set_transient($cache_key, $stats, 30 * MINUTE_IN_SECONDS);
    return $stats;
}

function skyrobot_get_home_random_post_ids($limit = 5) {
    $limit = max(1, min(12, (int) $limit));
    $cache_key = 'skyrobot_home_random_' . $limit;
    $cached_ids = get_transient($cache_key);
    if (is_array($cached_ids)) {
        return array_map('intval', $cached_ids);
    }

    $pool = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
        'suppress_filters' => false,
    ));
    $pool = array_map('intval', (array) $pool);

    if (empty($pool)) {
        set_transient($cache_key, array(), 10 * MINUTE_IN_SECONDS);
        return array();
    }

    shuffle($pool);
    $ids = array_slice($pool, 0, $limit);

    set_transient($cache_key, $ids, 20 * MINUTE_IN_SECONDS);
    return $ids;
}

function skyrobot_get_category_page_context($category_id, $hot_limit = 6) {
    $category_id = (int) $category_id;
    if ($category_id <= 0) {
        return array();
    }

    $hot_limit = max(3, min(12, (int) $hot_limit));
    $cache_key = 'skyrobot_cat_ctx_' . $category_id . '_' . $hot_limit;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $category = get_category($category_id);
    if (!$category || is_wp_error($category)) {
        return array();
    }

    $recent_query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => array('publish'),
        'cat' => $category_id,
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
    $recent_7d = isset($recent_query->found_posts) ? (int) $recent_query->found_posts : 0;
    wp_reset_postdata();

    $latest_post = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'cat' => $category_id,
        'posts_per_page' => 1,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => false,
    ));
    $latest_date = '';
    if (!empty($latest_post) && isset($latest_post[0]->ID)) {
        $latest_date = get_the_date('Y-m-d', (int) $latest_post[0]->ID);
    }

    $subcategories = array();
    $sub_terms = get_categories(array(
        'taxonomy' => 'category',
        'hide_empty' => true,
        'parent' => $category_id,
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 10,
    ));
    if (!is_wp_error($sub_terms) && !empty($sub_terms)) {
        foreach ($sub_terms as $sub_term) {
            $sub_term_id = isset($sub_term->term_id) ? (int) $sub_term->term_id : 0;
            if ($sub_term_id <= 0) {
                continue;
            }
            $subcategories[] = array(
                'id' => $sub_term_id,
                'name' => isset($sub_term->name) ? (string) $sub_term->name : '',
                'count' => isset($sub_term->count) ? (int) $sub_term->count : 0,
                'url' => get_category_link($sub_term_id),
            );
        }
    }

    $hot_query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => array('publish'),
        'cat' => $category_id,
        'posts_per_page' => $hot_limit,
        'ignore_sticky_posts' => 1,
        'meta_key' => 'views',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'date_query' => array(
            array(
                'after' => '90 days ago',
            )
        ),
        'meta_query' => array(
            array(
                'key' => 'views',
                'compare' => 'EXISTS',
            )
        ),
        'fields' => 'ids',
        'no_found_rows' => true,
    ));

    $hot_post_ids = array();
    if (!empty($hot_query->posts)) {
        $hot_post_ids = array_map('intval', $hot_query->posts);
    }
    wp_reset_postdata();

    if (empty($hot_post_ids)) {
        $fallback_hot = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'cat' => $category_id,
            'posts_per_page' => $hot_limit,
            'ignore_sticky_posts' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'suppress_filters' => false,
        ));
        $hot_post_ids = array_map('intval', (array) $fallback_hot);
    }

    $tag_bucket = array();
    $tag_source_posts = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'cat' => $category_id,
        'posts_per_page' => 60,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'fields' => 'ids',
        'suppress_filters' => false,
    ));
    foreach ((array) $tag_source_posts as $tag_post_id) {
        $tag_post_id = (int) $tag_post_id;
        if ($tag_post_id <= 0) {
            continue;
        }

        $post_tags = wp_get_post_tags($tag_post_id, array('fields' => 'all'));
        if (empty($post_tags) || is_wp_error($post_tags)) {
            continue;
        }

        foreach ($post_tags as $tag_term) {
            $tag_id = isset($tag_term->term_id) ? (int) $tag_term->term_id : 0;
            if ($tag_id <= 0) {
                continue;
            }

            if (!isset($tag_bucket[$tag_id])) {
                $tag_link = get_tag_link($tag_id);
                $tag_bucket[$tag_id] = array(
                    'id' => $tag_id,
                    'name' => isset($tag_term->name) ? (string) $tag_term->name : '',
                    'slug' => isset($tag_term->slug) ? (string) $tag_term->slug : '',
                    'weight' => 0,
                    'count' => isset($tag_term->count) ? (int) $tag_term->count : 0,
                    'url' => is_wp_error($tag_link) ? '' : $tag_link,
                );
            }

            $tag_bucket[$tag_id]['weight'] += 1;
        }
    }

    if (!empty($tag_bucket)) {
        uasort($tag_bucket, function ($a, $b) {
            $aw = isset($a['weight']) ? (int) $a['weight'] : 0;
            $bw = isset($b['weight']) ? (int) $b['weight'] : 0;
            if ($aw === $bw) {
                $ac = isset($a['count']) ? (int) $a['count'] : 0;
                $bc = isset($b['count']) ? (int) $b['count'] : 0;
                if ($ac === $bc) {
                    return 0;
                }
                return ($ac > $bc) ? -1 : 1;
            }
            return ($aw > $bw) ? -1 : 1;
        });
    }
    $hot_tags = array_values(array_slice($tag_bucket, 0, 14, true));

    $context = array(
        'category_id' => $category_id,
        'post_count' => isset($category->count) ? (int) $category->count : 0,
        'recent_7d' => max(0, $recent_7d),
        'latest_date' => (string) $latest_date,
        'description' => term_description($category_id, 'category'),
        'subcategories' => $subcategories,
        'hot_posts' => $hot_post_ids,
        'hot_tags' => $hot_tags,
    );

    set_transient($cache_key, $context, 30 * MINUTE_IN_SECONDS);
    return $context;
}

function skyrobot_get_special_term_snapshot($term_id) {
    $term_id = (int) $term_id;
    if ($term_id <= 0 || !taxonomy_exists('special')) {
        return array();
    }

    $cache_key = 'skyrobot_special_term_' . $term_id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $term = get_term($term_id, 'special');
    if (!$term || is_wp_error($term)) {
        return array();
    }

    $term_link = get_term_link($term_id, 'special');
    $thumb = get_term_meta($term_id, 'wpcom_thumb', true);

    $latest_date = '';
    $latest_ts = 0;
    $latest_post = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'tax_query' => array(
            array(
                'taxonomy' => 'special',
                'field' => 'term_id',
                'terms' => $term_id,
            )
        ),
        'suppress_filters' => false,
    ));
    if (!empty($latest_post) && isset($latest_post[0]->ID)) {
        $latest_ts = (int) get_post_time('U', false, (int) $latest_post[0]->ID);
        $latest_date = get_the_date('Y-m-d', (int) $latest_post[0]->ID);
    }

    $recent_query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => array('publish'),
        'posts_per_page' => 1,
        'ignore_sticky_posts' => 1,
        'tax_query' => array(
            array(
                'taxonomy' => 'special',
                'field' => 'term_id',
                'terms' => $term_id,
            )
        ),
        'date_query' => array(
            array(
                'after' => '7 days ago',
            )
        ),
        'fields' => 'ids',
        'no_found_rows' => false,
    ));
    $recent_7d = isset($recent_query->found_posts) ? (int) $recent_query->found_posts : 0;
    wp_reset_postdata();

    $preview_posts = array();
    $preview_query = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 3,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'tax_query' => array(
            array(
                'taxonomy' => 'special',
                'field' => 'term_id',
                'terms' => $term_id,
            )
        ),
        'suppress_filters' => false,
    ));
    foreach ((array) $preview_query as $preview_post) {
        $preview_id = isset($preview_post->ID) ? (int) $preview_post->ID : 0;
        if ($preview_id <= 0) {
            continue;
        }
        $preview_posts[] = array(
            'id' => $preview_id,
            'title' => get_the_title($preview_id),
            'url' => get_permalink($preview_id),
            'date' => get_the_date('m-d', $preview_id),
            'views' => (int) get_post_meta($preview_id, 'views', true),
        );
    }

    if (!$thumb && !empty($preview_posts) && isset($preview_posts[0]['id'])) {
        $thumb = get_the_post_thumbnail_url((int) $preview_posts[0]['id'], 'post-thumbnail');
        if (!$thumb) {
            $thumb = get_the_post_thumbnail_url((int) $preview_posts[0]['id'], 'large');
        }
    }

    $snapshot = array(
        'id' => $term_id,
        'name' => isset($term->name) ? (string) $term->name : '',
        'slug' => isset($term->slug) ? (string) $term->slug : '',
        'count' => isset($term->count) ? (int) $term->count : 0,
        'description' => term_description($term_id, 'special'),
        'url' => is_wp_error($term_link) ? '' : $term_link,
        'thumb' => (string) $thumb,
        'latest_date' => (string) $latest_date,
        'latest_timestamp' => (int) $latest_ts,
        'recent_7d' => max(0, $recent_7d),
        'preview_posts' => $preview_posts,
    );

    set_transient($cache_key, $snapshot, 30 * MINUTE_IN_SECONDS);
    return $snapshot;
}

function skyrobot_get_special_hub_context($limit = 12) {
    $limit = max(6, min(24, (int) $limit));
    if (!taxonomy_exists('special')) {
        return array(
            'total_terms' => 0,
            'total_posts' => 0,
            'recent_7d' => 0,
            'terms' => array(),
            'hot_terms' => array(),
            'latest_terms' => array(),
            'hot_tags' => array(),
            'has_more' => false,
        );
    }

    $cache_key = 'skyrobot_special_hub_' . $limit;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $terms = get_terms(array(
        'taxonomy' => 'special',
        'hide_empty' => true,
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => 80,
    ));

    if (is_wp_error($terms) || empty($terms)) {
        return array(
            'total_terms' => 0,
            'total_posts' => 0,
            'recent_7d' => 0,
            'terms' => array(),
            'hot_terms' => array(),
            'latest_terms' => array(),
            'hot_tags' => array(),
            'has_more' => false,
        );
    }

    $snapshots = array();
    $total_posts = 0;
    $recent_7d = 0;
    $tag_bucket = array();

    foreach ($terms as $term) {
        $term_id = isset($term->term_id) ? (int) $term->term_id : 0;
        if ($term_id <= 0) {
            continue;
        }

        $snapshot = skyrobot_get_special_term_snapshot($term_id);
        if (empty($snapshot)) {
            continue;
        }

        $snapshots[] = $snapshot;
        $total_posts += isset($snapshot['count']) ? (int) $snapshot['count'] : 0;
        $recent_7d += isset($snapshot['recent_7d']) ? (int) $snapshot['recent_7d'] : 0;

        if (!empty($snapshot['preview_posts'])) {
            foreach ($snapshot['preview_posts'] as $preview_post) {
                $preview_id = isset($preview_post['id']) ? (int) $preview_post['id'] : 0;
                if ($preview_id <= 0) {
                    continue;
                }
                $preview_tags = wp_get_post_tags($preview_id, array('fields' => 'all'));
                if (empty($preview_tags) || is_wp_error($preview_tags)) {
                    continue;
                }
                foreach ($preview_tags as $preview_tag) {
                    $preview_tag_id = isset($preview_tag->term_id) ? (int) $preview_tag->term_id : 0;
                    if ($preview_tag_id <= 0) {
                        continue;
                    }
                    if (!isset($tag_bucket[$preview_tag_id])) {
                        $preview_tag_link = get_tag_link($preview_tag_id);
                        $tag_bucket[$preview_tag_id] = array(
                            'id' => $preview_tag_id,
                            'name' => isset($preview_tag->name) ? (string) $preview_tag->name : '',
                            'slug' => isset($preview_tag->slug) ? (string) $preview_tag->slug : '',
                            'count' => isset($preview_tag->count) ? (int) $preview_tag->count : 0,
                            'weight' => 0,
                            'url' => is_wp_error($preview_tag_link) ? '' : $preview_tag_link,
                        );
                    }
                    $tag_bucket[$preview_tag_id]['weight'] += 1;
                }
            }
        }
    }

    $hot_terms = $snapshots;
    usort($hot_terms, function ($a, $b) {
        $ac = isset($a['count']) ? (int) $a['count'] : 0;
        $bc = isset($b['count']) ? (int) $b['count'] : 0;
        if ($ac === $bc) {
            return 0;
        }
        return ($ac > $bc) ? -1 : 1;
    });
    $hot_terms = array_slice($hot_terms, 0, 8);

    $latest_terms = $snapshots;
    usort($latest_terms, function ($a, $b) {
        $at = isset($a['latest_timestamp']) ? (int) $a['latest_timestamp'] : 0;
        $bt = isset($b['latest_timestamp']) ? (int) $b['latest_timestamp'] : 0;
        if ($at === $bt) {
            return 0;
        }
        return ($at > $bt) ? -1 : 1;
    });
    $latest_terms = array_slice($latest_terms, 0, 8);

    if (!empty($tag_bucket)) {
        uasort($tag_bucket, function ($a, $b) {
            $aw = isset($a['weight']) ? (int) $a['weight'] : 0;
            $bw = isset($b['weight']) ? (int) $b['weight'] : 0;
            if ($aw === $bw) {
                return 0;
            }
            return ($aw > $bw) ? -1 : 1;
        });
    }

    $context = array(
        'total_terms' => count($snapshots),
        'total_posts' => max(0, $total_posts),
        'recent_7d' => max(0, $recent_7d),
        'terms' => array_slice($snapshots, 0, $limit),
        'hot_terms' => $hot_terms,
        'latest_terms' => $latest_terms,
        'hot_tags' => array_values(array_slice($tag_bucket, 0, 16, true)),
        'has_more' => count($snapshots) > $limit,
    );

    set_transient($cache_key, $context, 30 * MINUTE_IN_SECONDS);
    return $context;
}

function skyrobot_get_tag_preview_posts($tag_id, $limit = 2) {
    $tag_id = (int) $tag_id;
    $limit = max(1, min(6, (int) $limit));
    if ($tag_id <= 0) {
        return array(
            'posts' => array(),
            'latest_timestamp' => 0,
            'latest_date' => '',
            'recent_7d' => 0,
        );
    }

    $cache_key = 'skyrobot_tag_preview_' . $tag_id . '_' . $limit;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $posts = get_posts(array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'ignore_sticky_posts' => true,
        'orderby' => 'date',
        'order' => 'DESC',
        'tag_id' => $tag_id,
        'suppress_filters' => false,
    ));

    $post_items = array();
    $latest_ts = 0;
    $latest_date = '';
    foreach ((array) $posts as $post_item) {
        $post_id = isset($post_item->ID) ? (int) $post_item->ID : 0;
        if ($post_id <= 0) {
            continue;
        }

        if ($latest_ts === 0) {
            $latest_ts = (int) get_post_time('U', false, $post_id);
            $latest_date = get_the_date('Y-m-d', $post_id);
        }

        $post_items[] = array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'url' => get_permalink($post_id),
            'date' => get_the_date('m-d', $post_id),
        );
    }

    $recent_query = new WP_Query(array(
        'post_type' => 'post',
        'post_status' => array('publish'),
        'posts_per_page' => 1,
        'ignore_sticky_posts' => 1,
        'tag_id' => $tag_id,
        'date_query' => array(
            array(
                'after' => '7 days ago',
            )
        ),
        'fields' => 'ids',
        'no_found_rows' => false,
    ));
    $recent_7d = isset($recent_query->found_posts) ? (int) $recent_query->found_posts : 0;
    wp_reset_postdata();

    $result = array(
        'posts' => $post_items,
        'latest_timestamp' => $latest_ts,
        'latest_date' => $latest_date,
        'recent_7d' => max(0, $recent_7d),
    );

    set_transient($cache_key, $result, 30 * MINUTE_IN_SECONDS);
    return $result;
}

function skyrobot_get_tag_hub_context($limit_tags = 24) {
    $limit_tags = max(12, min(48, (int) $limit_tags));
    $cache_key = 'skyrobot_tag_hub_' . $limit_tags;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $terms = get_terms(array(
        'taxonomy' => 'post_tag',
        'hide_empty' => true,
        'orderby' => 'count',
        'order' => 'DESC',
        'number' => max(120, $limit_tags * 3),
    ));

    if (is_wp_error($terms) || empty($terms)) {
        return array(
            'total_tags' => 0,
            'covered_posts' => 0,
            'active_tags_7d' => 0,
            'top_chips' => array(),
            'tag_cards' => array(),
            'related_specials' => array(),
        );
    }

    $top_terms = array_slice($terms, 0, $limit_tags);
    $top_chips = array();
    $tag_cards = array();
    $active_tags_7d = 0;

    foreach ($top_terms as $term) {
        $term_id = isset($term->term_id) ? (int) $term->term_id : 0;
        if ($term_id <= 0) {
            continue;
        }

        $tag_link = get_tag_link($term_id);
        if (is_wp_error($tag_link)) {
            continue;
        }

        $preview_data = skyrobot_get_tag_preview_posts($term_id, 2);
        if (isset($preview_data['recent_7d']) && (int) $preview_data['recent_7d'] > 0) {
            $active_tags_7d++;
        }

        $term_slug = isset($term->slug) ? (string) $term->slug : '';
        $color_index = function_exists('skyrobot_tag_color_index') ? skyrobot_tag_color_index($term_slug) : 1;

        $chip = array(
            'id' => $term_id,
            'name' => isset($term->name) ? (string) $term->name : '',
            'slug' => $term_slug,
            'count' => isset($term->count) ? (int) $term->count : 0,
            'url' => $tag_link,
            'color' => (int) $color_index,
        );
        $top_chips[] = $chip;

        $tag_cards[] = array(
            'id' => $term_id,
            'name' => isset($term->name) ? (string) $term->name : '',
            'slug' => $term_slug,
            'count' => isset($term->count) ? (int) $term->count : 0,
            'url' => $tag_link,
            'color' => (int) $color_index,
            'latest_date' => isset($preview_data['latest_date']) ? (string) $preview_data['latest_date'] : '',
            'latest_timestamp' => isset($preview_data['latest_timestamp']) ? (int) $preview_data['latest_timestamp'] : 0,
            'recent_7d' => isset($preview_data['recent_7d']) ? (int) $preview_data['recent_7d'] : 0,
            'preview_posts' => isset($preview_data['posts']) ? (array) $preview_data['posts'] : array(),
        );
    }

    $related_specials = array();
    if (taxonomy_exists('special') && function_exists('skyrobot_get_home_hot_special_term_ids')) {
        $special_ids = skyrobot_get_home_hot_special_term_ids(8);
        if (!empty($special_ids)) {
            $special_terms = get_terms(array(
                'taxonomy' => 'special',
                'hide_empty' => true,
                'include' => $special_ids,
                'orderby' => 'include',
            ));
            if (!is_wp_error($special_terms) && !empty($special_terms)) {
                foreach ($special_terms as $special_term) {
                    $special_id = isset($special_term->term_id) ? (int) $special_term->term_id : 0;
                    if ($special_id <= 0) {
                        continue;
                    }
                    $special_link = get_term_link($special_id, 'special');
                    if (is_wp_error($special_link)) {
                        continue;
                    }
                    $related_specials[] = array(
                        'id' => $special_id,
                        'name' => isset($special_term->name) ? (string) $special_term->name : '',
                        'count' => isset($special_term->count) ? (int) $special_term->count : 0,
                        'url' => $special_link,
                    );
                }
            }
        }
    }

    $post_counts = wp_count_posts('post');
    $covered_posts = isset($post_counts->publish) ? (int) $post_counts->publish : 0;

    $context = array(
        'total_tags' => count($terms),
        'covered_posts' => max(0, $covered_posts),
        'active_tags_7d' => max(0, $active_tags_7d),
        'top_chips' => $top_chips,
        'tag_cards' => $tag_cards,
        'related_specials' => $related_specials,
    );

    set_transient($cache_key, $context, 30 * MINUTE_IN_SECONDS);
    return $context;
}

function skyrobot_build_heading_payload($content) {
    $content = (string) $content;
    if ($content === '') {
        return array(
            'content' => $content,
            'items' => array(),
        );
    }

    $items = array();
    $used_ids = array();
    $counter = 1;

    $new_content = preg_replace_callback('/<h([2-3])([^>]*)>(.*?)<\/h\1>/is', function ($matches) use (&$items, &$used_ids, &$counter) {
        $level = isset($matches[1]) ? (int) $matches[1] : 2;
        $attrs = isset($matches[2]) ? (string) $matches[2] : '';
        $inner = isset($matches[3]) ? (string) $matches[3] : '';
        $title = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($inner)));
        if ($title === '') {
            $title = 'Section ' . $counter;
        }

        $heading_id = '';
        if (preg_match('/\sid=(["\'])(.*?)\1/i', $attrs, $id_match)) {
            $heading_id = sanitize_title($id_match[2]);
        }
        if ($heading_id === '') {
            $heading_id = sanitize_title($title);
        }
        if ($heading_id === '') {
            $heading_id = 'section-' . $counter;
        }

        $base_id = $heading_id;
        $suffix = 2;
        while (isset($used_ids[$heading_id])) {
            $heading_id = $base_id . '-' . $suffix;
            $suffix++;
        }
        $used_ids[$heading_id] = true;

        if (preg_match('/\sid=(["\'])(.*?)\1/i', $attrs)) {
            $attrs = preg_replace('/\sid=(["\'])(.*?)\1/i', ' id="' . esc_attr($heading_id) . '"', $attrs, 1);
        } else {
            $attrs .= ' id="' . esc_attr($heading_id) . '"';
        }

        $items[] = array(
            'id' => $heading_id,
            'title' => $title,
            'level' => $level,
        );

        $counter++;
        return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
    }, $content);

    return array(
        'content' => $new_content ? $new_content : $content,
        'items' => $items,
    );
}

function skyrobot_get_single_toc_payload($post_id) {
    static $cache = array();

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return array(
            'raw' => '',
            'content' => '',
            'items' => array(),
        );
    }

    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

    $raw_content = (string) get_post_field('post_content', $post_id);
    $payload = skyrobot_build_heading_payload($raw_content);
    $cache[$post_id] = array(
        'raw' => $raw_content,
        'content' => isset($payload['content']) ? (string) $payload['content'] : $raw_content,
        'items' => isset($payload['items']) ? (array) $payload['items'] : array(),
    );

    return $cache[$post_id];
}

function skyrobot_prepare_single_toc_content($content) {
    if (is_admin() || !is_singular('post')) {
        return $content;
    }

    global $post;
    if (!$post || !isset($post->ID)) {
        return $content;
    }

    $payload = skyrobot_get_single_toc_payload((int) $post->ID);
    if (!is_array($payload) || !isset($payload['raw'])) {
        return $content;
    }

    if (trim((string) $content) !== trim((string) $payload['raw'])) {
        return $content;
    }

    return isset($payload['content']) ? $payload['content'] : $content;
}
add_filter('the_content', 'skyrobot_prepare_single_toc_content', 5);

function skyrobot_get_single_sidebar_context($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return array();
    }

    $cache_key = 'skyrobot_single_sidebar_' . $post_id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        return array();
    }

    $content_text = trim(wp_strip_all_tags((string) get_post_field('post_content', $post_id)));
    $content_length = function_exists('mb_strlen') ? (int) mb_strlen($content_text, 'UTF-8') : (int) strlen($content_text);
    $reading_minutes = max(1, (int) ceil($content_length / 450));

    $categories = get_the_category($post_id);
    $primary_category = (!empty($categories) && isset($categories[0])) ? $categories[0] : null;
    $primary_category_id = ($primary_category && isset($primary_category->term_id)) ? (int) $primary_category->term_id : 0;

    $same_cat_hot = array();
    if ($primary_category_id > 0) {
        $hot_query = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => array('publish'),
            'posts_per_page' => 6,
            'ignore_sticky_posts' => 1,
            'cat' => $primary_category_id,
            'post__not_in' => array($post_id),
            'meta_key' => 'views',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'date_query' => array(
                array(
                    'after' => '90 days ago',
                )
            ),
            'meta_query' => array(
                array(
                    'key' => 'views',
                    'compare' => 'EXISTS',
                )
            ),
            'fields' => 'ids',
            'no_found_rows' => true,
        ));
        if (!empty($hot_query->posts)) {
            $same_cat_hot = array_map('intval', $hot_query->posts);
        }
        wp_reset_postdata();

        if (empty($same_cat_hot)) {
            $fallback_same_cat = get_posts(array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 6,
                'ignore_sticky_posts' => true,
                'cat' => $primary_category_id,
                'post__not_in' => array($post_id),
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
                'suppress_filters' => false,
            ));
            $same_cat_hot = array_map('intval', (array) $fallback_same_cat);
        }
    }

    $related_posts = array();
    $tag_ids = wp_get_post_tags($post_id, array('fields' => 'ids'));
    $tag_ids = array_map('intval', (array) $tag_ids);
    if (!empty($tag_ids)) {
        $related_query = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 6,
            'ignore_sticky_posts' => true,
            'post__not_in' => array($post_id),
            'tag__in' => $tag_ids,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'suppress_filters' => false,
        ));
        $related_posts = array_map('intval', (array) $related_query);
    }
    if (empty($related_posts)) {
        $fallback_related = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 6,
            'ignore_sticky_posts' => true,
            'post__not_in' => array($post_id),
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'suppress_filters' => false,
        ));
        $related_posts = array_map('intval', (array) $fallback_related);
    }

    $post_tags = array();
    $tag_terms = wp_get_post_tags($post_id, array('fields' => 'all'));
    if (!empty($tag_terms) && !is_wp_error($tag_terms)) {
        foreach ($tag_terms as $tag_term) {
            $tag_term_id = isset($tag_term->term_id) ? (int) $tag_term->term_id : 0;
            if ($tag_term_id <= 0) {
                continue;
            }
            $tag_link = get_tag_link($tag_term_id);
            if (is_wp_error($tag_link)) {
                continue;
            }
            $post_tags[] = array(
                'id' => $tag_term_id,
                'name' => isset($tag_term->name) ? (string) $tag_term->name : '',
                'slug' => isset($tag_term->slug) ? (string) $tag_term->slug : '',
                'url' => $tag_link,
                'count' => isset($tag_term->count) ? (int) $tag_term->count : 0,
            );
        }
    }

    $toc_payload = skyrobot_get_single_toc_payload($post_id);
    $toc_items = isset($toc_payload['items']) ? (array) $toc_payload['items'] : array();

    $context = array(
        'meta' => array(
            'date' => get_the_date('Y-m-d', $post_id),
            'views' => (int) get_post_meta($post_id, 'views', true),
            'comments' => (int) get_comments_number($post_id),
            'reading_minutes' => $reading_minutes,
            'word_count' => max(0, $content_length),
            'category' => $primary_category && isset($primary_category->name) ? (string) $primary_category->name : '',
            'category_url' => '',
        ),
        'toc' => $toc_items,
        'same_cat_hot' => $same_cat_hot,
        'related_posts' => $related_posts,
        'tags' => $post_tags,
    );

    if ($primary_category_id > 0) {
        $primary_link = get_category_link($primary_category_id);
        $context['meta']['category_url'] = is_wp_error($primary_link) ? '' : $primary_link;
    }

    set_transient($cache_key, $context, 20 * MINUTE_IN_SECONDS);
    return $context;
}

function skyrobot_flush_transients_by_prefix($prefix) {
    global $wpdb;

    $prefix = trim((string) $prefix);
    if ($prefix === '' || !isset($wpdb->options)) {
        return;
    }

    $value_like = $wpdb->esc_like('_transient_' . $prefix) . '%';
    $timeout_like = $wpdb->esc_like('_transient_timeout_' . $prefix) . '%';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $value_like,
            $timeout_like
        )
    );
}

function skyrobot_flush_home_data_transients() {
    $hot_limits = array(8, 10, 12, 16, 20);
    foreach ($hot_limits as $limit) {
        delete_transient('skyrobot_home_hot_' . $limit);
    }

    $spotlight_limits = array(4, 6, 8, 12, 20);
    foreach ($spotlight_limits as $limit) {
        delete_transient('skyrobot_home_spotlight_' . $limit);
    }

    $special_limits = array(6, 8, 10, 12, 20);
    foreach ($special_limits as $limit) {
        delete_transient('skyrobot_home_hot_specials_' . $limit);
    }

    $tag_limits = array(11, 12, 16, 18, 20, 24, 30, 40);
    foreach ($tag_limits as $limit) {
        delete_transient('skyrobot_home_hot_tags_' . $limit);
    }

    $random_limits = array(3, 4, 5, 6, 8, 10, 12);
    foreach ($random_limits as $limit) {
        delete_transient('skyrobot_home_random_' . $limit);
    }

    delete_transient('skyrobot_site_stat_snapshot');
    skyrobot_flush_transients_by_prefix('skyrobot_cat_ctx_');
    skyrobot_flush_transients_by_prefix('skyrobot_special_term_');
    skyrobot_flush_transients_by_prefix('skyrobot_special_hub_');
    skyrobot_flush_transients_by_prefix('skyrobot_tag_preview_');
    skyrobot_flush_transients_by_prefix('skyrobot_tag_hub_');
    skyrobot_flush_transients_by_prefix('skyrobot_single_sidebar_');
}

function skyrobot_flush_home_data_on_post_change($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (get_post_type($post_id) !== 'post') {
        return;
    }

    skyrobot_flush_home_data_transients();
}
add_action('save_post', 'skyrobot_flush_home_data_on_post_change');
add_action('deleted_post', 'skyrobot_flush_home_data_on_post_change');
add_action('trashed_post', 'skyrobot_flush_home_data_on_post_change');
add_action('untrashed_post', 'skyrobot_flush_home_data_on_post_change');

function skyrobot_flush_home_data_on_term_change($term_id, $tt_id = 0, $taxonomy = '') {
    if (!in_array($taxonomy, array('category', 'post_tag', 'special'), true)) {
        return;
    }
    skyrobot_flush_home_data_transients();
}
add_action('created_term', 'skyrobot_flush_home_data_on_term_change', 10, 3);
add_action('edited_term', 'skyrobot_flush_home_data_on_term_change', 10, 3);
add_action('delete_term', 'skyrobot_flush_home_data_on_term_change', 10, 3);

function skyrobot_tag_color_index($slug, $palette_size = 12) {
    $palette_size = max(1, (int) $palette_size);
    $hash = sprintf('%u', crc32((string) $slug));
    return ((int) $hash % $palette_size) + 1;
}

add_filter('wp_generate_tag_cloud_data', 'skyrobot_colorize_tag_cloud');
function skyrobot_colorize_tag_cloud($tags_data) {
    if (!is_array($tags_data)) {
        return $tags_data;
    }

    foreach ($tags_data as $i => $tag_data) {
        $slug = '';
        if (isset($tag_data['slug']) && $tag_data['slug']) {
            $slug = (string) $tag_data['slug'];
        } elseif (isset($tag_data['id']) && $tag_data['id']) {
            $term = get_term((int) $tag_data['id'], 'post_tag');
            if ($term && !is_wp_error($term)) {
                $slug = (string) $term->slug;
            }
        }

        if ($slug === '') {
            continue;
        }

        $index = skyrobot_tag_color_index($slug);
        $extra_class = ' sky-tag-color-' . $index;
        $tags_data[$i]['class'] = isset($tag_data['class']) ? $tag_data['class'] . $extra_class : trim($extra_class);
    }

    return $tags_data;
}

function skyrobot_get_about_profile_config() {
    $config = array(
        'category_slug' => 'about-me',
        'name_cn' => '李庆平',
        'name_en' => 'Sky',
        'headline' => '不懂 AI 的产品经理，不是好软件工程师',
        'intro' => '10 年软件开发经验，聚焦工业机器人、预测性维护与 AI 落地实践。关注从概念到交付的真实闭环。',
        'city' => '广东 深圳',
        'wechat_id' => 'skyfloriaforever',
        'wechat_qr' => get_template_directory_uri() . '/images/about/wechat-sky.jpg',
        'wechat_note' => '添加时备注“博客”或“AI咨询”，我会优先回复。',
        'primary_cta' => array(
            'label' => '加微信交流',
            'url' => '#aboutme-contact',
        ),
        'secondary_cta' => array(
            'label' => '查看专题',
            'url' => home_url('/zhuanti/'),
        ),
        'problem_points' => array(
            'AI 项目切入路径不清：明确目标、边界和最小可行方案。',
            '产品与研发协作成本高：把需求转化为可执行的技术任务。',
            '系统迭代效率低：通过插件化和工程化提升扩展能力。',
        ),
        'domain_points' => array(
            '工业机器人领域大型应用软件规划与落地。',
            '预测性维护系统整体方案设计与推进。',
            '工业机器人系统插件化改造与架构优化。',
        ),
        'method_points' => array(
            array(
                'title' => '自动化',
                'desc' => '减少重复劳动，提升交付效率。',
            ),
            array(
                'title' => '模板化',
                'desc' => '沉淀可复用资产，降低试错成本。',
            ),
            array(
                'title' => '流程化',
                'desc' => '建立稳定闭环，保障质量与节奏。',
            ),
            array(
                'title' => '工具化',
                'desc' => '让能力可执行、可复制、可放大。',
            ),
        ),
        'value_points' => array(
            '把 AI、产品与工程能力融合成可落地的方案。',
            '推动团队从概念讨论走向工程交付。',
            '建立可持续迭代的系统化能力，而不是一次性方案。',
        ),
        'site_positioning' => array(
            '面向 AI 实战与效率提升的内容型技术博客。',
            '持续输出工具教程、案例拆解、方法沉淀。',
            '帮助读者把“知道”转化为“做到”。',
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

    return apply_filters('skyrobot_about_profile_config', $config);
}

function skyrobot_get_about_page_posts($category_slug = 'about-me', $limit = 6) {
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

function skyrobot_get_about_page_context($category_slug = 'about-me', $limit = 6) {
    $profile = skyrobot_get_about_profile_config();
    if (isset($profile['category_slug']) && $profile['category_slug']) {
        $category_slug = sanitize_title((string) $profile['category_slug']);
    }

    $category = get_category_by_slug($category_slug);
    $category_name = $category && !is_wp_error($category) && isset($category->name) ? (string) $category->name : '';
    $category_desc = $category && !is_wp_error($category) && isset($category->term_id) ? term_description((int) $category->term_id, 'category') : '';

    $stats = function_exists('skyrobot_get_site_stat_snapshot') ? skyrobot_get_site_stat_snapshot() : array();
    $post_ids = skyrobot_get_about_page_posts($category_slug, $limit);

    return array(
        'profile' => $profile,
        'category_slug' => $category_slug,
        'category_name' => $category_name,
        'category_desc' => $category_desc,
        'stats' => $stats,
        'post_ids' => $post_ids,
    );
}
