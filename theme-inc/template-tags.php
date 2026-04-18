<?php
/**
 * Custom template tags for the theme.
 *
 * @package QWE_Developer_Flavor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display tutorial meta info (date, reading time, difficulty).
 */
function qwe_tutorial_meta() {
    echo '<div class="tutorial-meta">';

    // Author name.
    $author_name = get_the_author();
    echo '<span class="tutorial-meta__author" itemprop="author" itemscope itemtype="https://schema.org/Person">';
    echo '<span itemprop="name">' . esc_html( $author_name ) . '</span>';
    echo '</span>';

    echo '<span class="tutorial-meta__date">';
    echo '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>';
    echo '</span>';

    if ( get_the_modified_date() !== get_the_date() ) {
        echo '<span class="tutorial-meta__updated">';
        printf(
            /* translators: %s: date */
            esc_html__( 'Updated %s', 'qwe-developer-flavor' ),
            '<time datetime="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . esc_html( get_the_modified_date() ) . '</time>'
        );
        echo '</span>';
    }

    echo '<span class="tutorial-meta__reading-time">' . esc_html( qwe_reading_time() ) . '</span>';

    $difficulty = qwe_difficulty_label();
    $diff_class = qwe_difficulty_class();
    echo '<span class="tutorial-meta__difficulty ' . esc_attr( $diff_class ) . '">' . esc_html( $difficulty ) . '</span>';

    echo '</div>';
}

/**
 * Display tutorial categories as linked tags.
 */
function qwe_tutorial_categories() {
    $categories = get_the_terms( get_the_ID(), 'tutorial_category' );
    if ( $categories && ! is_wp_error( $categories ) ) {
        echo '<div class="tutorial-categories">';
        foreach ( $categories as $cat ) {
            echo '<a href="' . esc_url( get_term_link( $cat ) ) . '" class="tutorial-category-tag">' . esc_html( $cat->name ) . '</a>';
        }
        echo '</div>';
    }
}

/**
 * Display post navigation (previous/next).
 */
function qwe_post_navigation() {
    $post_type = get_post_type();

    if ( 'tutorial' === $post_type ) {
        $prev = get_previous_post( true, '', 'tutorial_category' );
        $next = get_next_post( true, '', 'tutorial_category' );
    } elseif ( 'ai-tools' === $post_type ) {
        $prev = get_previous_post( true, '', 'ai_tool_category' );
        $next = get_next_post( true, '', 'ai_tool_category' );
    } else {
        $prev = get_previous_post();
        $next = get_next_post();
    }

    if ( ! $prev && ! $next ) {
        return;
    }

    echo '<nav class="post-navigation" aria-label="' . esc_attr__( 'Post navigation', 'qwe-developer-flavor' ) . '">';
    echo '<div class="post-navigation__inner">';

    if ( $prev ) {
        echo '<a href="' . esc_url( get_permalink( $prev ) ) . '" class="post-navigation__link post-navigation__link--prev">';
        echo '<span class="post-navigation__label">' . esc_html__( 'Previous', 'qwe-developer-flavor' ) . '</span>';
        echo '<span class="post-navigation__title">' . esc_html( get_the_title( $prev ) ) . '</span>';
        echo '</a>';
    }

    if ( $next ) {
        echo '<a href="' . esc_url( get_permalink( $next ) ) . '" class="post-navigation__link post-navigation__link--next">';
        echo '<span class="post-navigation__label">' . esc_html__( 'Next', 'qwe-developer-flavor' ) . '</span>';
        echo '<span class="post-navigation__title">' . esc_html( get_the_title( $next ) ) . '</span>';
        echo '</a>';
    }

    echo '</div>';
    echo '</nav>';
}

/**
 * Display related tutorials.
 */
function qwe_related_tutorials( $count = 3 ) {
    $categories = get_the_terms( get_the_ID(), 'tutorial_category' );

    if ( ! $categories || is_wp_error( $categories ) ) {
        return;
    }

    $cat_ids = wp_list_pluck( $categories, 'term_id' );

    $related = new WP_Query( array(
        'post_type'      => 'tutorial',
        'posts_per_page' => $count,
        'post__not_in'   => array( get_the_ID() ),
        'tax_query'      => array(
            array(
                'taxonomy' => 'tutorial_category',
                'field'    => 'term_id',
                'terms'    => $cat_ids,
            ),
        ),
    ) );

    if ( $related->have_posts() ) :
        ?>
        <section class="related-tutorials section">
            <div class="container">
                <h2 class="related-tutorials__title"><?php esc_html_e( 'Related Tutorials', 'qwe-developer-flavor' ); ?></h2>
                <div class="courses-grid">
                    <?php while ( $related->have_posts() ) : $related->the_post(); ?>
                        <?php get_template_part( 'template-parts/course/course', 'card' ); ?>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>
        <?php
    endif;
    wp_reset_postdata();
}

/**
 * Display AI Tool categories for single ai-tools posts.
 */
function qwe_ai_tool_categories() {
    $categories = get_the_terms( get_the_ID(), 'ai_tool_category' );
    if ( $categories && ! is_wp_error( $categories ) ) {
        echo '<div class="tutorial-categories">';
        foreach ( $categories as $cat ) {
            echo '<a href="' . esc_url( get_term_link( $cat ) ) . '" class="tutorial-category-tag">' . esc_html( $cat->name ) . '</a>';
        }
        echo '</div>';
    }
}

/**
 * Display related AI Tools.
 */
function qwe_related_ai_tools( $count = 3 ) {
    $categories = get_the_terms( get_the_ID(), 'ai_tool_category' );

    if ( ! $categories || is_wp_error( $categories ) ) {
        return;
    }

    $cat_ids = wp_list_pluck( $categories, 'term_id' );

    $related = new WP_Query( array(
        'post_type'      => 'ai-tools',
        'posts_per_page' => $count,
        'post__not_in'   => array( get_the_ID() ),
        'tax_query'      => array(
            array(
                'taxonomy' => 'ai_tool_category',
                'field'    => 'term_id',
                'terms'    => $cat_ids,
            ),
        ),
    ) );

    if ( $related->have_posts() ) :
        ?>
        <section class="related-tutorials section">
            <div class="container">
                <h2 class="related-tutorials__title"><?php esc_html_e( 'Related AI Tools', 'qwe-developer-flavor' ); ?></h2>
                <div class="courses-grid">
                    <?php while ( $related->have_posts() ) : $related->the_post(); ?>
                        <?php get_template_part( 'template-parts/course/course', 'card' ); ?>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>
        <?php
    endif;
    wp_reset_postdata();
}
