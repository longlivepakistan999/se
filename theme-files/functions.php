<?php
/**
 * QWE AI Education Theme Functions
 *
 * @package QWE_Developer_Flavor
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'QWE_THEME_VERSION', '1.1.0' );
define( 'QWE_THEME_DIR', get_template_directory() );
define( 'QWE_THEME_URI', get_template_directory_uri() );
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'feed_links_extra', 3); 

/**
 * Force English locale on the frontend.
 *
 * Keeps the admin dashboard in the user's chosen language
 * while ensuring the public site always displays in English.
 */
function qwe_force_frontend_english( $locale ) {
    if ( ! is_admin() ) {
        return 'en_US';
    }
    return $locale;
}
add_filter( 'locale', 'qwe_force_frontend_english' );

/**
 * Theme setup.
 */
function qwe_theme_setup() {
    load_theme_textdomain( 'qwe-developer-flavor', QWE_THEME_DIR . '/languages' );

     
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ) );
    add_theme_support( 'custom-logo', array(
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ) );
    add_theme_support( 'customize-selective-refresh-widgets' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'responsive-embeds' );

    add_image_size( 'qwe-tutorial-card', 640, 360, true );
    add_image_size( 'qwe-tutorial-hero', 1200, 500, true );

    register_nav_menus( array(
        'primary' => esc_html__( 'Primary Menu', 'qwe-developer-flavor' ),
        'footer'  => esc_html__( 'Footer Menu', 'qwe-developer-flavor' ),
    ) );
}
add_action( 'after_setup_theme', 'qwe_theme_setup' );

/**
 * Enqueue scripts and styles.
 */
function qwe_enqueue_assets() {
    wp_enqueue_style(
        'qwe-google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap',
        array(),
        null
    );

    wp_enqueue_style(
        'qwe-style',
        get_stylesheet_uri(),
        array( 'qwe-google-fonts' ),
        QWE_THEME_VERSION
    );

    wp_enqueue_script(
        'qwe-main',
        QWE_THEME_URI . '/assets/js/main.js',
        array(),
        QWE_THEME_VERSION,
        true
    );

    wp_localize_script( 'qwe-main', 'qweData', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'qwe_nonce' ),
        'siteUrl' => home_url( '/' ),
        'i18n'    => array(
            'copy'       => esc_html__( 'Copy', 'qwe-developer-flavor' ),
            'copied'     => esc_html__( 'Copied!', 'qwe-developer-flavor' ),
            'copyToClip' => esc_attr__( 'Copy code to clipboard', 'qwe-developer-flavor' ),
        ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'qwe_enqueue_assets' );

/**
 * Register widget areas.
 */
function qwe_widgets_init() {
    register_sidebar( array(
        'name'          => esc_html__( 'Sidebar', 'qwe-developer-flavor' ),
        'id'            => 'sidebar-1',
        'description'   => esc_html__( 'Add widgets here for the tutorial sidebar.', 'qwe-developer-flavor' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    register_sidebar( array(
        'name'          => esc_html__( 'Footer Widget Area', 'qwe-developer-flavor' ),
        'id'            => 'footer-1',
        'description'   => esc_html__( 'Add widgets here for the footer.', 'qwe-developer-flavor' ),
        'before_widget' => '<div id="%1$s" class="footer-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="footer-widget-title">',
        'after_title'   => '</h4>',
    ) );
}
add_action( 'widgets_init', 'qwe_widgets_init' );

/**
 * Include additional theme files.
 */
require_once QWE_THEME_DIR . '/inc/custom-post-types.php';
require_once QWE_THEME_DIR . '/inc/customizer.php';
require_once QWE_THEME_DIR . '/inc/template-tags.php';
require_once QWE_THEME_DIR . '/inc/theme-hooks.php';
require_once QWE_THEME_DIR . '/inc/seo.php';
require_once QWE_THEME_DIR . '/inc/sample-content.php';

/**
 * Custom excerpt length.
 */
function qwe_excerpt_length( $length ) {
    return 25;
}
add_filter( 'excerpt_length', 'qwe_excerpt_length' );

/**
 * Custom excerpt more text.
 */
function qwe_excerpt_more( $more ) {
    return '&hellip;';
}
add_filter( 'excerpt_more', 'qwe_excerpt_more' );

/**
 * Add custom body classes.
 */
function qwe_body_classes( $classes ) {
    if ( is_singular( 'tutorial' ) ) {
        $classes[] = 'single-tutorial-page';
    }
    if ( is_singular( 'ai-tools' ) ) {
        $classes[] = 'single-tutorial-page';
    }
    if ( is_front_page() ) {
        $classes[] = 'front-page';
    }
    if ( is_post_type_archive( 'tutorial' ) || is_tax( 'tutorial_category' ) ) {
        $classes[] = 'tutorial-archive-page';
    }
    if ( is_post_type_archive( 'ai-tools' ) || is_tax( 'ai_tool_category' ) ) {
        $classes[] = 'tutorial-archive-page';
    }
    return $classes;
}
add_filter( 'body_class', 'qwe_body_classes' );

/**
 * Modify the main query for tutorial archives.
 */
function qwe_pre_get_posts( $query ) {
    if ( ! is_admin() && $query->is_main_query() ) {
        if ( is_post_type_archive( 'tutorial' ) || is_tax( 'tutorial_category' )
            || is_post_type_archive( 'ai-tools' ) || is_tax( 'ai_tool_category' ) ) {
            $query->set( 'posts_per_page', 12 );
        }
    }
}
add_action( 'pre_get_posts', 'qwe_pre_get_posts' );

/**
 * Fallback menu when no menu is assigned to Primary Menu location.
 * Displays key pages automatically.
 */
function qwe_fallback_menu() {
    echo '<ul id="primary-menu">';
    echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'qwe-developer-flavor' ) . '</a></li>';

    $archive_link = get_post_type_archive_link( 'tutorial' );
    if ( $archive_link ) {
        echo '<li><a href="' . esc_url( $archive_link ) . '">' . esc_html__( 'Tutorials', 'qwe-developer-flavor' ) . '</a></li>';
    }

    $pages = get_pages( array( 'number' => 5, 'sort_column' => 'menu_order' ) );
    if ( $pages ) {
        foreach ( $pages as $page ) {
            echo '<li><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( $page->post_title ) . '</a></li>';
        }
    }
    echo '</ul>';
}

/**
 * Ensure rewrite rules are up-to-date.
 *
 * Flushes rewrite rules once after theme setup if the tutorial
 * post type rules are not yet registered. This fixes the /tutorial/ 404 issue.
 */
function qwe_maybe_flush_rewrite_rules() {
    if ( get_option( 'qwe_rewrite_rules_flushed' ) !== QWE_THEME_VERSION ) {
        flush_rewrite_rules();
        update_option( 'qwe_rewrite_rules_flushed', QWE_THEME_VERSION );
    }
}
add_action( 'init', 'qwe_maybe_flush_rewrite_rules', 99 );

/**
 * Estimated reading time for tutorials.
 */
function qwe_reading_time( $post_id = null ) {
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    $content    = get_post_field( 'post_content', $post_id );
    $word_count = str_word_count( wp_strip_all_tags( $content ) );
    $minutes    = max( 1, ceil( $word_count / 200 ) );

    return sprintf(
        /* translators: %d: number of minutes */
        _n( '%d min read', '%d min read', $minutes, 'qwe-developer-flavor' ),
        $minutes
    );
}

/**
 * Get tutorial difficulty level label.
 */
function qwe_difficulty_label( $post_id = null ) {
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    $difficulty = get_post_meta( $post_id, '_qwe_difficulty', true );
    $labels = array(
        'beginner'     => esc_html__( 'Beginner', 'qwe-developer-flavor' ),
        'intermediate' => esc_html__( 'Intermediate', 'qwe-developer-flavor' ),
        'advanced'     => esc_html__( 'Advanced', 'qwe-developer-flavor' ),
    );
    return isset( $labels[ $difficulty ] ) ? $labels[ $difficulty ] : $labels['beginner'];
}

/**
 * Get difficulty CSS class.
 */
function qwe_difficulty_class( $post_id = null ) {
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    $difficulty = get_post_meta( $post_id, '_qwe_difficulty', true );
    if ( ! $difficulty ) {
        $difficulty = 'beginner';
    }
    return 'difficulty--' . sanitize_html_class( $difficulty );
}
