<?php
/**
 * Sample Content Generator for QWE AI Academy.
 *
 * Generates tutorial posts, categories, and regular posts
 * for demonstration purposes.
 *
 * Usage: Add ?qwe_generate_sample=1 to any admin page URL
 * Only works for administrators.
 *
 * @package QWE_Developer_Flavor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generate sample content on admin request.
 */
function qwe_generate_sample_content() {
    if ( ! isset( $_GET['qwe_generate_sample'] ) || '1' !== $_GET['qwe_generate_sample'] ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'qwe_generate_sample' ) ) {
        // Generate nonce link for admin notice.
        add_action( 'admin_notices', 'qwe_sample_content_nonce_notice' );
        return;
    }

    // Create tutorial categories.
    $categories = array(
        'ChatGPT & LLMs'     => 'Learn how to use ChatGPT, Claude, Gemini, and other large language models effectively for work and creativity.',
        'AI Art & Design'     => 'Master AI image generation with Midjourney, DALL-E, Stable Diffusion, and other creative AI tools.',
        'AI Coding'           => 'Use AI-powered coding assistants like GitHub Copilot, Cursor, and Claude to write better code faster.',
        'AI for Business'     => 'Leverage AI tools to boost productivity, automate tasks, and grow your business.',
        'AI Writing'          => 'Create compelling content with AI writing assistants. From blog posts to marketing copy.',
        'AI Video & Audio'    => 'Generate and edit video and audio content using AI tools like Runway, ElevenLabs, and Suno.',
        'AI Data Analysis'    => 'Analyze data, create visualizations, and extract insights using AI-powered tools.',
    );

    $cat_ids = array();
    foreach ( $categories as $name => $desc ) {
        $term = wp_insert_term( $name, 'tutorial_category', array( 'description' => $desc ) );
        if ( ! is_wp_error( $term ) ) {
            $cat_ids[ $name ] = $term['term_id'];
        } elseif ( isset( $term->error_data['term_exists'] ) ) {
            $cat_ids[ $name ] = $term->error_data['term_exists'];
        }
    }

    // Tutorial posts data.
    $tutorials = array(
        array(
            'title'      => 'Getting Started with ChatGPT: A Complete Beginner Guide',
            'content'    => qwe_sample_tutorial_content_chatgpt(),
            'excerpt'    => 'Learn how to use ChatGPT from scratch. This beginner-friendly guide covers everything from creating an account to writing effective prompts.',
            'category'   => 'ChatGPT & LLMs',
            'difficulty' => 'beginner',
            'featured'   => '1',
        ),
        array(
            'title'      => '10 Advanced ChatGPT Prompting Techniques You Need to Know',
            'content'    => qwe_sample_tutorial_content_prompts(),
            'excerpt'    => 'Go beyond basic prompts. Learn chain-of-thought, few-shot learning, role-playing, and other advanced techniques to get better results from ChatGPT.',
            'category'   => 'ChatGPT & LLMs',
            'difficulty' => 'intermediate',
            'featured'   => '1',
        ),
        array(
            'title'      => 'How to Create Stunning AI Art with Midjourney',
            'content'    => qwe_sample_tutorial_content_midjourney(),
            'excerpt'    => 'A step-by-step guide to creating beautiful AI-generated images using Midjourney. Learn prompt engineering for visual art.',
            'category'   => 'AI Art & Design',
            'difficulty' => 'beginner',
            'featured'   => '1',
        ),
        array(
            'title'      => 'AI-Powered Coding: Getting Started with GitHub Copilot',
            'content'    => qwe_sample_tutorial_content_copilot(),
            'excerpt'    => 'Learn how to set up and use GitHub Copilot to write code faster. Includes tips for getting the best suggestions.',
            'category'   => 'AI Coding',
            'difficulty' => 'beginner',
            'featured'   => '1',
        ),
        array(
            'title'      => 'Writing Blog Posts with AI: A Practical Workflow',
            'content'    => qwe_sample_tutorial_content_writing(),
            'excerpt'    => 'Discover a practical workflow for using AI to research, outline, draft, and polish blog posts while maintaining your unique voice.',
            'category'   => 'AI Writing',
            'difficulty' => 'intermediate',
            'featured'   => '1',
        ),
        array(
            'title'      => 'Using AI for Data Analysis: From Spreadsheets to Insights',
            'content'    => qwe_sample_tutorial_content_data(),
            'excerpt'    => 'Learn how to use AI tools to analyze data, create charts, and find patterns in your datasets without programming knowledge.',
            'category'   => 'AI Data Analysis',
            'difficulty' => 'intermediate',
            'featured'   => '1',
        ),
        array(
            'title'      => 'Create Professional Videos with AI: Runway ML Guide',
            'content'    => qwe_sample_tutorial_content_video(),
            'excerpt'    => 'Learn how to use Runway ML to generate, edit, and enhance video content using AI. Perfect for content creators.',
            'category'   => 'AI Video & Audio',
            'difficulty' => 'intermediate',
            'featured'   => '0',
        ),
        array(
            'title'      => 'Automate Your Business Tasks with AI: A Practical Guide',
            'content'    => qwe_sample_tutorial_content_business(),
            'excerpt'    => 'Discover how to use AI tools to automate repetitive business tasks, from email management to document processing.',
            'category'   => 'AI for Business',
            'difficulty' => 'beginner',
            'featured'   => '0',
        ),
        array(
            'title'      => 'Stable Diffusion: Local Installation and First Images',
            'content'    => qwe_sample_tutorial_content_sd(),
            'excerpt'    => 'Set up Stable Diffusion on your own computer and generate your first AI images. Complete installation guide for Windows and Mac.',
            'category'   => 'AI Art & Design',
            'difficulty' => 'advanced',
            'featured'   => '0',
        ),
        array(
            'title'      => 'Claude AI vs ChatGPT: Which One Should You Use?',
            'content'    => qwe_sample_tutorial_content_comparison(),
            'excerpt'    => 'A detailed comparison of Claude and ChatGPT. Learn the strengths of each AI assistant and when to use which one.',
            'category'   => 'ChatGPT & LLMs',
            'difficulty' => 'beginner',
            'featured'   => '0',
        ),
    );

    $count = 0;
    foreach ( $tutorials as $tutorial ) {
        $existing = get_posts( array(
            'post_type'              => 'tutorial',
            'title'                  => $tutorial['title'],
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ) );
        if ( ! empty( $existing ) ) {
            continue;
        }

        $post_id = wp_insert_post( array(
            'post_title'   => $tutorial['title'],
            'post_content' => $tutorial['content'],
            'post_excerpt' => $tutorial['excerpt'],
            'post_status'  => 'publish',
            'post_type'    => 'tutorial',
            'post_author'  => get_current_user_id(),
        ) );

        if ( ! is_wp_error( $post_id ) ) {
            if ( isset( $cat_ids[ $tutorial['category'] ] ) ) {
                wp_set_object_terms( $post_id, $cat_ids[ $tutorial['category'] ], 'tutorial_category' );
            }
            update_post_meta( $post_id, '_qwe_difficulty', $tutorial['difficulty'] );
            update_post_meta( $post_id, '_qwe_featured', $tutorial['featured'] );
            $count++;
        }
    }

    // Create pages (About, Contact, Privacy Policy).
    $pages_created = qwe_create_sample_pages();
    $count += $pages_created;

    // Create navigation menus.
    qwe_create_sample_menus();

    set_transient( 'qwe_sample_generated', $count, 30 );
    wp_safe_redirect( admin_url( '?qwe_sample_done=1' ) );
    exit;
}
add_action( 'admin_init', 'qwe_generate_sample_content' );

/**
 * Show nonce link notice.
 */
function qwe_sample_content_nonce_notice() {
    $url = wp_nonce_url( admin_url( '?qwe_generate_sample=1' ), 'qwe_generate_sample' );
    echo '<div class="notice notice-info"><p>';
    printf(
        '%s <a href="%s" class="button button-primary">%s</a>',
        esc_html__( 'Click the button to generate sample tutorial content:', 'qwe-developer-flavor' ),
        esc_url( $url ),
        esc_html__( 'Generate Sample Content', 'qwe-developer-flavor' )
    );
    echo '</p></div>';
}

/**
 * Show success notice.
 */
function qwe_sample_content_success_notice() {
    if ( ! isset( $_GET['qwe_sample_done'] ) ) {
        return;
    }
    $count = get_transient( 'qwe_sample_generated' );
    if ( false === $count ) {
        $count = 0;
    }
    delete_transient( 'qwe_sample_generated' );
    echo '<div class="notice notice-success is-dismissible"><p>';
    printf(
        esc_html__( 'Successfully generated %d sample items (tutorials, categories, and pages)!', 'qwe-developer-flavor' ),
        intval( $count )
    );
    echo '</p></div>';
}
add_action( 'admin_notices', 'qwe_sample_content_success_notice' );

/* =========================================================================
   Tutorial Content Functions
   ========================================================================= */

function qwe_sample_tutorial_content_chatgpt() {
    return '<!-- wp:heading -->
<h2>What is ChatGPT?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>ChatGPT is an AI chatbot developed by OpenAI that can understand and generate human-like text. It can help you with writing, analysis, coding, brainstorming, and much more. Think of it as a highly knowledgeable assistant that is always available.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Creating Your Account</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Getting started with ChatGPT is straightforward. Visit chat.openai.com and click "Sign Up." You can register using your email address, Google account, or Microsoft account. The free tier gives you access to GPT-3.5, which is powerful enough for most tasks.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Your First Conversation</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Once you are logged in, you will see a chat interface. Simply type your message in the text box and press Enter. Start with something simple like "Explain quantum computing in simple terms" to see how ChatGPT responds.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Tips for Better Conversations</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li><strong>Be specific:</strong> Instead of "Tell me about dogs," try "What are the top 5 dog breeds for apartment living and why?"</li>
<li><strong>Provide context:</strong> Tell ChatGPT who you are and what you need. "I am a marketing manager. Help me write a product launch email."</li>
<li><strong>Iterate:</strong> If the first response is not quite right, ask ChatGPT to adjust. "Make it shorter" or "Use a more casual tone."</li>
<li><strong>Ask for formats:</strong> Request tables, bullet points, numbered lists, or specific structures.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Understanding the Interface</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The ChatGPT interface has several useful features. On the left sidebar, you will find your conversation history. Each conversation is saved and can be renamed for easy reference. You can start a new chat at any time without losing previous conversations.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>What ChatGPT Can Do</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>ChatGPT excels at a wide range of tasks including writing assistance, code generation, language translation, summarization, brainstorming ideas, explaining complex topics, and creative writing. The key is learning how to ask the right questions.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Limitations to Keep in Mind</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>While ChatGPT is incredibly capable, it has limitations. It can occasionally generate incorrect information, its training data has a cutoff date, and it cannot browse the internet in the free version. Always verify important facts independently.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Next Steps</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Now that you know the basics, start experimenting! Try using ChatGPT for different tasks in your daily life. Check out our advanced prompting techniques tutorial to learn how to get even better results from your conversations.</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_tutorial_content_prompts() {
    return '<!-- wp:heading -->
<h2>Why Prompting Matters</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The quality of your output from ChatGPT is directly tied to the quality of your input. Advanced prompting techniques help you communicate more effectively with AI, resulting in more accurate, useful, and creative responses.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>1. Chain-of-Thought Prompting</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Instead of asking for a direct answer, ask ChatGPT to think through the problem step by step. Add "Let us think about this step by step" to your prompt. This technique dramatically improves accuracy for complex reasoning tasks.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>2. Few-Shot Learning</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Provide examples of the input-output pattern you want before asking your actual question. For instance, show 2-3 examples of how you want product descriptions formatted, then ask it to create a new one. This teaches the model your preferred style.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>3. Role-Based Prompting</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Assign ChatGPT a specific role or persona. "You are an experienced Python developer with 10 years of experience" or "Act as a marketing strategist at a Fortune 500 company." This frames the response within relevant expertise.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>4. Constraint-Based Prompting</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Set specific constraints for the output. Specify word count, tone, format, audience level, or what to include and exclude. The more precise your constraints, the more targeted the output will be.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>5. Iterative Refinement</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Build on responses through multiple turns. Start broad, then narrow down. Ask ChatGPT to improve specific aspects of its previous response. This conversational approach often yields better results than trying to get everything perfect in one prompt.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Putting It All Together</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The best prompts often combine multiple techniques. A well-crafted prompt might include a role assignment, context about your situation, specific constraints, and examples of desired output. Practice these techniques regularly to develop your prompting skills.</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_tutorial_content_midjourney() {
    return '<!-- wp:heading -->
<h2>What is Midjourney?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Midjourney is an AI art generator that creates stunning images from text descriptions. It runs through Discord, making it easy to access and use. Whether you want photorealistic images, artistic illustrations, or abstract art, Midjourney can create it.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Setting Up Midjourney</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>To use Midjourney, you need a Discord account. Join the official Midjourney Discord server and subscribe to a plan. Once subscribed, you can generate images in any of the bot channels or by messaging the Midjourney bot directly.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Your First Image</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Type <code>/imagine</code> followed by your description. For example: <code>/imagine a cozy coffee shop on a rainy evening, warm lighting, watercolor style</code>. Midjourney will generate four image variations for you to choose from.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Prompt Engineering for Art</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Great Midjourney images come from great prompts. Include details about subject, style, lighting, mood, camera angle, and artistic medium. Use terms like "cinematic lighting," "8k resolution," "concept art," or specific artist styles.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Prompt Structure</h3>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol>
<li><strong>Subject:</strong> What is in the image (a dragon, a cityscape, a portrait)</li>
<li><strong>Style:</strong> Art style or medium (oil painting, photography, anime)</li>
<li><strong>Details:</strong> Lighting, colors, mood, composition</li>
<li><strong>Parameters:</strong> Technical settings like aspect ratio and quality</li>
</ol>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Useful Parameters</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Midjourney supports various parameters to control output. Use <code>--ar 16:9</code> for widescreen, <code>--v 6</code> for the latest model version, <code>--style raw</code> for less stylized results, and <code>--q 2</code> for higher quality. These parameters go at the end of your prompt.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Next Steps</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Start experimenting with different subjects and styles. Save prompts that work well and build your own prompt library. Check out our advanced Midjourney tutorial for techniques like image-to-image, inpainting, and multi-prompt composition.</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_tutorial_content_copilot() {
    return '<!-- wp:heading -->
<h2>What is GitHub Copilot?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>GitHub Copilot is an AI-powered coding assistant that suggests code as you type. It understands context from your comments, function names, and surrounding code to provide intelligent completions. It supports dozens of programming languages and works in popular editors.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Installation and Setup</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>GitHub Copilot is available as an extension for VS Code, JetBrains IDEs, Neovim, and more. Subscribe to GitHub Copilot from your GitHub account settings, then install the extension in your preferred editor. Sign in with your GitHub account to activate it.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>How It Works</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>As you write code, Copilot analyzes the context and suggests completions in gray text. Press Tab to accept a suggestion, or keep typing to see new ones. You can also trigger suggestions manually with keyboard shortcuts.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Writing Effective Comments</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>One of the best ways to use Copilot is to write descriptive comments before your code. For example, writing <code>// Function that validates an email address and returns true/false</code> will prompt Copilot to generate the complete function for you.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Best Practices</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li><strong>Review every suggestion:</strong> Always read and understand the code before accepting it</li>
<li><strong>Use descriptive names:</strong> Good variable and function names help Copilot understand your intent</li>
<li><strong>Provide context:</strong> Import statements and type definitions help Copilot generate better code</li>
<li><strong>Break down complex tasks:</strong> Write comments for each step of a complex function</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Copilot Chat</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>GitHub Copilot Chat adds a conversational interface to your editor. You can ask questions about your code, request explanations, generate tests, and get help with debugging. Select code and ask "Explain this code" or "Write tests for this function."</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_tutorial_content_writing() {
    return '<!-- wp:heading -->
<h2>The AI Writing Workflow</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>AI is not a replacement for human writing - it is a powerful amplifier. The best results come from a structured workflow that combines AI speed with human creativity. Here is a practical 5-step workflow you can use today.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Step 1: Research and Ideation</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Use AI to brainstorm topics, gather background information, and identify key angles. Ask ChatGPT to list trending topics in your niche, suggest unique angles for common subjects, or summarize recent developments in a field.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Step 2: Create an Outline</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ask AI to generate a detailed outline for your post. Provide your target audience, key points to cover, desired length, and tone. Review and adjust the outline to match your vision before moving to drafting.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Step 3: Draft with AI Assistance</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Write section by section, using AI to help with initial drafts. You might write some sections entirely yourself and use AI for others. The key is to maintain your authentic voice while leveraging AI for speed and completeness.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Step 4: Edit and Refine</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>This is where the human touch matters most. Review the entire piece for accuracy, tone, flow, and originality. Use AI to check grammar, suggest improvements, and identify areas that need more depth or clarity.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Step 5: Final Polish</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Add your personal insights, examples from experience, and unique perspective. Write a compelling introduction and conclusion. Create meta descriptions and social media excerpts. Your final piece should sound like you, enhanced by AI.</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_tutorial_content_data() {
    return '<!-- wp:heading -->
<h2>AI-Powered Data Analysis</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You do not need to be a data scientist to analyze data with AI. Modern AI tools can help you clean data, find patterns, create visualizations, and generate reports - all through natural language conversations.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Getting Started with ChatGPT for Data</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>ChatGPT with Code Interpreter (now called Advanced Data Analysis) can directly work with your files. Upload a CSV or Excel file, and ask questions like "What trends do you see?" or "Create a bar chart showing monthly sales."</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Cleaning and Preparing Data</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>AI excels at data cleaning tasks. Ask it to handle missing values, remove duplicates, standardize formats, and identify outliers. Describe the problems you see in your data, and AI will suggest or implement solutions.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Creating Visualizations</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Request specific chart types: bar charts, line graphs, scatter plots, heatmaps, or pie charts. Specify colors, labels, and formatting. AI can generate publication-ready visualizations that you can download and use immediately.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Extracting Insights</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ask AI to identify correlations, seasonal patterns, growth trends, and anomalies in your data. It can perform statistical tests, calculate growth rates, and provide interpretations that help you make data-driven decisions.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Best Practices</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>Always verify AI-generated statistics against your source data</li>
<li>Start with exploratory questions before diving into specific analyses</li>
<li>Ask AI to explain its methodology so you understand the results</li>
<li>Use multiple tools and approaches for important business decisions</li>
</ul>
<!-- /wp:list -->';
}

function qwe_sample_tutorial_content_video() {
    return '<!-- wp:heading -->
<h2>Introduction to AI Video Generation</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>AI video tools have revolutionized content creation. Runway ML is one of the leading platforms, offering text-to-video generation, video editing, and special effects powered by AI. This tutorial will get you started with creating professional videos.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Getting Started with Runway ML</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Create an account at runway.ml and explore the dashboard. Runway offers various AI-powered tools including Gen-2 for text-to-video, image-to-video conversion, background removal, and motion tracking. The free tier includes limited generation credits.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Text to Video Generation</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Runway Gen-2 can create short video clips from text descriptions. Describe the scene you want: "A timelapse of a city skyline transitioning from day to night, cinematic quality." Be specific about camera movement, lighting, and style for best results.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Image to Video</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Upload a static image and animate it with AI. This is great for bringing illustrations, photos, and AI-generated art to life. Specify the type of motion you want: camera pan, zoom, or subject movement.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>AI Video Editing Tools</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Runway also provides AI-powered editing tools: remove backgrounds in real-time, apply style transfer to entire videos, enhance resolution, and add effects. These tools save hours of manual editing work.</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_tutorial_content_business() {
    return '<!-- wp:heading -->
<h2>AI Automation for Business</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>AI tools can handle many repetitive business tasks, freeing you to focus on strategy and creative work. From email management to document processing, the opportunities for automation are vast and growing.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Email Management with AI</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>AI can draft email responses, prioritize your inbox, summarize long email threads, and schedule follow-ups. Tools like ChatGPT can generate professional email templates for common scenarios like customer inquiries, meeting requests, and project updates.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Document Processing</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Use AI to summarize lengthy documents, extract key information from contracts, generate reports from raw data, and create professional presentations. Upload documents to ChatGPT and ask specific questions about the content.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Customer Service Automation</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>AI chatbots can handle common customer questions, route complex issues to human agents, and provide 24/7 support. Train your AI on your FAQ and product documentation for accurate, consistent responses.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Getting Started</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Start by identifying your most time-consuming repetitive tasks. Choose one task to automate first, learn the tools, and measure the time saved. Gradually expand AI automation to other areas of your business.</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_tutorial_content_sd() {
    return '<!-- wp:heading -->
<h2>What is Stable Diffusion?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Stable Diffusion is an open-source AI image generation model that you can run on your own computer. Unlike cloud-based services, running it locally gives you complete control, unlimited generations, and privacy for your creations.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>System Requirements</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>You will need a computer with a dedicated GPU (NVIDIA recommended, at least 6GB VRAM), 16GB RAM minimum, and about 10GB of free disk space. AMD GPUs are supported but NVIDIA CUDA provides the best performance.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Installation with Automatic1111</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The most popular way to run Stable Diffusion locally is through the Automatic1111 WebUI. It provides a user-friendly browser interface with all the features you need. Follow these steps to install on Windows or Mac.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>Windows Installation</h3>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol>
<li>Install Python 3.10.6 from python.org</li>
<li>Install Git from git-scm.com</li>
<li>Clone the Automatic1111 repository from GitHub</li>
<li>Download a Stable Diffusion model (e.g., SD 1.5 or SDXL)</li>
<li>Run the webui-user.bat file to start the interface</li>
</ol>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Generating Your First Image</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Once the WebUI is running, open your browser to localhost:7860. Enter a prompt in the text field and click Generate. Start with simple prompts and gradually add detail. Use the negative prompt field to specify what you do not want in the image.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Key Settings</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Understanding key settings will improve your results. Sampling steps (20-30 is usually good), CFG scale (7-12 for most prompts), and image dimensions affect quality and generation time. Experiment with different samplers like Euler a and DPM++ 2M Karras.</p>
<!-- /wp:paragraph -->';
}

/* =========================================================================
   Page Creation
   ========================================================================= */

/**
 * Create sample pages (About, Contact, Privacy Policy).
 *
 * @return int Number of pages created.
 */
function qwe_create_sample_pages() {
    $pages = array(
        array(
            'title'    => 'About Us',
            'slug'     => 'about',
            'content'  => qwe_sample_page_content_about(),
            'template' => 'page-about.php',
        ),
        array(
            'title'    => 'Contact',
            'slug'     => 'contact',
            'content'  => qwe_sample_page_content_contact(),
            'template' => 'page-contact.php',
        ),
        array(
            'title'    => 'Privacy Policy',
            'slug'     => 'privacy',
            'content'  => qwe_sample_page_content_privacy(),
            'template' => 'page-privacy.php',
        ),
    );

    $count = 0;
    foreach ( $pages as $page ) {
        $existing = get_posts( array(
            'post_type'              => 'page',
            'title'                  => $page['title'],
            'posts_per_page'         => 1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ) );
        if ( ! empty( $existing ) ) {
            // Page exists — just ensure the template is set.
            update_post_meta( $existing[0]->ID, '_wp_page_template', $page['template'] );
            continue;
        }

        $page_id = wp_insert_post( array(
            'post_title'   => $page['title'],
            'post_name'    => $page['slug'],
            'post_content' => $page['content'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ) );

        if ( ! is_wp_error( $page_id ) ) {
            update_post_meta( $page_id, '_wp_page_template', $page['template'] );
            $count++;
        }
    }

    return $count;
}

/* =========================================================================
   Navigation Menu Creation
   ========================================================================= */

/**
 * Create primary and footer navigation menus with items.
 */
function qwe_create_sample_menus() {
    // --- Primary Menu ---
    $primary_menu_name = 'Primary Menu';
    $primary_menu      = wp_get_nav_menu_object( $primary_menu_name );

    if ( ! $primary_menu ) {
        $primary_menu_id = wp_create_nav_menu( $primary_menu_name );
    } else {
        $primary_menu_id = $primary_menu->term_id;
    }

    if ( ! is_wp_error( $primary_menu_id ) ) {
        // Only add items if menu is empty.
        $existing_items = wp_get_nav_menu_items( $primary_menu_id );
        if ( empty( $existing_items ) ) {
            // Home link.
            wp_update_nav_menu_item( $primary_menu_id, 0, array(
                'menu-item-title'  => __( 'Home', 'qwe-developer-flavor' ),
                'menu-item-url'    => home_url( '/' ),
                'menu-item-status' => 'publish',
                'menu-item-type'   => 'custom',
            ) );

            // Tutorials archive.
            wp_update_nav_menu_item( $primary_menu_id, 0, array(
                'menu-item-title'  => __( 'Tutorials', 'qwe-developer-flavor' ),
                'menu-item-url'    => get_post_type_archive_link( 'tutorial' ),
                'menu-item-status' => 'publish',
                'menu-item-type'   => 'custom',
            ) );

            // Tutorial categories as submenu items.
            $tutorial_cats = get_terms( array(
                'taxonomy'   => 'tutorial_category',
                'hide_empty' => false,
            ) );
            if ( ! is_wp_error( $tutorial_cats ) && ! empty( $tutorial_cats ) ) {
                // Get the Tutorials menu item ID for parent reference.
                $menu_items   = wp_get_nav_menu_items( $primary_menu_id );
                $tutorials_id = 0;
                foreach ( $menu_items as $item ) {
                    if ( 'Tutorials' === $item->title ) {
                        $tutorials_id = $item->ID;
                        break;
                    }
                }

                foreach ( $tutorial_cats as $cat ) {
                    wp_update_nav_menu_item( $primary_menu_id, 0, array(
                        'menu-item-title'     => $cat->name,
                        'menu-item-object'    => 'tutorial_category',
                        'menu-item-object-id' => $cat->term_id,
                        'menu-item-type'      => 'taxonomy',
                        'menu-item-status'    => 'publish',
                        'menu-item-parent-id' => $tutorials_id,
                    ) );
                }
            }

            // About Us page.
            $about_page = get_posts( array(
                'post_type'      => 'page',
                'name'           => 'about',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ) );
            if ( ! empty( $about_page ) ) {
                wp_update_nav_menu_item( $primary_menu_id, 0, array(
                    'menu-item-title'     => __( 'About Us', 'qwe-developer-flavor' ),
                    'menu-item-object'    => 'page',
                    'menu-item-object-id' => $about_page[0]->ID,
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                ) );
            }

            // Contact page.
            $contact_page = get_posts( array(
                'post_type'      => 'page',
                'name'           => 'contact',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ) );
            if ( ! empty( $contact_page ) ) {
                wp_update_nav_menu_item( $primary_menu_id, 0, array(
                    'menu-item-title'     => __( 'Contact', 'qwe-developer-flavor' ),
                    'menu-item-object'    => 'page',
                    'menu-item-object-id' => $contact_page[0]->ID,
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                ) );
            }
        }

        // Assign to primary location.
        $locations = get_theme_mod( 'nav_menu_locations', array() );
        $locations['primary'] = $primary_menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
    }

    // --- Footer Menu ---
    $footer_menu_name = 'Footer Menu';
    $footer_menu      = wp_get_nav_menu_object( $footer_menu_name );

    if ( ! $footer_menu ) {
        $footer_menu_id = wp_create_nav_menu( $footer_menu_name );
    } else {
        $footer_menu_id = $footer_menu->term_id;
    }

    if ( ! is_wp_error( $footer_menu_id ) ) {
        $existing_items = wp_get_nav_menu_items( $footer_menu_id );
        if ( empty( $existing_items ) ) {
            // Home.
            wp_update_nav_menu_item( $footer_menu_id, 0, array(
                'menu-item-title'  => __( 'Home', 'qwe-developer-flavor' ),
                'menu-item-url'    => home_url( '/' ),
                'menu-item-status' => 'publish',
                'menu-item-type'   => 'custom',
            ) );

            // Tutorials.
            wp_update_nav_menu_item( $footer_menu_id, 0, array(
                'menu-item-title'  => __( 'All Tutorials', 'qwe-developer-flavor' ),
                'menu-item-url'    => get_post_type_archive_link( 'tutorial' ),
                'menu-item-status' => 'publish',
                'menu-item-type'   => 'custom',
            ) );

            // About page.
            $about_page = get_posts( array(
                'post_type'      => 'page',
                'name'           => 'about',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ) );
            if ( ! empty( $about_page ) ) {
                wp_update_nav_menu_item( $footer_menu_id, 0, array(
                    'menu-item-title'     => __( 'About Us', 'qwe-developer-flavor' ),
                    'menu-item-object'    => 'page',
                    'menu-item-object-id' => $about_page[0]->ID,
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                ) );
            }

            // Contact page.
            $contact_page = get_posts( array(
                'post_type'      => 'page',
                'name'           => 'contact',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ) );
            if ( ! empty( $contact_page ) ) {
                wp_update_nav_menu_item( $footer_menu_id, 0, array(
                    'menu-item-title'     => __( 'Contact', 'qwe-developer-flavor' ),
                    'menu-item-object'    => 'page',
                    'menu-item-object-id' => $contact_page[0]->ID,
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                ) );
            }

            // Privacy page.
            $privacy_page = get_posts( array(
                'post_type'      => 'page',
                'name'           => 'privacy',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ) );
            if ( ! empty( $privacy_page ) ) {
                wp_update_nav_menu_item( $footer_menu_id, 0, array(
                    'menu-item-title'     => __( 'Privacy Policy', 'qwe-developer-flavor' ),
                    'menu-item-object'    => 'page',
                    'menu-item-object-id' => $privacy_page[0]->ID,
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                ) );
            }
        }

        // Assign to footer location.
        $locations = get_theme_mod( 'nav_menu_locations', array() );
        $locations['footer'] = $footer_menu_id;
        set_theme_mod( 'nav_menu_locations', $locations );
    }
}

/* =========================================================================
   Page Content Functions
   ========================================================================= */

function qwe_sample_page_content_about() {
    return '<!-- wp:heading -->
<h2>Our Mission</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>QWE AI Academy was founded with a simple goal: make AI education free and accessible to everyone. We believe that understanding AI tools is becoming an essential skill, and no one should be left behind because of cost barriers.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>What We Offer</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We provide comprehensive, beginner-friendly tutorials on the most popular AI tools available today. From ChatGPT and Claude to Midjourney and Stable Diffusion, our step-by-step guides help you master AI at your own pace.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li><strong>100% Free:</strong> All our tutorials are completely free. No hidden paywalls, no premium tiers, no subscriptions required.</li>
<li><strong>Beginner-Friendly:</strong> Every tutorial is written in plain language with clear, step-by-step instructions that anyone can follow.</li>
<li><strong>Practical Focus:</strong> We focus on real-world applications. Learn skills you can use immediately in your work and daily life.</li>
<li><strong>Always Updated:</strong> AI tools evolve rapidly. We continuously update our tutorials to reflect the latest features and best practices.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Our Topics</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Our tutorials cover a wide range of AI topics designed for learners at every level:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li><strong>ChatGPT &amp; LLMs:</strong> Master conversational AI tools for writing, research, and problem-solving.</li>
<li><strong>AI Art &amp; Design:</strong> Create stunning images with Midjourney, DALL-E, and Stable Diffusion.</li>
<li><strong>AI Coding:</strong> Boost your programming productivity with GitHub Copilot, Cursor, and AI coding assistants.</li>
<li><strong>AI for Business:</strong> Automate tasks, improve workflows, and make data-driven decisions.</li>
<li><strong>AI Writing:</strong> Craft compelling content faster with AI writing tools.</li>
<li><strong>AI Video &amp; Audio:</strong> Generate and edit multimedia content using cutting-edge AI tools.</li>
<li><strong>AI Data Analysis:</strong> Turn raw data into actionable insights without coding expertise.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Who We Are</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We are a team of AI enthusiasts, educators, and tech professionals who are passionate about sharing knowledge. Our writers use these AI tools daily and bring practical, real-world experience to every tutorial we publish.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Join Our Community</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Learning AI is better together. Follow us on social media to stay updated with new tutorials, tips, and AI news. Have a suggestion for a tutorial topic? We would love to hear from you — reach out through our contact page!</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_page_content_contact() {
    return '<!-- wp:heading -->
<h2>Get in Touch</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We would love to hear from you! Whether you have a question about one of our tutorials, a suggestion for a new topic, or just want to say hello, feel free to reach out.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Tutorial Suggestions</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Is there an AI tool or topic you would like us to cover? We are always looking for new tutorial ideas. Let us know what you want to learn, and we will do our best to create a comprehensive guide for it.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Report an Issue</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Found an error in one of our tutorials? AI tools update frequently, and sometimes our instructions may become outdated. Please let us know so we can update the content and keep our tutorials accurate for everyone.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>How to Reach Us</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li><strong>Email:</strong> contact@qwe.edu.pl</li>
<li><strong>Response Time:</strong> We typically respond within 24-48 hours on business days.</li>
<li><strong>Social Media:</strong> You can also reach us through our social media channels linked in the footer.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Collaboration</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Interested in contributing tutorials or collaborating with QWE AI Academy? We welcome guest writers and partners who share our passion for AI education. Send us an email with your proposal and we will get back to you.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Please note that all tutorials on QWE AI Academy are free. We do not accept sponsored content or paid promotions that compromise the quality and impartiality of our educational material.</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_page_content_privacy() {
    return '<!-- wp:heading -->
<h2>Introduction</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>QWE AI Academy ("we," "our," or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, and safeguard information when you visit our website www.qwe.edu.pl.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Information We Collect</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We collect minimal information to provide and improve our free educational service:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li><strong>Usage Data:</strong> We collect anonymous analytics data including pages visited, time spent on pages, browser type, device type, and referring website. This helps us understand which tutorials are most helpful.</li>
<li><strong>Contact Information:</strong> If you contact us via email, we collect your email address and the content of your message to respond to your inquiry.</li>
<li><strong>Cookies:</strong> We use essential cookies for basic website functionality and analytics cookies to understand how our site is used.</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>What We Do NOT Collect</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>QWE AI Academy does not require user registration. We do not collect passwords, payment information, personal identification numbers, or any sensitive personal data. Our site is designed to be used without creating an account.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>How We Use Information</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li>To maintain and improve our website and tutorials</li>
<li>To understand which content is most valuable to our readers</li>
<li>To respond to inquiries and feedback</li>
<li>To detect and prevent technical issues</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Third-Party Services</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We may use third-party analytics services (such as Google Analytics) to help us understand how our website is used. These services may collect information sent by your browser as part of a web page request. Please refer to their respective privacy policies for more information.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Data Security</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We implement appropriate technical and organizational measures to protect the information we collect. However, no method of transmission over the Internet is 100% secure, and we cannot guarantee absolute security.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Your Rights</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Under applicable data protection laws (including GDPR), you have the right to access, correct, delete, or restrict the processing of your personal data. To exercise these rights, please contact us at the email address provided on our Contact page.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Changes to This Policy</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We may update this Privacy Policy from time to time. Any changes will be posted on this page with an updated "Last updated" date. We encourage you to review this page periodically.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Contact Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>If you have any questions about this Privacy Policy, please contact us through our Contact page or email us at contact@qwe.edu.pl.</p>
<!-- /wp:paragraph -->';
}

function qwe_sample_tutorial_content_comparison() {
    return '<!-- wp:heading -->
<h2>Overview</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>ChatGPT and Claude are two of the most popular AI assistants available today. While they share many capabilities, each has unique strengths that make it better suited for certain tasks. This guide will help you choose the right tool for your needs.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>ChatGPT Strengths</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li><strong>Ecosystem:</strong> Extensive plugin marketplace and GPT Store</li>
<li><strong>Code Interpreter:</strong> Built-in ability to run Python code and analyze data files</li>
<li><strong>Image Generation:</strong> DALL-E integration for creating images</li>
<li><strong>Voice Mode:</strong> Natural voice conversations on mobile</li>
<li><strong>Web Browsing:</strong> Can search the internet for current information</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>Claude Strengths</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
<li><strong>Long Context:</strong> Can process very long documents (up to 200K tokens)</li>
<li><strong>Analysis:</strong> Excels at careful, nuanced analysis of complex topics</li>
<li><strong>Writing Quality:</strong> Often produces more natural, well-structured prose</li>
<li><strong>Safety:</strong> Strong focus on accuracy and avoiding harmful outputs</li>
<li><strong>Coding:</strong> Excellent at code generation and debugging</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>When to Use Each</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Use ChatGPT when you need internet access, image generation, or data analysis with file uploads. Choose Claude for long document analysis, careful reasoning, creative writing, and coding tasks. For everyday questions, both perform excellently.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Pricing Comparison</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Both offer free tiers with limitations and paid plans around $20/month. The paid versions unlock faster responses, priority access, and advanced features. Consider trying both free tiers before committing to a subscription.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Our Recommendation</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>The best approach is to use both tools. Keep both available and choose based on the specific task at hand. As AI evolves rapidly, the strengths of each platform continue to change, so stay flexible and experiment regularly.</p>
<!-- /wp:paragraph -->';
}
