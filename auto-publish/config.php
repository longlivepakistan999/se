<?php
/**
 * Auto-Publish Configuration
 *
 * @package QWE_Auto_Publish
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Allow CLI execution by bootstrapping WordPress.
    $wp_load = dirname( __FILE__ ) . '/../../../../wp-load.php';
    if ( file_exists( $wp_load ) ) {
        require_once $wp_load;
    } else {
        die( 'Cannot find wp-load.php' );
    }
}

// ============================================================
// AI API Settings
// ============================================================

// Supported: 'claude' or 'openai'
define( 'QWE_AI_PROVIDER', 'claude' );

// Claude API
define( 'QWE_CLAUDE_API_KEY', '' ); // sk-ant-... (set your Claude API key here)	
define( 'QWE_CLAUDE_MODEL', 'claude-sonnet-4-5-20250929' );

// OpenAI API
define( 'QWE_OPENAI_API_KEY', '' );  // sk-...
define( 'QWE_OPENAI_MODEL', 'gpt-5' );

// ============================================================
// Web Search Settings (Claude searches the web during generation)
// ============================================================

// Enable web search during article generation (Pass 1 only).
// Claude will search the web for the latest facts before writing.
// Cost: $10 per 1,000 searches + standard token costs.
define( 'QWE_WEB_SEARCH_ENABLED', true );

// Maximum number of web searches per article generation.
// Budget: ~2 for SERP analysis, ~3 for facts, ~3 for edge cases/community.
define( 'QWE_WEB_SEARCH_MAX_USES', 8 );

// ============================================================
// Publishing Settings
// ============================================================

// Articles per run (each cron execution).
define( 'QWE_ARTICLES_PER_RUN', 3 );

// Post status: 'publish' for immediate, 'draft' for manual review.
define( 'QWE_POST_STATUS', 'publish' );

// WordPress author ID for published articles.
define( 'QWE_AUTHOR_ID', 1 );

// Author display name (shown on articles and in structured data).
define( 'QWE_AUTHOR_NAME', 'Jack Tom' );

// ============================================================
// Trending Settings
// ============================================================

// Enable or disable trending/hot topic articles.
define( 'QWE_TRENDING_ENABLED', true );

// Ratio: percentage of articles from trending (rest from long-tail).
// 35 means 35% trending, 65% long-tail.
define( 'QWE_TRENDING_RATIO', 35 );

// Reddit AI subreddits to monitor (all AI-specific, no filtering needed).
define( 'QWE_REDDIT_SUBREDDITS', serialize( array(
    'ChatGPT',
    'artificial',
    'midjourney',
    'StableDiffusion',
    'LocalLLaMA',
    'ClaudeAI',
    'singularity',
) ) );

// Max trending topics to fetch per subreddit per run.
define( 'QWE_TRENDING_PER_SUB', 5 );

// Trending sources (3 total):
//   1. Reddit     — AI-specific subreddits above (no filtering needed)
//   2. Hacker News — top stories filtered by AI keyword relevance
//   3. RSS Feeds  — AI news from TechCrunch AI, The Verge AI, VentureBeat AI,
//                    Google AI Blog, OpenAI Blog, MIT Tech Review (filtered)

// ============================================================
// Database
// ============================================================

define( 'QWE_DB_PATH', dirname( __FILE__ ) . '/data/auto_publish.db' );

// ============================================================
// Security key for web-based cron trigger.
// Usage: /auto-publish/cron.php?key=YOUR_SECRET_KEY
// ============================================================

define( 'QWE_CRON_SECRET', 'qwe2024abc' );

// ============================================================
// Category mapping: tutorial_category slug => display name
// Must match your WordPress tutorial_category taxonomy slugs.
// ============================================================

define( 'QWE_CATEGORIES', serialize( array(
    'chatgpt-llms'      => 'ChatGPT & LLMs',
    'ai-art-design'     => 'AI Art & Design',
    'ai-coding'         => 'AI Coding',
    'ai-data-analysis'  => 'AI Data Analysis',
    'ai-writing'        => 'AI Writing',
    'ai-video-audio'    => 'AI Video & Audio',
    'ai-business'       => 'AI for Business',
    'ai-tools'          => 'AI Tools',
) ) );

// ============================================================
// Content language
// ============================================================

define( 'QWE_CONTENT_LANGUAGE', 'English' );

// ============================================================
// AI Tools post type config.
// The ai-tools post type is already registered in the theme.
// These constants tell the publisher which slugs to use.
// ============================================================

define( 'QWE_TOOL_POST_TYPE', 'ai-tools' );
define( 'QWE_TOOL_TAXONOMY', 'ai_tool_category' );
