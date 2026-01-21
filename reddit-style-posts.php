<?php
/**
 * Plugin Name: Reddit Style Posts
 * Plugin URI: https://example.com
 * Description: Transforms WordPress blog posts into Reddit-style layout with upvote/downvote system and prominent comments to boost engagement and SEO.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 * Text Domain: reddit-style-posts
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Define plugin constants
define('RSP_VERSION', '1.0.0');
define('RSP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RSP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class Reddit_Style_Posts {
    
    private static $instance = null;
    private $votes_table;
    private $rendering_featured_image = false;
    private $loading_comments_in_injection = false;
    private $comments_already_rendered = false;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->votes_table = $wpdb->prefix . 'rsp_votes';
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Inject Reddit-style elements into existing theme
        add_filter('the_content', array($this, 'inject_reddit_elements'), 10);
        
        // Prevent duplicate featured images in single posts
        add_filter('post_thumbnail_html', array($this, 'remove_duplicate_thumbnail'), 10, 5);
        
        // Comments modifications
        add_filter('comments_template', array($this, 'custom_comments_template'));
        
        // AJAX handlers for voting
        add_action('wp_ajax_rsp_vote', array($this, 'ajax_handle_vote'));
        add_action('wp_ajax_nopriv_rsp_vote', array($this, 'ajax_handle_vote'));
        
        // AJAX handlers for comments
        add_action('wp_ajax_rsp_load_comments', array($this, 'ajax_load_comments'));
        add_action('wp_ajax_nopriv_rsp_load_comments', array($this, 'ajax_load_comments'));
        
        // AJAX handlers for comment moderation
        add_action('wp_ajax_rsp_delete_comment', array($this, 'ajax_delete_comment'));
        add_action('wp_ajax_rsp_approve_comment', array($this, 'ajax_approve_comment'));
        add_action('wp_ajax_rsp_unapprove_comment', array($this, 'ajax_unapprove_comment'));
        add_action('wp_ajax_rsp_spam_comment', array($this, 'ajax_spam_comment'));
        
        // Add vote counts to posts
        add_action('wp_insert_post', array($this, 'initialize_post_meta'), 10, 2);
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Allow anonymous commenting (no name/email required)
        add_filter('pre_comment_approved', array($this, 'allow_anonymous_comments'), 10, 2);
        add_filter('preprocess_comment', array($this, 'set_anonymous_author'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create votes table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->votes_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            comment_id bigint(20) DEFAULT 0,
            user_id bigint(20) DEFAULT 0,
            user_ip varchar(100) DEFAULT NULL,
            vote_type varchar(10) NOT NULL,
            voted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY comment_id (comment_id),
            KEY user_id (user_id),
            KEY user_ip (user_ip),
            UNIQUE KEY unique_vote (post_id, comment_id, user_id, user_ip)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Set default options
        add_option('rsp_enable_voting', '1');
        add_option('rsp_show_vote_count', '1');
        add_option('rsp_enable_share_buttons', '1');
        add_option('rsp_excerpt_length', '150');
        add_option('rsp_comments_visible', '1');
        add_option('rsp_guest_voting', '0');
        add_option('rsp_debug_mode', '0');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if needed
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Register any custom functionality
    }
    
    /**
     * Initialize post meta for new posts
     */
    public function initialize_post_meta($post_id, $post) {
        if ($post->post_type === 'post' && $post->post_status === 'publish') {
            if (!get_post_meta($post_id, '_rsp_upvotes', true)) {
                update_post_meta($post_id, '_rsp_upvotes', 0);
            }
            if (!get_post_meta($post_id, '_rsp_downvotes', true)) {
                update_post_meta($post_id, '_rsp_downvotes', 0);
            }
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        if (is_single() && get_post_type() === 'post') {
            wp_enqueue_style('rsp-style', RSP_PLUGIN_URL . 'assets/style.css', array(), RSP_VERSION);
            wp_enqueue_script('rsp-script', RSP_PLUGIN_URL . 'assets/script.js', array('jquery'), RSP_VERSION, true);
            
            wp_localize_script('rsp-script', 'rspAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rsp_nonce'),
                'post_id' => get_the_ID(),
                'is_user_logged_in' => is_user_logged_in()
            ));
            
            // Add inline CSS to override Zox News theme styles
            $current_theme = wp_get_theme();
            if ($current_theme->get('Name') === 'Zox News' || $current_theme->get_template() === 'zox-news') {
                wp_add_inline_style('rsp-style', '
                    /* Zox News theme compatibility fixes */
                    .rsp-comments-container { display: block !important; }
                    #comments.rsp-comments { display: block !important; }
                    .rsp-comment-respond, #respond { display: block !important; }
                    .comment-form-comment { display: block !important; }
                    .comment-form textarea { display: block !important; width: 100% !important; }
                ');
            }
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'reddit-style-posts') !== false) {
            wp_enqueue_style('rsp-admin-style', RSP_PLUGIN_URL . 'assets/admin-style.css', array(), RSP_VERSION);
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Reddit Style Posts', 'reddit-style-posts'),
            __('Reddit Posts', 'reddit-style-posts'),
            'manage_options',
            'reddit-style-posts',
            array($this, 'admin_settings_page'),
            'dashicons-reddit',
            31
        );
        
        // Add Comments Management submenu
        add_submenu_page(
            'reddit-style-posts',
            __('Manage Comments', 'reddit-style-posts'),
            __('Comments', 'reddit-style-posts'),
            'moderate_comments',
            'reddit-style-comments',
            array($this, 'admin_comments_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        $settings = array(
            'rsp_enable_voting',
            'rsp_show_vote_count',
            'rsp_enable_share_buttons',
            'rsp_excerpt_length',
            'rsp_comments_visible',
            'rsp_guest_voting',
            'rsp_debug_mode'
        );
        
        foreach ($settings as $setting) {
            register_setting('rsp_settings_group', $setting);
        }
    }
    
    /**
     * Admin settings page
     */
    public function admin_settings_page() {
        global $wpdb;
        
        $total_votes = $wpdb->get_var("SELECT COUNT(*) FROM {$this->votes_table}");
        $total_upvotes = $wpdb->get_var("SELECT COUNT(*) FROM {$this->votes_table} WHERE vote_type = 'upvote'");
        $total_downvotes = $wpdb->get_var("SELECT COUNT(*) FROM {$this->votes_table} WHERE vote_type = 'downvote'");
        
        ?>
        <div class="wrap rsp-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="rsp-admin-grid">
                <div class="rsp-admin-section">
                    <h2><?php _e('Statistics', 'reddit-style-posts'); ?></h2>
                    <div class="rsp-stats-grid">
                        <div class="rsp-stat-card">
                            <span class="rsp-stat-label"><?php _e('Total Votes', 'reddit-style-posts'); ?></span>
                            <span class="rsp-stat-value"><?php echo number_format($total_votes); ?></span>
                        </div>
                        <div class="rsp-stat-card upvote">
                            <span class="rsp-stat-label"><?php _e('Upvotes', 'reddit-style-posts'); ?></span>
                            <span class="rsp-stat-value"><?php echo number_format($total_upvotes); ?></span>
                        </div>
                        <div class="rsp-stat-card downvote">
                            <span class="rsp-stat-label"><?php _e('Downvotes', 'reddit-style-posts'); ?></span>
                            <span class="rsp-stat-value"><?php echo number_format($total_downvotes); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="rsp-admin-section">
                    <h2><?php _e('Settings', 'reddit-style-posts'); ?></h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('rsp_settings_group'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Enable Voting System', 'reddit-style-posts'); ?></th>
                                <td>
                                    <input type="checkbox" name="rsp_enable_voting" value="1" 
                                           <?php checked(get_option('rsp_enable_voting', '1'), '1'); ?>>
                                    <p class="description"><?php _e('Allow users to upvote/downvote posts', 'reddit-style-posts'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Show Vote Count', 'reddit-style-posts'); ?></th>
                                <td>
                                    <input type="checkbox" name="rsp_show_vote_count" value="1" 
                                           <?php checked(get_option('rsp_show_vote_count', '1'), '1'); ?>>
                                    <p class="description"><?php _e('Display the number of votes publicly', 'reddit-style-posts'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Allow Guest Voting', 'reddit-style-posts'); ?></th>
                                <td>
                                    <input type="checkbox" name="rsp_guest_voting" value="1" 
                                           <?php checked(get_option('rsp_guest_voting', '0'), '1'); ?>>
                                    <p class="description"><?php _e('Allow non-logged-in users to vote (tracked by IP)', 'reddit-style-posts'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Enable Share Buttons', 'reddit-style-posts'); ?></th>
                                <td>
                                    <input type="checkbox" name="rsp_enable_share_buttons" value="1" 
                                           <?php checked(get_option('rsp_enable_share_buttons', '1'), '1'); ?>>
                                    <p class="description"><?php _e('Show social share buttons', 'reddit-style-posts'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Excerpt Length', 'reddit-style-posts'); ?></th>
                                <td>
                                    <input type="number" name="rsp_excerpt_length" 
                                           value="<?php echo esc_attr(get_option('rsp_excerpt_length', '150')); ?>" 
                                           min="50" max="500" class="small-text">
                                    <p class="description"><?php _e('Number of words to show in excerpt', 'reddit-style-posts'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Comments Always Visible', 'reddit-style-posts'); ?></th>
                                <td>
                                    <input type="checkbox" name="rsp_comments_visible" value="1" 
                                           <?php checked(get_option('rsp_comments_visible', '1'), '1'); ?>>
                                    <p class="description"><?php _e('Keep comments section visible (Reddit-style)', 'reddit-style-posts'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Enable Debug Info', 'reddit-style-posts'); ?></th>
                                <td>
                                    <input type="checkbox" name="rsp_debug_mode" value="1" 
                                           <?php checked(get_option('rsp_debug_mode', '0'), '1'); ?>>
                                    <p class="description"><?php _e('Show debug information (only visible to logged-in admins)', 'reddit-style-posts'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Inject Reddit-style elements into post content
     */
    public function inject_reddit_elements($content) {
        // Only on single post pages
        if (!is_single() || get_post_type() !== 'post' || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        $post_id = get_the_ID();
        $vote_counts = $this->get_vote_counts($post_id);
        $user_vote = $this->get_user_vote($post_id);
        $enable_voting = get_option('rsp_enable_voting', '1') === '1';
        $show_vote_count = get_option('rsp_show_vote_count', '1') === '1';
        $enable_share = get_option('rsp_enable_share_buttons', '1') === '1';
        $excerpt_length = get_option('rsp_excerpt_length', '150');
        
        // Remove featured image from content to prevent duplication
        $content = preg_replace('/<img[^>]+wp-post-image[^>]*>/i', '', $content);
        
        // Start output buffering
        ob_start();
        ?>
        
        <div class="rsp-content-injection">
            
            <!-- Featured Image (if exists) - displayed once here -->
            <?php if (has_post_thumbnail()): ?>
            <div class="rsp-featured-image">
                <?php 
                $this->rendering_featured_image = true;
                the_post_thumbnail('large'); 
                $this->rendering_featured_image = false;
                ?>
            </div>
            <?php endif; ?>
            
            <!-- Post Content with Read More Button -->
            <div class="rsp-post-content">
                <div class="rsp-excerpt" id="rsp-excerpt">
                    <?php
                    // Remove "..." to let the CSS fade effect handle the visual cutoff
                    $excerpt = wp_trim_words($content, $excerpt_length, '');
                    echo $excerpt;
                    ?>
                </div>
                
                <div class="rsp-full-content" id="rsp-full-content" style="display: none;">
                    <?php echo $content; ?>
                </div>
                
                <button class="rsp-read-more-btn" id="rsp-toggle-content">
                    <span class="rsp-expand-text">Read More</span>
                    <span class="rsp-collapse-text" style="display: none;">Show Less</span>
                </button>
            </div>
            
            <!-- Bottom 40% Space: Action Buttons -->
            <div class="rsp-post-actions-wrapper">
                <div class="rsp-post-actions">
                    
                    <!-- Upvote Button - Authentic Reddit Icon -->
                    <?php if ($enable_voting): ?>
                    <button class="rsp-action-btn rsp-vote-action rsp-upvote-action <?php echo $user_vote === 'upvote' ? 'active' : ''; ?>" 
                            data-post-id="<?php echo $post_id; ?>" 
                            data-vote-type="upvote"
                            aria-label="Upvote">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.877 19h-1.754c-.108 0-.157-.005-.221-.027-.359-.109-.418-.452-.418-.796v-6.875h-3.486c-.192 0-.393-.024-.535-.142-.134-.111-.195-.296-.195-.488 0-.126.03-.261.088-.38l6.378-13.107c.076-.155.174-.282.305-.372.124-.086.283-.13.451-.13.165 0 .328.044.451.13.132.09.228.217.305.372l6.379 13.107c.058.119.087.254.087.38 0 .192-.062.377-.196.488-.142.118-.344.142-.535.142h-3.485v6.875c0 .344-.059.687-.418.796-.064.022-.112.027-.221.027z"/>
                        </svg>
                        <span class="rsp-action-label">
                            <?php if ($show_vote_count): ?>
                                <span class="rsp-vote-count" data-score="<?php echo $vote_counts['score']; ?>">
                                    <?php echo number_format_i18n($vote_counts['score']); ?>
                                </span>
                            <?php else: ?>
                                Upvote
                            <?php endif; ?>
                        </span>
                    </button>
                    <?php endif; ?>
                    
                    <!-- Comment Button - Authentic Reddit Icon -->
                    <button class="rsp-action-btn rsp-comment-btn" onclick="document.getElementById('rsp-comments').scrollIntoView({behavior: 'smooth'})">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12.008 0c6.628 0 12 4.596 12 10.283 0 5.686-5.372 10.282-12 10.282-1.197 0-2.35-.153-3.428-.44l-5.562 3.146c-.36.203-.806-.066-.806-.486v-4.593c-2.67-1.773-4.212-4.408-4.212-7.422 0-5.687 5.372-10.283 12.008-10.283zm-4.716 13.138c.557 0 1.008-.451 1.008-1.008 0-.557-.451-1.009-1.008-1.009-.556 0-1.008.452-1.008 1.009 0 .557.452 1.008 1.008 1.008zm4.716 0c.556 0 1.008-.451 1.008-1.008 0-.557-.452-1.009-1.008-1.009-.557 0-1.009.452-1.009 1.009 0 .557.452 1.008 1.009 1.008zm4.708 0c.556 0 1.008-.451 1.008-1.008 0-.557-.452-1.009-1.008-1.009-.557 0-1.008.452-1.008 1.009 0 .557.451 1.008 1.008 1.008z"/>
                        </svg>
                        <span class="rsp-action-label">
                            <?php 
                            $comment_count = get_comments_number();
                            echo $comment_count . ' ' . _n('Comment', 'Comments', $comment_count, 'reddit-style-posts');
                            ?>
                        </span>
                    </button>
                    
                    <!-- Share Button - Clean Upload/Share Icon -->
                    <?php if ($enable_share): ?>
                    <button class="rsp-action-btn rsp-share-btn" id="rsp-share-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                            <polyline points="16 6 12 2 8 6"/>
                            <line x1="12" y1="2" x2="12" y2="15"/>
                        </svg>
                        <span class="rsp-action-label">Share</span>
                    </button>
                    
                    <!-- Share Dropdown -->
                    <div class="rsp-share-dropdown" id="rsp-share-dropdown" style="display: none;">
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode(get_the_title()); ?>" 
                           target="_blank" class="rsp-share-option">
                            Twitter
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>" 
                           target="_blank" class="rsp-share-option">
                            Facebook
                        </a>
                        <a href="https://www.reddit.com/submit?url=<?php echo urlencode(get_permalink()); ?>&title=<?php echo urlencode(get_the_title()); ?>" 
                           target="_blank" class="rsp-share-option">
                            Reddit
                        </a>
                        <button class="rsp-share-option" id="rsp-copy-link">
                            Copy Link
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Comments Section - Loaded immediately after action buttons -->
            <div class="rsp-comments-container" id="rsp-comments">
                <?php
                // Load comments immediately after action buttons - directly include template
                if ((comments_open() || get_comments_number()) && !$this->comments_already_rendered) {
                    $this->loading_comments_in_injection = true;
                    $this->comments_already_rendered = true; // Prevent duplicate rendering
                    
                    // Ensure WordPress globals are properly set for comments
                    global $post, $wp_query, $withcomments;
                    
                    // Force comments to load even if theme doesn't set this
                    $withcomments = true;
                    
                    // Make sure the post object is available
                    if (!isset($post)) {
                        $post = get_post($post_id);
                    }
                    
                    // Setup postdata to ensure all template tags work correctly
                    setup_postdata($post);
                    
                    // CRITICAL: Load comments into the query so have_comments() works
                    // This is what makes comments actually appear in the template
                    if (!isset($wp_query->comments) || empty($wp_query->comments)) {
                        $comments = get_comments(array(
                            'post_id' => $post_id,
                            'status' => 'approve',
                            'order' => 'ASC'
                        ));
                        $wp_query->comments = $comments;
                        $wp_query->comment_count = count($comments);
                        
                        // Debug: Add visible info for admins only
                        if (get_option('rsp_debug_mode', '0') === '1' && current_user_can('manage_options')) {
                            echo '<div style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; font-size: 12px;">';
                            echo '<strong>🔍 RSP Debug Info (Admin Only):</strong><br>';
                            echo '• Comments loaded: ' . count($comments) . '<br>';
                            echo '• Post ID: ' . $post_id . '<br>';
                            echo '• Comments open: ' . (comments_open() ? 'Yes' : 'No') . '<br>';
                            echo '• get_comments_number(): ' . get_comments_number() . '<br>';
                            echo '• $wp_query->comment_count: ' . (isset($wp_query->comment_count) ? $wp_query->comment_count : 'Not set') . '<br>';
                            echo '• have_comments(): ' . (have_comments() ? 'Yes' : 'No') . '<br>';
                            echo '• Theme: ' . wp_get_theme()->get('Name') . '<br>';
                            echo '• Active plugins: ' . count(get_option('active_plugins', array())) . '<br>';
                            echo '</div>';
                        }
                    }
                    
                    // Directly include our custom template to ensure compatibility with all themes
                    $custom_comments_template = RSP_PLUGIN_DIR . 'templates/comments.php';
                    if (file_exists($custom_comments_template)) {
                        include($custom_comments_template);
                        
                        // Debug info after template loads
                        if (get_option('rsp_debug_mode', '0') === '1' && current_user_can('manage_options')) {
                            echo '<div style="background: #d4edda; border: 2px solid #28a745; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; font-size: 12px;">';
                            echo '<strong>✅ Template loaded successfully</strong><br>';
                            echo '• Template: ' . basename($custom_comments_template) . '<br>';
                            echo '• File exists: Yes<br>';
                            echo '• Template path: ' . esc_html($custom_comments_template) . '<br>';
                            echo '</div>';
                        }
                    } else {
                        // Fallback to WordPress default if our template doesn't exist
                        comments_template();
                        
                        // Debug info for fallback
                        if (get_option('rsp_debug_mode', '0') === '1' && current_user_can('manage_options')) {
                            echo '<div style="background: #f8d7da; border: 2px solid #dc3545; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; font-size: 12px;">';
                            echo '<strong>⚠️ Using fallback template</strong><br>';
                            echo '• Custom template not found at: ' . esc_html($custom_comments_template) . '<br>';
                            echo '</div>';
                        }
                    }
                    
                    $this->loading_comments_in_injection = false;
                }
                ?>
            </div>
        </div>
        
        <?php
        $injected_content = ob_get_clean();
        
        // Return the injected content which includes everything
        return $injected_content;
    }
    
    /**
     * Remove duplicate featured image from theme
     */
    public function remove_duplicate_thumbnail($html, $post_id, $post_thumbnail_id, $size, $attr) {
        // Allow our own featured image to render
        if ($this->rendering_featured_image) {
            return $html;
        }
        
        // Only block the CURRENT post's featured image on single post pages
        // Don't block sidebar images, widget images, or other post thumbnails
        if (is_single() && get_post_type() === 'post' && in_the_loop() && is_main_query()) {
            // Only block if this thumbnail is for the current post being displayed
            $current_post_id = get_the_ID();
            if ($post_id === $current_post_id) {
                return ''; // Return empty to prevent duplicate of THIS post's featured image only
            }
        }
        
        return $html;
    }
    
    /**
     * Custom comments template
     */
    public function custom_comments_template($template) {
        // Only intercept on single post pages
        if (!is_single() || get_post_type() !== 'post') {
            return $template;
        }
        
        // If we're currently loading comments in our injection, use our template
        if ($this->loading_comments_in_injection) {
            $custom_template = RSP_PLUGIN_DIR . 'templates/comments.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
            return $template;
        }
        
        // If comments have already been rendered by our plugin, suppress theme's version
        if ($this->comments_already_rendered) {
            return RSP_PLUGIN_DIR . 'templates/empty-comments.php';
        }
        
        // Check if we're in the main loop and query - if so, suppress theme comments
        // because we've already loaded them in our injection
        if (in_the_loop() && is_main_query()) {
            // Return empty template to prevent duplicate comments from theme
            return RSP_PLUGIN_DIR . 'templates/empty-comments.php';
        }
        
        // For any other case (like theme calling comments_template outside the loop),
        // suppress it to prevent duplicates
        return RSP_PLUGIN_DIR . 'templates/empty-comments.php';
    }
    
    /**
     * AJAX: Handle voting
     */
    public function ajax_handle_vote() {
        check_ajax_referer('rsp_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $vote_type = sanitize_text_field($_POST['vote_type']);
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        
        // Check if voting is enabled
        if (get_option('rsp_enable_voting', '1') !== '1') {
            wp_send_json_error('Voting is disabled');
        }
        
        // Check if guest voting is allowed
        if (!is_user_logged_in() && get_option('rsp_guest_voting', '0') !== '1') {
            wp_send_json_error('Please login to vote');
        }
        
        global $wpdb;
        
        $user_id = get_current_user_id();
        $user_ip = $this->get_user_ip();
        
        // Check if user already voted
        $existing_vote = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->votes_table} 
             WHERE post_id = %d AND comment_id = %d 
             AND (user_id = %d OR user_ip = %s)",
            $post_id, $comment_id, $user_id, $user_ip
        ));
        
        if ($existing_vote) {
            // If same vote, remove it (toggle off)
            if ($existing_vote->vote_type === $vote_type) {
                $wpdb->delete(
                    $this->votes_table,
                    array('id' => $existing_vote->id),
                    array('%d')
                );
                $action = 'removed';
            } else {
                // Change vote type
                $wpdb->update(
                    $this->votes_table,
                    array('vote_type' => $vote_type),
                    array('id' => $existing_vote->id),
                    array('%s'),
                    array('%d')
                );
                $action = 'changed';
            }
        } else {
            // Add new vote
            $wpdb->insert(
                $this->votes_table,
                array(
                    'post_id' => $post_id,
                    'comment_id' => $comment_id,
                    'user_id' => $user_id,
                    'user_ip' => $user_ip,
                    'vote_type' => $vote_type,
                    'voted_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%s', '%s')
            );
            $action = 'added';
        }
        
        // Get updated vote counts
        $vote_counts = $this->get_vote_counts($post_id, $comment_id);
        
        wp_send_json_success(array(
            'action' => $action,
            'upvotes' => $vote_counts['upvotes'],
            'downvotes' => $vote_counts['downvotes'],
            'score' => $vote_counts['score']
        ));
    }
    
    /**
     * Get vote counts for post or comment
     */
    public function get_vote_counts($post_id, $comment_id = 0) {
        global $wpdb;
        
        $upvotes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE post_id = %d AND comment_id = %d AND vote_type = 'upvote'",
            $post_id, $comment_id
        ));
        
        $downvotes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE post_id = %d AND comment_id = %d AND vote_type = 'downvote'",
            $post_id, $comment_id
        ));
        
        return array(
            'upvotes' => intval($upvotes),
            'downvotes' => intval($downvotes),
            'score' => intval($upvotes) - intval($downvotes)
        );
    }
    
    /**
     * Get user's vote for post or comment
     */
    public function get_user_vote($post_id, $comment_id = 0) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $user_ip = $this->get_user_ip();
        
        $vote = $wpdb->get_var($wpdb->prepare(
            "SELECT vote_type FROM {$this->votes_table} 
             WHERE post_id = %d AND comment_id = %d 
             AND (user_id = %d OR user_ip = %s)
             ORDER BY id DESC LIMIT 1",
            $post_id, $comment_id, $user_id, $user_ip
        ));
        
        return $vote;
    }
    
    /**
     * AJAX: Load comments
     */
    public function ajax_load_comments() {
        check_ajax_referer('rsp_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        
        ob_start();
        comments_template();
        $comments_html = ob_get_clean();
        
        wp_send_json_success(array('html' => $comments_html));
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
    
    /**
     * Admin Comments Management Page
     */
    public function admin_comments_page() {
        // Handle bulk actions
        if (isset($_POST['rsp_bulk_action']) && isset($_POST['comment_ids'])) {
            check_admin_referer('rsp_bulk_comments');
            
            $action = sanitize_text_field($_POST['rsp_bulk_action']);
            $comment_ids = array_map('intval', $_POST['comment_ids']);
            
            foreach ($comment_ids as $comment_id) {
                switch ($action) {
                    case 'approve':
                        wp_set_comment_status($comment_id, 'approve');
                        break;
                    case 'unapprove':
                        wp_set_comment_status($comment_id, 'hold');
                        break;
                    case 'spam':
                        wp_spam_comment($comment_id);
                        break;
                    case 'delete':
                        wp_delete_comment($comment_id, true);
                        break;
                }
            }
            
            echo '<div class="notice notice-success"><p>' . __('Comments updated successfully.', 'reddit-style-posts') . '</p></div>';
        }
        
        // Get filter parameters
        $status = isset($_GET['comment_status']) ? sanitize_text_field($_GET['comment_status']) : 'all';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // Query comments
        $args = array(
            'number' => $per_page,
            'offset' => ($paged - 1) * $per_page,
            'orderby' => 'comment_date',
            'order' => 'DESC',
        );
        
        if ($status !== 'all') {
            $args['status'] = $status;
        }
        
        $comments_query = new WP_Comment_Query($args);
        $comments = $comments_query->comments;
        
        // Get comment counts by status
        $total_comments = wp_count_comments();
        
        ?>
        <div class="wrap rsp-admin-wrap">
            <h1><?php _e('Manage Comments', 'reddit-style-posts'); ?></h1>
            
            <ul class="subsubsub">
                <li><a href="?page=reddit-style-comments&comment_status=all" <?php echo $status === 'all' ? 'class="current"' : ''; ?>>
                    <?php _e('All', 'reddit-style-posts'); ?> <span class="count">(<?php echo $total_comments->total_comments; ?>)</span>
                </a> |</li>
                <li><a href="?page=reddit-style-comments&comment_status=approve" <?php echo $status === 'approve' ? 'class="current"' : ''; ?>>
                    <?php _e('Approved', 'reddit-style-posts'); ?> <span class="count">(<?php echo $total_comments->approved; ?>)</span>
                </a> |</li>
                <li><a href="?page=reddit-style-comments&comment_status=hold" <?php echo $status === 'hold' ? 'class="current"' : ''; ?>>
                    <?php _e('Pending', 'reddit-style-posts'); ?> <span class="count">(<?php echo $total_comments->moderated; ?>)</span>
                </a> |</li>
                <li><a href="?page=reddit-style-comments&comment_status=spam" <?php echo $status === 'spam' ? 'class="current"' : ''; ?>>
                    <?php _e('Spam', 'reddit-style-posts'); ?> <span class="count">(<?php echo $total_comments->spam; ?>)</span>
                </a></li>
            </ul>
            
            <form method="post" id="rsp-comments-form">
                <?php wp_nonce_field('rsp_bulk_comments'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="rsp_bulk_action">
                            <option value=""><?php _e('Bulk Actions', 'reddit-style-posts'); ?></option>
                            <option value="approve"><?php _e('Approve', 'reddit-style-posts'); ?></option>
                            <option value="unapprove"><?php _e('Unapprove', 'reddit-style-posts'); ?></option>
                            <option value="spam"><?php _e('Mark as Spam', 'reddit-style-posts'); ?></option>
                            <option value="delete"><?php _e('Delete Permanently', 'reddit-style-posts'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Apply', 'reddit-style-posts'); ?>">
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped comments">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th><?php _e('Author', 'reddit-style-posts'); ?></th>
                            <th><?php _e('Comment', 'reddit-style-posts'); ?></th>
                            <th><?php _e('Post', 'reddit-style-posts'); ?></th>
                            <th><?php _e('Status', 'reddit-style-posts'); ?></th>
                            <th><?php _e('Date', 'reddit-style-posts'); ?></th>
                            <th><?php _e('Actions', 'reddit-style-posts'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comments)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <?php _e('No comments found.', 'reddit-style-posts'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <tr class="comment-<?php echo $comment->comment_ID; ?>" data-comment-id="<?php echo $comment->comment_ID; ?>">
                                    <th class="check-column">
                                        <input type="checkbox" name="comment_ids[]" value="<?php echo $comment->comment_ID; ?>">
                                    </th>
                                    <td>
                                        <div class="comment-author">
                                            <?php echo get_avatar($comment, 32); ?>
                                            <strong><?php echo esc_html($comment->comment_author ?: 'Anonymous'); ?></strong>
                                        </div>
                                        <?php if ($comment->comment_author_email): ?>
                                            <div style="font-size: 12px; color: #666;">
                                                <?php echo esc_html($comment->comment_author_email); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="font-size: 12px; color: #999;">
                                            IP: <?php echo esc_html($comment->comment_author_IP); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="max-width: 400px;">
                                            <?php echo wp_trim_words($comment->comment_content, 20); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo get_permalink($comment->comment_post_ID); ?>" target="_blank">
                                            <?php echo get_the_title($comment->comment_post_ID); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php
                                        $status_labels = array(
                                            '1' => '<span style="color: #46b450;">✓ Approved</span>',
                                            '0' => '<span style="color: #ffb900;">⏱ Pending</span>',
                                            'spam' => '<span style="color: #dc3232;">✗ Spam</span>',
                                        );
                                        echo $status_labels[$comment->comment_approved] ?? $comment->comment_approved;
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($comment->comment_date)); ?>
                                    </td>
                                    <td>
                                        <div class="rsp-comment-actions">
                                            <?php if ($comment->comment_approved === '0'): ?>
                                                <button type="button" class="button button-small rsp-approve-btn" data-comment-id="<?php echo $comment->comment_ID; ?>">
                                                    <?php _e('Approve', 'reddit-style-posts'); ?>
                                                </button>
                                            <?php elseif ($comment->comment_approved === '1'): ?>
                                                <button type="button" class="button button-small rsp-unapprove-btn" data-comment-id="<?php echo $comment->comment_ID; ?>">
                                                    <?php _e('Unapprove', 'reddit-style-posts'); ?>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($comment->comment_approved !== 'spam'): ?>
                                                <button type="button" class="button button-small rsp-spam-btn" data-comment-id="<?php echo $comment->comment_ID; ?>">
                                                    <?php _e('Spam', 'reddit-style-posts'); ?>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="button button-small button-link-delete rsp-delete-btn" data-comment-id="<?php echo $comment->comment_ID; ?>">
                                                <?php _e('Delete', 'reddit-style-posts'); ?>
                                            </button>
                                            
                                            <a href="<?php echo get_permalink($comment->comment_post_ID); ?>#comment-<?php echo $comment->comment_ID; ?>" 
                                               target="_blank" class="button button-small">
                                                <?php _e('View', 'reddit-style-posts'); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php
                // Pagination
                $total_pages = ceil($comments_query->found_comments / $per_page);
                if ($total_pages > 1):
                ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'current' => $paged,
                            'total' => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ));
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Select all checkboxes
            $('#cb-select-all').on('change', function() {
                $('input[name="comment_ids[]"]').prop('checked', $(this).prop('checked'));
            });
            
            // Individual comment actions
            $('.rsp-approve-btn').on('click', function() {
                moderateComment($(this).data('comment-id'), 'approve');
            });
            
            $('.rsp-unapprove-btn').on('click', function() {
                moderateComment($(this).data('comment-id'), 'unapprove');
            });
            
            $('.rsp-spam-btn').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to mark this as spam?', 'reddit-style-posts'); ?>')) {
                    moderateComment($(this).data('comment-id'), 'spam');
                }
            });
            
            $('.rsp-delete-btn').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to permanently delete this comment?', 'reddit-style-posts'); ?>')) {
                    moderateComment($(this).data('comment-id'), 'delete');
                }
            });
            
            function moderateComment(commentId, action) {
                var $row = $('.comment-' + commentId);
                $row.css('opacity', '0.5');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rsp_' + action + '_comment',
                        comment_id: commentId,
                        nonce: '<?php echo wp_create_nonce('rsp_moderate_comment'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            if (action === 'delete') {
                                $row.fadeOut(300, function() { $(this).remove(); });
                            } else {
                                location.reload();
                            }
                        } else {
                            alert(response.data || '<?php _e('Action failed.', 'reddit-style-posts'); ?>');
                            $row.css('opacity', '1');
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred.', 'reddit-style-posts'); ?>');
                        $row.css('opacity', '1');
                    }
                });
            }
        });
        </script>
        
        <style>
        .rsp-comment-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .rsp-comment-actions .button {
            margin: 0;
        }
        .comment-author {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        .comment-author img {
            border-radius: 50%;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Delete comment
     */
    public function ajax_delete_comment() {
        check_ajax_referer('rsp_moderate_comment', 'nonce');
        
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(__('You do not have permission to delete comments.', 'reddit-style-posts'));
        }
        
        $comment_id = intval($_POST['comment_id']);
        
        if (wp_delete_comment($comment_id, true)) {
            wp_send_json_success(__('Comment deleted successfully.', 'reddit-style-posts'));
        } else {
            wp_send_json_error(__('Failed to delete comment.', 'reddit-style-posts'));
        }
    }
    
    /**
     * AJAX: Approve comment
     */
    public function ajax_approve_comment() {
        check_ajax_referer('rsp_moderate_comment', 'nonce');
        
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(__('You do not have permission to approve comments.', 'reddit-style-posts'));
        }
        
        $comment_id = intval($_POST['comment_id']);
        
        if (wp_set_comment_status($comment_id, 'approve')) {
            wp_send_json_success(__('Comment approved successfully.', 'reddit-style-posts'));
        } else {
            wp_send_json_error(__('Failed to approve comment.', 'reddit-style-posts'));
        }
    }
    
    /**
     * AJAX: Unapprove comment
     */
    public function ajax_unapprove_comment() {
        check_ajax_referer('rsp_moderate_comment', 'nonce');
        
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(__('You do not have permission to unapprove comments.', 'reddit-style-posts'));
        }
        
        $comment_id = intval($_POST['comment_id']);
        
        if (wp_set_comment_status($comment_id, 'hold')) {
            wp_send_json_success(__('Comment unapproved successfully.', 'reddit-style-posts'));
        } else {
            wp_send_json_error(__('Failed to unapprove comment.', 'reddit-style-posts'));
        }
    }
    
    /**
     * AJAX: Mark comment as spam
     */
    public function ajax_spam_comment() {
        check_ajax_referer('rsp_moderate_comment', 'nonce');
        
        if (!current_user_can('moderate_comments')) {
            wp_send_json_error(__('You do not have permission to mark comments as spam.', 'reddit-style-posts'));
        }
        
        $comment_id = intval($_POST['comment_id']);
        
        if (wp_spam_comment($comment_id)) {
            wp_send_json_success(__('Comment marked as spam.', 'reddit-style-posts'));
        } else {
            wp_send_json_error(__('Failed to mark comment as spam.', 'reddit-style-posts'));
        }
    }
    
    /**
     * Allow anonymous comments (bypass name/email requirement)
     */
    public function allow_anonymous_comments($approved, $commentdata) {
        return $approved;
    }
    
    /**
     * Set default anonymous author for comments without name
     */
    public function set_anonymous_author($commentdata) {
        if (empty($commentdata['comment_author'])) {
            $commentdata['comment_author'] = 'Anonymous';
        }
        if (empty($commentdata['comment_author_email'])) {
            $commentdata['comment_author_email'] = '';
        }
        return $commentdata;
    }
}

// Initialize the plugin
Reddit_Style_Posts::get_instance();

