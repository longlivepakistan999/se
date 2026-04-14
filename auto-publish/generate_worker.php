<?php
/**
 * Background Article Generator Worker
 *
 * Runs in background via exec() from admin.php.
 * Reads task from data/generate_task.json, generates and publishes article.
 *
 * Usage: php generate_worker.php (CLI only, launched by admin.php)
 *
 * @package QWE_Auto_Publish
 */

// Only allow CLI execution.
if ( 'cli' !== php_sapi_name() ) {
    die( 'CLI only' );
}

set_time_limit( 600 );

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/generator.php';
require_once __DIR__ . '/publisher.php';

$task_file = __DIR__ . '/data/generate_task.json';

// Read task.
if ( ! file_exists( $task_file ) ) {
    exit( 1 );
}

$task = json_decode( file_get_contents( $task_file ), true );
if ( ! $task || 'pending' !== $task['status'] ) {
    exit( 1 );
}

// Update status to running.
$task['status']     = 'running';
$task['started_at'] = date( 'Y-m-d H:i:s' );
file_put_contents( $task_file, json_encode( $task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

// Log.
$log_line = "[" . date( 'Y-m-d H:i:s' ) . "] WORKER: Starting generation for keyword: {$task['keyword']}\n";
file_put_contents( __DIR__ . '/data/auto_publish.log', $log_line, FILE_APPEND );

// Ensure tables exist.
QWE_DB::init_tables();

// Generate article.
$article = QWE_Generator::generate(
    $task['keyword'],
    'manual',
    $task['category'],
    $task['difficulty'] ?: 'beginner'
);

if ( ! $article ) {
    $task['status']       = 'failed';
    $task['completed_at'] = date( 'Y-m-d H:i:s' );
    $task['result']       = array( 'message' => 'Generation failed. Check the log for details.' );
    file_put_contents( $task_file, json_encode( $task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

    $log_line = "[" . date( 'Y-m-d H:i:s' ) . "] WORKER: Generation failed for keyword: {$task['keyword']}\n";
    file_put_contents( __DIR__ . '/data/auto_publish.log', $log_line, FILE_APPEND );
    exit( 1 );
}

// Publish.
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

    $log_line = "[" . date( 'Y-m-d H:i:s' ) . "] WORKER: Published [{$post_id}]: {$article['title']}\n";
    file_put_contents( __DIR__ . '/data/auto_publish.log', $log_line, FILE_APPEND );
} else {
    $task['status']       = 'failed';
    $task['completed_at'] = date( 'Y-m-d H:i:s' );
    $task['result']       = array( 'message' => 'Article generated but publishing failed. Check log for details.' );

    $log_line = "[" . date( 'Y-m-d H:i:s' ) . "] WORKER: Publishing failed for keyword: {$task['keyword']}\n";
    file_put_contents( __DIR__ . '/data/auto_publish.log', $log_line, FILE_APPEND );
}

file_put_contents( $task_file, json_encode( $task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
