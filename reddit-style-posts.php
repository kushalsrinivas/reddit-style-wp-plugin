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
        
        // Template override for single posts
        add_filter('template_include', array($this, 'override_post_template'));
        
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
     * Override single post template
     */
    public function override_post_template($template) {
        if (is_single() && get_post_type() === 'post') {
            $custom_template = RSP_PLUGIN_DIR . 'templates/single-post.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    /**
     * Custom comments template
     */
    public function custom_comments_template($template) {
        if (is_single() && get_post_type() === 'post') {
            $custom_template = RSP_PLUGIN_DIR . 'templates/comments.php';
            if (file_exists($custom_template)) {
                return $custom_template;
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

