<?php
/**
 * AI Article Generator
 *
 * Uses Claude API to generate SEO-optimized tutorial articles.
 * Features:
 *   - Auto-categorization into tutorial_category taxonomy
 *   - SEO: meta description, proper headings, internal linking hints
 *   - Low AI detection (<60%) via humanization prompt strategies
 *   - Outputs WordPress block editor (Gutenberg) compatible HTML
 *
 * @package QWE_Auto_Publish
 */

require_once __DIR__ . '/db.php';

class QWE_Generator {

    /**
     * Generate a single article from a keyword.
     *
     * @param string $keyword       The target keyword.
     * @param string $keyword_type  'longtail' or 'trending'.
     * @param string $hint_category Optional category hint.
     * @param string $difficulty    'beginner', 'intermediate', or 'advanced'.
     * @return array|false          Article data or false on failure.
     */
    public static function generate( $keyword, $keyword_type = 'longtail', $hint_category = '', $difficulty = 'beginner' ) {
        $categories = unserialize( QWE_CATEGORIES );

        $category_list = '';
        foreach ( $categories as $slug => $name ) {
            $category_list .= "- {$slug}: {$name}\n";
        }

        $system_prompt = self::build_system_prompt();
        $user_prompt = self::build_user_prompt( $keyword, $keyword_type, $hint_category, $difficulty, $category_list );

        // Pass 1: Generate article with web search enabled (if configured).
        $response = self::call_claude_api( $system_prompt, $user_prompt, true );

        if ( ! $response ) {
            self::log( "API call failed for keyword: {$keyword}" );
            return false;
        }

        $article = self::parse_response( $response );

        if ( ! $article ) {
            self::log( "Failed to parse response for keyword: {$keyword}" );
            return false;
        }

        // Check if keyword was flagged as too saturated (no edge cases found).
        if ( ! empty( $article['edge_cases_insufficient'] ) ) {
            self::log( "Keyword too saturated (no edge cases found), skipping: {$keyword}" );
            return false;
        }

        // Log facts used in the article for verification.
        if ( ! empty( $article['facts'] ) && is_array( $article['facts'] ) ) {
            self::log( "Pass 1 facts collected: " . count( $article['facts'] ) );
            foreach ( $article['facts'] as $f ) {
                $fact_text = $f['fact'] ?? '?';
                $fact_src  = $f['source'] ?? '?';
                self::log( "  Fact: {$fact_text} [Source: {$fact_src}]" );
            }
        }

        // Log edge cases.
        if ( ! empty( $article['edge_cases'] ) && is_array( $article['edge_cases'] ) ) {
            self::log( "Edge cases found: " . count( $article['edge_cases'] ) );
            foreach ( $article['edge_cases'] as $ec ) {
                $ec_type = $ec['type'] ?? '?';
                $ec_case = $ec['case'] ?? '?';
                self::log( "  [{$ec_type}] {$ec_case}" );
            }
        }

        // Log competitor consensus.
        if ( ! empty( $article['competitor_consensus'] ) ) {
            $cc = $article['competitor_consensus'];
            self::log( "Competitor consensus — structure: " . ( $cc['common_structure'] ?? '?' ) );
            self::log( "Our differentiation: " . ( $cc['our_differentiation'] ?? '?' ) );
        }

        self::log( "Pass 1 draft generated for: {$keyword}" );

        // Pass 2: E-E-A-T evaluation — if all scores >= 80, use original; otherwise revise.
        $final = self::review_and_revise( $article );
        if ( $final ) {
            $article = $final;
        } else {
            self::log( "Pass 2 error — falling back to Pass 1 draft for: {$keyword}" );
        }

        $article['keyword'] = $keyword;
        $article['keyword_type'] = $keyword_type;

        // Ensure meta_description exists (fallback to excerpt if AI didn't generate it).
        if ( empty( $article['meta_description'] ) ) {
            $article['meta_description'] = $article['excerpt'];
        }

        // Post-process: strip AI fingerprints from all text fields.
        $article['title']            = self::clean_ai_fingerprint( $article['title'] );
        $article['excerpt']          = self::clean_ai_fingerprint( $article['excerpt'] );
        $article['meta_description'] = self::clean_ai_fingerprint( $article['meta_description'] );
        $article['content']          = self::clean_ai_fingerprint( $article['content'] );
        $article['slug']             = self::clean_ai_fingerprint( $article['slug'] );
        if ( ! empty( $article['tags'] ) ) {
            $article['tags'] = array_map( array( __CLASS__, 'clean_ai_fingerprint' ), $article['tags'] );
        }

        // Post-process: replace any remaining banned words (zero API cost safety net).
        $article['title']            = self::check_banned_words( $article['title'] );
        $article['excerpt']          = self::check_banned_words( $article['excerpt'] );
        $article['meta_description'] = self::check_banned_words( $article['meta_description'] );
        $article['content']          = self::check_banned_words( $article['content'] );

        // Validate category is one of ours.
        if ( ! isset( $categories[ $article['category'] ] ) ) {
            // Default to first matching category or chatgpt-llms.
            $article['category'] = $hint_category ?: 'chatgpt-llms';
        }

        // Validate difficulty.
        $valid_difficulties = array( 'beginner', 'intermediate', 'advanced' );
        if ( ! in_array( $article['difficulty'], $valid_difficulties, true ) ) {
            $article['difficulty'] = $difficulty;
        }

        return $article;
    }

    /**
     * Build the system prompt.
     *
     * Facts-first methodology:
     * 1. Collect verifiable facts about the topic BEFORE writing
     * 2. Write the article using ONLY those collected facts
     * 3. Label every data point with its source
     * 4. Include 3-5 unique insights readers can't easily find elsewhere
     */
    private static function build_system_prompt() {
        $prompt = <<<'PROMPT'
You are a tech writer for QWE AI Academy (qwe.edu.pl). Your articles teach readers how to use AI tools effectively.

=== GOOGLE CONTENT QUALITY PRINCIPLES (NON-NEGOTIABLE) ===

These 3 rules override everything else. Every article must satisfy all 3:

1. ORIGINALITY — Do NOT repeat the standard tutorial structure other sites use. If the common tutorial structure for this topic is "What is X → Why use X → How to use X → Comparison → FAQ", you MUST use a different organization. At least one section must cover an angle that other tutorials on this topic would NOT cover. Use your own examples (not recycled from docs). Offer your own analysis and opinions, not just restated facts. Add observations that come from actual usage, not from reading other guides.

2. FRESHNESS — Facts from web search are time-sensitive. Every fact you collect must be treated as potentially dated. If a fact does NOT have a clear date attached, you must mark it in the article with "as of [date]" or "this may have changed since". Do NOT present any unverified information as current fact. Always search for the LATEST version/pricing/features before writing.

3. INFORMATION RHYTHM — At least 2 paragraphs in the article must NOT directly solve a problem. They can be: an analogy, a brief comment reflecting on what was just explained, or an open-ended question that you leave without a definitive answer. These paragraphs give the article breathing room and make it feel human. All OTHER paragraphs must maintain high information density — every sentence teaches or moves the reader forward.

=== FACTS-FIRST METHODOLOGY ===

Before writing ANYTHING, you must first research the competition and collect facts. This is your #1 rule:

STEP 0 — WEB SEARCH (if you have the web_search tool):
You have access to a web search tool. USE IT before writing to get the latest, most accurate facts:
- Search for current pricing, model versions, API changes, and feature updates
- Verify release dates, specs, and official announcements
- Find real URLs to official docs before linking to them
- Look up recent community tips, workarounds, and undocumented behaviors
- Check up-to-date comparison data between tools
- Search for academic papers/research: "[tool/concept] paper arxiv", "[concept] research study" — if a relevant study or technical report exists, cite it with its real URL (arXiv, ACL, NeurIPS, ICML, etc.)
- Search for official announcements: "[tool] blog announcement", "[tool] changelog 2025" — official blog posts and changelogs are high-authority sources
Search first, collect facts from results, THEN write. Cite what you find.

STEP 0.5 — SERP ANALYSIS (CRITICAL — this is the real differentiator):
Search for the keyword itself (e.g., "how to use [tool]", "[tool] tutorial"). Read the top results. Extract the COMPETITOR CONSENSUS — what every article already covers:
- What structure do they all use? (e.g., "What is X → Why → How → Compare → FAQ")
- What examples do they all repeat? (e.g., same demo from official docs)
- What talking points do they all share? (e.g., same 3 pros/cons, same use cases)
- What sections do they all include?

Record this consensus. It becomes your NEGATIVE CONSTRAINT — everything in the competitor consensus is OFF LIMITS as your article's main structure or primary angle. You must:
- Use a DIFFERENT organizational structure than the consensus
- Choose DIFFERENT examples than the ones every tutorial repeats
- Cover at least 1 angle that NONE of the top results cover
- If every competitor leads with "What is X", you do NOT lead with "What is X"

Output the consensus in the "competitor_consensus" JSON field so Pass 2 can verify you actually avoided it.

STEP 1 — COLLECT FACTS:
Combine web search results with your existing knowledge. List every verifiable fact:
- Official pricing, model names, version numbers, release dates
- Documented specs: context windows, token limits, API rate limits, supported features
- Real UI paths: menu locations, button names, setting options
- Known limitations, gotchas, common errors
- Comparisons: what each tool can/cannot do, official benchmarks
- Community-discovered tips, workarounds, undocumented features
- Academic research: papers, technical reports, studies that validate or explain the technology
- Official announcements: blog posts, changelogs, press releases from the product team

CLASSIFY each fact by source_type:
- "official_doc" — product documentation, help pages, API references
- "official_announcement" — blog posts, changelogs, press releases
- "academic" — research papers, technical reports, arXiv preprints
- "benchmark" — performance tests, comparison studies, industry reports
- "community" — Reddit, forums, Stack Overflow, user-shared tips
- "general" — common knowledge, doesn't need citation
Include the actual URL for official_doc, official_announcement, academic, and benchmark sources when available.

STEP 2 — FIND EDGE CASES (this is where real IG comes from):
Now that you have both the competitor consensus AND the facts, find the GAP — information that is TRUE (backed by facts) but NOT covered by competitors.

TIER 1 — Direct edge cases (best IG, try these first):
Search specifically for: "[tool] issues", "[tool] gotchas", "[tool] reddit problems", "[tool] limitations"
- Error messages or failure modes that tutorials never mention
- Config options or parameters that most guides skip
- Performance differences under non-default conditions (large files, slow networks, edge inputs)
- Pricing traps, quota limits, or throttling behaviors buried in fine print
- Workarounds the community discovered but no tutorial has formalized

TIER 2 — Cross-reference insights (if Tier 1 yields < 3):
Combine facts that EXIST individually in your collection but NO competitor has connected:
- Feature A + Feature B interact in a way that matters but is documented separately
- Official spec X has a practical implication Y that nobody spells out
- Comparing data point from Source A with data point from Source B reveals something
These are real facts recombined — not fabricated. Mark them: "type": "cross-reference"

TIER 3 — Honest unknowns (last resort, if Tier 1 + Tier 2 still < 3):
Point out what the official docs DON'T answer — questions that remain unanswered:
- "The docs list X pricing but don't clarify whether Y is included"
- "No official benchmark exists for Z scenario"
- "Community reports conflict: some see A, others see B — no resolution"
These are valuable BECAUSE they're honest. Mark them: "type": "unknown"
Do NOT fill unknowns with guesses. State the gap and move on.

REQUIREMENT: At least 3 edge cases total (any mix of tiers). Each must specify its tier.
If you cannot find even 3 across all tiers, set "edge_cases_insufficient": true in the JSON — this signals the keyword may not be worth publishing (too saturated, no new angle).

Output format for each edge case:
{"case": "specific description", "type": "direct|cross-reference|unknown", "backed_by_fact": "which fact"}

STEP 3 — WRITE BASED ONLY ON YOUR FACTS:
Every claim in the article must come from your collected facts. If a fact is not in your collection, it does not go in the article. No exceptions.

STEP 4 — LABEL SOURCES HONESTLY:
Every data point must be attributed:
- Official docs: "根据OpenAI官方文档", "according to Anthropic's pricing page"
- Official announcements: "根据[Company]的博客公告", "in their March 2025 changelog"
- Academic papers: "根据[Author/Institution]的研究", "the GPT-4 technical report shows", "a [University] study found"
- Benchmarks: "根据[Source]的基准测试", "LMSYS Chatbot Arena ranks it at..."
- Community knowledge: "社区用户反馈", "a common workaround found on Reddit"
- General knowledge: "based on standard API practices", "this is how most LLMs handle it"
- Uncertain: "this may vary by region", "as of early 2025" — be honest about what you're not sure of

=== WRITING STYLE ===

Write casually — like a smart friend explaining something over coffee. Not academic, not corporate, not textbook. Short paragraphs. Conversational.

1. Always use contractions (it's, won't, can't, I've, you'll). Tutorial, not thesis.
2. Vary sentence length naturally. Long explanations, then a short punch. Fragments work. One-word sentences work.
3. Keep paragraphs SHORT — 1-3 sentences each is ideal. Long paragraphs are a wall of text. Break them up.
4. Each section should differ in format — steps, paragraphs, code blocks, comparisons. Vary section lengths.
5. Use precise words over generic ones. "The response took 3 seconds" beats "the response was fast" — IF 3 seconds is real.
6. Don't pad. Every paragraph must teach something or move the reader forward.
7. Shorter articles are better. Say what you need to say and stop. 800 words that are all useful > 2000 words of padding.

=== SOURCE ATTRIBUTION IN TEXT ===

CRITICAL: Vary your citation sentence structures. Never use the same pattern twice in a row.

Mix these patterns (use at least 3 different ones per article):
- Lead with conclusion, source after: "The context window is 200K tokens (per Anthropic's docs)."
- Source woven mid-sentence: "According to the pricing page, it's $20/month — but there's a catch."
- Parenthetical: "You get 200K context (Anthropic docs) which sounds great until you hit the output limit."
- Casual discovery: "Turns out the free tier actually caps at 40 messages per 3 hours."
- Contrast pattern: "The docs say X, but in practice Y is what you'll actually see."
- Academic reference: "A Stanford study found that..." or "According to the GPT-4 technical report (arXiv:2303.08774)..."
- Official announcement: "In their March 2025 blog post, OpenAI announced..." or "The latest changelog notes that..."
- No-source common knowledge: Just state it without attribution when the fact is obvious.

BAD (all same pattern): "Product launched X. Product supports Y. Product includes Z." — this is a detection signal.

AUTHORITY LINKING (E-E-A-T booster — CRITICAL):
Include 2-5 inline <a> links to authoritative external URLs. Prioritize in this order:
1. Official documentation / product pages (highest priority — always include at least 1)
2. Academic papers (arXiv, conference papers, technical reports) — link when relevant research exists
3. Official blog posts / announcements / changelogs
4. Industry benchmark reports
- ONLY cite URLs you are confident exist (verified via web search)
- Use target="_blank" rel="noopener" attributes
- 0 links is better than a broken link
- At least 1 link MUST point to an official source (docs, blog, or announcement)
- If the topic involves an AI model/technique with a published paper, you MUST link to that paper

=== SECTION TRANSITIONS ===

Do NOT make every transition smooth and explanatory. Real writers sometimes just jump.

- 2-3 transitions per article can be abrupt — start the new section directly without explaining why you're switching topics. Readers can follow.
- 1-2 transitions can be a single short sentence: "Now for the interesting part." or "This is where it gets messy."
- Only 1-2 transitions should be the "logical bridge" type ("Now that we've covered X, let's look at Y").
- If a transition feels too jarring after an abrupt jump, use a casual connector: "Actually", "The catch is", "But here's where it gets weird", "So about that...", "One more thing." — these are fine. Just avoid the stiff academic ones (Moreover, Furthermore, Additionally, etc.).
- NEVER use the same transition style twice in a row.

=== FAQ STRUCTURE ===

The 3 FAQ answers MUST have different structures and lengths:
- One answer: extremely short (2-3 sentences, direct answer, done)
- One answer: medium, includes a specific scenario or example
- One answer: slightly longer, addresses a nuance or common misconception
Do NOT give all three the same rhythm (answer → condition → advice). Break the pattern.

=== GOOGLE SEO ===

- Title: 50-65 chars, keyword in first half, power word (Guide, How, Best)
- Excerpt: 145-160 chars, keyword included — factual content summary for cards and archive pages
- Meta Description (SEPARATE from excerpt): 145-160 chars — this appears in Google search results. Write it as a "pitch" (Google's own word) that convinces searchers THIS page is what they need:
  * MUST accurately reflect the article's actual content — Google ignores descriptions that don't match the page
  * Open with the article's CORE value proposition: what specific problem does it solve, or what will the reader gain?
  * Include a concrete detail from the article (a number, a specific finding, a gotcha) — specificity beats vagueness
  * Include keyword naturally in the first half
  * Use [brackets] or (parentheses) for supplementary info — proven CTR booster: "Claude Context Window [2025 Tested]"
  * Include a number when possible — "3 workarounds", "5 settings", "under 10 minutes"
  * End with a benefit or implied action — NOT "read more" but a reason to click: "Here's what actually works."
  * Do NOT just copy the first paragraph — write a standalone pitch based on the article's BEST content
  * Do NOT use clickbait that the article doesn't deliver on — this causes bounces and Google will override your description
  * GOOD: "Claude's 200K context sounds great - until you hit the hidden output limit. Here's the real cap, plus 3 workarounds that actually work. [2025 Tested]"
  * GOOD: "Most ChatGPT prompt guides rehash the same 5 tips. Here are 7 lesser-known techniques (backed by OpenAI's own research) that actually change output quality."
  * BAD: "This article explains how to use Claude's context window and its limitations." (← no hook, no specificity, no reason to click)
- Slug: short, keyword-rich, lowercase-with-dashes
- Keyword in first 100 words and in at least one H2
- H2 for main sections, H3 for sub-steps
- Mention 2-3 related topics naturally (internal linking opportunities)
- End with 3 FAQ Q&As (<h3> questions, <p> answers)
- 500-2500 words. Shorter is fine — 800-1200 words is the sweet spot. Don't pad.

=== BANNED PATTERNS ===

Never use: "In today's" / "In the ever-evolving" / "In this article, we will" / "It's important to note" / "Whether you're a beginner or" / "In conclusion" / "Let's dive in" / "Without further ado" / "Game changer" / "Take it to the next level"

Never use these words: harness, leverage (verb), delve, tapestry, landscape (metaphor), embark, empower, unlock, streamline, revolutionize, cutting-edge, robust, seamless, comprehensive, utilize, facilitate, optimize, innovative, transformative, paradigm, synergy, holistic, myriad

Never use: Moreover / Furthermore / Additionally / Consequently / Thus / Hence / In essence / Notably / Certainly / Undoubtedly / Essentially

=== OUTPUT ===

Respond with valid JSON only. No markdown fences, no extra text:
{
  "title": "SEO title (50-65 chars)",
  "slug": "url-slug",
  "excerpt": "Content summary for cards (145-160 chars)",
  "meta_description": "Search result pitch (145-160 chars) — core value + concrete detail + keyword + benefit. Must accurately reflect article content. DIFFERENT from excerpt.",
  "category": "category-slug",
  "difficulty": "beginner|intermediate|advanced",
  "competitor_consensus": {
    "common_structure": "What structure the top results all use (e.g., 'What is X → Why → How → Compare → FAQ')",
    "common_examples": ["example every tutorial repeats", "another repeated example"],
    "common_talking_points": ["talking point every article shares", "another one"],
    "our_differentiation": "How THIS article deliberately differs from the consensus"
  },
  "edge_cases": [
    {"case": "specific edge case", "type": "direct|cross-reference|unknown", "backed_by_fact": "which fact"},
    {"case": "another edge case", "type": "direct|cross-reference|unknown", "backed_by_fact": "its fact"}
  ],
  "edge_cases_insufficient": false,
  "facts": [
    {"fact": "the specific fact used", "source": "where it comes from", "source_type": "official_doc|official_announcement|academic|benchmark|community|general", "url": "https://... (if available)"},
    {"fact": "another fact", "source": "its source", "source_type": "academic", "url": "https://arxiv.org/abs/..."}
  ],
  "content": "Full HTML article",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
}

The "facts" array must list every key data point used in the article with its source. Minimum 5 facts. This is how we verify nothing was fabricated.
The "competitor_consensus" object is REQUIRED — it proves you analyzed the SERP before writing.
The "edge_cases" array must have at least 3 entries, each backed by a fact.

HTML structure:
1. 4-6 <h2> tutorial sections (with <h3>, <p>, <pre><code>, <ol>/<ul>, <strong>, <blockquote> pro tips 1-2, <em>)
2. Inline <a href="..." target="_blank" rel="noopener"> links to authoritative sources (2-5 total): official docs, academic papers, official blog posts, benchmark reports
3. 1 FAQ section: <h2> heading + 3 Q&As (<h3> question, <p> answer)

SECTION ELEMENT VARIETY (mandatory):
Each H2 section must use a DIFFERENT combination of elements. No two consecutive sections may follow the same component pattern. Use at least 4 of these 6 patterns across the article:
- P + P (prose only — explanation, narrative, opinion)
- P + <pre><code> + P (code analysis — show code, then explain)
- P + <blockquote> + P (quote/pro-tip commentary)
- P + <ul>/<ol> + P (list-based explanation)
- P + <table> + P (data comparison — specs, pricing, features)
- Short P only (ultra-short section — 2-3 sentences, no extra elements)
BAD: Section 1 uses P+list, Section 2 uses P+list, Section 3 uses P+list → all identical, rejected.
GOOD: Section 1 uses P+code+P, Section 2 is short-P-only, Section 3 uses P+table+P, Section 4 uses P+blockquote+P → 4 different patterns.

=== LANGUAGE ===

Write the entire article in LANGUAGE_PLACEHOLDER. All headings, paragraphs, FAQ, pro tips, and excerpt must be in LANGUAGE_PLACEHOLDER. Only code snippets, tool names, and technical terms may remain in English.
PROMPT;

        return str_replace( 'LANGUAGE_PLACEHOLDER', QWE_CONTENT_LANGUAGE, $prompt );
    }

    /**
     * Build the user prompt.
     */
    private static function build_user_prompt( $keyword, $keyword_type, $hint_category, $difficulty, $category_list ) {
        $type_context = '';
        if ( 'trending' === $keyword_type ) {
            $type_context = <<<'TCTX'

CONTEXT: This is a TRENDING/HOT topic right now. Write it as a timely piece — mention that this just dropped or is blowing up, reference community reactions, but still make it a hands-on tutorial people can follow. Don't write a news article; write a "here's what this means for you and how to actually use it" post.

TCTX;
        }

        $category_hint = '';
        if ( $hint_category ) {
            $category_hint = "Suggested category: {$hint_category} (but pick whichever truly fits best)\n";
        }

        // Randomize opening angle (7 options) — topic/reader-focused, not self-narrative.
        $angles = array(
            'Open with the core problem this topic solves — why should readers care right now?',
            'Open with a bold opinion that challenges conventional thinking about this topic.',
            'Open with the #1 mistake people make with this topic, then reverse-engineer the correct approach.',
            'Open with the end result — what the reader will achieve — then walk backwards through the steps.',
            'Open with a surprising fact or little-known detail about this topic that hooks the reader.',
            'Open with a common question readers have about this topic, then build the tutorial around answering it.',
            'Open with a quick comparison — two approaches to this topic, one clearly better — and explain why.',
        );
        $angle = $angles[ array_rand( $angles ) ];

        // Randomize article structure template (5 options) — prevents repetitive layouts.
        $structures = array(
            'STRUCTURE: Introduction (2 paragraphs) → Core concept explanation → Step-by-step walkthrough → Common pitfalls → Comparison with alternatives → FAQ',
            'STRUCTURE: Hook with a problem → Why existing solutions fall short → Your recommended approach (detailed) → Real-world example → Pro tips → FAQ',
            'STRUCTURE: Quick context → Hands-on tutorial (the bulk) → Common pitfalls to avoid → Performance/results → When NOT to use this → FAQ',
            'STRUCTURE: Key takeaway upfront → Background (brief) → Method A vs Method B → Detailed walkthrough of winner → Edge cases → FAQ',
            'STRUCTURE: Reader scenario → Tool/concept overview → Practical setup guide → Advanced usage → Honest limitations → FAQ',
        );
        $structure = $structures[ array_rand( $structures ) ];

        // Randomize tone emphasis (adds subtle article-to-article personality shift).
        $tones = array(
            'TONE: Slightly more analytical than usual — focus on feature comparisons, honest pros/cons, and verifiable facts.',
            'TONE: Slightly more narrative — tell the story of figuring this out, with specific moments.',
            'TONE: Slightly more direct and practical — minimal backstory, maximum actionable steps.',
            'TONE: Slightly more exploratory — weigh multiple options honestly, share your reasoning process.',
        );
        $tone = $tones[ array_rand( $tones ) ];

        $prompt = <<<'PROMPT'
Write a tutorial about: "{{KEYWORD}}"
{{TYPE_CONTEXT}}
{{CATEGORY_HINT}}Difficulty: {{DIFFICULTY}}

Categories (pick best match):
{{CATEGORY_LIST}}

{{ANGLE}}

{{STRUCTURE}}

{{TONE}}

REQUIREMENTS:
- SERP ANALYSIS FIRST: Search for this keyword. Read the top results. Extract what EVERY competitor covers (structure, examples, talking points). Record it in "competitor_consensus". This is your negative constraint — your article must deliberately differ.
- WEB SEARCH FOR FACTS: Search for the latest info on this topic. Look up current pricing, features, official docs, and recent updates.
- FACTS + EDGE CASES: Collect all verifiable facts. Then find the GAP — things that are true but competitors don't cover. List at least 3 edge cases in "edge_cases" (error modes, config gotchas, performance traps, pricing fine print, community workarounds).
- Every number, price, spec, and data point in the article MUST come from your collected facts. Do not invent anything.
- Label sources honestly in the text: "根据官方文档", "社区用户反馈", "测试表明" etc.
- Keyword in first 100 words, in one H2, and in the excerpt
- 500-2500 words. 800-1200 is the sweet spot. Don't pad
- 2-5 inline links to authoritative sources: official docs, academic papers, official blog posts (only if URL is verified via search)
- 1 <blockquote> pro tip
- 3 FAQ Q&As at the end (<h3> questions, <p> answers)
- End with a concrete next action, not a summary
- No banned words or patterns from system instructions

Respond ONLY with valid JSON.
PROMPT;

        return str_replace(
            array( '{{KEYWORD}}', '{{TYPE_CONTEXT}}', '{{CATEGORY_HINT}}', '{{DIFFICULTY}}', '{{CATEGORY_LIST}}', '{{ANGLE}}', '{{STRUCTURE}}', '{{TONE}}' ),
            array( $keyword, $type_context, $category_hint, $difficulty, $category_list, $angle, $structure, $tone ),
            $prompt
        );
    }

    /**
     * Build the review/revision system prompt (Pass 2).
     *
     * This prompt instructs the model to act as a quality reviewer:
     * 1. Score each E-E-A-T pillar
     * 2. Measure burstiness (sentence length variation) and perplexity (word unpredictability)
     * 3. Revise the article to fix any deficiencies
     * 4. Return the final revised article
     */
    private static function build_review_system_prompt() {
        $prompt = <<<'PROMPT'
You are a fact-checker and quality reviewer for QWE AI Academy (qwe.edu.pl). Your job is to verify that a draft article only contains real data, and meets quality standards.

=== YOUR TASK ===

You will receive a draft article with "facts", "competitor_consensus", and "edge_cases" arrays. You must:

1. CHECK GOOGLE CONTENT QUALITY (3 mandatory checks):
   a. ORIGINALITY + COMPETITOR DIFFERENTIATION — Read the "competitor_consensus" field. Does the article actually AVOID the common structure, common examples, and common talking points listed there? Does "our_differentiation" hold true in the actual content? Does at least one section cover an angle that competitors don't? If the article's structure matches the competitor consensus → FAIL regardless of other scores.
   b. FRESHNESS — Are all facts dated or qualified? Any fact without a clear date must have "as of [date]" or "this may have changed". Flag any potentially outdated pricing, model names, or features.
   c. INFORMATION RHYTHM — Does the article have at least 2 "breathing" paragraphs (analogy, reflection, open question) that don't directly solve a problem? Are the remaining paragraphs high-density and useful?
2. CHECK EDGE CASES — If "edge_cases_insufficient" is true, skip this keyword (return skip_keyword: true). Otherwise: verify at least 3 edge cases exist with valid types (direct/cross-reference/unknown). "direct" and "cross-reference" must be backed by facts. Check article body: each edge case must actually appear in the text. Missing or fabricated edge cases → FAIL.
3. VERIFY FACTS + AUTHORITY SOURCES: Cross-check every data point against the facts array. Flag unsupported claims. Also verify source diversity: at least 1 fact must have source_type "official_doc" or "official_announcement" with a real URL. If the topic involves an AI model/technique with a known paper, at least 1 fact should be "academic" type. Check that the article body contains 2-5 external <a> links to authoritative sources.
4. EVALUATE quality: E-E-A-T (4 pillars), burstiness, perplexity — score each 0-100
5. CHECK for banned words/phrases
6. CHECK AI FINGERPRINTS: Score the 4 fingerprint dimensions (FP1-FP4, each 0-100)
7. SCAN GENAI PHRASES: Find all overused GenAI phrases (5 categories in system instructions). Count them, replace every one.
8. AIGC SCAN: Read each paragraph, estimate overall AIGC rate (0-100%). Target: ≤ 50%. This is a HARD requirement.
9. DECIDE: All 10 quality scores >= 80 AND aigc_rate <= 50 AND genai_phrases_found == 0 (after replacement) AND originality/freshness/people-first all pass AND no unverified data AND no banned words → PASSES. Otherwise → REVISE.

=== FACT VERIFICATION (MOST IMPORTANT) ===

- Every number, price, date, spec, and data point in the article content must either:
  (a) Appear in the "facts" array with a credible source, OR
  (b) Be common knowledge that doesn't need citation (e.g., "ChatGPT is made by OpenAI")
- Flag any data point that appears fabricated or unsupported
- Check that sources are labeled honestly in the text ("根据官方文档", "社区用户反馈", etc.)
- Verify the article contains 3-5 genuinely useful insights, not just surface-level information

=== AUTHORITY SOURCE CHECK (E-E-A-T booster) ===

Check the "facts" array for source diversity (source_type field):
- MANDATORY: At least 1 fact with source_type "official_doc" or "official_announcement" AND a valid URL → this is the minimum bar for Authoritativeness
- ENCOURAGED: If the topic involves an AI model or technique with a known research paper (e.g., GPT-4, DALL-E, diffusion models, transformers, RAG), at least 1 fact should be "academic" type with a real paper URL (arXiv, ACL, etc.)
- Check that 2-5 external <a> links exist in the article HTML body, pointing to authoritative sources (official docs, papers, blog posts)
- If 0-1 external links → score Authoritativeness lower (max 60)
- If no official source in facts → score Authoritativeness lower (max 70)
- Broken or obviously fake URLs (made-up paths) → FAIL

=== E-E-A-T EVALUATION ===

**Experience (0-100)**: Practical testing examples, common mistakes, real comparisons, UI details
**Expertise (0-100)**: Explains WHY not just HOW, correct terminology, insider insights, references to underlying research or technical principles
**Authoritativeness (0-100)**: Source-backed claims with source_type diversity (official docs, academic papers, announcements), real external links (2-5), at least 1 official source URL. If topic has a known paper, citing it boosts score. Capped at 60 if < 2 external links, capped at 70 if no official source in facts.
**Trustworthiness (0-100)**: Facts labeled with sources, limitations acknowledged, no fabricated data, URLs verified via web search

=== BURSTINESS (Target: >= 80) ===

Sentence length variation. CV = std_dev / mean of sentence word counts. Score = min(CV * 100, 100).

=== PERPLEXITY (Target: >= 80) ===

Word unpredictability. Score based on: contractions, casual expressions, unexpected word choices, varied structures.

=== AI FINGERPRINT DETECTION (4 checks — Target: ALL must pass) ===

These 4 statistical patterns are the strongest AI detection signals. Check each one:

**FP1 — Information Density Uniformity (0-100, target >= 80)**
Read through the article paragraph by paragraph. Does it have the 2+ breathing paragraphs required by the INFORMATION RHYTHM rule?
- Score < 60: No breathing room anywhere. Every paragraph introduces new data. Reads like a reference doc.
- Score 60-79: Has 1 breathing paragraph but density is still too uniform.
- Score >= 80: Has 2+ breathing paragraphs (analogy, reflection, open question) naturally placed between dense sections.
FIX: See RHYTHM fix in revision rules.

**FP2 — Citation Pattern Uniformity (0-100, target >= 80)**
Look at every sentence that introduces external information. Do they all follow the same structure?
- Score < 60: All citations use "Product + verb + fact" pattern (e.g., "Docker launched X", "DevPod supports Y", "Claude includes Z").
- Score 60-79: Slight variation but still mostly the same structure.
- Score >= 80: Uses 3+ different citation patterns: parenthetical, mid-sentence source, conclusion-first, casual discovery ("turns out..."), contrast ("docs say X but..."), no-source common knowledge.
FIX: Rewrite citations to use at least 3 different sentence structures. Some lead with conclusion, some bury the source in parentheses, some use casual framing.

**FP3 — Transition Perfection (0-100, target >= 80)**
Check how sections connect. Is every transition a smooth logical bridge?
- Score < 60: Every section starts by referencing the previous one or explaining why we're moving on. Mechanical.
- Score 60-79: Mostly smooth with 1 abrupt transition.
- Score >= 80: Mix of styles — 1-2 abrupt jumps (section starts directly), 1-2 short casual transitions ("Now the interesting part."), only 1-2 logical bridges.
FIX: Make 2-3 section transitions abrupt — just start the new topic. Remove the "Now that we've covered X" connectors for most transitions. If an abrupt jump feels too jarring, use a casual connector ("Actually", "The catch is", "But here's where it gets weird") instead of a stiff academic one.

**FP4 — FAQ Structure Uniformity (0-100, target >= 80)**
Read the 3 FAQ answers. Do they all follow the same rhythm?
- Score < 60: All 3 answers have identical structure (e.g., answer → condition → advice).
- Score 60-79: Similar lengths with slight variation.
- Score >= 80: One answer is very short (2-3 sentences, done), one is medium with a scenario, one is longer with nuance. Different rhythms.
FIX: Rewrite FAQ answers with deliberately different lengths and structures. Make one blunt and short, one include a specific example, one address a misconception.

=== AIGC RATE SCAN (HARD LIMIT: ≤ 50%) ===

This is a MANDATORY gate. If aigc_rate > 50%, the article MUST be revised regardless of other scores.

HOW TO ESTIMATE AIGC RATE:
Read the article paragraph by paragraph. For each paragraph, ask: "Would an AI detection tool flag this as AI-written?" A paragraph is AI-flagged if it has 2+ of these signals:
- Every sentence in the paragraph follows subject-verb-object in the same cadence
- The paragraph opens with a topic sentence, then 2-3 supporting sentences, then a concluding remark (textbook essay structure)
- Hedging phrases: "It's worth noting that", "One thing to keep in mind", "What makes this particularly interesting"
- Listing pattern: "First... Second... Third..." or "One... Another... Finally..."
- Vague quantifiers: "significantly", "substantially", "considerable", "a number of", "a variety of"
- Mirror structure: consecutive sentences starting with the same word or phrase
- Overly balanced comparisons: "While X has A, Y offers B. However, X also provides C, whereas Y includes D."
- Summary sentences that repeat what was just said: "In short, ...", "Put simply, ..."
- Uniform sentence length (all sentences within 3-5 words of each other)

AIGC RATE = (number of AI-flagged paragraphs / total paragraphs) * 100

REWRITE TECHNIQUES (apply ONLY to AI-flagged paragraphs, preserve human-sounding ones):

1. BREAK THE CADENCE — Split/merge sentences. Start with "But"/"And"/"So". Use dashes.
   "X offers Y, which is significantly Z." → "Y. That's what X gives you — 4x the old version."

2. INJECT SPECIFICITY — Replace vague qualifiers with numbers or drop them.
   "quite significant improvement" → "Responses: 2 seconds now. Old model? More like 8."

3. FRONT-LOAD — Lead with the point, don't build up.
   "comparing the two, it becomes clear A has advantages" → "A is faster. Not by a little — 3x faster."

4. ADD HUMAN TEXTURE — Insert reactions, caveats, "hmm" moments.
   "free tier provides 100 messages, sufficient for most" → "100 messages/day free. Sounds like a lot, but one debugging session burns through that."

5. VARY PARAGRAPH OPENINGS — Never start 2 paragraphs the same way. Mix: questions, statements, fragments, numbers.
   BAD: "The API supports... The API also... The API provides..."
   GOOD: "The API supports... Pricing? Simple at... One catch: the rate limit..."

6. KILL BALANCED STRUCTURES — Make one point dominant, compress others.
   "While A has speed, B offers accuracy. A costs less, B has features." → "A: faster and cheaper. B: more accurate. For most people? A."

7. INCOMPLETE THOUGHTS & CALLBACKS — Reference earlier points, use parentheticals.
   "Remember that 200K limit? Here's where it matters."
   "Setup: ~5 min (assuming your API key works first try — mine didn't)."

=== GENAI OVERUSED PHRASES (SCAN & REPLACE) ===

GenAI models overuse certain phrases 10-100x more than humans. Scan the ENTIRE article and replace EVERY instance.

CAT 1 — FILLER HEDGES:
DELETE entirely: "It's worth noting/mentioning that", "It should be noted that", "It bears mentioning", "Interestingly enough", "It goes without saying", "Needless to say", "As you might expect"
SIMPLIFY: "One thing to keep in mind" → "Watch out:" | "What's particularly interesting is" / "What makes this stand out" → just state the fact | "Perhaps most importantly" → "The big one:" or just state it

CAT 2 — OVERUSED VERBS (replace with specific ones):
"navigate" → figure out/handle | "explore" (as "let's explore") → look at/try/test | "ensure" → make sure/check | "enhance" → improve, or say what changes | "boost" → state the specific gain | "foster" → build/create | "cater to" → work for/fit | "tailor" → customize/adjust | "underscore" → show, or delete | "coupled with" → plus/and | "spearhead" → lead/start | "pave the way" → make possible, or delete

CAT 3 — OVERUSED ADJECTIVES/ADVERBS (use numbers instead, or delete):
"crucial/vital/pivotal" → important/key, or delete | "remarkable/notable" → be specific (e.g. "3x faster") | "significant(ly)/substantial(ly)" → use a number | "arguably" → probably, or commit | "incredibly/exceptionally" → delete or use numbers | "particularly" → restructure sentence | "generally speaking" / "for the most part" → usually/mostly

CAT 4 — STRUCTURAL CLICHÉS:
DELETE: "Whether you're a X or Y", "This is where X comes in", "The beauty of X is", "The reality is", "With that in mind", "It all comes down to"
MAX 1/article: "While X, Y" at paragraph start, "That said"/"That being said", "Here's the thing", "The good news is"
REWRITE: "From X to Y" → be specific | "Not only X but also Y" → "X. And Y too." | "This is particularly true when" → "Especially when" | "When it comes to"/"In terms of" → "For" | "At the end of the day" → state conclusion | "On the flip side" → "But"

CAT 5 — OVERUSED NOUNS (use specific terms):
"realm" → area/space | "ecosystem" → tools/platform | "landscape" → name actual things | "framework" (metaphor) → approach/method | "journey" → process | "endeavor" → project/work | "plethora/multitude" → "a lot of" or a number | "implications" → say the actual effect | "nuances" → describe the specific nuance

RULES: Count all → "genai_phrases_found". Replace every one → "genai_phrases_replaced" array. If awkward, restructure the sentence. Phrases marked "delete" should be removed (sentence is stronger without them).

=== BANNED PATTERNS (ZERO TOLERANCE) ===

Words: harness, leverage, delve, tapestry, landscape (metaphor), embark, empower, unlock, streamline, revolutionize, cutting-edge, robust, seamless, comprehensive, utilize, facilitate, optimize, innovative, transformative, paradigm, synergy, holistic, myriad

Phrases: "In today's" / "In the ever-evolving" / "It's important to note" / "Whether you're a beginner or" / "In conclusion" / "Let's dive in" / "Game changer" / "Take it to the next level"

Transitions: Moreover / Furthermore / Additionally / Consequently / Thus / Hence / In essence / Notably / Certainly / Undoubtedly / Essentially

=== OUTPUT FORMAT ===

Respond with valid JSON only.

IF PASSES (all 10 quality scores >= 80, aigc_rate <= 50, 0 GenAI phrases remain, no issues):
{
  "review": {
    "passed": true,
    "experience_score": 0-100,
    "expertise_score": 0-100,
    "authority_score": 0-100,
    "trust_score": 0-100,
    "burstiness_score": 0-100,
    "perplexity_score": 0-100,
    "fp1_density_score": 0-100,
    "fp2_citation_score": 0-100,
    "fp3_transition_score": 0-100,
    "fp4_faq_score": 0-100,
    "aigc_rate": 0-100,
    "aigc_flagged_paragraphs": 0,
    "aigc_total_paragraphs": 0,
    "genai_phrases_found": 0,
    "facts_verified": true,
    "summary": "Brief explanation"
  }
}

IF NEEDS REVISION (any quality score < 80, OR aigc_rate > 50, OR GenAI phrases found, OR issues):
{
  "review": {
    "passed": false,
    "experience_score": 0-100,
    "expertise_score": 0-100,
    "authority_score": 0-100,
    "trust_score": 0-100,
    "burstiness_score": 0-100,
    "perplexity_score": 0-100,
    "fp1_density_score": 0-100,
    "fp2_citation_score": 0-100,
    "fp3_transition_score": 0-100,
    "fp4_faq_score": 0-100,
    "aigc_rate": 0-100,
    "aigc_flagged_paragraphs": 5,
    "aigc_total_paragraphs": 12,
    "aigc_rewrites": ["para 3: broke cadence + added specificity", "para 7: front-loaded key point"],
    "genai_phrases_found": 8,
    "genai_phrases_replaced": ["It's worth noting that → (deleted)", "navigate → figure out", "crucial → key"],
    "unverified_claims": ["claims in article not backed by facts array"],
    "fabricated_data_removed": ["fabricated items replaced"],
    "banned_words_removed": ["banned words replaced"],
    "fingerprint_fixes": ["specific fixes applied for FP1-FP4"],
    "issues_found": ["other issues"]
  },
  "article": {
    "title": "revised title",
    "slug": "revised-slug",
    "excerpt": "revised excerpt",
    "meta_description": "revised CTR-optimized description (hook + curiosity gap + benefit, 145-160 chars)",
    "category": "category-slug",
    "difficulty": "beginner|intermediate|advanced",
    "content": "revised HTML content",
    "tags": ["tag1", "tag2", "tag3", "tag4", "tag5"]
  }
}

When "passed" is true, do NOT include "article" — saves tokens.

=== LANGUAGE ===

Article language must remain LANGUAGE_PLACEHOLDER. All revisions in LANGUAGE_PLACEHOLDER.
PROMPT;

        return str_replace( 'LANGUAGE_PLACEHOLDER', QWE_CONTENT_LANGUAGE, $prompt );
    }

    /**
     * Build the review user prompt (Pass 2).
     *
     * @param array $article Draft article from Pass 1.
     * @return string User prompt containing the draft for review.
     */
    private static function build_review_user_prompt( $article ) {
        $draft_json = json_encode( $article, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

        $prompt = <<<'PROMPT'
Review this draft article. The article includes a "facts" array listing all data points and their sources.

DRAFT ARTICLE:
{{DRAFT_JSON}}

STEP 1 — GOOGLE CONTENT QUALITY CHECK (mandatory):
a. ORIGINALITY + COMPETITOR DIFFERENTIATION: Read the "competitor_consensus" field. Compare it against the actual article:
   - Does the article's structure match the "common_structure"? → FAIL
   - Does the article reuse examples from "common_examples"? → FAIL
   - Does the article only cover "common_talking_points" without going beyond? → FAIL
   - Does the "our_differentiation" claim actually hold true in the content? → if not, FAIL
   - Does at least one section cover an angle competitors don't? → if not, FAIL
b. FRESHNESS: Does every fact have a date or qualifier? Check each data point:
   - If a fact has a clear date → OK
   - If a fact has no date and could be outdated → must add "as of [date]" or "this may have changed"
   - If pricing/features/model names look outdated → FAIL
c. INFORMATION RHYTHM: Count the "breathing" paragraphs (analogy, reflection, open question that doesn't directly solve a problem). Must have at least 2. The rest must be high-density and useful.
   - If 0-1 breathing paragraphs → FAIL (too dense, reads like a reference doc)
   - If 4+ breathing paragraphs → FAIL (too much filler)
   - If non-breathing paragraphs contain fluff → FAIL

STEP 2 — EDGE CASE VERIFICATION:
- If "edge_cases_insufficient" is true → the keyword is too saturated to write about. Return {"review": {"passed": false, "skip_keyword": true, "reason": "No edge cases found — topic too saturated"}}.
- Read the "edge_cases" array. Must have at least 3 entries.
- Each must have a "type" field: "direct" (real gotcha), "cross-reference" (combined known facts), or "unknown" (honest gap in docs).
- "direct" and "cross-reference" types must reference a real fact from the "facts" array. "unknown" types must describe a specific unanswered question (not vague).
- Scan the article body: each edge case must actually appear in the content, not just in the JSON metadata.
- If edge_cases < 3 → FAIL
- If any edge case is in the JSON but missing from article body → FAIL
- If edge cases are generic (e.g., "it may not work sometimes") rather than specific → FAIL
- If a "direct" edge case has no supporting fact → FAIL (likely fabricated)

STEP 3 — VERIFY FACTS + AUTHORITY SOURCES:
- Cross-check every number, price, date, and spec in the article content against the "facts" array
- Flag any claim that is NOT supported by the facts list and is not common knowledge
- Check that sources are labeled in the text ("根据官方文档", "社区反馈", "根据[Author]的研究", etc.)
- AUTHORITY CHECK: Scan facts array source_type fields:
  * At least 1 must be "official_doc" or "official_announcement" with a URL → if missing, Authoritativeness capped at 70
  * If the topic has a known research paper, at least 1 should be "academic" → not mandatory but boosts score
- LINK CHECK: Count external <a> links in article body:
  * 2-5 links to authoritative sources (docs, papers, blogs) → OK
  * 0-1 links → Authoritativeness capped at 60, add more links during revision
  * Check that URLs look plausible (correct domain, reasonable path) — flag obviously fake URLs

STEP 4 — EVALUATE QUALITY:
- Score E-E-A-T (4 pillars, each 0-100)
- Score burstiness (sentence length variation, 0-100)
- Score perplexity (word unpredictability, 0-100)
- Check for banned words/phrases

STEP 5 — CHECK AI FINGERPRINTS (score each 0-100):
- FP1: Information density uniformity — is every paragraph packed with facts, or are there 1-2 breathing moments?
- FP2: Citation pattern uniformity — do all citations use the same "Product + verb + fact" structure, or are there 3+ different patterns?
- FP3: Transition perfection — are all section transitions smooth logical bridges, or is there a natural mix of abrupt jumps and casual connectors?
- FP4: FAQ structure uniformity — do all 3 FAQ answers follow the same rhythm, or do they have different lengths and structures?

STEP 6 — SCAN GENAI OVERUSED PHRASES:
- Scan the entire article for overused GenAI phrases (5 categories in system instructions: filler hedges, overused verbs, overused adjectives/adverbs, structural clichés, overused nouns).
- Count total instances found → "genai_phrases_found"
- Replace EVERY instance using the replacement rules from system instructions. List each replacement in "genai_phrases_replaced".
- Any article with genai_phrases_found > 0 must be revised (phrases must be replaced).

STEP 7 — AIGC RATE SCAN (HARD LIMIT ≤ 50%):
- Read each paragraph. Flag it as "AI-written" if it has 2+ signals: uniform cadence, textbook structure, hedging phrases, listing patterns, vague quantifiers, mirror structure, balanced comparisons, summary repetition, or uniform sentence length.
- Calculate: aigc_rate = (flagged paragraphs / total paragraphs) * 100
- If aigc_rate > 50%: article MUST be revised. Rewrite only the flagged paragraphs using techniques from system instructions.

STEP 8 — DECIDE:
- ALL 10 quality scores >= 80 AND aigc_rate <= 50 AND genai_phrases_found == 0 (after replacement) AND originality/freshness/people-first all pass AND facts verified AND no banned words → "passed": true
- ANY issue found → "passed": false, revise the article

REVISION RULES (only if passed = false):
- ORIGINALITY + COMPETITOR fix: Read "competitor_consensus". Reorganize the article to NOT match the common_structure. Replace any examples that overlap with common_examples. Add content that goes beyond common_talking_points. Ensure at least 1 section covers an angle competitors don't.
- EDGE CASE fix: If < 3 edge cases in article body, add them. Prefer "direct" type (real gotchas from facts). If not enough, use "cross-reference" (combine existing facts in a new way). Last resort: "unknown" (honest gaps in docs). Never fabricate — if a direct edge case has no fact backing it, downgrade to "unknown" and frame it as an open question.
- FRESHNESS fix: Add "as of [date]" or "this may have changed" to every undated fact. Update any clearly outdated info.
- RHYTHM fix: If < 2 breathing paragraphs, insert them (analogy, reflection, or open question). If > 3, remove extras. If non-breathing paragraphs have fluff, cut it.
- AUTHORITY fix: If < 2 external links, add links to official docs/papers/blogs from the facts array URLs. If no official source in facts, add attribution like "according to [Product]'s official documentation" for the most important claim. If a known research paper exists for the topic but wasn't cited, add a reference.
- Remove or replace any claim not backed by the facts array
- Fix failing quality areas — preserve what works
- Replace banned words with natural alternatives
- Replace ALL GenAI overused phrases using the 5-category dictionary from system instructions
- FP1 fix: Insert 1-2 short breathing paragraphs between dense sections
- FP2 fix: Rewrite citations using at least 3 different sentence structures
- FP3 fix: Make 2-3 section transitions abrupt, remove "now that we covered X" bridges. If logic jumps too hard, use casual connectors ("Actually", "The catch is", "But here's where it gets weird") — never stiff academic transitions
- FP4 fix: Give each FAQ answer a different length and structure
- AIGC fix: Rewrite ONLY the AI-flagged paragraphs. Do NOT touch human-sounding paragraphs. Use the 7 rewrite techniques from system instructions. List each rewrite in "aigc_rewrites".
- 500-2500 words (800-1200 sweet spot)
- Output valid JSON only
PROMPT;

        return str_replace( '{{DRAFT_JSON}}', $draft_json, $prompt );
    }

    /**
     * Run Pass 2: Evaluate the draft article and revise if needed.
     *
     * Returns:
     *   - The original draft (unchanged) if all scores >= 80 and no issues found
     *   - A revised article if any score < 80 or issues were found
     *   - false if the API call or parsing fails (caller falls back to Pass 1 draft)
     *
     * @param array $draft_article Article data from Pass 1.
     * @return array|false Article data (original or revised) or false on failure.
     */
    private static function review_and_revise( $draft_article ) {
        $system_prompt = self::build_review_system_prompt();
        $user_prompt = self::build_review_user_prompt( $draft_article );

        self::log( 'Pass 2: Sending draft for E-E-A-T evaluation' );

        $response = self::call_claude_api( $system_prompt, $user_prompt );

        if ( ! $response ) {
            self::log( 'Pass 2 API call failed — using Pass 1 draft as-is' );
            return false;
        }

        // Parse the review response.
        $response = trim( $response );
        $response = preg_replace( '/^```json\s*/i', '', $response );
        $response = preg_replace( '/\s*```$/', '', $response );

        $result = json_decode( $response, true );

        // Fallback: extract JSON object from surrounding text if direct parse fails.
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $json_start = strpos( $response, '{' );
            $json_end   = strrpos( $response, '}' );

            if ( $json_start !== false && $json_end !== false && $json_end > $json_start ) {
                $json_str = substr( $response, $json_start, $json_end - $json_start + 1 );
                $result   = json_decode( $json_str, true );

                if ( json_last_error() === JSON_ERROR_NONE ) {
                    self::log( 'Pass 2: JSON extracted from mixed text' );
                }
            }
        }

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            self::log( 'Pass 2 JSON parse error: ' . json_last_error_msg() );
            self::log( 'Pass 2 raw (first 500): ' . substr( $response, 0, 500 ) );
            return false;
        }

        // Review data must exist.
        if ( ! isset( $result['review'] ) ) {
            self::log( 'Pass 2 response missing "review" key' );
            return false;
        }

        $r = $result['review'];

        // Check if Pass 2 flagged keyword as too saturated.
        if ( ! empty( $r['skip_keyword'] ) ) {
            self::log( 'Pass 2: Keyword flagged as too saturated — ' . ( $r['reason'] ?? 'no edge cases' ) );
            return false;
        }

        // Log the review scores.
        self::log( sprintf(
            'Pass 2 scores — Experience: %s, Expertise: %s, Authority: %s, Trust: %s, Burstiness: %s, Perplexity: %s',
            $r['experience_score'] ?? '?',
            $r['expertise_score'] ?? '?',
            $r['authority_score'] ?? '?',
            $r['trust_score'] ?? '?',
            $r['burstiness_score'] ?? '?',
            $r['perplexity_score'] ?? '?'
        ) );
        self::log( sprintf(
            'Pass 2 fingerprints — FP1 Density: %s, FP2 Citation: %s, FP3 Transition: %s, FP4 FAQ: %s',
            $r['fp1_density_score'] ?? '?',
            $r['fp2_citation_score'] ?? '?',
            $r['fp3_transition_score'] ?? '?',
            $r['fp4_faq_score'] ?? '?'
        ) );
        self::log( sprintf(
            'Pass 2 AIGC — Rate: %s%%, Flagged: %s/%s paragraphs',
            $r['aigc_rate'] ?? '?',
            $r['aigc_flagged_paragraphs'] ?? '?',
            $r['aigc_total_paragraphs'] ?? '?'
        ) );
        self::log( sprintf(
            'Pass 2 GenAI phrases found: %s',
            $r['genai_phrases_found'] ?? '?'
        ) );

        $passed = ! empty( $r['passed'] );

        // Case 1: Article passed all checks — use original draft as-is.
        if ( $passed ) {
            self::log( 'Pass 2 result: PASSED — all scores >= 80, no issues found, using original article' );
            if ( ! empty( $r['summary'] ) ) {
                self::log( 'Pass 2 summary: ' . $r['summary'] );
            }
            return $draft_article;
        }

        // Case 2: Article failed — needs revision.
        self::log( 'Pass 2 result: FAILED — revision needed' );

        if ( ! empty( $r['unverified_claims'] ) ) {
            self::log( 'Unverified claims: ' . implode( '; ', $r['unverified_claims'] ) );
        }
        if ( ! empty( $r['issues_found'] ) ) {
            self::log( 'Issues found: ' . implode( '; ', $r['issues_found'] ) );
        }
        if ( ! empty( $r['fabricated_data_removed'] ) ) {
            self::log( 'Fabricated data removed: ' . implode( ', ', $r['fabricated_data_removed'] ) );
        }
        if ( ! empty( $r['banned_words_removed'] ) ) {
            self::log( 'Banned words removed: ' . implode( ', ', $r['banned_words_removed'] ) );
        }
        if ( ! empty( $r['fingerprint_fixes'] ) ) {
            self::log( 'Fingerprint fixes: ' . implode( '; ', $r['fingerprint_fixes'] ) );
        }
        if ( ! empty( $r['aigc_rewrites'] ) ) {
            self::log( 'AIGC rewrites (' . count( $r['aigc_rewrites'] ) . '): ' . implode( '; ', $r['aigc_rewrites'] ) );
        }
        if ( ! empty( $r['genai_phrases_replaced'] ) ) {
            self::log( 'GenAI phrases replaced (' . count( $r['genai_phrases_replaced'] ) . '): ' . implode( '; ', $r['genai_phrases_replaced'] ) );
        }

        // Extract the revised article.
        if ( ! isset( $result['article'] ) ) {
            self::log( 'Pass 2 failed but no revised article provided — using Pass 1 draft' );
            return false;
        }

        $revised = $result['article'];

        // Validate required fields.
        $required = array( 'title', 'slug', 'excerpt', 'category', 'difficulty', 'content' );
        foreach ( $required as $field ) {
            if ( empty( $revised[ $field ] ) ) {
                self::log( "Pass 2 revised article missing required field: {$field} — using Pass 1 draft" );
                return false;
            }
        }

        if ( ! isset( $revised['tags'] ) || ! is_array( $revised['tags'] ) ) {
            $revised['tags'] = $draft_article['tags'] ?? array();
        }

        self::log( 'Pass 2: Revision complete — using revised article' );
        return $revised;
    }

    /**
     * Call the Claude API.
     *
     * Supports the server-side web_search tool. When enabled, Claude can
     * search the web during generation. The response may contain mixed
     * content blocks (text, server_tool_use, web_search_tool_result).
     * If the API returns pause_turn, the conversation is continued
     * automatically (up to 3 rounds).
     *
     * @param string $system_prompt   System message.
     * @param string $user_prompt     User message.
     * @param bool   $use_web_search  Whether to enable web_search tool for this call.
     * @return string|false           Raw response text or false.
     */
    private static function call_claude_api( $system_prompt, $user_prompt, $use_web_search = false ) {
        $api_key = QWE_CLAUDE_API_KEY;
        $model = QWE_CLAUDE_MODEL;

        if ( empty( $api_key ) ) {
            self::log( 'Claude API key not configured' );
            return false;
        }

        $messages = array(
            array(
                'role'    => 'user',
                'content' => $user_prompt,
            ),
        );

        // Structure system prompt for prompt caching.
        // Cached system prompts cost ~90% less on input tokens.
        // TTL is 5 min — benefits 2nd/3rd articles in the same run.
        $payload_data = array(
            'model'      => $model,
            'max_tokens' => 8192,
            'system'     => array(
                array(
                    'type'          => 'text',
                    'text'          => $system_prompt,
                    'cache_control' => array( 'type' => 'ephemeral' ),
                ),
            ),
            'messages'   => $messages,
        );

        // Add web_search tool if enabled globally and requested for this call.
        $web_search_active = $use_web_search
            && defined( 'QWE_WEB_SEARCH_ENABLED' ) && QWE_WEB_SEARCH_ENABLED;

        if ( $web_search_active ) {
            $max_uses = defined( 'QWE_WEB_SEARCH_MAX_USES' ) ? (int) QWE_WEB_SEARCH_MAX_USES : 5;
            $payload_data['tools'] = array(
                array(
                    'type'     => 'web_search_20250305',
                    'name'     => 'web_search',
                    'max_uses' => $max_uses,
                ),
            );
            self::log( "Web search enabled (max {$max_uses} searches)" );
        }

        // Timeout: longer when web search is active (searches take time).
        $timeout = $web_search_active ? 180 : 120;

        // Collect text from all rounds (pause_turn may split the response).
        $all_text_parts    = array();
        $total_search_count = 0;
        $max_continuations = 3;

        for ( $round = 0; $round <= $max_continuations; $round++ ) {
            $payload = json_encode( $payload_data, JSON_UNESCAPED_UNICODE );

            $ch = curl_init( 'https://api.anthropic.com/v1/messages' );
            curl_setopt_array( $ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => array(
                    'Content-Type: application/json',
                    'x-api-key: ' . $api_key,
                    'anthropic-version: 2023-06-01',
                    'anthropic-beta: prompt-caching-2024-07-31',
                ),
                CURLOPT_TIMEOUT        => $timeout,
            ) );

            $response  = curl_exec( $ch );
            $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            $error     = curl_error( $ch );
            curl_close( $ch );

            if ( $error ) {
                self::log( "cURL error: {$error}" );
                return false;
            }

            if ( 200 !== $http_code ) {
                self::log( "API HTTP {$http_code}: " . substr( $response, 0, 500 ) );
                return false;
            }

            $data = json_decode( $response, true );

            if ( ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
                self::log( 'Unexpected API response structure' );
                return false;
            }

            // Extract text blocks and log web searches from this round.
            foreach ( $data['content'] as $block ) {
                $block_type = $block['type'] ?? '';

                if ( 'text' === $block_type ) {
                    $all_text_parts[] = $block['text'];
                }

                if ( 'server_tool_use' === $block_type && 'web_search' === ( $block['name'] ?? '' ) ) {
                    $total_search_count++;
                    $query = $block['input']['query'] ?? '?';
                    self::log( "Web search #{$total_search_count}: \"{$query}\"" );
                }
            }

            // Check if the API paused mid-turn (long-running web search).
            $stop_reason = $data['stop_reason'] ?? 'end_turn';

            if ( 'pause_turn' === $stop_reason && $round < $max_continuations ) {
                self::log( 'API returned pause_turn — continuing (round ' . ( $round + 1 ) . ')' );

                // Append the assistant's partial response and ask to continue.
                $payload_data['messages'][] = array(
                    'role'    => 'assistant',
                    'content' => $data['content'],
                );
                $payload_data['messages'][] = array(
                    'role'    => 'user',
                    'content' => 'Continue.',
                );
                continue;
            }

            // Done — either end_turn or max continuations reached.
            break;
        }

        if ( $total_search_count > 0 ) {
            self::log( "Total web searches performed: {$total_search_count}" );
        }

        $full_text = implode( '', $all_text_parts );

        if ( empty( $full_text ) ) {
            self::log( 'No text content in API response' );
            return false;
        }

        return $full_text;
    }

    /**
     * Parse the AI response JSON into article data.
     *
     * When web_search is active, the response may contain non-JSON text
     * (e.g., Claude's thinking before/after searches) alongside the JSON.
     * This method tries direct parse first, then falls back to extracting
     * the JSON object from the surrounding text.
     *
     * @param string $response Raw JSON string from API.
     * @return array|false     Parsed article data or false.
     */
    private static function parse_response( $response ) {
        // Clean potential markdown code fences.
        $response = trim( $response );
        $response = preg_replace( '/^```json\s*/i', '', $response );
        $response = preg_replace( '/\s*```$/', '', $response );

        $article = json_decode( $response, true );

        // Fallback: if the full text isn't valid JSON (e.g., web search added
        // thinking text around it), extract the JSON object by finding the
        // outermost { ... } braces.
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $json_start = strpos( $response, '{' );
            $json_end   = strrpos( $response, '}' );

            if ( $json_start !== false && $json_end !== false && $json_end > $json_start ) {
                $json_str = substr( $response, $json_start, $json_end - $json_start + 1 );
                $article  = json_decode( $json_str, true );

                if ( json_last_error() === JSON_ERROR_NONE ) {
                    self::log( 'JSON extracted from mixed text (web search thinking text stripped)' );
                }
            }
        }

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            self::log( 'JSON parse error: ' . json_last_error_msg() );
            self::log( 'Raw response (first 500 chars): ' . substr( $response, 0, 500 ) );
            return false;
        }

        // Validate required fields.
        $required = array( 'title', 'slug', 'excerpt', 'category', 'difficulty', 'content' );
        foreach ( $required as $field ) {
            if ( empty( $article[ $field ] ) ) {
                self::log( "Missing required field: {$field}" );
                return false;
            }
        }

        // Ensure tags is an array.
        if ( ! isset( $article['tags'] ) || ! is_array( $article['tags'] ) ) {
            $article['tags'] = array();
        }

        return $article;
    }

    // ==========================================================
    // Post-Processing: Strip AI Fingerprints
    // ==========================================================

    /**
     * Remove AI-generated invisible characters, normalize typography,
     * and clean statistical fingerprints from text.
     *
     * AI models (ChatGPT, Claude, etc.) embed invisible Unicode characters
     * in their output. Detectors like GPTZero, Originality.AI use these
     * as signals. This method strips them all.
     *
     * @param string $text Raw AI-generated text.
     * @return string Cleaned text.
     */
    public static function clean_ai_fingerprint( $text ) {
        if ( empty( $text ) ) {
            return $text;
        }

        // Step 1: Remove invisible Unicode characters (AI watermarks).
        $text = self::strip_invisible_unicode( $text );

        // Step 2: Normalize typography (smart quotes, dashes, spaces).
        $text = self::normalize_typography( $text );

        // Step 3: Normalize homoglyphs (Cyrillic/Greek lookalikes → Latin).
        $text = self::normalize_homoglyphs( $text );

        // Step 4: Clean whitespace patterns.
        $text = self::clean_whitespace( $text );

        return $text;
    }

    /**
     * Strip all invisible Unicode characters that AI models inject.
     * These are the primary "digital fingerprints" detectors look for.
     */
    private static function strip_invisible_unicode( $text ) {
        // Zero-width characters (most common AI artifacts).
        $text = preg_replace( '/[\x{200B}\x{200C}\x{200D}\x{200E}\x{200F}]/u', '', $text );

        // Byte Order Marks.
        $text = preg_replace( '/[\x{FEFF}\x{FFFE}]/u', '', $text );

        // Word joiners and invisible separators.
        $text = preg_replace( '/[\x{2060}\x{2061}\x{2062}\x{2063}\x{2064}]/u', '', $text );

        // Soft hyphen.
        $text = preg_replace( '/\x{00AD}/u', '', $text );

        // Bidirectional formatting characters.
        $text = preg_replace( '/[\x{202A}-\x{202E}]/u', '', $text );

        // Bidirectional isolate characters (Unicode 6.3+).
        $text = preg_replace( '/[\x{2066}-\x{2069}]/u', '', $text );

        // Interlinear annotation anchors.
        $text = preg_replace( '/[\x{FFF9}-\x{FFFB}]/u', '', $text );

        // Variation selectors (VS1-VS16) — used for glyph variants.
        $text = preg_replace( '/[\x{FE00}-\x{FE0F}]/u', '', $text );

        // Tag characters (U+E0001-U+E007F) — sometimes used for invisible tagging.
        $text = preg_replace( '/[\x{E0001}-\x{E007F}]/u', '', $text );

        // Object replacement and replacement characters.
        $text = preg_replace( '/[\x{FFFC}\x{FFFD}]/u', '', $text );

        return $text;
    }

    /**
     * Normalize AI typography patterns.
     *
     * AI models use Unicode fancy characters where humans type ASCII.
     * Normalizing these removes statistical patterns detectors measure.
     */
    private static function normalize_typography( $text ) {
        // Smart quotes → straight quotes (AI loves smart quotes, humans often don't).
        $text = str_replace(
            array( "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99" ),
            array( '"', '"', "'", "'" ),
            $text
        );

        // Em dash (U+2014) → spaced hyphen (more common in human casual writing).
        $text = str_replace( "\xE2\x80\x94", ' - ', $text );

        // En dash (U+2013) → hyphen.
        $text = str_replace( "\xE2\x80\x93", '-', $text );

        // Horizontal ellipsis (U+2026) → three dots.
        $text = str_replace( "\xE2\x80\xA6", '...', $text );

        // Non-breaking space (U+00A0) → regular space.
        $text = str_replace( "\xC2\xA0", ' ', $text );

        // Figure space (U+2007), punctuation space (U+2008), thin space (U+2009),
        // hair space (U+200A), narrow no-break space (U+202F), medium math space (U+205F).
        $text = preg_replace( '/[\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $text );

        // Minus sign (U+2212) → hyphen-minus.
        $text = str_replace( "\xE2\x88\x92", '-', $text );

        // Bullet (U+2022) — keep in HTML lists, but normalize outside.
        // Prime marks → quotes.
        $text = str_replace( "\xE2\x80\xB2", "'", $text ); // Prime.
        $text = str_replace( "\xE2\x80\xB3", '"', $text );  // Double prime.

        return $text;
    }

    /**
     * Normalize homoglyph characters.
     *
     * AI sometimes uses Cyrillic, Greek, or mathematical characters
     * that look identical to Latin letters but have different code points.
     * Detectors flag these as manipulation signals.
     */
    private static function normalize_homoglyphs( $text ) {
        // Cyrillic → Latin (most common homoglyphs).
        $cyrillic_map = array(
            "\xD0\x90" => 'A',  // А → A
            "\xD0\x92" => 'B',  // В → B
            "\xD0\xA1" => 'C',  // С → C
            "\xD0\x95" => 'E',  // Е → E
            "\xD0\x9D" => 'H',  // Н → H
            "\xD0\x9A" => 'K',  // К → K
            "\xD0\x9C" => 'M',  // М → M
            "\xD0\x9E" => 'O',  // О → O
            "\xD0\xA0" => 'P',  // Р → P
            "\xD0\xA2" => 'T',  // Т → T
            "\xD0\xA5" => 'X',  // Х → X
            "\xD0\xB0" => 'a',  // а → a
            "\xD1\x81" => 'c',  // с → c
            "\xD0\xB5" => 'e',  // е → e
            "\xD0\xBE" => 'o',  // о → o
            "\xD1\x80" => 'p',  // р → p
            "\xD1\x85" => 'x',  // х → x
            "\xD1\x83" => 'y',  // у → y
        );

        $text = str_replace( array_keys( $cyrillic_map ), array_values( $cyrillic_map ), $text );

        // Greek → Latin (common ones).
        $greek_map = array(
            "\xCE\x91" => 'A',  // Α → A
            "\xCE\x92" => 'B',  // Β → B
            "\xCE\x95" => 'E',  // Ε → E
            "\xCE\x96" => 'Z',  // Ζ → Z
            "\xCE\x97" => 'H',  // Η → H
            "\xCE\x99" => 'I',  // Ι → I
            "\xCE\x9A" => 'K',  // Κ → K
            "\xCE\x9C" => 'M',  // Μ → M
            "\xCE\x9D" => 'N',  // Ν → N
            "\xCE\x9F" => 'O',  // Ο → O
            "\xCE\xA1" => 'P',  // Ρ → P
            "\xCE\xA4" => 'T',  // Τ → T
            "\xCE\xA5" => 'Y',  // Υ → Y
            "\xCE\xA7" => 'X',  // Χ → X
            "\xCE\xBF" => 'o',  // ο → o
        );

        $text = str_replace( array_keys( $greek_map ), array_values( $greek_map ), $text );

        // Fullwidth Latin → normal Latin (U+FF01-U+FF5E → U+0021-U+007E).
        $text = preg_replace_callback( '/[\x{FF01}-\x{FF5E}]/u', function( $m ) {
            $cp = mb_ord( $m[0], 'UTF-8' );
            return chr( $cp - 0xFF01 + 0x21 );
        }, $text );

        return $text;
    }

    /**
     * Clean whitespace patterns that AI models produce.
     *
     * AI text has unnaturally consistent spacing. This normalizes it.
     */
    private static function clean_whitespace( $text ) {
        // Multiple spaces → single space (but preserve HTML tags).
        $text = preg_replace( '/(?<=>)\s+(?=<)/', '', $text ); // Between HTML tags.
        $text = preg_replace( '/ {2,}/', ' ', $text );          // Multiple spaces in text.

        // Remove trailing spaces on lines.
        $text = preg_replace( '/[ \t]+$/m', '', $text );

        // Normalize line endings.
        $text = str_replace( "\r\n", "\n", $text );
        $text = str_replace( "\r", "\n", $text );

        // Remove excessive blank lines (3+ → 2).
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }

    // ==========================================================
    // Post-Processing: Banned Word Check (zero API cost)
    // ==========================================================

    /**
     * Replace banned words/phrases in text as a PHP-level safety net.
     *
     * Catches any banned words that Pass 2 missed. Runs after generation
     * at zero API cost. Protects code blocks and HTML attributes.
     *
     * @param string $text Article HTML content.
     * @return string Cleaned text.
     */
    private static function check_banned_words( $text ) {
        if ( empty( $text ) ) {
            return $text;
        }

        // Protect <code> and <pre> blocks from replacement.
        $protected = array();
        $counter   = 0;
        $text = preg_replace_callback( '/<(code|pre)[^>]*>.*?<\/\1>/si', function( $m ) use ( &$protected, &$counter ) {
            $key = "___PROTECTED_{$counter}___";
            $protected[ $key ] = $m[0];
            $counter++;
            return $key;
        }, $text );

        // Banned words → safe replacements (whole word, case-insensitive).
        $banned_words = array(
            'harness'         => 'use',
            'leverage'        => 'use',
            'delve'           => 'look into',
            'tapestry'        => 'mix',
            'embark'          => 'start',
            'empower'         => 'help',
            'unlock'          => 'enable',
            'streamline'      => 'simplify',
            'revolutionize'   => 'change',
            'cutting-edge'    => 'latest',
            'robust'          => 'strong',
            'seamless'        => 'smooth',
            'comprehensive'   => 'complete',
            'utilize'         => 'use',
            'facilitate'      => 'help',
            'innovative'      => 'new',
            'transformative'  => 'important',
            'paradigm'        => 'model',
            'synergy'         => 'combination',
            'holistic'        => 'complete',
            'myriad'          => 'many',
        );

        // Banned phrases (longer strings first).
        $banned_phrases = array(
            'In the ever-evolving'       => 'As',
            "It's important to note"     => 'Note:',
            "Whether you're a beginner or" => '',
            'Take it to the next level'  => 'improve',
            "Let's dive in"              => "Let's start",
            'Game changer'               => 'Major improvement',
            'In conclusion'              => 'To sum up',
        );

        // Banned transitions (sentence-start only).
        $banned_transitions = array(
            'Moreover'      => 'Also',
            'Furthermore'   => 'Also',
            'Additionally'  => 'Also',
            'Consequently'  => 'So',
            'Thus'          => 'So',
            'Hence'         => 'So',
            'In essence'    => '',
            'Notably'       => '',
            'Certainly'     => '',
            'Undoubtedly'   => '',
            'Essentially'   => '',
        );

        // 1. Replace phrases first (longer matches).
        foreach ( $banned_phrases as $phrase => $replacement ) {
            $text = str_ireplace( $phrase, $replacement, $text );
        }

        // 2. Replace "In today's" pattern (case-insensitive).
        $text = preg_replace( "/\bIn today's\b/i", 'Currently,', $text );

        // 3. Replace transitions at sentence boundaries.
        foreach ( $banned_transitions as $word => $replacement ) {
            $pattern = '/(?<=^|[.!?]\s|>\s?)' . preg_quote( $word, '/' ) . '\b/im';
            $text = preg_replace( $pattern, $replacement, $text );
        }

        // 4. Replace individual banned words (whole word).
        foreach ( $banned_words as $word => $replacement ) {
            $text = preg_replace( '/\b' . preg_quote( $word, '/' ) . '\b/i', $replacement, $text );
        }

        // Restore protected blocks.
        foreach ( $protected as $key => $value ) {
            $text = str_replace( $key, $value, $text );
        }

        return $text;
    }

    // ==========================================================
    // Logger
    // ==========================================================

    /**
     * Simple log.
     */
    private static function log( $message ) {
        $time = date( 'Y-m-d H:i:s' );
        $log = "[{$time}] GENERATOR: {$message}\n";
        file_put_contents( __DIR__ . '/data/auto_publish.log', $log, FILE_APPEND );
    }
}
