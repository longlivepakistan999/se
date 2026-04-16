<?php
/**
 * Auto-Publish Web Admin Panel
 *
 * Access: /auto-publish/admin.php?key=YOUR_SECRET
 *
 * Features:
 *   - Dashboard stats overview
 *   - Recent articles list (longtail / trending marked)
 *   - Pending keywords count per category
 *   - Manual trigger: run, fetch trending, view log
 *   - Trending toggle
 *
 * @package QWE_Auto_Publish
 */

require_once __DIR__ . '/config.php';

// Auth check.
if ( ! isset( $_GET['key'] ) || ! hash_equals( QWE_CRON_SECRET, $_GET['key'] ) ) {
    http_response_code( 403 );
    die( 'Forbidden. Append ?key=YOUR_SECRET to the URL.' );
}

require_once __DIR__ . '/db.php';
QWE_DB::init_tables();

$secret = htmlspecialchars( $_GET['key'], ENT_QUOTES, 'UTF-8' );
$base_url = '?key=' . urlencode( $_GET['key'] );
$categories = unserialize( QWE_CATEGORIES );

// Determine current view/tab early (needed for redirect context).
$valid_views = array( 'dashboard', 'keywords', 'trending', 'generate', 'settings', 'log', 'search' );
$view = isset( $_GET['view'] ) && in_array( $_GET['view'], $valid_views, true ) ? $_GET['view'] : 'dashboard';

// Handle actions.
$message = '';
if ( isset( $_GET['action'] ) ) {
    $action = $_GET['action'];

    if ( 'run' === $action ) {
        // Run generation inline (avoids HTTP timeout issues).
        require_once __DIR__ . '/trending.php';
        require_once __DIR__ . '/generator.php';
        require_once __DIR__ . '/publisher.php';

        // Set longer execution time for article generation.
        @set_time_limit( 300 );

        // Step 1: Fetch trending.
        $trending_added = 0;
        if ( QWE_TRENDING_ENABLED ) {
            $trending_added = QWE_Trending::fetch_all();
        }

        // Step 2: Calculate ratio.
        $total_articles = QWE_ARTICLES_PER_RUN;
        $trending_count = 0;
        $longtail_count = $total_articles;
        if ( QWE_TRENDING_ENABLED ) {
            $trending_count = max( 1, round( $total_articles * QWE_TRENDING_RATIO / 100 ) );
            $longtail_count = $total_articles - $trending_count;
        }

        // Step 3: Generate articles.
        $published = 0;
        for ( $i = 0; $i < $longtail_count; $i++ ) {
            $kw = QWE_DB::get_next_keyword();
            if ( ! $kw ) break;
            // Skip if this keyword was already published (prevents cross-source duplicates).
            if ( QWE_DB::keyword_already_used( $kw['keyword'] ) ) {
                QWE_DB::mark_keyword_used( $kw['id'], 0 );
                continue;
            }
            $article = QWE_Generator::generate( $kw['keyword'], 'longtail', $kw['category'], $kw['difficulty'] );
            if ( $article ) {
                $post_id = QWE_Publisher::publish( $article );
                if ( $post_id ) {
                    QWE_DB::mark_keyword_used( $kw['id'], $post_id );
                    QWE_DB::log_article( $post_id, $article['title'], $kw['keyword'], 'longtail', $article['category'], $article['difficulty'], $kw['id'] );
                    $published++;
                }
            }
            if ( $i < $longtail_count - 1 ) sleep( 3 );
        }
        for ( $i = 0; $i < $trending_count; $i++ ) {
            $tr = QWE_DB::get_next_trending();
            if ( ! $tr ) {
                $kw = QWE_DB::get_next_keyword();
                if ( ! $kw ) break;
                if ( QWE_DB::keyword_already_used( $kw['keyword'] ) ) {
                    QWE_DB::mark_keyword_used( $kw['id'], 0 );
                    continue;
                }
                $article = QWE_Generator::generate( $kw['keyword'], 'longtail', $kw['category'], $kw['difficulty'] );
                if ( $article ) {
                    $post_id = QWE_Publisher::publish( $article );
                    if ( $post_id ) {
                        QWE_DB::mark_keyword_used( $kw['id'], $post_id );
                        QWE_DB::log_article( $post_id, $article['title'], $kw['keyword'], 'longtail', $article['category'], $article['difficulty'], $kw['id'] );
                        $published++;
                    }
                }
            } else {
                // Skip if this topic was already published (prevents duplicate content).
                if ( QWE_DB::keyword_already_used( $tr['title'] ) ) {
                    continue;
                }
                $article = QWE_Generator::generate( $tr['title'], 'trending', $tr['category'], 'beginner' );
                if ( $article ) {
                    $post_id = QWE_Publisher::publish( $article );
                    if ( $post_id ) {
                        QWE_DB::mark_trending_used( $tr['id'], $post_id, $article['title'] );
                        QWE_DB::log_article( $post_id, $article['title'], $tr['title'], 'trending', $article['category'], $article['difficulty'], $tr['id'] );
                        $published++;
                    }
                }
            }
            if ( $i < $trending_count - 1 ) sleep( 3 );
        }

        $message = "Run complete: {$published}/{$total_articles} articles published. Trending fetched: {$trending_added} topics.";
    }

    if ( 'fetch' === $action ) {
        require_once __DIR__ . '/trending.php';
        $added = QWE_Trending::fetch_all();
        $message = "Fetched trending topics: {$added} new topics added.";
    }

    if ( 'clear-log' === $action ) {
        $log_file = __DIR__ . '/data/auto_publish.log';
        if ( file_exists( $log_file ) ) {
            file_put_contents( $log_file, '' );
        }
        $message = 'Log cleared.';
    }

    // Add keyword(s).
    if ( 'add-keyword' === $action && isset( $_POST['keywords'] ) && isset( $_POST['kw_category'] ) && isset( $_POST['kw_difficulty'] ) ) {
        $raw = trim( $_POST['keywords'] );
        $cat = sanitize_slug( $_POST['kw_category'] );
        $diff = sanitize_slug( $_POST['kw_difficulty'] );
        $valid_cats = array_keys( $categories );
        $valid_diffs = array( 'beginner', 'intermediate', 'advanced' );

        if ( $raw && in_array( $cat, $valid_cats, true ) && in_array( $diff, $valid_diffs, true ) ) {
            $lines = array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $raw ) ) );
            $added_kw = 0;
            foreach ( $lines as $line ) {
                if ( strlen( $line ) >= 5 ) {
                    $result = QWE_DB::add_keyword( $line, $cat, $diff );
                    if ( $result ) {
                        $added_kw++;
                    }
                }
            }
            $message = "Added {$added_kw} keyword(s) to '{$cat}' ({$diff}).";
        } else {
            $message = 'Invalid input. Please check category, difficulty, and keywords.';
        }
    }

    // Delete keyword.
    if ( 'delete-keyword' === $action && isset( $_GET['kw_id'] ) ) {
        $kw_id = (int) $_GET['kw_id'];
        if ( QWE_DB::delete_keyword( $kw_id ) ) {
            $message = "Keyword #{$kw_id} deleted.";
        } else {
            $message = "Could not delete keyword #{$kw_id} (may already be used).";
        }
    }

    // Delete trending topic.
    if ( 'delete-trending' === $action && isset( $_GET['tr_id'] ) ) {
        $tr_id = (int) $_GET['tr_id'];
        if ( QWE_DB::delete_trending( $tr_id ) ) {
            $message = "Trending topic #{$tr_id} deleted.";
        } else {
            $message = "Could not delete trending #{$tr_id} (may already be used).";
        }
    }

    // Generate and publish single article from keyword (background).
    if ( 'generate-article' === $action && isset( $_POST['keyword'] ) ) {
        $task_file = __DIR__ . '/data/generate_task.json';
        $can_generate = true;

        // Check if a task is already running.
        if ( file_exists( $task_file ) ) {
            $existing = json_decode( file_get_contents( $task_file ), true );
            if ( $existing && in_array( $existing['status'], array( 'pending', 'running' ), true ) ) {
                // Check for stale tasks (running > 5 minutes).
                $start_time = strtotime( $existing['started_at'] ?: $existing['created_at'] );
                if ( time() - $start_time > 300 ) {
                    // Task timed out, allow new one.
                    unlink( $task_file );
                } else {
                    $message = 'A generation task is already running. Please wait for it to finish.';
                    $can_generate = false;
                }
            }
        }

        if ( $can_generate ) {
            $gen_keyword    = trim( $_POST['keyword'] );
            $gen_category   = isset( $_POST['gen_category'] ) ? sanitize_slug( $_POST['gen_category'] ) : '';
            $gen_difficulty  = isset( $_POST['gen_difficulty'] ) ? sanitize_slug( $_POST['gen_difficulty'] ) : 'beginner';
            $valid_diffs     = array( 'beginner', 'intermediate', 'advanced' );
            $valid_cats      = array_keys( $categories );

            if ( strlen( $gen_keyword ) < 3 ) {
                $message = 'Keyword must be at least 3 characters.';
            } elseif ( $gen_difficulty && ! in_array( $gen_difficulty, $valid_diffs, true ) ) {
                $message = 'Invalid difficulty level.';
            } elseif ( $gen_category && ! in_array( $gen_category, $valid_cats, true ) ) {
                $message = 'Invalid category.';
            } else {
                // Create task file.
                $task = array(
                    'keyword'      => $gen_keyword,
                    'category'     => $gen_category,
                    'difficulty'   => $gen_difficulty,
                    'status'       => 'pending',
                    'created_at'   => date( 'Y-m-d H:i:s' ),
                    'started_at'   => null,
                    'completed_at' => null,
                    'result'       => null,
                );
                file_put_contents( $task_file, json_encode( $task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

                // Flag: run generation after page output (via fastcgi_finish_request).
                $run_bg_generation = true;

                $message = 'Article generation started! The page will auto-refresh to show progress.';
            }
        }
    }

    // Clear completed/failed task.
    if ( 'clear-task' === $action ) {
        $task_file = __DIR__ . '/data/generate_task.json';
        if ( file_exists( $task_file ) ) {
            unlink( $task_file );
        }
    }

    // Save provider settings.
    if ( 'save-settings' === $action && isset( $_POST['provider'] ) ) {
        $new_provider   = ( 'openai' === $_POST['provider'] ) ? 'openai' : 'claude';
        $new_api_key    = isset( $_POST['openai_api_key'] ) ? trim( $_POST['openai_api_key'] ) : '';
        $new_model      = isset( $_POST['openai_model'] ) ? trim( $_POST['openai_model'] ) : 'gpt-4o';

        if ( 'openai' === $new_provider && empty( $new_api_key ) ) {
            $message = 'Please enter your OpenAI API Key before switching to ChatGPT.';
        } else {
            QWE_DB::save_provider_settings( $new_provider, $new_api_key, $new_model );
            $provider_label = ( 'openai' === $new_provider ) ? 'ChatGPT' : 'Claude';
            $message = "AI provider switched to {$provider_label} successfully.";
        }
    }
}

/**
 * Simple slug sanitizer (no WP dependency needed here).
 */
function sanitize_slug( $input ) {
    return preg_replace( '/[^a-z0-9\-]/', '', strtolower( trim( $input ) ) );
}

/**
 * Sanitize a value for safe use in a CSS class name.
 * Only allows lowercase letters, digits, and hyphens.
 */
function safe_css_class( $value ) {
    return preg_replace( '/[^a-z0-9\-]/', '', strtolower( $value ) );
}

/**
 * Render pagination links.
 *
 * @param int    $current   Current page number.
 * @param int    $total     Total pages.
 * @param int    $count     Total items.
 * @param string $param     URL parameter name for page number.
 * @param string $base_url  Base URL with key param.
 * @param array  $extra     Extra URL parameters to preserve.
 */
function render_pagination( $current, $total, $count, $param, $base_url, $extra = array() ) {
    if ( $total <= 1 ) {
        return;
    }

    $qs = '';
    foreach ( $extra as $k => $v ) {
        $qs .= '&' . htmlspecialchars( $k ) . '=' . htmlspecialchars( $v );
    }

    echo '<div class="pagination">';

    // Previous.
    if ( $current > 1 ) {
        echo '<a href="' . $base_url . $qs . '&' . $param . '=' . ( $current - 1 ) . '">&laquo;</a>';
    }

    // Page numbers (show max 7 pages with ellipsis).
    $start = max( 1, $current - 3 );
    $end   = min( $total, $current + 3 );

    if ( $start > 1 ) {
        echo '<a href="' . $base_url . $qs . '&' . $param . '=1">1</a>';
        if ( $start > 2 ) {
            echo '<span class="info-text">...</span>';
        }
    }

    for ( $p = $start; $p <= $end; $p++ ) {
        if ( $p === $current ) {
            echo '<span class="current">' . $p . '</span>';
        } else {
            echo '<a href="' . $base_url . $qs . '&' . $param . '=' . $p . '">' . $p . '</a>';
        }
    }

    if ( $end < $total ) {
        if ( $end < $total - 1 ) {
            echo '<span class="info-text">...</span>';
        }
        echo '<a href="' . $base_url . $qs . '&' . $param . '=' . $total . '">' . $total . '</a>';
    }

    // Next.
    if ( $current < $total ) {
        echo '<a href="' . $base_url . $qs . '&' . $param . '=' . ( $current + 1 ) . '">&raquo;</a>';
    }

    echo '<span class="info-text">(' . $count . ' total)</span>';
    echo '</div>';
}

// Get stats.
$stats = QWE_DB::get_stats();
$pending = QWE_DB::count_pending_keywords();

// Keyword management data (only load when needed).
$kw_list = array();
$kw_total = 0;
if ( 'keywords' === $view ) {
    $valid_kw_statuses = array( 'all', 'pending', 'used' );
    $kw_filter_status = isset( $_GET['kw_status'] ) && in_array( $_GET['kw_status'], $valid_kw_statuses, true ) ? $_GET['kw_status'] : 'pending';
    $valid_kw_cats = array_merge( array( 'all' ), array_keys( $categories ) );
    $kw_filter_cat = isset( $_GET['kw_cat'] ) && in_array( $_GET['kw_cat'], $valid_kw_cats, true ) ? $_GET['kw_cat'] : 'all';
    $kw_page          = max( 1, isset( $_GET['kw_page'] ) ? (int) $_GET['kw_page'] : 1 );
    $kw_per_page      = 50;
    $kw_offset        = ( $kw_page - 1 ) * $kw_per_page;

    $kw_list  = QWE_DB::get_keywords( $kw_filter_status, $kw_filter_cat, $kw_per_page, $kw_offset );
    $kw_total = QWE_DB::count_keywords( $kw_filter_status, $kw_filter_cat );
    $kw_pages = max( 1, ceil( $kw_total / $kw_per_page ) );
}

// Pagination settings.
$per_page = 20;
$pdo = QWE_DB::connect();

// Articles pagination (only on dashboard view).
$recent = array();
$art_page = 1;
$art_total = 0;
$art_pages = 1;
if ( 'dashboard' === $view ) {
    $art_page = max( 1, isset( $_GET['art_page'] ) ? (int) $_GET['art_page'] : 1 );
    $art_total = (int) $pdo->query( "SELECT COUNT(*) FROM articles" )->fetchColumn();
    $art_pages = max( 1, ceil( $art_total / $per_page ) );
    $art_offset = ( $art_page - 1 ) * $per_page;
    $stmt = $pdo->prepare( "SELECT * FROM articles ORDER BY created_at DESC LIMIT ? OFFSET ?" );
    $stmt->execute( array( $per_page, $art_offset ) );
    $recent = $stmt->fetchAll( PDO::FETCH_ASSOC );
}

// Trending pagination (only on trending view).
$pending_trending = array();
$tr_page = 1;
$tr_total = 0;
$tr_pages = 1;
if ( 'trending' === $view ) {
    $tr_page = max( 1, isset( $_GET['tr_page'] ) ? (int) $_GET['tr_page'] : 1 );
    $tr_total = (int) $pdo->query( "SELECT COUNT(*) FROM trending WHERE status = 'pending'" )->fetchColumn();
    $tr_pages = max( 1, ceil( $tr_total / $per_page ) );
    $tr_offset = ( $tr_page - 1 ) * $per_page;
    $stmt = $pdo->prepare( "SELECT * FROM trending WHERE status = 'pending' ORDER BY score DESC LIMIT ? OFFSET ?" );
    $stmt->execute( array( $per_page, $tr_offset ) );
    $pending_trending = $stmt->fetchAll( PDO::FETCH_ASSOC );
}

// Search data (search view).
$search_q = '';
$search_results = array( 'keywords' => array(), 'articles' => array(), 'trending' => array() );
$search_counts = array( 'keywords' => 0, 'articles' => 0, 'trending' => 0 );
if ( 'search' === $view && isset( $_GET['q'] ) && strlen( trim( $_GET['q'] ) ) >= 2 ) {
    $search_q = trim( $_GET['q'] );
    // Escape LIKE wildcards (% and _) in user input to prevent unintended matches.
    $escaped_q = str_replace( array( '%', '_' ), array( '\\%', '\\_' ), $search_q );
    $like = '%' . $escaped_q . '%';

    // Search keywords.
    $stmt = $pdo->prepare( "SELECT COUNT(*) FROM keywords WHERE keyword LIKE ? ESCAPE '\\'" );
    $stmt->execute( array( $like ) );
    $search_counts['keywords'] = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare( "SELECT * FROM keywords WHERE keyword LIKE ? ESCAPE '\\' ORDER BY status ASC, category ASC LIMIT 50" );
    $stmt->execute( array( $like ) );
    $search_results['keywords'] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    // Search articles.
    $stmt = $pdo->prepare( "SELECT COUNT(*) FROM articles WHERE title LIKE ? ESCAPE '\\' OR keyword LIKE ? ESCAPE '\\'" );
    $stmt->execute( array( $like, $like ) );
    $search_counts['articles'] = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare( "SELECT * FROM articles WHERE title LIKE ? ESCAPE '\\' OR keyword LIKE ? ESCAPE '\\' ORDER BY created_at DESC LIMIT 50" );
    $stmt->execute( array( $like, $like ) );
    $search_results['articles'] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    // Search trending.
    $stmt = $pdo->prepare( "SELECT COUNT(*) FROM trending WHERE title LIKE ? ESCAPE '\\'" );
    $stmt->execute( array( $like ) );
    $search_counts['trending'] = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare( "SELECT * FROM trending WHERE title LIKE ? ESCAPE '\\' ORDER BY status ASC, score DESC LIMIT 50" );
    $stmt->execute( array( $like ) );
    $search_results['trending'] = $stmt->fetchAll( PDO::FETCH_ASSOC );
}

// Get log tail (only on log view).
$log_content = '';
$log_file = __DIR__ . '/data/auto_publish.log';
if ( 'log' === $view && file_exists( $log_file ) ) {
    // Read last 50 lines efficiently without loading entire file into memory.
    $tail_lines = 50;
    $fp = fopen( $log_file, 'r' );
    if ( $fp ) {
        $buffer = '';
        $line_count = 0;
        // Seek from end of file in chunks.
        fseek( $fp, 0, SEEK_END );
        $pos = ftell( $fp );
        $chunk_size = 4096;
        while ( $pos > 0 && $line_count < $tail_lines + 1 ) {
            $read_size = min( $chunk_size, $pos );
            $pos -= $read_size;
            fseek( $fp, $pos );
            $chunk = fread( $fp, $read_size );
            $buffer = $chunk . $buffer;
            $line_count = substr_count( $buffer, "\n" );
        }
        fclose( $fp );
        $lines = explode( "\n", $buffer );
        // Take last 50 non-empty lines.
        $lines = array_slice( $lines, -( $tail_lines + 1 ) );
        $log_content = implode( "\n", $lines );
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QWE Auto-Publish Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; color: #1a1a2e; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #4F46E5, #06B6D4); color: white; padding: 20px 30px; }
        .header h1 { font-size: 22px; font-weight: 700; }
        .header p { opacity: 0.8; font-size: 13px; margin-top: 4px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .message { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .stat-card .number { font-size: 32px; font-weight: 800; color: #4F46E5; }
        .stat-card .label { font-size: 13px; color: #64748b; margin-top: 4px; }
        .actions { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: #4F46E5; color: white; }
        .btn-primary:hover { background: #4338CA; }
        .btn-secondary { background: white; color: #4F46E5; border: 1px solid #4F46E5; }
        .btn-secondary:hover { background: #EEF2FF; }
        .btn-danger { background: #EF4444; color: white; }
        .btn-danger:hover { background: #DC2626; }
        .btn-success { background: #10B981; color: white; }
        .btn-success:hover { background: #059669; }
        .section { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .section h2 { font-size: 16px; font-weight: 700; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 8px 12px; background: #f8fafc; font-weight: 600; color: #64748b; border-bottom: 1px solid #e5e7eb; }
        td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        tr:hover td { background: #f8fafc; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-longtail { background: #DBEAFE; color: #1D4ED8; }
        .badge-trending { background: #FEE2E2; color: #DC2626; }
        .badge-beginner { background: #D1FAE5; color: #065F46; }
        .badge-intermediate { background: #FEF3C7; color: #92400E; }
        .badge-advanced { background: #FCE7F3; color: #9D174D; }
        .badge-enabled { background: #D1FAE5; color: #065F46; }
        .badge-disabled { background: #F3F4F6; color: #6B7280; }
        .log { background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 12px; line-height: 1.8; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
        .config-table td:first-child { font-weight: 600; width: 200px; }
        .progress-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-top: 6px; }
        .progress-bar__fill { height: 100%; background: linear-gradient(90deg, #4F46E5, #06B6D4); border-radius: 3px; }
        .tabs { display: flex; gap: 0; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
        .tab { padding: 10px 20px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 14px; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab:hover { color: #4F46E5; }
        .tab.active { color: #4F46E5; border-bottom-color: #4F46E5; }
        .form-row { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; }
        .form-row label { font-weight: 600; font-size: 13px; color: #374151; min-width: 80px; padding-top: 8px; }
        .form-row select, .form-row input[type="text"] { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
        .form-row textarea { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; width: 100%; min-height: 80px; font-family: inherit; resize: vertical; }
        .btn-xs { padding: 4px 10px; font-size: 12px; border-radius: 4px; }
        .pagination { display: flex; gap: 6px; align-items: center; margin-top: 16px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 6px 12px; border-radius: 6px; font-size: 13px; text-decoration: none; }
        .pagination a { background: white; color: #4F46E5; border: 1px solid #d1d5db; }
        .pagination a:hover { background: #EEF2FF; }
        .pagination .current { background: #4F46E5; color: white; border: 1px solid #4F46E5; }
        .filter-bar { display: flex; gap: 10px; margin-bottom: 16px; align-items: center; flex-wrap: wrap; }
        .filter-bar select { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; }
        .filter-bar .btn { padding: 6px 14px; }
        .info-text { color: #64748b; font-size: 13px; }
        .search-bar { display: flex; gap: 8px; margin-left: auto; align-items: center; }
        .search-bar input[type="text"] { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; width: 200px; }
        .search-bar .btn { padding: 6px 14px; font-size: 13px; }
        .search-highlight { background: #FEF3C7; padding: 1px 2px; border-radius: 2px; }
        .result-group { margin-bottom: 8px; }
        .result-group h3 { font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .result-count { background: #EEF2FF; color: #4F46E5; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
        .badge-used { background: #FEE2E2; color: #DC2626; }
        .badge-pending { background: #D1FAE5; color: #065F46; }
        .badge-manual { background: #E0E7FF; color: #3730A3; }
        .generate-note { background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E40AF; padding: 10px 14px; border-radius: 8px; margin-top: 12px; font-size: 13px; }
        .generate-progress { text-align: center; padding: 40px 20px; }
        .spinner { width: 40px; height: 40px; border: 4px solid #e5e7eb; border-top-color: #4F46E5; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .message-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 14px 18px; border-radius: 8px; font-size: 14px; line-height: 1.6; }
        .message-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 14px 18px; border-radius: 8px; font-size: 14px; }
        @media (max-width: 768px) { .grid { grid-template-columns: repeat(2, 1fr); } .actions { flex-direction: column; } .form-row { flex-direction: column; } .form-row label { min-width: auto; } .search-bar { margin-left: 0; width: 100%; } .search-bar input[type="text"] { flex: 1; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>QWE Auto-Publish</h1>
        <p>AI Tutorial Auto-Generation Dashboard</p>
    </div>

    <div class="container">
        <?php if ( $message ) : ?>
            <div class="message"><?php echo htmlspecialchars( $message ); ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <a href="<?php echo $base_url; ?>" class="tab <?php echo 'dashboard' === $view ? 'active' : ''; ?>">Dashboard</a>
            <a href="<?php echo $base_url; ?>&view=keywords" class="tab <?php echo 'keywords' === $view ? 'active' : ''; ?>">Keywords (<?php echo $stats['pending_keywords']; ?>)</a>
            <a href="<?php echo $base_url; ?>&view=trending" class="tab <?php echo 'trending' === $view ? 'active' : ''; ?>">Trending</a>
            <a href="<?php echo $base_url; ?>&view=generate" class="tab <?php echo 'generate' === $view ? 'active' : ''; ?>">Generate</a>
            <a href="<?php echo $base_url; ?>&view=settings" class="tab <?php echo 'settings' === $view ? 'active' : ''; ?>">Settings</a>
            <a href="<?php echo $base_url; ?>&view=log" class="tab <?php echo 'log' === $view ? 'active' : ''; ?>">Log</a>
            <form class="search-bar" method="GET">
                <input type="hidden" name="key" value="<?php echo $secret; ?>">
                <input type="hidden" name="view" value="search">
                <input type="text" name="q" placeholder="Search keywords, articles, trending..." value="<?php echo htmlspecialchars( $search_q ); ?>">
                <button type="submit" class="btn btn-secondary">Search</button>
            </form>
        </div>

        <?php if ( 'dashboard' === $view ) : ?>
        <!-- ===================== DASHBOARD VIEW ===================== -->

        <!-- Stats -->
        <div class="grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_articles']; ?></div>
                <div class="label">Total Articles</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['today_articles']; ?></div>
                <div class="label">Published Today</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#1D4ED8"><?php echo $stats['longtail_articles']; ?></div>
                <div class="label">Long-tail Articles</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#DC2626"><?php echo $stats['trending_articles']; ?></div>
                <div class="label">Trending Articles</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#3730A3"><?php echo $stats['manual_articles']; ?></div>
                <div class="label">Manual Articles</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#10B981"><?php echo $stats['pending_keywords']; ?></div>
                <div class="label">Pending Keywords</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color:#F59E0B"><?php echo $stats['pending_trending']; ?></div>
                <div class="label">Pending Trending</div>
            </div>
        </div>

        <!-- Actions -->
        <div class="actions">
            <a href="<?php echo $base_url; ?>&action=run" class="btn btn-primary" onclick="return confirm('Run auto-publish now? This will generate and publish articles.')">Generate & Publish Now</a>
            <a href="<?php echo $base_url; ?>&action=fetch" class="btn btn-success">Fetch Trending Topics</a>
            <a href="<?php echo $base_url; ?>" class="btn btn-secondary">Refresh</a>
        </div>

        <!-- Config -->
        <div class="section">
            <h2>Configuration</h2>
            <table class="config-table">
                <tr><td>AI Provider</td><td><?php echo QWE_AI_PROVIDER; ?></td></tr>
                <tr><td>API Key</td><td><?php echo QWE_CLAUDE_API_KEY ? '****' . htmlspecialchars( substr( QWE_CLAUDE_API_KEY, -6 ) ) : '<span style="color:red">NOT SET</span>'; ?></td></tr>
                <tr><td>Articles Per Run</td><td><?php echo QWE_ARTICLES_PER_RUN; ?></td></tr>
                <tr><td>Post Status</td><td><?php echo QWE_POST_STATUS; ?></td></tr>
                <tr><td>Trending</td><td><span class="badge <?php echo QWE_TRENDING_ENABLED ? 'badge-enabled' : 'badge-disabled'; ?>"><?php echo QWE_TRENDING_ENABLED ? 'ENABLED' : 'DISABLED'; ?></span></td></tr>
                <tr><td>Trending Ratio</td><td><?php echo QWE_TRENDING_RATIO; ?>% trending / <?php echo 100 - QWE_TRENDING_RATIO; ?>% long-tail</td></tr>
                <tr><td>Trending Sources</td><td>Reddit (7 AI subs) + Hacker News (AI filtered) + RSS Feeds (6 AI blogs)</td></tr>
            </table>
        </div>

        <!-- Pending Keywords Per Category -->
        <div class="section">
            <h2>Pending Keywords by Category</h2>
            <table>
                <tr><th>Category</th><th>Pending / Total</th><th>Used %</th></tr>
                <?php
                // Get total keywords (pending + used) per category for accurate progress.
                $all_per_cat = $pdo->query(
                    "SELECT category, COUNT(*) as count FROM keywords GROUP BY category"
                )->fetchAll( PDO::FETCH_KEY_PAIR );
                foreach ( $categories as $slug => $name ) :
                    $count = isset( $pending[ $slug ] ) ? $pending[ $slug ] : 0;
                    $total_in_cat = isset( $all_per_cat[ $slug ] ) ? $all_per_cat[ $slug ] : 0;
                    $used = $total_in_cat - $count;
                    $pct = $total_in_cat > 0 ? max( 0, min( 100, round( $used / $total_in_cat * 100 ) ) ) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars( $name ); ?></td>
                    <td><?php echo $count; ?> / <?php echo $total_in_cat; ?></td>
                    <td style="width:200px">
                        <div class="progress-bar"><div class="progress-bar__fill" style="width:<?php echo $pct; ?>%"></div></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Recent Articles -->
        <div class="section">
            <h2>Published Articles (<?php echo $art_total; ?>)</h2>
            <?php if ( empty( $recent ) ) : ?>
                <p style="color:#64748b">No articles published yet.</p>
            <?php else : ?>
            <table>
                <tr><th>ID</th><th>Title</th><th>Type</th><th>Category</th><th>Difficulty</th><th>Date</th></tr>
                <?php foreach ( $recent as $article ) : ?>
                <tr>
                    <td><?php echo (int) $article['post_id']; ?></td>
                    <td><?php echo htmlspecialchars( mb_strimwidth( $article['title'], 0, 60, '...' ) ); ?></td>
                    <td><span class="badge badge-<?php echo safe_css_class( $article['keyword_type'] ); ?>"><?php echo htmlspecialchars( $article['keyword_type'] ); ?></span></td>
                    <td><?php echo htmlspecialchars( $article['category'] ); ?></td>
                    <td><span class="badge badge-<?php echo safe_css_class( $article['difficulty'] ); ?>"><?php echo htmlspecialchars( $article['difficulty'] ); ?></span></td>
                    <td><?php echo htmlspecialchars( $article['created_at'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php render_pagination( $art_page, $art_pages, $art_total, 'art_page', $base_url ); ?>
            <?php endif; ?>
        </div>

        <?php endif; // end dashboard view ?>


        <?php if ( 'keywords' === $view ) : ?>
        <!-- ===================== KEYWORDS VIEW ===================== -->

        <!-- Add Keywords (Batch) -->
        <div class="section">
            <h2>Add Keywords (Batch)</h2>
            <form method="POST" action="<?php echo $base_url; ?>&action=add-keyword&view=keywords">
                <div class="form-row">
                    <label>Category</label>
                    <select name="kw_category">
                        <?php foreach ( $categories as $slug => $name ) : ?>
                        <option value="<?php echo $slug; ?>"><?php echo htmlspecialchars( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Difficulty</label>
                    <select name="kw_difficulty">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Keywords</label>
                    <textarea name="keywords" placeholder="One keyword per line, e.g.:&#10;how to use ChatGPT for resume writing&#10;best AI tools for small business&#10;midjourney vs stable diffusion comparison"></textarea>
                </div>
                <div class="form-row">
                    <label></label>
                    <button type="submit" class="btn btn-primary">Add Keywords</button>
                    <span class="info-text" style="padding-top:8px">One keyword per line. Duplicates are automatically skipped.</span>
                </div>
            </form>
        </div>

        <!-- Keyword List -->
        <div class="section">
            <h2>All Keywords (<?php echo $kw_total; ?> total)</h2>

            <!-- Filters -->
            <form class="filter-bar" method="GET">
                <input type="hidden" name="key" value="<?php echo $secret; ?>">
                <input type="hidden" name="view" value="keywords">
                <select name="kw_status">
                    <option value="all" <?php echo 'all' === $kw_filter_status ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo 'pending' === $kw_filter_status ? 'selected' : ''; ?>>Pending</option>
                    <option value="used" <?php echo 'used' === $kw_filter_status ? 'selected' : ''; ?>>Used</option>
                </select>
                <select name="kw_cat">
                    <option value="all" <?php echo 'all' === $kw_filter_cat ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach ( $categories as $slug => $name ) : ?>
                    <option value="<?php echo $slug; ?>" <?php echo $slug === $kw_filter_cat ? 'selected' : ''; ?>><?php echo htmlspecialchars( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>

            <?php if ( empty( $kw_list ) ) : ?>
                <p class="info-text">No keywords found with current filters.</p>
            <?php else : ?>
            <table>
                <tr><th>ID</th><th>Keyword</th><th>Category</th><th>Difficulty</th><th>Status</th><th>Post ID</th><th>Action</th></tr>
                <?php foreach ( $kw_list as $kw ) : ?>
                <tr>
                    <td><?php echo (int) $kw['id']; ?></td>
                    <td><?php echo htmlspecialchars( $kw['keyword'] ); ?></td>
                    <td><?php echo htmlspecialchars( isset( $categories[ $kw['category'] ] ) ? $categories[ $kw['category'] ] : $kw['category'] ); ?></td>
                    <td><span class="badge badge-<?php echo safe_css_class( $kw['difficulty'] ); ?>"><?php echo htmlspecialchars( $kw['difficulty'] ); ?></span></td>
                    <td>
                        <?php if ( 'used' === $kw['status'] ) : ?>
                            <span class="badge badge-trending">used</span>
                        <?php else : ?>
                            <span class="badge badge-enabled">pending</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $kw['post_id'] ? (int) $kw['post_id'] : '-'; ?></td>
                    <td>
                        <?php if ( 'pending' === $kw['status'] ) : ?>
                        <a href="<?php echo $base_url; ?>&action=delete-keyword&kw_id=<?php echo (int) $kw['id']; ?>&view=keywords&kw_status=<?php echo $kw_filter_status; ?>&kw_cat=<?php echo $kw_filter_cat; ?>&kw_page=<?php echo $kw_page; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this keyword?')">Delete</a>
                        <?php else : ?>
                        <span class="info-text">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <?php render_pagination( $kw_page, $kw_pages, $kw_total, 'kw_page', $base_url, array( 'view' => 'keywords', 'kw_status' => $kw_filter_status, 'kw_cat' => $kw_filter_cat ) ); ?>
            <?php endif; ?>
        </div>

        <?php endif; // end keywords view ?>


        <?php if ( 'trending' === $view ) : ?>
        <!-- ===================== TRENDING VIEW ===================== -->

        <div class="actions">
            <a href="<?php echo $base_url; ?>&action=fetch&view=trending" class="btn btn-success">Fetch Trending Topics Now</a>
        </div>

        <div class="section">
            <h2>Pending Trending Topics (<?php echo $tr_total; ?>)</h2>
            <p class="info-text" style="margin-bottom:12px">Topics are auto-deleted after 7 days if unused. Used topics are kept permanently as records.</p>
            <?php if ( empty( $pending_trending ) ) : ?>
                <p class="info-text">No pending trending topics.</p>
            <?php else : ?>
            <table>
                <tr><th>ID</th><th>Title</th><th>Source</th><th>Score</th><th>Category</th><th>Fetched</th><th>Action</th></tr>
                <?php foreach ( $pending_trending as $t ) : ?>
                <tr>
                    <td><?php echo (int) $t['id']; ?></td>
                    <td><?php echo htmlspecialchars( mb_strimwidth( $t['title'], 0, 70, '...' ) ); ?></td>
                    <td><?php
                        if ( 'reddit' === $t['source'] ) {
                            echo 'r/' . htmlspecialchars( $t['subreddit'] );
                        } elseif ( 'hackernews' === $t['source'] ) {
                            echo 'Hacker News';
                        } else {
                            echo htmlspecialchars( $t['subreddit'] );
                        }
                    ?></td>
                    <td><?php echo (int) $t['score']; ?></td>
                    <td><?php echo htmlspecialchars( $t['category'] ); ?></td>
                    <td><?php echo htmlspecialchars( $t['fetched_at'] ); ?></td>
                    <td>
                        <a href="<?php echo $base_url; ?>&action=delete-trending&tr_id=<?php echo (int) $t['id']; ?>&view=trending&tr_page=<?php echo $tr_page; ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this trending topic?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php render_pagination( $tr_page, $tr_pages, $tr_total, 'tr_page', $base_url, array( 'view' => 'trending' ) ); ?>
            <?php endif; ?>
        </div>

        <?php endif; // end trending view ?>


        <?php if ( 'generate' === $view ) : ?>
        <!-- ===================== GENERATE VIEW ===================== -->

        <?php
        $task_file = __DIR__ . '/data/generate_task.json';
        $gen_task = null;
        if ( file_exists( $task_file ) ) {
            $gen_task = json_decode( file_get_contents( $task_file ), true );
            // Auto-expire stale tasks (running > 5 minutes).
            if ( $gen_task && in_array( $gen_task['status'], array( 'pending', 'running' ), true ) ) {
                $start_time = strtotime( $gen_task['started_at'] ?: $gen_task['created_at'] );
                if ( time() - $start_time > 300 ) {
                    $gen_task['status'] = 'failed';
                    $gen_task['completed_at'] = date( 'Y-m-d H:i:s' );
                    $gen_task['result'] = array( 'message' => 'Generation timed out (exceeded 5 minutes). Please try again.' );
                    file_put_contents( $task_file, json_encode( $gen_task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
                }
            }
        }
        ?>

        <?php if ( $gen_task && in_array( $gen_task['status'], array( 'pending', 'running' ), true ) ) : ?>
        <!-- Task is running -->
        <div class="section">
            <h2>Generating Article...</h2>
            <div class="generate-progress">
                <div class="spinner"></div>
                <p style="font-size:16px;font-weight:600;">Keyword: <?php echo htmlspecialchars( $gen_task['keyword'] ); ?></p>
                <?php $cur_ps = QWE_DB::get_provider_settings(); ?>
                <p class="info-text">Provider: <?php echo ( 'openai' === $cur_ps['provider'] ) ? 'ChatGPT' : 'Claude'; ?> | Started: <?php echo htmlspecialchars( $gen_task['started_at'] ?: $gen_task['created_at'] ); ?></p>
                <p class="info-text" style="margin-top:12px">Generation takes 60-90 seconds. This page auto-refreshes every 5 seconds.</p>
            </div>
        </div>
        <script>setTimeout(function(){ window.location.href = '<?php echo $base_url; ?>&view=generate'; }, 5000);</script>

        <?php elseif ( $gen_task && 'completed' === $gen_task['status'] ) : ?>
        <!-- Task completed -->
        <div class="section">
            <h2>Generation Complete</h2>
            <div class="message-success">
                <?php echo htmlspecialchars( $gen_task['result']['message'] ); ?>
                <?php if ( ! empty( $gen_task['result']['title'] ) ) : ?>
                <br>Title: <?php echo htmlspecialchars( $gen_task['result']['title'] ); ?>
                <?php endif; ?>
            </div>
            <div style="margin-top:16px">
                <a href="<?php echo $base_url; ?>&view=generate&action=clear-task" class="btn btn-primary">Generate Another</a>
                <a href="<?php echo $base_url; ?>" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>

        <?php elseif ( $gen_task && 'failed' === $gen_task['status'] ) : ?>
        <!-- Task failed -->
        <div class="section">
            <h2>Generation Failed</h2>
            <div class="message-error">
                <?php echo htmlspecialchars( $gen_task['result']['message'] ); ?>
            </div>
            <div style="margin-top:16px">
                <a href="<?php echo $base_url; ?>&view=generate&action=clear-task" class="btn btn-primary">Try Again</a>
                <a href="<?php echo $base_url; ?>&view=log" class="btn btn-secondary">View Log</a>
            </div>
        </div>

        <?php else : ?>
        <!-- No task - show form -->
        <div class="section">
            <h2>Generate & Publish Article</h2>
            <p class="info-text" style="margin-bottom:16px">Enter a keyword to generate and immediately publish an article. The keyword does not need to exist in the keywords database.</p>
            <form method="POST" action="<?php echo $base_url; ?>&action=generate-article&view=generate">
                <div class="form-row">
                    <label>Keyword</label>
                    <input type="text" name="keyword" placeholder="e.g., how to use ChatGPT for data analysis" required style="flex:1; min-width:300px;">
                </div>
                <div class="form-row">
                    <label>Category</label>
                    <select name="gen_category">
                        <option value="">Auto-detect</option>
                        <?php foreach ( $categories as $slug => $name ) : ?>
                        <option value="<?php echo $slug; ?>"><?php echo htmlspecialchars( $name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Difficulty</label>
                    <select name="gen_difficulty">
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                    </select>
                </div>
                <div class="form-row">
                    <label></label>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Generate and publish article now?')">Generate & Publish</button>
                </div>
            </form>
            <div class="generate-note">
                Generation takes 60-90 seconds (2-pass AI pipeline with web search). The article will be published automatically when ready.
            </div>
        </div>

        <?php endif; ?>

        <?php endif; // end generate view ?>


        <?php if ( 'settings' === $view ) : ?>
        <!-- ===================== SETTINGS VIEW ===================== -->

        <?php $ps = QWE_DB::get_provider_settings(); ?>

        <div class="section">
            <h2>AI Provider Settings</h2>
            <p class="info-text" style="margin-bottom:16px">Switch between Claude and ChatGPT. This affects all article generation (automatic cron and manual keyword).</p>
            <form method="POST" action="<?php echo $base_url; ?>&action=save-settings&view=settings">
                <div class="form-row">
                    <label>AI Provider</label>
                    <select name="provider" id="provider-select" onchange="toggleOpenAI()">
                        <option value="claude" <?php echo 'claude' === $ps['provider'] ? 'selected' : ''; ?>>Claude</option>
                        <option value="openai" <?php echo 'openai' === $ps['provider'] ? 'selected' : ''; ?>>ChatGPT (OpenAI)</option>
                    </select>
                </div>
                <div id="openai-fields" style="<?php echo 'openai' === $ps['provider'] ? '' : 'display:none;'; ?>">
                    <div class="form-row">
                        <label>API Key</label>
                        <input type="password" name="openai_api_key" value="<?php echo htmlspecialchars( $ps['openai_api_key'] ); ?>" placeholder="sk-..." style="flex:1; min-width:300px;">
                    </div>
                    <div class="form-row">
                        <label>Model</label>
                        <input type="text" name="openai_model" value="<?php echo htmlspecialchars( $ps['openai_model'] ); ?>" placeholder="gpt-4o" style="width:250px;">
                    </div>
                </div>
                <div class="form-row">
                    <label></label>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
            <script>
            function toggleOpenAI() {
                var sel = document.getElementById('provider-select');
                var fields = document.getElementById('openai-fields');
                fields.style.display = (sel.value === 'openai') ? '' : 'none';
            }
            </script>
        </div>

        <!-- Current Status -->
        <div class="section">
            <h2>Current Configuration</h2>
            <table class="config-table">
                <tr>
                    <td>Active Provider</td>
                    <td>
                        <?php if ( 'openai' === $ps['provider'] ) : ?>
                            <span class="badge" style="background:#10B981;color:white;">ChatGPT</span> <?php echo htmlspecialchars( $ps['openai_model'] ); ?>
                        <?php else : ?>
                            <span class="badge" style="background:#4F46E5;color:white;">Claude</span> <?php echo htmlspecialchars( QWE_CLAUDE_MODEL ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Claude API Key</td>
                    <td><?php echo QWE_CLAUDE_API_KEY ? '****' . htmlspecialchars( substr( QWE_CLAUDE_API_KEY, -6 ) ) : '<span style="color:red">NOT SET (config.php)</span>'; ?></td>
                </tr>
                <tr>
                    <td>OpenAI API Key</td>
                    <td><?php echo $ps['openai_api_key'] ? '****' . htmlspecialchars( substr( $ps['openai_api_key'], -6 ) ) : '<span style="color:#64748b">Not configured</span>'; ?></td>
                </tr>
                <tr>
                    <td>Web Search</td>
                    <td><?php echo ( 'openai' === $ps['provider'] ) ? '<span class="info-text">Not available (ChatGPT)</span>' : ( QWE_WEB_SEARCH_ENABLED ? '<span class="badge badge-enabled">ENABLED</span>' : '<span class="badge badge-disabled">DISABLED</span>' ); ?></td>
                </tr>
            </table>
        </div>

        <?php endif; // end settings view ?>


        <?php if ( 'log' === $view ) : ?>
        <!-- ===================== LOG VIEW ===================== -->

        <div class="actions">
            <a href="<?php echo $base_url; ?>&action=clear-log&view=log" class="btn btn-danger" onclick="return confirm('Clear the log file?')">Clear Log</a>
            <a href="<?php echo $base_url; ?>&view=log" class="btn btn-secondary">Refresh</a>
        </div>

        <div class="section">
            <h2>Recent Log (Last 50 lines)</h2>
            <div class="log"><?php echo $log_content ? htmlspecialchars( $log_content ) : 'No log entries yet.'; ?></div>
        </div>

        <?php endif; // end log view ?>


        <?php if ( 'search' === $view ) : ?>
        <!-- ===================== SEARCH VIEW ===================== -->

        <?php if ( ! $search_q ) : ?>
            <div class="section">
                <h2>Search</h2>
                <p class="info-text">Enter at least 2 characters to search across keywords, articles, and trending topics.</p>
            </div>
        <?php else : ?>
            <?php $total_found = $search_counts['keywords'] + $search_counts['articles'] + $search_counts['trending']; ?>
            <div class="section">
                <h2>Search Results for "<?php echo htmlspecialchars( $search_q ); ?>" (<?php echo $total_found; ?> found)</h2>

                <?php if ( 0 === $total_found ) : ?>
                    <p class="info-text">No results found. Try a different search term.</p>
                <?php endif; ?>

                <!-- Keywords Results -->
                <?php if ( ! empty( $search_results['keywords'] ) ) : ?>
                <div class="result-group">
                    <h3>Keywords <span class="result-count"><?php echo $search_counts['keywords']; ?></span></h3>
                    <table>
                        <tr><th>ID</th><th>Keyword</th><th>Category</th><th>Difficulty</th><th>Status</th><th>Action</th></tr>
                        <?php foreach ( $search_results['keywords'] as $kw ) : ?>
                        <tr>
                            <td><?php echo (int) $kw['id']; ?></td>
                            <td><?php echo htmlspecialchars( $kw['keyword'] ); ?></td>
                            <td><?php echo htmlspecialchars( isset( $categories[ $kw['category'] ] ) ? $categories[ $kw['category'] ] : $kw['category'] ); ?></td>
                            <td><span class="badge badge-<?php echo safe_css_class( $kw['difficulty'] ); ?>"><?php echo htmlspecialchars( $kw['difficulty'] ); ?></span></td>
                            <td><span class="badge badge-<?php echo $kw['status'] === 'used' ? 'used' : 'pending'; ?>"><?php echo htmlspecialchars( $kw['status'] ); ?></span></td>
                            <td>
                                <?php if ( 'pending' === $kw['status'] ) : ?>
                                <a href="<?php echo $base_url; ?>&action=delete-keyword&kw_id=<?php echo (int) $kw['id']; ?>&view=search&q=<?php echo urlencode( $search_q ); ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this keyword?')">Delete</a>
                                <?php else : ?>
                                <span class="info-text">Post #<?php echo (int) $kw['post_id']; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php if ( $search_counts['keywords'] > 50 ) : ?>
                        <p class="info-text" style="margin-top:8px">Showing first 50 of <?php echo $search_counts['keywords']; ?> results. Use the Keywords tab for full list.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Articles Results -->
                <?php if ( ! empty( $search_results['articles'] ) ) : ?>
                <div class="result-group">
                    <h3>Articles <span class="result-count"><?php echo $search_counts['articles']; ?></span></h3>
                    <table>
                        <tr><th>Post ID</th><th>Title</th><th>Keyword</th><th>Type</th><th>Category</th><th>Date</th></tr>
                        <?php foreach ( $search_results['articles'] as $art ) : ?>
                        <tr>
                            <td><?php echo (int) $art['post_id']; ?></td>
                            <td><?php echo htmlspecialchars( mb_strimwidth( $art['title'], 0, 60, '...' ) ); ?></td>
                            <td><?php echo htmlspecialchars( mb_strimwidth( $art['keyword'], 0, 40, '...' ) ); ?></td>
                            <td><span class="badge badge-<?php echo safe_css_class( $art['keyword_type'] ); ?>"><?php echo htmlspecialchars( $art['keyword_type'] ); ?></span></td>
                            <td><?php echo htmlspecialchars( $art['category'] ); ?></td>
                            <td><?php echo htmlspecialchars( $art['created_at'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php if ( $search_counts['articles'] > 50 ) : ?>
                        <p class="info-text" style="margin-top:8px">Showing first 50 of <?php echo $search_counts['articles']; ?> results.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Trending Results -->
                <?php if ( ! empty( $search_results['trending'] ) ) : ?>
                <div class="result-group">
                    <h3>Trending Topics <span class="result-count"><?php echo $search_counts['trending']; ?></span></h3>
                    <table>
                        <tr><th>ID</th><th>Title</th><th>Source</th><th>Score</th><th>Status</th><th>Action</th></tr>
                        <?php foreach ( $search_results['trending'] as $t ) : ?>
                        <tr>
                            <td><?php echo (int) $t['id']; ?></td>
                            <td><?php echo htmlspecialchars( mb_strimwidth( $t['title'], 0, 60, '...' ) ); ?></td>
                            <td><?php
                                if ( 'reddit' === $t['source'] ) {
                                    echo 'r/' . htmlspecialchars( $t['subreddit'] );
                                } elseif ( 'hackernews' === $t['source'] ) {
                                    echo 'Hacker News';
                                } else {
                                    echo htmlspecialchars( $t['subreddit'] );
                                }
                            ?></td>
                            <td><?php echo (int) $t['score']; ?></td>
                            <td><span class="badge badge-<?php echo $t['status'] === 'used' ? 'used' : 'pending'; ?>"><?php echo htmlspecialchars( $t['status'] ); ?></span></td>
                            <td>
                                <?php if ( 'pending' === $t['status'] ) : ?>
                                <a href="<?php echo $base_url; ?>&action=delete-trending&tr_id=<?php echo (int) $t['id']; ?>&view=search&q=<?php echo urlencode( $search_q ); ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this trending topic?')">Delete</a>
                                <?php else : ?>
                                <span class="info-text">Post #<?php echo (int) $t['post_id']; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php if ( $search_counts['trending'] > 50 ) : ?>
                        <p class="info-text" style="margin-top:8px">Showing first 50 of <?php echo $search_counts['trending']; ?> results.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <?php endif; // end search view ?>

    </div>
</body>
</html>
<?php
// ============================================================
// Background generation: runs AFTER the page is sent to browser.
// ============================================================
if ( ! empty( $run_bg_generation ) ) {

    // Send response to browser, then keep PHP running.
    if ( function_exists( 'fastcgi_finish_request' ) ) {
        fastcgi_finish_request();
    } else {
        ignore_user_abort( true );
        if ( ob_get_level() ) {
            ob_end_flush();
        }
        flush();
    }

    set_time_limit( 300 );

    $task_file = __DIR__ . '/data/generate_task.json';
    $task = json_decode( file_get_contents( $task_file ), true );

    if ( $task && 'pending' === $task['status'] ) {

        // Update status to running.
        $task['status']     = 'running';
        $task['started_at'] = date( 'Y-m-d H:i:s' );
        file_put_contents( $task_file, json_encode( $task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

        $log_line = "[" . date( 'Y-m-d H:i:s' ) . "] GENERATE: Starting for keyword: {$task['keyword']}\n";
        file_put_contents( __DIR__ . '/data/auto_publish.log', $log_line, FILE_APPEND );

        require_once __DIR__ . '/generator.php';
        require_once __DIR__ . '/publisher.php';

        // Generate article (uses global provider from Settings).
        $article = QWE_Generator::generate(
            $task['keyword'],
            'manual',
            $task['category'],
            $task['difficulty'] ?: 'beginner'
        );

        if ( $article ) {
            $post_id = QWE_Publisher::publish( $article );

            if ( $post_id ) {
                QWE_DB::log_article(
                    $post_id,
                    $article['title'],
                    $task['keyword'],
                    'manual',
                    $article['category'],
                    $article['difficulty']
                );
                $task['status']       = 'completed';
                $task['completed_at'] = date( 'Y-m-d H:i:s' );
                $task['result']       = array(
                    'post_id' => $post_id,
                    'title'   => $article['title'],
                    'message' => "Article published successfully! Post ID: {$post_id}",
                );

                $log_line = "[" . date( 'Y-m-d H:i:s' ) . "] GENERATE: Published [{$post_id}]: {$article['title']}\n";
                file_put_contents( __DIR__ . '/data/auto_publish.log', $log_line, FILE_APPEND );
            } else {
                $task['status']       = 'failed';
                $task['completed_at'] = date( 'Y-m-d H:i:s' );
                $task['result']       = array( 'message' => 'Article generated but publishing failed. Check log for details.' );

                $log_line = "[" . date( 'Y-m-d H:i:s' ) . "] GENERATE: Publishing failed for: {$task['keyword']}\n";
                file_put_contents( __DIR__ . '/data/auto_publish.log', $log_line, FILE_APPEND );
            }
        } else {
            $task['status']       = 'failed';
            $task['completed_at'] = date( 'Y-m-d H:i:s' );
            $task['result']       = array( 'message' => 'Generation failed. Check the log for details.' );

            $log_line = "[" . date( 'Y-m-d H:i:s' ) . "] GENERATE: Generation failed for: {$task['keyword']}\n";
            file_put_contents( __DIR__ . '/data/auto_publish.log', $log_line, FILE_APPEND );
        }

        file_put_contents( $task_file, json_encode( $task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
    }
}
?>
