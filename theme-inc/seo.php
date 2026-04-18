<?php
/**
 * SEO Enhancements.
 *
 * @package QWE_Developer_Flavor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Output JSON-LD structured data.
 */
function qwe_output_structured_data() {
    if ( is_front_page() ) {
        $data = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'WebSite',
            'name'        => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'url'         => home_url( '/' ),
            'inLanguage'  => 'en',
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string',
            ),
        );
        echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";

        $org = array(
            '@context' => 'https://schema.org',
            '@type'    => 'EducationalOrganization',
            'name'     => get_bloginfo( 'name' ),
            'url'      => home_url( '/' ),
        );
        $logo_id = get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
            if ( $logo_url ) {
                $org['logo'] = $logo_url;
            }
        }
        echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    if ( is_singular( 'tutorial' ) || is_singular( 'ai-tools' ) || is_singular( 'post' ) ) {
        $post = get_post();
        $ld_desc = get_post_meta( $post->ID, '_qwe_meta_description', true );
        $ld_desc = $ld_desc ? $ld_desc : get_the_excerpt();

        $data = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => get_the_title(),
            'description'   => $ld_desc,
            'datePublished' => get_the_date( 'c' ),
            'dateModified'  => get_the_modified_date( 'c' ),
            'author'        => array(
                '@type' => 'Person',
                'name'  => get_the_author(),
            ),
            'publisher'     => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
            ),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_permalink(),
            ),
            'isAccessibleForFree' => true,
            'inLanguage'          => 'en',
        );

        if ( 'tutorial' === get_post_type() ) {
            $categories = get_the_terms( $post->ID, 'tutorial_category' );
            $cat_name = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0]->name : 'AI';
            $data['articleSection'] = $cat_name;
        } elseif ( 'ai-tools' === get_post_type() ) {
            $categories = get_the_terms( $post->ID, 'ai_tool_category' );
            $cat_name = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0]->name : 'AI Tools';
            $data['articleSection'] = $cat_name;
        } else {
            $cat = get_the_category();
            if ( ! empty( $cat ) ) {
                $data['articleSection'] = $cat[0]->name;
            }
        }

        if ( has_post_thumbnail() ) {
            $data['image'] = get_the_post_thumbnail_url( $post->ID, 'qwe-tutorial-hero' );
        }

        echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    if ( is_post_type_archive( 'tutorial' ) || is_tax( 'tutorial_category' )
        || is_post_type_archive( 'ai-tools' ) || is_tax( 'ai_tool_category' ) ) {
        $archive_name = __( 'AI Tutorials', 'qwe-developer-flavor' );
        if ( is_post_type_archive( 'ai-tools' ) ) {
            $archive_name = __( 'AI Tools', 'qwe-developer-flavor' );
        } elseif ( is_tax() ) {
            $archive_name = single_term_title( '', false );
        }
        $data = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'CollectionPage',
            'name'       => $archive_name,
            'url'        => get_pagenum_link(),
            'inLanguage' => 'en',
        );

        if ( is_tax() ) {
            $term = get_queried_object();
            if ( $term->description ) {
                $data['description'] = $term->description;
            }
        }

        echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}
add_action( 'wp_head', 'qwe_output_structured_data', 5 );

/**
 * Output Open Graph meta tags.
 */
function qwe_output_og_tags() {
    echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
    echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";

    if ( is_front_page() ) {
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( get_bloginfo( 'description' ) ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( home_url( '/' ) ) . '">' . "\n";
    } elseif ( is_singular() ) {
        $og_desc = get_post_meta( get_the_ID(), '_qwe_meta_description', true );
        $og_desc = $og_desc ? $og_desc : get_the_excerpt();
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( get_the_title() ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_permalink() ) . '">' . "\n";
        echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
        echo '<meta property="article:author" content="' . esc_attr( get_the_author() ) . '">' . "\n";
        if ( has_post_thumbnail() ) {
            $img_id  = get_post_thumbnail_id();
            $img_url = get_the_post_thumbnail_url( null, 'qwe-tutorial-hero' );
            $img_meta = wp_get_attachment_metadata( $img_id );
            echo '<meta property="og:image" content="' . esc_url( $img_url ) . '">' . "\n";
            if ( $img_meta && isset( $img_meta['width'], $img_meta['height'] ) ) {
                echo '<meta property="og:image:width" content="' . esc_attr( $img_meta['width'] ) . '">' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( $img_meta['height'] ) . '">' . "\n";
            }
        }
    } elseif ( is_post_type_archive( 'tutorial' ) ) {
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr__( 'AI Tutorials', 'qwe-developer-flavor' ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_post_type_archive_link( 'tutorial' ) ) . '">' . "\n";
    } elseif ( is_post_type_archive( 'ai-tools' ) ) {
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr__( 'AI Tools', 'qwe-developer-flavor' ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_post_type_archive_link( 'ai-tools' ) ) . '">' . "\n";
    } elseif ( is_tax( 'tutorial_category' ) || is_tax( 'ai_tool_category' ) ) {
        $term = get_queried_object();
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $term->name ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_term_link( $term ) ) . '">' . "\n";
        if ( $term->description ) {
            echo '<meta property="og:description" content="' . esc_attr( $term->description ) . '">' . "\n";
        }
    }

    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}
add_action( 'wp_head', 'qwe_output_og_tags', 5 );

/**
 * Add meta description tag.
 *
 * For tutorials: uses _qwe_meta_description (CTR-optimized) if available,
 * falls back to the post excerpt. The meta_description is specifically
 * written for search result click-through, while excerpt is for cards.
 */
function qwe_meta_description() {
    $description = '';

    if ( is_front_page() ) {
        $description = get_bloginfo( 'description' );
    } elseif ( is_singular() ) {
        // Prefer CTR-optimized meta description over generic excerpt.
        $meta_desc = get_post_meta( get_the_ID(), '_qwe_meta_description', true );
        $description = $meta_desc ? $meta_desc : get_the_excerpt();
    } elseif ( is_post_type_archive( 'tutorial' ) ) {
        $description = __( 'Free AI tutorials - Learn ChatGPT, AI art, AI coding, and more at QWE AI Academy', 'qwe-developer-flavor' );
    } elseif ( is_post_type_archive( 'ai-tools' ) ) {
        $description = __( 'AI tool deployment guides - Installation tutorials and setup instructions at QWE AI Academy', 'qwe-developer-flavor' );
    } elseif ( is_tax( 'tutorial_category' ) || is_tax( 'ai_tool_category' ) ) {
        $term = get_queried_object();
        $description = $term->description ? $term->description : sprintf(
            /* translators: %s: category name */
            __( 'Free %s tutorials - Learn AI at QWE AI Academy', 'qwe-developer-flavor' ),
            $term->name
        );
    }

    if ( $description ) {
        echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $description ) ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'qwe_meta_description', 1 );

/**
 * Extend WordPress canonical URLs to cover archives.
 *
 * WordPress handles canonical for singular and front page natively since 5.0.
 * We only add canonical for custom post type archives and taxonomy archives.
 */
function qwe_canonical_url() {
    if ( is_post_type_archive( 'tutorial' ) || is_tax( 'tutorial_category' )
        || is_post_type_archive( 'ai-tools' ) || is_tax( 'ai_tool_category' ) ) {
        echo '<link rel="canonical" href="' . esc_url( get_pagenum_link() ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'qwe_canonical_url', 1 );

/**
 * Add noindex for search results and 404 pages.
 */
function qwe_robots_meta() {
    if ( is_search() || is_404() ) {
        echo '<meta name="robots" content="noindex, follow">' . "\n";
    }
}
add_action( 'wp_head', 'qwe_robots_meta', 1 );

/**
 * Add hreflang tag for English content.
 */
function qwe_hreflang_tag() {
    if ( is_front_page() ) {
        $url = home_url( '/' );
    } elseif ( is_singular() ) {
        $url = get_permalink();
    } elseif ( is_post_type_archive( 'tutorial' ) ) {
        $url = get_post_type_archive_link( 'tutorial' );
    } elseif ( is_post_type_archive( 'ai-tools' ) ) {
        $url = get_post_type_archive_link( 'ai-tools' );
    } elseif ( is_tax( 'tutorial_category' ) || is_tax( 'ai_tool_category' ) ) {
        $url = get_term_link( get_queried_object() );
    } else {
        return;
    }

    if ( $url && ! is_wp_error( $url ) ) {
        echo '<link rel="alternate" hreflang="en" href="' . esc_url( $url ) . '">' . "\n";
    }
}
add_action( 'wp_head', 'qwe_hreflang_tag', 1 );

/**
 * Generate breadcrumbs with proper Schema.org markup.
 */
function qwe_breadcrumbs() {
    if ( is_front_page() ) {
        return;
    }

    $items = array();

    $items[] = array(
        'url'  => home_url( '/' ),
        'name' => __( 'Home', 'qwe-developer-flavor' ),
    );

    if ( is_singular( 'tutorial' ) ) {
        $items[] = array(
            'url'  => get_post_type_archive_link( 'tutorial' ),
            'name' => __( 'Tutorials', 'qwe-developer-flavor' ),
        );
        $categories = get_the_terms( get_the_ID(), 'tutorial_category' );
        if ( $categories && ! is_wp_error( $categories ) ) {
            $cat = $categories[0];
            $items[] = array(
                'url'  => get_term_link( $cat ),
                'name' => $cat->name,
            );
        }
        $items[] = array(
            'name' => get_the_title(),
        );
    } elseif ( is_singular( 'ai-tools' ) ) {
        $items[] = array(
            'url'  => get_post_type_archive_link( 'ai-tools' ),
            'name' => __( 'AI Tools', 'qwe-developer-flavor' ),
        );
        $categories = get_the_terms( get_the_ID(), 'ai_tool_category' );
        if ( $categories && ! is_wp_error( $categories ) ) {
            $cat = $categories[0];
            $items[] = array(
                'url'  => get_term_link( $cat ),
                'name' => $cat->name,
            );
        }
        $items[] = array(
            'name' => get_the_title(),
        );
    } elseif ( is_post_type_archive( 'tutorial' ) ) {
        $items[] = array(
            'name' => __( 'Tutorials', 'qwe-developer-flavor' ),
        );
    } elseif ( is_post_type_archive( 'ai-tools' ) ) {
        $items[] = array(
            'name' => __( 'AI Tools', 'qwe-developer-flavor' ),
        );
    } elseif ( is_tax( 'tutorial_category' ) ) {
        $items[] = array(
            'url'  => get_post_type_archive_link( 'tutorial' ),
            'name' => __( 'Tutorials', 'qwe-developer-flavor' ),
        );
        $items[] = array(
            'name' => single_term_title( '', false ),
        );
    } elseif ( is_tax( 'ai_tool_category' ) ) {
        $items[] = array(
            'url'  => get_post_type_archive_link( 'ai-tools' ),
            'name' => __( 'AI Tools', 'qwe-developer-flavor' ),
        );
        $items[] = array(
            'name' => single_term_title( '', false ),
        );
    } elseif ( is_singular( 'post' ) ) {
        $items[] = array(
            'name' => get_the_title(),
        );
    } elseif ( is_page() ) {
        $items[] = array(
            'name' => get_the_title(),
        );
    } elseif ( is_search() ) {
        $items[] = array(
            'name' => __( 'Search Results', 'qwe-developer-flavor' ),
        );
    } elseif ( is_404() ) {
        $items[] = array(
            'name' => __( 'Page Not Found', 'qwe-developer-flavor' ),
        );
    }

    if ( ! empty( $items ) ) {
        echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'qwe-developer-flavor' ) . '">';
        echo '<div class="container">';
        echo '<ol class="breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">';
        foreach ( $items as $i => $item ) {
            $is_last = ( $i === count( $items ) - 1 );
            echo '<li class="breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            if ( ! $is_last && isset( $item['url'] ) ) {
                echo '<a itemprop="item" href="' . esc_url( $item['url'] ) . '"><span itemprop="name">' . esc_html( $item['name'] ) . '</span></a>';
            } else {
                echo '<span aria-current="page" itemprop="name">' . esc_html( $item['name'] ) . '</span>';
            }
            echo '<meta itemprop="position" content="' . ( $i + 1 ) . '">';
            echo '</li>';
        }
        echo '</ol>';
        echo '</div>';
        echo '</nav>';
    }
}
