<?php
/**
 * Cron Entry Point
 *
 * Main script executed by server cron.
 *
 * Usage:
 *   CLI:  php /path/to/auto-publish/cron.php
 *   Web:  https://site.com/wp-content/themes/theme/auto-publish/cron.php?key=YOUR_SECRET
 *   Stats: php /path/to/auto-publish/cron.php --stats
 *   Fetch: php /path/to/auto-publish/cron.php --fetch-only
 *
 * Cron example (every 4 hours):
 *   0 *\/4 * * * php /www/wwwroot/www.qwe.edu.pl/wp-content/themes/YOUR_THEME/auto-publish/cron.php >> /dev/null 2>&1
 *
 * @package QWE_Auto_Publish
 */

// Load dependencies first (config.php defines constants like QWE_CRON_SECRET).
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/trending.php';
require_once __DIR__ . '/generator.php';
require_once __DIR__ . '/publisher.php';

// Prevent direct web access without key.
$is_cli = ( 'cli' === php_sapi_name() );

if ( ! $is_cli ) {
    if ( ! isset( $_GET['key'] ) || ! hash_equals( QWE_CRON_SECRET, $_GET['key'] ) ) {
        http_response_code( 403 );
        die( 'Forbidden' );
    }
}

// Ensure tables exist.
QWE_DB::init_tables();

// ============================================================
// Handle CLI flags
// ============================================================

if ( $is_cli ) {
    $args = array_slice( $argv, 1 );

    if ( in_array( '--stats', $args, true ) ) {
        show_stats();
        exit( 0 );
    }

    if ( in_array( '--fetch-only', $args, true ) ) {
        echo "Fetching trending topics...\n";
        $added = QWE_Trending::fetch_all();
        echo "Done. {$added} new trending topics added.\n";
        exit( 0 );
    }

    if ( in_array( '--help', $args, true ) ) {
        echo "QWE Auto-Publish\n\n";
        echo "Usage:\n";
        echo "  php cron.php              Run full cycle (fetch + generate + publish)\n";
        echo "  php cron.php --stats      Show statistics\n";
        echo "  php cron.php --fetch-only Fetch trending topics only\n";
        echo "  php cron.php --help       Show this help\n";
        exit( 0 );
    }
}

// ============================================================
// Main execution
// ============================================================

log_msg( '=== Auto-Publish Run Started ===' );

// Step 1: Fetch trending topics (if enabled).
if ( QWE_TRENDING_ENABLED ) {
    log_msg( 'Fetching trending topics...' );
    $trending_added = QWE_Trending::fetch_all();
    log_msg( "Trending: {$trending_added} new topics fetched" );
}

// Step 2: Determine how many articles to generate.
$total_articles = QWE_ARTICLES_PER_RUN;
$trending_count = 0;
$longtail_count = $total_articles;

if ( QWE_TRENDING_ENABLED ) {
    $trending_count = max( 1, round( $total_articles * QWE_TRENDING_RATIO / 100 ) );
    $longtail_count = $total_articles - $trending_count;
}

log_msg( "Plan: {$longtail_count} longtail + {$trending_count} trending = {$total_articles} articles" );

$published = 0;

// Step 3: Generate and publish long-tail articles.
for ( $i = 0; $i < $longtail_count; $i++ ) {
    $result = generate_longtail_article();
    if ( $result ) {
        $published++;
    }
    // Sleep between articles to avoid API rate limits.
    if ( $i < $longtail_count - 1 ) {
        sleep( 5 );
    }
}

// Step 4: Generate and publish trending articles.
for ( $i = 0; $i < $trending_count; $i++ ) {
    $result = generate_trending_article();
    if ( $result ) {
        $published++;
    }
    if ( $i < $trending_count - 1 ) {
        sleep( 5 );
    }
}

log_msg( "=== Run Complete: {$published}/{$total_articles} articles published ===" );

if ( ! $is_cli ) {
    header( 'Content-Type: application/json' );
    echo json_encode( array(
        'success'   => true,
        'published' => $published,
        'planned'   => $total_articles,
    ) );
}

// ============================================================
// Functions
// ============================================================

/**
 * Generate and publish one long-tail keyword article.
 */
function generate_longtail_article() {
    // Pick a random pending keyword (balanced across categories).
    $keyword_data = QWE_DB::get_next_keyword();

    if ( ! $keyword_data ) {
        log_msg( 'No pending long-tail keywords available' );
        return false;
    }

    // Check if this keyword was already published (prevents cross-source duplicates).
    if ( QWE_DB::keyword_already_used( $keyword_data['keyword'] ) ) {
        log_msg( "Keyword already published: \"{$keyword_data['keyword']}\", skipping" );
        QWE_DB::mark_keyword_used( $keyword_data['id'], 0 );
        return false;
    }

    log_msg( "Generating longtail: \"{$keyword_data['keyword']}\" [{$keyword_data['category']}]" );

    $article = QWE_Generator::generate(
        $keyword_data['keyword'],
        'longtail',
        $keyword_data['category'],
        $keyword_data['difficulty']
    );

    if ( ! $article ) {
        log_msg( 'Generation failed, skipping' );
        return false;
    }

    $post_id = QWE_Publisher::publish( $article );

    if ( ! $post_id ) {
        log_msg( 'Publishing failed, skipping' );
        return false;
    }

    // Mark keyword as used.
    QWE_DB::mark_keyword_used( $keyword_data['id'], $post_id );

    // Log article.
    QWE_DB::log_article(
        $post_id,
        $article['title'],
        $keyword_data['keyword'],
        'longtail',
        $article['category'],
        $article['difficulty'],
        $keyword_data['id']
    );

    log_msg( "Published longtail [{$post_id}]: {$article['title']}" );
    return true;
}

/**
 * Generate and publish one trending topic article.
 */
function generate_trending_article() {
    $trending_data = QWE_DB::get_next_trending();

    if ( ! $trending_data ) {
        log_msg( 'No pending trending topics, falling back to longtail' );
        return generate_longtail_article();
    }

    // Check if this topic was already published (prevents duplicate content).
    if ( QWE_DB::keyword_already_used( $trending_data['title'] ) ) {
        log_msg( "Trending topic already published: \"{$trending_data['title']}\", skipping" );
        return false;
    }

    $source_label = 'r/' . $trending_data['subreddit'];
    if ( 'hackernews' === $trending_data['source'] ) {
        $source_label = 'Hacker News';
    } elseif ( 'rss' === $trending_data['source'] ) {
        $source_label = $trending_data['subreddit'];
    }
    log_msg( "Generating trending: \"{$trending_data['title']}\" [{$source_label}]" );

    // Use the trending title as the keyword.
    $article = QWE_Generator::generate(
        $trending_data['title'],
        'trending',
        $trending_data['category'],
        'beginner'
    );

    if ( ! $article ) {
        log_msg( 'Generation failed, skipping trending topic' );
        return false;
    }

    $post_id = QWE_Publisher::publish( $article );

    if ( ! $post_id ) {
        log_msg( 'Publishing failed, skipping' );
        return false;
    }

    // Mark trending as used.
    QWE_DB::mark_trending_used( $trending_data['id'], $post_id, $article['title'] );

    // Log article.
    QWE_DB::log_article(
        $post_id,
        $article['title'],
        $trending_data['title'],
        'trending',
        $article['category'],
        $article['difficulty'],
        $trending_data['id']
    );

    log_msg( "Published trending [{$post_id}]: {$article['title']}" );
    return true;
}

/**
 * Show statistics.
 */
function show_stats() {
    $stats = QWE_DB::get_stats();

    echo "\n=== QWE Auto-Publish Statistics ===\n\n";
    echo "Total articles published:   {$stats['total_articles']}\n";
    echo "  Long-tail keywords:       {$stats['longtail_articles']}\n";
    echo "  Trending topics:          {$stats['trending_articles']}\n";
    echo "  Published today:          {$stats['today_articles']}\n";
    echo "\n";
    echo "Pending long-tail keywords: {$stats['pending_keywords']}\n";
    echo "Pending trending topics:    {$stats['pending_trending']}\n";
    echo "\n";

    if ( ! empty( $stats['by_category'] ) ) {
        echo "Articles by category:\n";
        foreach ( $stats['by_category'] as $cat => $count ) {
            echo "  {$cat}: {$count}\n";
        }
    }

    echo "\nTrending enabled: " . ( QWE_TRENDING_ENABLED ? 'YES' : 'NO' ) . "\n";
    echo "Trending ratio: " . QWE_TRENDING_RATIO . "%\n";
    echo "Articles per run: " . QWE_ARTICLES_PER_RUN . "\n";
    echo "Post status: " . QWE_POST_STATUS . "\n";
    echo "\n";
}

/**
 * Log to console (CLI) and file.
 */
function log_msg( $message ) {
    global $is_cli;
    $time = date( 'Y-m-d H:i:s' );
    $line = "[{$time}] {$message}";

    if ( $is_cli ) {
        echo $line . "\n";
    }

    file_put_contents(
        __DIR__ . '/data/auto_publish.log',
        $line . "\n",
        FILE_APPEND
    );
}
