<?php
/**
 * SQLite Database Handler
 *
 * @package QWE_Auto_Publish
 */

require_once __DIR__ . '/config.php';

class QWE_DB {

    /** @var PDO */
    private static $pdo = null;

    /**
     * Get PDO connection (singleton).
     */
    public static function connect() {
        if ( null === self::$pdo ) {
            $dir = dirname( QWE_DB_PATH );
            if ( ! is_dir( $dir ) ) {
                mkdir( $dir, 0755, true );
            }
            self::$pdo = new PDO( 'sqlite:' . QWE_DB_PATH );
            self::$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            self::$pdo->exec( 'PRAGMA journal_mode=WAL' );
            self::$pdo->exec( 'PRAGMA foreign_keys=ON' );
        }
        return self::$pdo;
    }

    /**
     * Initialize database tables.
     */
    public static function init_tables() {
        $pdo = self::connect();

        $pdo->exec( "
            CREATE TABLE IF NOT EXISTS keywords (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                keyword     TEXT NOT NULL UNIQUE,
                category    TEXT NOT NULL,
                difficulty  TEXT NOT NULL DEFAULT 'beginner',
                status      TEXT NOT NULL DEFAULT 'pending',
                used_date   TEXT,
                post_id     INTEGER,
                created_at  TEXT NOT NULL DEFAULT (datetime('now'))
            )
        " );

        $pdo->exec( "
            CREATE TABLE IF NOT EXISTS trending (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                title       TEXT NOT NULL,
                source      TEXT NOT NULL,
                subreddit   TEXT,
                keyword     TEXT,
                category    TEXT,
                score       INTEGER DEFAULT 0,
                status      TEXT NOT NULL DEFAULT 'pending',
                used_date   TEXT,
                post_id     INTEGER,
                fetched_at  TEXT NOT NULL DEFAULT (datetime('now'))
            )
        " );

        $pdo->exec( "
            CREATE TABLE IF NOT EXISTS articles (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id       INTEGER NOT NULL,
                title         TEXT NOT NULL,
                keyword       TEXT NOT NULL,
                keyword_type  TEXT NOT NULL,
                category      TEXT NOT NULL,
                difficulty    TEXT NOT NULL DEFAULT 'beginner',
                source_id     INTEGER,
                created_at    TEXT NOT NULL DEFAULT (datetime('now'))
            )
        " );

        $pdo->exec( "CREATE INDEX IF NOT EXISTS idx_keywords_status ON keywords(status)" );
        $pdo->exec( "CREATE INDEX IF NOT EXISTS idx_keywords_category ON keywords(category)" );
        $pdo->exec( "CREATE INDEX IF NOT EXISTS idx_trending_status ON trending(status)" );
        $pdo->exec( "CREATE UNIQUE INDEX IF NOT EXISTS idx_trending_title ON trending(title)" );
        $pdo->exec( "CREATE INDEX IF NOT EXISTS idx_articles_keyword_type ON articles(keyword_type)" );
        $pdo->exec( "CREATE INDEX IF NOT EXISTS idx_articles_category ON articles(category)" );
    }

    // ==========================================================
    // Keywords
    // ==========================================================

    /**
     * Insert a long-tail keyword (skip if exists).
     */
    public static function add_keyword( $keyword, $category, $difficulty = 'beginner' ) {
        $pdo = self::connect();
        $stmt = $pdo->prepare(
            "INSERT OR IGNORE INTO keywords (keyword, category, difficulty) VALUES (?, ?, ?)"
        );
        $stmt->execute( array( $keyword, $category, $difficulty ) );
        // rowCount() returns 0 when OR IGNORE skips a duplicate.
        return $stmt->rowCount() > 0;
    }

    /**
     * Get next pending keyword, optionally filtered by category.
     * Returns associative array or false.
     */
    public static function get_next_keyword( $category = null ) {
        $pdo = self::connect();
        if ( $category ) {
            $stmt = $pdo->prepare(
                "SELECT * FROM keywords WHERE status = 'pending' AND category = ? ORDER BY RANDOM() LIMIT 1"
            );
            $stmt->execute( array( $category ) );
        } else {
            $stmt = $pdo->query(
                "SELECT * FROM keywords WHERE status = 'pending' ORDER BY RANDOM() LIMIT 1"
            );
        }
        return $stmt->fetch( PDO::FETCH_ASSOC );
    }

    /**
     * Mark keyword as used.
     */
    public static function mark_keyword_used( $id, $post_id ) {
        $pdo = self::connect();
        $stmt = $pdo->prepare(
            "UPDATE keywords SET status = 'used', used_date = datetime('now'), post_id = ? WHERE id = ?"
        );
        return $stmt->execute( array( $post_id, $id ) );
    }

    /**
     * Count pending keywords per category.
     */
    public static function count_pending_keywords() {
        $pdo = self::connect();
        $stmt = $pdo->query(
            "SELECT category, COUNT(*) as count FROM keywords WHERE status = 'pending' GROUP BY category"
        );
        return $stmt->fetchAll( PDO::FETCH_KEY_PAIR );
    }

    /**
     * Delete a keyword by ID (only pending ones).
     */
    public static function delete_keyword( $id ) {
        $pdo = self::connect();
        $stmt = $pdo->prepare(
            "DELETE FROM keywords WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute( array( $id ) );
        return $stmt->rowCount() > 0;
    }

    /**
     * Get all keywords with optional filters.
     *
     * @param string $status   Filter by status ('pending', 'used', or 'all').
     * @param string $category Filter by category slug (or 'all').
     * @param int    $limit    Max results.
     * @param int    $offset   Offset for pagination.
     * @return array
     */
    public static function get_keywords( $status = 'all', $category = 'all', $limit = 50, $offset = 0 ) {
        $pdo = self::connect();
        $where = array();
        $params = array();

        if ( 'all' !== $status ) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ( 'all' !== $category ) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        $sql = 'SELECT * FROM keywords';
        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY category ASC, keyword ASC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare( $sql );
        $stmt->execute( $params );
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }

    /**
     * Count keywords with optional filters.
     */
    public static function count_keywords( $status = 'all', $category = 'all' ) {
        $pdo = self::connect();
        $where = array();
        $params = array();

        if ( 'all' !== $status ) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ( 'all' !== $category ) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        $sql = 'SELECT COUNT(*) FROM keywords';
        if ( ! empty( $where ) ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }

        $stmt = $pdo->prepare( $sql );
        $stmt->execute( $params );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Delete a trending topic by ID (only pending ones).
     */
    public static function delete_trending( $id ) {
        $pdo = self::connect();
        $stmt = $pdo->prepare(
            "DELETE FROM trending WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute( array( $id ) );
        return $stmt->rowCount() > 0;
    }

    // ==========================================================
    // Trending
    // ==========================================================

    /**
     * Insert a trending topic (avoid duplicates by title).
     */
    public static function add_trending( $title, $source, $subreddit, $score = 0, $category = null ) {
        $pdo = self::connect();
        $stmt = $pdo->prepare(
            "INSERT OR IGNORE INTO trending (title, source, subreddit, score, category) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute( array( $title, $source, $subreddit, $score, $category ) );
        return $stmt->rowCount() > 0;
    }

    /**
     * Get next pending trending topic.
     */
    public static function get_next_trending() {
        $pdo = self::connect();
        $stmt = $pdo->query(
            "SELECT * FROM trending WHERE status = 'pending' ORDER BY score DESC, fetched_at DESC LIMIT 1"
        );
        return $stmt->fetch( PDO::FETCH_ASSOC );
    }

    /**
     * Mark trending topic as used.
     */
    public static function mark_trending_used( $id, $post_id, $keyword ) {
        $pdo = self::connect();
        $stmt = $pdo->prepare(
            "UPDATE trending SET status = 'used', used_date = datetime('now'), post_id = ?, keyword = ? WHERE id = ?"
        );
        return $stmt->execute( array( $post_id, $keyword, $id ) );
    }

    /**
     * Clean old pending trending topics (older than 7 days).
     */
    public static function clean_old_trending() {
        $pdo = self::connect();
        return $pdo->exec(
            "DELETE FROM trending WHERE status = 'pending' AND fetched_at < datetime('now', '-7 days')"
        );
    }

    // ==========================================================
    // Articles log
    // ==========================================================

    /**
     * Log a published article.
     */
    public static function log_article( $post_id, $title, $keyword, $keyword_type, $category, $difficulty, $source_id = null ) {
        $pdo = self::connect();
        $stmt = $pdo->prepare(
            "INSERT INTO articles (post_id, title, keyword, keyword_type, category, difficulty, source_id) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute( array( $post_id, $title, $keyword, $keyword_type, $category, $difficulty, $source_id ) );
    }

    /**
     * Get article stats.
     */
    public static function get_stats() {
        $pdo = self::connect();

        $total = $pdo->query( "SELECT COUNT(*) FROM articles" )->fetchColumn();
        $longtail = $pdo->query( "SELECT COUNT(*) FROM articles WHERE keyword_type = 'longtail'" )->fetchColumn();
        $trending = $pdo->query( "SELECT COUNT(*) FROM articles WHERE keyword_type = 'trending'" )->fetchColumn();
        $manual = $pdo->query( "SELECT COUNT(*) FROM articles WHERE keyword_type = 'manual'" )->fetchColumn();
        $today = $pdo->query( "SELECT COUNT(*) FROM articles WHERE DATE(created_at) = DATE('now')" )->fetchColumn();
        $pending_kw = $pdo->query( "SELECT COUNT(*) FROM keywords WHERE status = 'pending'" )->fetchColumn();
        $pending_tr = $pdo->query( "SELECT COUNT(*) FROM trending WHERE status = 'pending'" )->fetchColumn();

        $by_category = $pdo->query(
            "SELECT category, COUNT(*) as count FROM articles GROUP BY category ORDER BY count DESC"
        )->fetchAll( PDO::FETCH_KEY_PAIR );

        return array(
            'total_articles'     => $total,
            'longtail_articles'  => $longtail,
            'trending_articles'  => $trending,
            'manual_articles'    => $manual,
            'today_articles'     => $today,
            'pending_keywords'   => $pending_kw,
            'pending_trending'   => $pending_tr,
            'by_category'        => $by_category,
        );
    }

    /**
     * Check if a keyword was already used (prevent duplicates).
     */
    public static function keyword_already_used( $keyword ) {
        $pdo = self::connect();
        $stmt = $pdo->prepare( "SELECT id FROM articles WHERE LOWER(keyword) = LOWER(?)" );
        $stmt->execute( array( $keyword ) );
        return (bool) $stmt->fetch();
    }

    // ==========================================================
    // Provider Settings (JSON file storage)
    // ==========================================================

    /**
     * Get the provider settings file path.
     */
    private static function settings_file() {
        return dirname( QWE_DB_PATH ) . '/provider_settings.json';
    }

    /**
     * Get current provider settings.
     *
     * Returns array with keys: provider, openai_api_key, openai_model.
     * Falls back to config.php constants if no settings file exists.
     */
    public static function get_provider_settings() {
        $defaults = array(
            'provider'       => defined( 'QWE_AI_PROVIDER' ) ? QWE_AI_PROVIDER : 'claude',
            'openai_api_key' => defined( 'QWE_OPENAI_API_KEY' ) ? QWE_OPENAI_API_KEY : '',
            'openai_model'   => defined( 'QWE_OPENAI_MODEL' ) ? QWE_OPENAI_MODEL : 'gpt-5',
        );

        $file = self::settings_file();
        if ( file_exists( $file ) ) {
            $data = json_decode( file_get_contents( $file ), true );
            if ( is_array( $data ) ) {
                return array_merge( $defaults, $data );
            }
        }

        return $defaults;
    }

    /**
     * Save provider settings.
     */
    public static function save_provider_settings( $provider, $openai_api_key, $openai_model ) {
        $data = array(
            'provider'       => $provider,
            'openai_api_key' => $openai_api_key,
            'openai_model'   => $openai_model,
        );
        $file = self::settings_file();
        return file_put_contents( $file, json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
    }
}
