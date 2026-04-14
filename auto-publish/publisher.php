<?php
/**
 * WordPress Publisher
 *
 * Publishes generated articles to WordPress using wp_insert_post().
 * Automatically assigns tutorial_category taxonomy and meta fields.
 * Stores keyword source info in post meta for tracking.
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

        // Sanitize slug first so duplicate check matches what WP actually stores.
        $slug = sanitize_title( $article['slug'] );

        // Check for duplicate slug.
        $existing = get_page_by_path( $slug, OBJECT, 'tutorial' );
        if ( $existing ) {
            self::log( "Duplicate slug: {$slug}, skipping" );
            return false;
        }

        // Ensure author display name matches config.
        self::sync_author_name();

        // Get or create the tutorial_category term.
        $term_id = self::get_or_create_category( $article['category'] );

        // Prepare post data.
        $post_data = array(
            'post_title'   => sanitize_text_field( $article['title'] ),
            'post_name'    => $slug,
            'post_content' => wp_kses_post( $article['content'] ),
            'post_excerpt' => sanitize_text_field( $article['excerpt'] ),
            'post_status'  => QWE_POST_STATUS,
            'post_type'    => 'tutorial',
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
            wp_set_object_terms( $post_id, $term_id, 'tutorial_category' );
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

        self::log( "Published: [{$post_id}] {$article['title']} ({$article['keyword_type']}: {$article['keyword']})" );

        return $post_id;
    }

    /**
     * Get the term_id for a category slug, create if it doesn't exist.
     *
     * @param string $slug Category slug from config.
     * @return int|false    Term ID or false.
     */
    private static function get_or_create_category( $slug ) {
        $categories = unserialize( QWE_CATEGORIES );
        $name = isset( $categories[ $slug ] ) ? $categories[ $slug ] : $slug;

        $term = get_term_by( 'slug', $slug, 'tutorial_category' );

        if ( $term ) {
            return $term->term_id;
        }

        // Create the term.
        $result = wp_insert_term( $name, 'tutorial_category', array(
            'slug' => $slug,
        ) );

        if ( is_wp_error( $result ) ) {
            self::log( "Failed to create category: {$slug} - " . $result->get_error_message() );
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
