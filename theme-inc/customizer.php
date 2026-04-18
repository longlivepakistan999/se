<?php
/**
 * Theme Customizer settings.
 *
 * @package QWE_Developer_Flavor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register customizer settings and controls.
 */
function qwe_customize_register( $wp_customize ) {

    // === Hero Section ===
    $wp_customize->add_section( 'qwe_hero_section', array(
        'title'    => __( 'Homepage Hero', 'qwe-developer-flavor' ),
        'priority' => 30,
    ) );

    $wp_customize->add_setting( 'qwe_hero_title', array(
        'default'           => __( 'Learn AI for Free — From Zero to Pro', 'qwe-developer-flavor' ),
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'qwe_hero_title', array(
        'label'   => __( 'Hero Title', 'qwe-developer-flavor' ),
        'section' => 'qwe_hero_section',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'qwe_hero_subtitle', array(
        'default'           => __( 'Master ChatGPT, AI art, AI coding, and more with our step-by-step tutorials. Completely free, no registration required.', 'qwe-developer-flavor' ),
        'sanitize_callback' => 'sanitize_textarea_field',
    ) );
    $wp_customize->add_control( 'qwe_hero_subtitle', array(
        'label'   => __( 'Hero Subtitle', 'qwe-developer-flavor' ),
        'section' => 'qwe_hero_section',
        'type'    => 'textarea',
    ) );

    $wp_customize->add_setting( 'qwe_hero_stats_tutorials', array(
        'default'           => '200+',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'qwe_hero_stats_tutorials', array(
        'label'   => __( 'Stats: Total Tutorials', 'qwe-developer-flavor' ),
        'section' => 'qwe_hero_section',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'qwe_hero_stats_categories', array(
        'default'           => '7',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'qwe_hero_stats_categories', array(
        'label'   => __( 'Stats: Categories Count', 'qwe-developer-flavor' ),
        'section' => 'qwe_hero_section',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'qwe_hero_stats_readers', array(
        'default'           => '50K+',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'qwe_hero_stats_readers', array(
        'label'   => __( 'Stats: Total Readers', 'qwe-developer-flavor' ),
        'section' => 'qwe_hero_section',
        'type'    => 'text',
    ) );

    // === CTA Section ===
    $wp_customize->add_section( 'qwe_cta_section', array(
        'title'    => __( 'Homepage CTA', 'qwe-developer-flavor' ),
        'priority' => 35,
    ) );

    $wp_customize->add_setting( 'qwe_cta_title', array(
        'default'           => __( 'Start Learning AI Today', 'qwe-developer-flavor' ),
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'qwe_cta_title', array(
        'label'   => __( 'CTA Title', 'qwe-developer-flavor' ),
        'section' => 'qwe_cta_section',
        'type'    => 'text',
    ) );

    $wp_customize->add_setting( 'qwe_cta_text', array(
        'default'           => __( 'All our tutorials are completely free. No registration required. Just pick a topic and start learning!', 'qwe-developer-flavor' ),
        'sanitize_callback' => 'sanitize_textarea_field',
    ) );
    $wp_customize->add_control( 'qwe_cta_text', array(
        'label'   => __( 'CTA Text', 'qwe-developer-flavor' ),
        'section' => 'qwe_cta_section',
        'type'    => 'textarea',
    ) );

    // === Footer Section ===
    $wp_customize->add_section( 'qwe_footer_section', array(
        'title'    => __( 'Footer Settings', 'qwe-developer-flavor' ),
        'priority' => 40,
    ) );

    $wp_customize->add_setting( 'qwe_footer_about', array(
        'default'           => __( 'QWE AI Academy - Your free resource for learning how to use AI tools effectively. From ChatGPT to Midjourney, master AI at your own pace.', 'qwe-developer-flavor' ),
        'sanitize_callback' => 'sanitize_textarea_field',
    ) );
    $wp_customize->add_control( 'qwe_footer_about', array(
        'label'   => __( 'Footer About Text', 'qwe-developer-flavor' ),
        'section' => 'qwe_footer_section',
        'type'    => 'textarea',
    ) );

    $wp_customize->add_setting( 'qwe_contact_email', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_email',
    ) );
    $wp_customize->add_control( 'qwe_contact_email', array(
        'label'   => __( 'Contact Email', 'qwe-developer-flavor' ),
        'section' => 'qwe_footer_section',
        'type'    => 'email',
    ) );

    // === Social Links ===
    $wp_customize->add_section( 'qwe_social_section', array(
        'title'    => __( 'Social Links', 'qwe-developer-flavor' ),
        'priority' => 45,
    ) );

    $socials = array(
        'twitter'  => 'Twitter / X',
        'github'   => 'GitHub',
        'youtube'  => 'YouTube',
        'facebook' => 'Facebook',
    );

    foreach ( $socials as $key => $label ) {
        $wp_customize->add_setting( "qwe_social_{$key}", array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ) );
        $wp_customize->add_control( "qwe_social_{$key}", array(
            'label'   => $label . ' URL',
            'section' => 'qwe_social_section',
            'type'    => 'url',
        ) );
    }
}
add_action( 'customize_register', 'qwe_customize_register' );
