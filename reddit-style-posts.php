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
        
        // Add vote counts to posts
        add_action('wp_insert_post', array($this, 'initialize_post_meta'), 10, 2);
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
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
            'rsp_guest_voting'
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
                    
                    <!-- Share Button - Authentic Reddit Icon -->
                    <?php if ($enable_share): ?>
                    <button class="rsp-action-btn rsp-share-btn" id="rsp-share-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.162 0c-.924 0-1.758.359-2.385.995l-7.929 8.034c-1.26.03-2.395.488-3.283 1.288-.888.8-1.48 1.897-1.645 3.127-.03.223.135.426.359.426h1.748c.185 0 .344-.135.374-.315.179-.997.673-1.843 1.375-2.422.703-.579 1.555-.888 2.445-.888h.135l-.015.015 7.944 8.049c.612.621 1.461.995 2.385.995 1.867 0 3.383-1.516 3.383-3.383 0-.924-.359-1.758-.995-2.385l-3.353-3.353 3.338-3.338c.621-.612.995-1.461.995-2.385 0-1.867-1.516-3.383-3.383-3.383zm-7.222 14.436c-.389.03-.778.105-1.152.225h-.045c.09-.404.27-.768.539-1.062l3.772-3.817 1.305 1.305-3.772 3.817c-.21.21-.449.374-.703.494-.209.105-.434.18-.659.21-.03 0-.03.015-.045.015l-.015-.015c-.03-.015-.06-.015-.09-.015-.03 0-.045 0-.075.015-.03-.015-.045-.015-.06-.015zm7.207 1.455c.404-.389.868-.643 1.365-.748.015 0 .03 0 .03-.015.135-.03.27-.045.419-.045.09 0 .165.015.254.015.045 0 .09.015.12.015.135.015.27.045.389.075l.015.015c-.015 0-.015 0 0 0 .015 0 .015 0 0 0 .09.03.18.06.254.09 0 .015.015.015.015.015.015 0 .015.015.03.015.494.225.913.599 1.197 1.062l-3.818 3.817-1.305-1.305 3.832-3.832.015-.015c-.419.015-.823-.09-1.182-.359z"/>
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
                // Load comments immediately after action buttons
                if (comments_open() || get_comments_number()) {
                    $this->loading_comments_in_injection = true;
                    comments_template();
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
        
        // Block theme's featured image on single post pages where we're injecting our own
        if (is_single() && get_post_type() === 'post' && in_the_loop() && is_main_query()) {
            return ''; // Return empty to prevent duplicate
        }
        return $html;
    }
    
    /**
     * Custom comments template
     */
    public function custom_comments_template($template) {
        if (is_single() && get_post_type() === 'post') {
            // If we're loading comments in our injection, use our template
            if ($this->loading_comments_in_injection) {
                $custom_template = RSP_PLUGIN_DIR . 'templates/comments.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            } else {
                // Prevent theme from loading comments separately
                // Return an empty template to suppress theme's comments
                return RSP_PLUGIN_DIR . 'templates/empty-comments.php';
            }
        }
        return $template;
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
}

// Initialize the plugin
Reddit_Style_Posts::get_instance();

