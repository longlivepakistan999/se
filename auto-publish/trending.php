<?php
/**
 * Trending Topic Fetcher
 *
 * Fetches hot AI topics from multiple free sources:
 *   1. Reddit   — AI-specific subreddits (minimal filtering)
 *   2. Hacker News — Top stories filtered for AI relevance
 *   3. RSS Feeds — AI-focused tech blogs and news sites
 *
 * All non-AI-specific sources are filtered through a comprehensive
 * keyword matching system to ensure only AI-related content passes.
 *
 * @package QWE_Auto_Publish
 */

require_once __DIR__ . '/db.php';

class QWE_Trending {

    // ==========================================================
    // AI Relevance Filter — keyword lists
    // ==========================================================

    /**
     * Strong AI indicators — if title contains ANY of these, it's AI-related.
     */
    private static $ai_strong_keywords = array(
        // Core AI terms.
        'artificial intelligence', 'machine learning', 'deep learning',
        'neural network', 'natural language processing', 'computer vision',
        'reinforcement learning', 'generative ai', 'gen ai', 'genai',
        'foundation model', 'transformer model', 'diffusion model',
        'large language model', 'multimodal model', 'ai model',
        'ai agent', 'ai assistant', 'ai chatbot', 'ai tool',

        // Specific AI products and companies.
        'chatgpt', 'gpt-4', 'gpt-5', 'gpt4', 'gpt5', 'gpt-4o', 'gpt4o',
        'openai', 'open ai',
        'claude', 'anthropic',
        'gemini', 'google ai', 'google deepmind', 'deepmind',
        'midjourney', 'dall-e', 'dall·e', 'dalle',
        'stable diffusion', 'stability ai', 'comfyui', 'automatic1111',
        'copilot', 'github copilot', 'codeium', 'cursor ai', 'cursor ide',
        'perplexity', 'perplexity ai',
        'mistral', 'mixtral', 'llama', 'meta ai', 'meta llama',
        'hugging face', 'huggingface',
        'runway', 'runway ml', 'pika', 'sora', 'kling ai',
        'elevenlabs', 'eleven labs',
        'notion ai', 'jasper ai', 'grammarly ai', 'canva ai',
        'adobe firefly', 'firefly',
        'whisper', 'tortoise tts',
        'langchain', 'llamaindex', 'autogpt', 'auto-gpt', 'babyagi',
        'crewai', 'crew ai',
        'ollama', 'ollama ai', 'localai', 'local ai',
        'replicate', 'together ai',
        'cohere', 'ai21', 'ai21 labs',
        'inflection', 'inflection ai', 'pi ai',
        'xai', 'x.ai', 'grok',
        'deepseek', 'qwen', 'yi model',
        'claude code', 'windsurf', 'bolt.new', 'v0.dev',

        // AI techniques and concepts.
        'prompt engineering', 'prompt tuning', 'few-shot', 'zero-shot',
        'fine-tuning', 'fine tuning', 'finetuning',
        'rag', 'retrieval augmented generation',
        'rlhf', 'constitutional ai', 'alignment',
        'token', 'tokenizer', 'embedding', 'vector database',
        'lora', 'qlora', 'quantization', 'gguf', 'ggml',
        'text-to-image', 'text-to-video', 'text-to-speech',
        'image generation', 'video generation', 'voice cloning',
        'ai coding', 'ai code', 'ai programming',
        'ai writing', 'ai content', 'ai copywriting',
        'ai art', 'ai image', 'ai photo', 'ai design',
        'ai video', 'ai audio', 'ai music', 'ai voice',
        'ai data', 'ai analytics', 'ai automation',
        'ai business', 'ai startup', 'ai company',
        'ai regulation', 'ai safety', 'ai ethics', 'ai policy',
        'ai benchmark', 'ai evaluation',
        'agentic', 'ai workflow',
        'hallucination', 'context window',

        // Abbreviations commonly used.
        'llm', 'llms', 'nlp', 'gpt', 'agi', 'asi',
        'stablediffusion', 'controlnet', 'img2img', 'txt2img',
        'tts', 'stt', 'asr',
        'mcp', 'model context protocol',
    );

    /**
     * Weak AI indicators — need at least 2 to match, or 1 weak + general context.
     */
    private static $ai_weak_keywords = array(
        'model', 'training', 'inference', 'dataset', 'benchmark',
        'parameter', 'weights', 'api', 'token', 'prompt',
        'generation', 'detection', 'recognition', 'classification',
        'synthetic', 'autonomous', 'bot', 'chatbot', 'robot',
        'automation', 'neural', 'cognitive', 'intelligence',
        'openai', 'google', 'microsoft', 'meta', 'nvidia',
        'gpu', 'cuda', 'tensor', 'pytorch', 'tensorflow',
    );

    /**
     * Negative keywords — if these appear WITHOUT strong AI keywords, likely not AI content.
     */
    private static $negative_keywords = array(
        'sports', 'football', 'basketball', 'soccer', 'baseball',
        'recipe', 'cooking', 'fashion', 'celebrity', 'gossip',
        'weather', 'horoscope', 'lottery', 'gambling',
        'real estate', 'mortgage', 'dating', 'relationship',
        'political party', 'election results',
    );

    // ==========================================================
    // Main entry
    // ==========================================================

    /**
     * Fetch trending topics from all configured sources.
     *
     * @return int Number of new trending topics added.
     */
    public static function fetch_all() {
        $total_added = 0;

        // Source 1: Reddit (AI-specific subreddits).
        self::log( '--- Fetching from Reddit ---' );
        $total_added += self::fetch_reddit();

        // Source 2: Hacker News (filtered for AI).
        self::log( '--- Fetching from Hacker News ---' );
        $total_added += self::fetch_hackernews();

        // Source 3: RSS Feeds (AI news/blogs).
        self::log( '--- Fetching from RSS Feeds ---' );
        $total_added += self::fetch_rss_feeds();

        // Clean old pending topics (older than 7 days).
        QWE_DB::clean_old_trending();

        self::log( "Total new trending topics: {$total_added}" );
        return $total_added;
    }

    // ==========================================================
    // Source 1: Reddit
    // ==========================================================

    /**
     * Fetch from all configured Reddit subreddits.
     *
     * @return int Number of new topics added.
     */
    public static function fetch_reddit() {
        $subreddits = unserialize( QWE_REDDIT_SUBREDDITS );
        $added = 0;

        foreach ( $subreddits as $sub ) {
            $added += self::fetch_subreddit( $sub );
            usleep( 500000 ); // 0.5s between requests.
        }

        return $added;
    }

    /**
     * Fetch hot posts from a single subreddit.
     *
     * @param string $subreddit Subreddit name (without r/).
     * @return int Number of new topics added.
     */
    private static function fetch_subreddit( $subreddit ) {
        $limit = QWE_TRENDING_PER_SUB;
        $url = "https://www.reddit.com/r/{$subreddit}/hot.json?limit={$limit}&raw_json=1";

        $response = self::http_get( $url );

        if ( false === $response ) {
            self::log( "Failed to fetch r/{$subreddit}" );
            return 0;
        }

        $data = json_decode( $response, true );

        if ( ! isset( $data['data']['children'] ) ) {
            self::log( "Invalid response from r/{$subreddit}" );
            return 0;
        }

        $added = 0;
        foreach ( $data['data']['children'] as $child ) {
            $post = $child['data'];

            // Skip pinned/stickied posts.
            if ( ! empty( $post['stickied'] ) ) {
                continue;
            }

            // Skip posts with very low engagement.
            if ( $post['score'] < 10 ) {
                continue;
            }

            $title = trim( $post['title'] );

            // Skip very short titles.
            if ( strlen( $title ) < 15 ) {
                continue;
            }

            // Reddit AI subs are pre-filtered, but still run a light check.
            // Map subreddit to likely category.
            $category = self::map_subreddit_to_category( $subreddit );

            $result = QWE_DB::add_trending(
                $title,
                'reddit',
                $subreddit,
                $post['score'],
                $category
            );

            if ( $result ) {
                $added++;
            }
        }

        self::log( "Reddit r/{$subreddit}: {$added} new topics" );
        return $added;
    }

    /**
     * Map a subreddit to the most likely tutorial category slug.
     */
    private static function map_subreddit_to_category( $subreddit ) {
        $map = array(
            'ChatGPT'          => 'chatgpt-llms',
            'ClaudeAI'         => 'chatgpt-llms',
            'LocalLLaMA'       => 'chatgpt-llms',
            'artificial'       => 'chatgpt-llms',
            'singularity'      => 'chatgpt-llms',
            'midjourney'       => 'ai-art-design',
            'StableDiffusion'  => 'ai-art-design',
        );

        return isset( $map[ $subreddit ] ) ? $map[ $subreddit ] : 'chatgpt-llms';
    }

    // ==========================================================
    // Source 2: Hacker News
    // ==========================================================

    /**
     * Fetch top stories from Hacker News, filtered for AI relevance.
     *
     * Uses the official HN Firebase API (free, no auth).
     * Endpoint: https://hacker-news.firebaseio.com/v0/
     *
     * @return int Number of new AI-related topics added.
     */
    public static function fetch_hackernews() {
        // Fetch top story IDs.
        $url = 'https://hacker-news.firebaseio.com/v0/topstories.json';
        $response = self::http_get( $url );

        if ( false === $response ) {
            self::log( 'Failed to fetch Hacker News top stories' );
            return 0;
        }

        $story_ids = json_decode( $response, true );

        if ( ! is_array( $story_ids ) || empty( $story_ids ) ) {
            self::log( 'Invalid Hacker News response' );
            return 0;
        }

        // Check top 60 stories to find AI-related ones.
        $check_count = min( 60, count( $story_ids ) );
        $added = 0;
        $max_to_add = 10; // Cap at 10 per fetch.
        $checked = 0;

        for ( $i = 0; $i < $check_count && $added < $max_to_add; $i++ ) {
            $story_id = $story_ids[ $i ];

            $story_url = "https://hacker-news.firebaseio.com/v0/item/{$story_id}.json";
            $story_response = self::http_get( $story_url );

            if ( false === $story_response ) {
                continue;
            }

            $story = json_decode( $story_response, true );

            if ( ! $story || empty( $story['title'] ) ) {
                continue;
            }

            // Skip Show HN, Ask HN polls and non-story items.
            if ( isset( $story['type'] ) && 'story' !== $story['type'] ) {
                continue;
            }

            $title = trim( $story['title'] );
            $score = isset( $story['score'] ) ? (int) $story['score'] : 0;

            // Skip low-engagement stories.
            if ( $score < 20 ) {
                continue;
            }

            // AI relevance filter — this is the critical step.
            if ( ! self::is_ai_related( $title ) ) {
                continue;
            }

            // Determine category from title.
            $category = self::categorize_from_title( $title );

            $result = QWE_DB::add_trending(
                $title,
                'hackernews',
                'HackerNews',
                $score,
                $category
            );

            if ( $result ) {
                $added++;
            }

            $checked++;

            // Rate limit: 0.2s between HN API calls.
            usleep( 200000 );
        }

        self::log( "Hacker News: checked {$check_count} stories, {$added} AI topics added" );
        return $added;
    }

    // ==========================================================
    // Source 3: RSS Feeds
    // ==========================================================

    /**
     * Fetch AI news from RSS feeds.
     *
     * Uses XML parsing on RSS/Atom feeds from AI-focused publications.
     * Some feeds are AI-specific; mixed feeds get filtered.
     *
     * @return int Number of new AI-related topics added.
     */
    public static function fetch_rss_feeds() {
        $feeds = self::get_rss_feed_list();
        $total_added = 0;

        foreach ( $feeds as $feed ) {
            $added = self::fetch_single_rss(
                $feed['url'],
                $feed['name'],
                $feed['needs_filter'],
                $feed['default_category']
            );
            $total_added += $added;
            usleep( 300000 ); // 0.3s between feeds.
        }

        return $total_added;
    }

    /**
     * List of RSS feeds to monitor.
     *
     * needs_filter: false = AI-specific feed, skip relevance check.
     *               true = mixed content, apply AI filter.
     */
    private static function get_rss_feed_list() {
        return array(
            array(
                'url'              => 'https://techcrunch.com/category/artificial-intelligence/feed/',
                'name'             => 'TechCrunch AI',
                'needs_filter'     => false, // AI-specific feed.
                'default_category' => 'chatgpt-llms',
            ),
            array(
                'url'              => 'https://www.theverge.com/rss/ai-artificial-intelligence/index.xml',
                'name'             => 'The Verge AI',
                'needs_filter'     => false,
                'default_category' => 'chatgpt-llms',
            ),
            array(
                'url'              => 'https://venturebeat.com/category/ai/feed/',
                'name'             => 'VentureBeat AI',
                'needs_filter'     => false,
                'default_category' => 'ai-business',
            ),
            array(
                'url'              => 'https://blog.google/technology/ai/rss/',
                'name'             => 'Google AI Blog',
                'needs_filter'     => false,
                'default_category' => 'chatgpt-llms',
            ),
            array(
                'url'              => 'https://openai.com/blog/rss/',
                'name'             => 'OpenAI Blog',
                'needs_filter'     => false,
                'default_category' => 'chatgpt-llms',
            ),
            array(
                'url'              => 'https://www.technologyreview.com/feed/',
                'name'             => 'MIT Tech Review',
                'needs_filter'     => true, // Mixed content — filter for AI.
                'default_category' => 'chatgpt-llms',
            ),
        );
    }

    /**
     * Fetch and parse a single RSS feed.
     *
     * @param string $url             Feed URL.
     * @param string $name            Human-readable source name.
     * @param bool   $needs_filter    Whether to apply AI relevance filter.
     * @param string $default_category Default category if auto-detection fails.
     * @return int Number of new topics added.
     */
    private static function fetch_single_rss( $url, $name, $needs_filter, $default_category ) {
        $response = self::http_get( $url );

        if ( false === $response ) {
            self::log( "Failed to fetch RSS: {$name}" );
            return 0;
        }

        // Suppress XML warnings for malformed feeds.
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $response );
        libxml_clear_errors();

        if ( false === $xml ) {
            self::log( "Failed to parse RSS XML: {$name}" );
            return 0;
        }

        $items = self::extract_rss_items( $xml );

        if ( empty( $items ) ) {
            self::log( "No items found in RSS: {$name}" );
            return 0;
        }

        $added = 0;
        $max_items = 10; // Process up to 10 items per feed.

        foreach ( array_slice( $items, 0, $max_items ) as $item ) {
            $title = trim( $item['title'] );

            if ( strlen( $title ) < 15 ) {
                continue;
            }

            // Apply AI filter if needed.
            if ( $needs_filter && ! self::is_ai_related( $title ) ) {
                continue;
            }

            // Determine category.
            $category = self::categorize_from_title( $title );
            if ( ! $category ) {
                $category = $default_category;
            }

            // Use a score based on position in feed (newer = higher).
            $score = max( 1, $max_items - $added );

            $result = QWE_DB::add_trending(
                $title,
                'rss',
                $name,
                $score,
                $category
            );

            if ( $result ) {
                $added++;
            }
        }

        self::log( "RSS {$name}: {$added} new topics" );
        return $added;
    }

    /**
     * Extract items from RSS or Atom feed XML.
     *
     * @param SimpleXMLElement $xml
     * @return array Array of ['title' => ..., 'link' => ...].
     */
    private static function extract_rss_items( $xml ) {
        $items = array();

        // Try RSS 2.0 format: <channel><item><title>
        if ( isset( $xml->channel->item ) ) {
            foreach ( $xml->channel->item as $item ) {
                $items[] = array(
                    'title' => (string) $item->title,
                    'link'  => (string) $item->link,
                );
            }
            return $items;
        }

        // Try Atom format: <entry><title>
        // Register Atom namespace if needed.
        $namespaces = $xml->getNamespaces( true );
        if ( isset( $xml->entry ) ) {
            foreach ( $xml->entry as $entry ) {
                $link = '';
                if ( isset( $entry->link ) ) {
                    $link = (string) $entry->link['href'];
                }
                $items[] = array(
                    'title' => (string) $entry->title,
                    'link'  => $link,
                );
            }
            return $items;
        }

        // Try Atom with default namespace.
        if ( ! empty( $namespaces ) ) {
            $ns = reset( $namespaces );
            $xml->registerXPathNamespace( 'atom', $ns );
            $entries = $xml->xpath( '//atom:entry' );
            if ( $entries ) {
                foreach ( $entries as $entry ) {
                    $entry->registerXPathNamespace( 'atom', $ns );
                    $title_nodes = $entry->xpath( 'atom:title' );
                    $link_nodes = $entry->xpath( 'atom:link[@rel="alternate"]/@href' );
                    if ( ! $link_nodes ) {
                        $link_nodes = $entry->xpath( 'atom:link/@href' );
                    }
                    $items[] = array(
                        'title' => $title_nodes ? (string) $title_nodes[0] : '',
                        'link'  => $link_nodes ? (string) $link_nodes[0] : '',
                    );
                }
            }
        }

        return $items;
    }

    // ==========================================================
    // AI Relevance Filter
    // ==========================================================

    /**
     * Check if a title/text is related to AI topics.
     *
     * Strategy:
     *   1. If any STRONG AI keyword matches → pass (definitely AI).
     *   2. If 2+ WEAK AI keywords match → pass (likely AI).
     *   3. If any NEGATIVE keyword matches and no strong → fail.
     *   4. Otherwise → fail (not enough AI signal).
     *
     * @param string $text Text to check (usually the article title).
     * @return bool True if AI-related.
     */
    public static function is_ai_related( $text ) {
        $text_lower = strtolower( $text );

        // Step 1: Check for negative keywords first.
        foreach ( self::$negative_keywords as $neg ) {
            if ( false !== strpos( $text_lower, $neg ) ) {
                // Has negative keyword — only pass if there's a strong AI match too.
                $has_strong = false;
                foreach ( self::$ai_strong_keywords as $kw ) {
                    if ( false !== strpos( $text_lower, $kw ) ) {
                        $has_strong = true;
                        break;
                    }
                }
                if ( ! $has_strong ) {
                    return false;
                }
            }
        }

        // Step 2: Check strong AI keywords.
        foreach ( self::$ai_strong_keywords as $kw ) {
            if ( false !== strpos( $text_lower, $kw ) ) {
                return true;
            }
        }

        // Step 3: Check weak keywords — need 2+ matches.
        $weak_matches = 0;
        foreach ( self::$ai_weak_keywords as $kw ) {
            if ( false !== strpos( $text_lower, $kw ) ) {
                $weak_matches++;
            }
            if ( $weak_matches >= 2 ) {
                return true;
            }
        }

        // Not enough AI signal.
        return false;
    }

    // ==========================================================
    // Auto-Categorization
    // ==========================================================

    /**
     * Determine the best tutorial category from a title.
     *
     * @param string $title
     * @return string Category slug or empty string.
     */
    private static function categorize_from_title( $title ) {
        $t = strtolower( $title );

        // AI Art & Design.
        $art_keywords = array(
            'midjourney', 'dall-e', 'dalle', 'stable diffusion', 'image generat',
            'ai art', 'ai image', 'ai photo', 'ai design', 'text-to-image',
            'txt2img', 'img2img', 'controlnet', 'comfyui', 'automatic1111',
            'adobe firefly', 'firefly', 'flux', 'ideogram', 'leonardo ai',
        );
        foreach ( $art_keywords as $kw ) {
            if ( false !== strpos( $t, $kw ) ) {
                return 'ai-art-design';
            }
        }

        // AI Coding.
        $coding_keywords = array(
            'copilot', 'ai coding', 'ai code', 'ai programming', 'ai developer',
            'cursor', 'codeium', 'code generation', 'ai debug', 'claude code',
            'coding assistant', 'code completion', 'windsurf', 'bolt.new',
            'v0.dev', 'ai ide', 'devin', 'software engineer',
        );
        foreach ( $coding_keywords as $kw ) {
            if ( false !== strpos( $t, $kw ) ) {
                return 'ai-coding';
            }
        }

        // AI Writing.
        $writing_keywords = array(
            'ai writ', 'ai content', 'ai copywriting', 'ai blog',
            'jasper', 'grammarly', 'chatgpt writ', 'ai seo',
            'content generat', 'ai editor', 'ai author', 'ai story',
        );
        foreach ( $writing_keywords as $kw ) {
            if ( false !== strpos( $t, $kw ) ) {
                return 'ai-writing';
            }
        }

        // AI Video & Audio.
        $media_keywords = array(
            'ai video', 'ai audio', 'ai music', 'ai voice', 'text-to-speech',
            'text-to-video', 'speech-to-text', 'tts', 'stt', 'sora',
            'runway', 'pika', 'elevenlabs', 'eleven labs', 'kling',
            'voice clon', 'video generat', 'music generat', 'ai podcast',
        );
        foreach ( $media_keywords as $kw ) {
            if ( false !== strpos( $t, $kw ) ) {
                return 'ai-video-audio';
            }
        }

        // AI Data Analysis.
        $data_keywords = array(
            'ai data', 'ai analytics', 'data analy', 'data scien',
            'machine learning', 'deep learning', 'neural network',
            'training', 'dataset', 'benchmark', 'ai research',
            'pytorch', 'tensorflow', 'model training', 'fine-tun',
        );
        foreach ( $data_keywords as $kw ) {
            if ( false !== strpos( $t, $kw ) ) {
                return 'ai-data-analysis';
            }
        }

        // AI for Business.
        $business_keywords = array(
            'ai business', 'ai startup', 'ai company', 'ai enterprise',
            'ai market', 'ai automat', 'ai workflow', 'ai productiv',
            'ai invest', 'ai fund', 'ai revenue', 'ai industr',
            'ai regulation', 'ai policy', 'ai law', 'ai ethic',
        );
        foreach ( $business_keywords as $kw ) {
            if ( false !== strpos( $t, $kw ) ) {
                return 'ai-business';
            }
        }

        // Default to ChatGPT & LLMs (largest category).
        return 'chatgpt-llms';
    }

    // ==========================================================
    // HTTP Helper
    // ==========================================================

    /**
     * Simple HTTP GET with user agent.
     *
     * @param string $url
     * @return string|false Response body or false on failure.
     */
    private static function http_get( $url ) {
        $context = stream_context_create( array(
            'http' => array(
                'method'  => 'GET',
                'header'  => "User-Agent: QWE-AI-Academy-Bot/1.0\r\nAccept: application/json, application/xml, text/xml, application/rss+xml, application/atom+xml, */*\r\n",
                'timeout' => 15,
            ),
            'ssl' => array(
                'verify_peer' => true,
            ),
        ) );

        $response = @file_get_contents( $url, false, $context );

        return $response;
    }

    // ==========================================================
    // Logger
    // ==========================================================

    /**
     * Simple log.
     */
    private static function log( $message ) {
        $time = date( 'Y-m-d H:i:s' );
        $log = "[{$time}] TRENDING: {$message}\n";
        file_put_contents( __DIR__ . '/data/auto_publish.log', $log, FILE_APPEND );
    }
}
