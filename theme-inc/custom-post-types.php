<?php
/**
 * Register Custom Post Types and Taxonomies.
 *
 * @package QWE_Developer_Flavor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register Tutorial post type.
 */
function qwe_register_post_types() {
    $labels = array(
        'name'                  => _x( 'Tutorials', 'Post type general name', 'qwe-developer-flavor' ),
        'singular_name'         => _x( 'Tutorial', 'Post type singular name', 'qwe-developer-flavor' ),
        'menu_name'             => _x( 'Tutorials', 'Admin Menu text', 'qwe-developer-flavor' ),
        'add_new'               => __( 'Add New', 'qwe-developer-flavor' ),
        'add_new_item'          => __( 'Add New Tutorial', 'qwe-developer-flavor' ),
        'new_item'              => __( 'New Tutorial', 'qwe-developer-flavor' ),
        'edit_item'             => __( 'Edit Tutorial', 'qwe-developer-flavor' ),
        'view_item'             => __( 'View Tutorial', 'qwe-developer-flavor' ),
        'all_items'             => __( 'All Tutorials', 'qwe-developer-flavor' ),
        'search_items'          => __( 'Search Tutorials', 'qwe-developer-flavor' ),
        'not_found'             => __( 'No tutorials found.', 'qwe-developer-flavor' ),
        'not_found_in_trash'    => __( 'No tutorials found in Trash.', 'qwe-developer-flavor' ),
        'archives'              => __( 'Tutorial Archives', 'qwe-developer-flavor' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'tutorial', 'with_front' => false ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-welcome-learn-more',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
    );

    register_post_type( 'tutorial', $args );
}
add_action( 'init', 'qwe_register_post_types' );

/**
 * Register AI Tools post type.
 */
function qwe_register_ai_tools_post_type() {
    $labels = array(
        'name'                  => _x( 'AI Tools', 'Post type general name', 'qwe-developer-flavor' ),
        'singular_name'         => _x( 'AI Tool', 'Post type singular name', 'qwe-developer-flavor' ),
        'menu_name'             => _x( 'AI Tools', 'Admin Menu text', 'qwe-developer-flavor' ),
        'add_new'               => __( 'Add New', 'qwe-developer-flavor' ),
        'add_new_item'          => __( 'Add New AI Tool', 'qwe-developer-flavor' ),
        'new_item'              => __( 'New AI Tool', 'qwe-developer-flavor' ),
        'edit_item'             => __( 'Edit AI Tool', 'qwe-developer-flavor' ),
        'view_item'             => __( 'View AI Tool', 'qwe-developer-flavor' ),
        'all_items'             => __( 'All AI Tools', 'qwe-developer-flavor' ),
        'search_items'          => __( 'Search AI Tools', 'qwe-developer-flavor' ),
        'not_found'             => __( 'No AI tools found.', 'qwe-developer-flavor' ),
        'not_found_in_trash'    => __( 'No AI tools found in Trash.', 'qwe-developer-flavor' ),
        'archives'              => __( 'AI Tool Archives', 'qwe-developer-flavor' ),
    );

    register_post_type( 'ai-tools', array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'ai-tools', 'with_front' => false ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 6,
        'menu_icon'          => 'dashicons-admin-tools',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
    ) );
}
add_action( 'init', 'qwe_register_ai_tools_post_type' );

/**
 * Register AI Tool Category taxonomy.
 */
function qwe_register_ai_tool_taxonomies() {
    register_taxonomy( 'ai_tool_category', array( 'ai-tools' ), array(
        'hierarchical'      => true,
        'labels'            => array(
            'name'              => _x( 'AI Tool Categories', 'taxonomy general name', 'qwe-developer-flavor' ),
            'singular_name'     => _x( 'AI Tool Category', 'taxonomy singular name', 'qwe-developer-flavor' ),
            'search_items'      => __( 'Search Categories', 'qwe-developer-flavor' ),
            'all_items'         => __( 'All Categories', 'qwe-developer-flavor' ),
            'edit_item'         => __( 'Edit Category', 'qwe-developer-flavor' ),
            'add_new_item'      => __( 'Add New Category', 'qwe-developer-flavor' ),
            'menu_name'         => __( 'Categories', 'qwe-developer-flavor' ),
        ),
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'ai-tool-category', 'with_front' => false ),
    ) );
}
add_action( 'init', 'qwe_register_ai_tool_taxonomies' );

/**
 * Register Tutorial Category taxonomy.
 */
function qwe_register_taxonomies() {
    $labels = array(
        'name'              => _x( 'Tutorial Categories', 'taxonomy general name', 'qwe-developer-flavor' ),
        'singular_name'     => _x( 'Tutorial Category', 'taxonomy singular name', 'qwe-developer-flavor' ),
        'search_items'      => __( 'Search Categories', 'qwe-developer-flavor' ),
        'all_items'         => __( 'All Categories', 'qwe-developer-flavor' ),
        'parent_item'       => __( 'Parent Category', 'qwe-developer-flavor' ),
        'parent_item_colon' => __( 'Parent Category:', 'qwe-developer-flavor' ),
        'edit_item'         => __( 'Edit Category', 'qwe-developer-flavor' ),
        'update_item'       => __( 'Update Category', 'qwe-developer-flavor' ),
        'add_new_item'      => __( 'Add New Category', 'qwe-developer-flavor' ),
        'new_item_name'     => __( 'New Category Name', 'qwe-developer-flavor' ),
        'menu_name'         => __( 'Categories', 'qwe-developer-flavor' ),
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'tutorial-category', 'with_front' => false ),
    );

    register_taxonomy( 'tutorial_category', array( 'tutorial' ), $args );
}
add_action( 'init', 'qwe_register_taxonomies' );

/**
 * Register tutorial meta box for difficulty and featured status.
 */
function qwe_add_tutorial_meta_boxes() {
    add_meta_box(
        'qwe_tutorial_options',
        __( 'Tutorial Options', 'qwe-developer-flavor' ),
        'qwe_tutorial_options_callback',
        'tutorial',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'qwe_add_tutorial_meta_boxes' );

function qwe_add_ai_tool_meta_boxes() {
    add_meta_box( 'qwe_tutorial_options', __( 'AI Tool Options', 'qwe-developer-flavor' ), 'qwe_tutorial_options_callback', 'ai-tools', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'qwe_add_ai_tool_meta_boxes' );
add_action( 'save_post_ai-tools', 'qwe_save_tutorial_meta' );

/**
 * Tutorial options meta box callback.
 */
function qwe_tutorial_options_callback( $post ) {
    wp_nonce_field( 'qwe_tutorial_options', 'qwe_tutorial_options_nonce' );

    $difficulty = get_post_meta( $post->ID, '_qwe_difficulty', true );
    $featured   = get_post_meta( $post->ID, '_qwe_featured', true );
    ?>
    <p>
        <label for="qwe_difficulty"><strong><?php esc_html_e( 'Difficulty Level', 'qwe-developer-flavor' ); ?></strong></label><br>
        <select id="qwe_difficulty" name="qwe_difficulty" style="width:100%;margin-top:4px;">
            <option value="beginner" <?php selected( $difficulty, 'beginner' ); ?>><?php esc_html_e( 'Beginner', 'qwe-developer-flavor' ); ?></option>
            <option value="intermediate" <?php selected( $difficulty, 'intermediate' ); ?>><?php esc_html_e( 'Intermediate', 'qwe-developer-flavor' ); ?></option>
            <option value="advanced" <?php selected( $difficulty, 'advanced' ); ?>><?php esc_html_e( 'Advanced', 'qwe-developer-flavor' ); ?></option>
        </select>
    </p>
    <p>
        <label>
            <input type="checkbox" name="qwe_featured" value="1" <?php checked( $featured, '1' ); ?>>
            <?php esc_html_e( 'Featured Tutorial (show on homepage)', 'qwe-developer-flavor' ); ?>
        </label>
    </p>
    <?php
}

/**
 * Save tutorial meta box data.
 */
function qwe_save_tutorial_meta( $post_id ) {
    if ( ! isset( $_POST['qwe_tutorial_options_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['qwe_tutorial_options_nonce'], 'qwe_tutorial_options' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['qwe_difficulty'] ) ) {
        $allowed = array( 'beginner', 'intermediate', 'advanced' );
        $difficulty = sanitize_text_field( $_POST['qwe_difficulty'] );
        if ( in_array( $difficulty, $allowed, true ) ) {
            update_post_meta( $post_id, '_qwe_difficulty', $difficulty );
        }
    }

    $featured = isset( $_POST['qwe_featured'] ) ? '1' : '0';
    update_post_meta( $post_id, '_qwe_featured', $featured );
}
add_action( 'save_post_tutorial', 'qwe_save_tutorial_meta' );

/**
 * Register Friend Link post type.
 */
function qwe_register_friend_link_cpt() {
    $labels = array(
        'name'               => _x( 'Friend Links', 'Post type general name', 'qwe-developer-flavor' ),
        'singular_name'      => _x( 'Friend Link', 'Post type singular name', 'qwe-developer-flavor' ),
        'menu_name'          => _x( 'Friend Links', 'Admin Menu text', 'qwe-developer-flavor' ),
        'add_new'            => __( 'Add New', 'qwe-developer-flavor' ),
        'add_new_item'       => __( 'Add New Friend Link', 'qwe-developer-flavor' ),
        'new_item'           => __( 'New Friend Link', 'qwe-developer-flavor' ),
        'edit_item'          => __( 'Edit Friend Link', 'qwe-developer-flavor' ),
        'view_item'          => __( 'View Friend Link', 'qwe-developer-flavor' ),
        'all_items'          => __( 'All Friend Links', 'qwe-developer-flavor' ),
        'search_items'       => __( 'Search Friend Links', 'qwe-developer-flavor' ),
        'not_found'          => __( 'No friend links found.', 'qwe-developer-flavor' ),
        'not_found_in_trash' => __( 'No friend links found in Trash.', 'qwe-developer-flavor' ),
    );

    register_post_type( 'friend_link', array(
        'labels'             => $labels,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 25,
        'menu_icon'          => 'dashicons-admin-links',
        'supports'           => array( 'title' ),
    ) );
}
add_action( 'init', 'qwe_register_friend_link_cpt' );

/**
 * Register friend link meta box for URL and sort order.
 */
function qwe_add_friend_link_meta_boxes() {
    add_meta_box(
        'qwe_friend_link_options',
        __( 'Link Settings', 'qwe-developer-flavor' ),
        'qwe_friend_link_options_callback',
        'friend_link',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'qwe_add_friend_link_meta_boxes' );

/**
 * Friend link meta box callback.
 */
function qwe_friend_link_options_callback( $post ) {
    wp_nonce_field( 'qwe_friend_link_options', 'qwe_friend_link_nonce' );

    $url   = get_post_meta( $post->ID, '_qwe_friend_url', true );
    $order = get_post_meta( $post->ID, '_qwe_friend_order', true );
    ?>
    <table class="form-table">
        <tr>
            <th><label for="qwe_friend_url"><?php esc_html_e( 'Website URL', 'qwe-developer-flavor' ); ?></label></th>
            <td><input type="url" id="qwe_friend_url" name="qwe_friend_url" value="<?php echo esc_url( $url ); ?>" class="large-text" placeholder="https://example.com"></td>
        </tr>
        <tr>
            <th><label for="qwe_friend_order"><?php esc_html_e( 'Sort Order', 'qwe-developer-flavor' ); ?></label></th>
            <td><input type="number" id="qwe_friend_order" name="qwe_friend_order" value="<?php echo esc_attr( $order ); ?>" class="small-text" placeholder="0">
            <p class="description"><?php esc_html_e( 'Smaller number = higher priority.', 'qwe-developer-flavor' ); ?></p></td>
        </tr>
    </table>
    <?php
}

/**
 * Save friend link meta box data.
 */
function qwe_save_friend_link_meta( $post_id ) {
    if ( ! isset( $_POST['qwe_friend_link_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['qwe_friend_link_nonce'], 'qwe_friend_link_options' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['qwe_friend_url'] ) ) {
        update_post_meta( $post_id, '_qwe_friend_url', esc_url_raw( $_POST['qwe_friend_url'] ) );
    }
    if ( isset( $_POST['qwe_friend_order'] ) ) {
        update_post_meta( $post_id, '_qwe_friend_order', intval( $_POST['qwe_friend_order'] ) );
    }
}
add_action( 'save_post_friend_link', 'qwe_save_friend_link_meta' );

/**
 * Add custom columns to friend link admin list.
 */
function qwe_friend_link_columns( $columns ) {
    $new = array();
    foreach ( $columns as $key => $value ) {
        $new[ $key ] = $value;
        if ( 'title' === $key ) {
            $new['friend_url']   = __( 'URL', 'qwe-developer-flavor' );
            $new['friend_order'] = __( 'Order', 'qwe-developer-flavor' );
        }
    }
    unset( $new['date'] );
    return $new;
}
add_filter( 'manage_friend_link_posts_columns', 'qwe_friend_link_columns' );

/**
 * Populate custom columns for friend link admin list.
 */
function qwe_friend_link_column_content( $column, $post_id ) {
    if ( 'friend_url' === $column ) {
        $url = get_post_meta( $post_id, '_qwe_friend_url', true );
        echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>' : '—';
    } elseif ( 'friend_order' === $column ) {
        echo esc_html( get_post_meta( $post_id, '_qwe_friend_order', true ) ?: '0' );
    }
}
add_action( 'manage_friend_link_posts_custom_column', 'qwe_friend_link_column_content', 10, 2 );

/**
 * Flush rewrite rules on theme activation.
 */
function qwe_rewrite_flush() {
    qwe_register_post_types();
    qwe_register_taxonomies();
    qwe_register_ai_tools_post_type();
    qwe_register_ai_tool_taxonomies();
    flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'qwe_rewrite_flush' );
