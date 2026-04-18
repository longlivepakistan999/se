<?php
/**
 * AI Tool Category Taxonomy Archive Template.
 *
 * @package QWE_Developer_Flavor
 */

get_header();

$current_term = get_queried_object();
?>

<?php qwe_breadcrumbs(); ?>

<div class="section">
    <div class="container">
        <header class="section__header">
            <h1><?php echo esc_html( $current_term->name ); ?></h1>
            <?php if ( $current_term->description ) : ?>
                <p><?php echo esc_html( $current_term->description ); ?></p>
            <?php endif; ?>
            <span class="archive-count">
                <?php
                printf(
                    esc_html( _n( '%d tool', '%d tools', $current_term->count, 'qwe-developer-flavor' ) ),
                    $current_term->count
                );
                ?>
            </span>
        </header>

        <!-- Category Filters -->
        <?php
        $categories = get_terms( array(
            'taxonomy'   => 'ai_tool_category',
            'hide_empty' => true,
        ) );
        ?>

        <?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
            <div class="course-filters">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'ai-tools' ) ); ?>" class="filter-btn">
                    <?php esc_html_e( 'All', 'qwe-developer-flavor' ); ?>
                </a>
                <?php foreach ( $categories as $cat ) : ?>
                    <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"
                       class="filter-btn <?php echo ( $current_term->term_id === $cat->term_id ) ? 'active' : ''; ?>">
                        <?php echo esc_html( $cat->name ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( have_posts() ) : ?>
            <div class="courses-grid">
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php get_template_part( 'template-parts/course/course', 'card' ); ?>
                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination( array(
                'mid_size'           => 2,
                'prev_text'          => '&larr; ' . esc_html__( 'Previous', 'qwe-developer-flavor' ),
                'next_text'          => esc_html__( 'Next', 'qwe-developer-flavor' ) . ' &rarr;',
                'screen_reader_text' => esc_html__( 'AI Tools navigation', 'qwe-developer-flavor' ),
            ) ); ?>
        <?php else : ?>
            <?php get_template_part( 'template-parts/content/content', 'none' ); ?>
        <?php endif; ?>
    </div>
</div>

<?php
get_footer();
