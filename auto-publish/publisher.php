<?php
/**
 * WordPress Publisher
 *
 * Publishes generated articles to WordPress using wp_insert_post().
 * Supports two post types:
 *   - 'tutorial'  + 'tutorial_category' taxonomy  (longtail, trending, manual)
 *   - 'ai-tool'   + 'ai_tool_category' taxonomy   (tool deployment tutorials)
 *
 * @package QWE_Auto_Publish
 */

require_once __DIR__ . '/db.php';

class QWE_Publisher {

    /**
     * Publish an article to WordPress.
     *
     * @param array $article Article data from QWE_Generator.
     * @return int|false      Post ID on success, false on failure.
     */
    public static function publish( $article ) {

        // Ensure WordPress functions are available.
        if ( ! function_exists( 'wp_insert_post' ) ) {
            self::log( 'WordPress not loaded' );
            return false;
        }

        // Determine post type and taxonomy based on keyword_type.
        $is_tool   = ( 'tool' === ( $article['keyword_type'] ?? '' ) );
        $post_type = $is_tool ? 'ai-tool'           : 'tutorial';
        $taxonomy  = $is_tool ? 'ai_tool_category'   : 'tutorial_category';

        // Register ai-tool post type if it doesn't exist yet.
        if ( $is_tool ) {
            self::register_tool_post_type();
        }

        // Sanitize slug first so duplicate check matches what WP actually stores.
        $slug = sanitize_title( $article['slug'] );

        // Check for duplicate slug.
        $existing = get_page_by_path( $slug, OBJECT, $post_type );
        if ( $existing ) {
            self::log( "Duplicate slug: {$slug}, skipping" );
            return false;
        }

        // Ensure author display name matches config.
        self::sync_author_name();

        // Get or create the category term.
        $term_id = self::get_or_create_category( $article['category'], $taxonomy );

        // Prepare post data.
        $post_data = array(
            'post_title'   => sanitize_text_field( $article['title'] ),
            'post_name'    => $slug,
            'post_content' => wp_kses_post( $article['content'] ),
            'post_excerpt' => sanitize_text_field( $article['excerpt'] ),
            'post_status'  => QWE_POST_STATUS,
            'post_type'    => $post_type,
            'post_author'  => QWE_AUTHOR_ID,
        );

        // Insert the post.
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            self::log( 'wp_insert_post error: ' . $post_id->get_error_message() );
            return false;
        }

        // Assign category.
        if ( $term_id ) {
            wp_set_object_terms( $post_id, $term_id, $taxonomy );
        }

        // Assign tags (post_tag taxonomy).
        if ( ! empty( $article['tags'] ) && is_array( $article['tags'] ) ) {
            $clean_tags = array_map( 'sanitize_text_field', $article['tags'] );
            $clean_tags = array_filter( $clean_tags );
            if ( ! empty( $clean_tags ) ) {
                wp_set_post_tags( $post_id, $clean_tags );
            }
        }

        // Set difficulty level.
        update_post_meta( $post_id, '_qwe_difficulty', sanitize_text_field( $article['difficulty'] ) );

        // Save CTR-optimized meta description (used by seo.php for <meta name="description">).
        if ( ! empty( $article['meta_description'] ) ) {
            update_post_meta( $post_id, '_qwe_meta_description', sanitize_text_field( $article['meta_description'] ) );
        }

        // Don't auto-feature.
        update_post_meta( $post_id, '_qwe_featured', '0' );

        // Store keyword tracking meta.
        update_post_meta( $post_id, '_qwe_keyword_type', sanitize_text_field( $article['keyword_type'] ) );
        update_post_meta( $post_id, '_qwe_source_keyword', sanitize_text_field( $article['keyword'] ) );
        update_post_meta( $post_id, '_qwe_auto_generated', '1' );

        self::log( "Published: [{$post_id}] {$article['title']} ({$article['keyword_type']}: {$article['keyword']}) [{$post_type}]" );

        return $post_id;
    }

    /**
     * Register the ai-tool custom post type and taxonomy if not already registered.
     */
    private static function register_tool_post_type() {
        if ( post_type_exists( 'ai-tool' ) ) {
            return;
        }

        register_post_type( 'ai-tool', array(
            'labels' => array(
                'name'          => 'AI Tools',
                'singular_name' => 'AI Tool',
            ),
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => array( 'slug' => 'ai-tools' ),
            'supports'     => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail' ),
            'show_in_rest' => true,
        ) );

        if ( ! taxonomy_exists( 'ai_tool_category' ) ) {
            register_taxonomy( 'ai_tool_category', 'ai-tool', array(
                'labels' => array(
                    'name'          => 'AI Tool Categories',
                    'singular_name' => 'AI Tool Category',
                ),
                'public'       => true,
                'hierarchical' => true,
                'rewrite'      => array( 'slug' => 'ai-tool-category' ),
                'show_in_rest' => true,
            ) );
        }
    }

    /**
     * Get the term_id for a category slug, create if it doesn't exist.
     *
     * @param string $slug     Category slug from config.
     * @param string $taxonomy Taxonomy name.
     * @return int|false        Term ID or false.
     */
    private static function get_or_create_category( $slug, $taxonomy = 'tutorial_category' ) {
        $categories = unserialize( QWE_CATEGORIES );
        $name = isset( $categories[ $slug ] ) ? $categories[ $slug ] : $slug;

        $term = get_term_by( 'slug', $slug, $taxonomy );

        if ( $term ) {
            return $term->term_id;
        }

        // Create the term.
        $result = wp_insert_term( $name, $taxonomy, array(
            'slug' => $slug,
        ) );

        if ( is_wp_error( $result ) ) {
            self::log( "Failed to create category: {$slug} ({$taxonomy}) - " . $result->get_error_message() );
            return false;
        }

        return $result['term_id'];
    }

    /**
     * Ensure the WP author's display_name matches QWE_AUTHOR_NAME.
     *
     * Runs once per session (static flag prevents repeated DB writes).
     */
    private static function sync_author_name() {
        static $synced = false;
        if ( $synced || ! defined( 'QWE_AUTHOR_NAME' ) || ! QWE_AUTHOR_NAME ) {
            return;
        }
        $synced = true;

        $user = get_userdata( QWE_AUTHOR_ID );
        if ( $user && $user->display_name !== QWE_AUTHOR_NAME ) {
            wp_update_user( array(
                'ID'           => QWE_AUTHOR_ID,
                'display_name' => QWE_AUTHOR_NAME,
            ) );
            self::log( 'Author display name updated to: ' . QWE_AUTHOR_NAME );
        }
    }

    /**
     * Simple log.
     */
    private static function log( $message ) {
        $time = date( 'Y-m-d H:i:s' );
        $log = "[{$time}] PUBLISHER: {$message}\n";
        file_put_contents( __DIR__ . '/data/auto_publish.log', $log, FILE_APPEND );
    }
}
