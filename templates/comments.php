<?php
/**
 * Reddit-Style Comments Template
 * Features threaded replies and voting
 * Optimized for injection mode
 */

if (!defined('ABSPATH')) {
    exit;
}

if (post_password_required()) {
    return;
}

$rsp = Reddit_Style_Posts::get_instance();

/**
 * Custom comment callback function - Define before use
 */
if (!function_exists('rsp_custom_comment')) {
    function rsp_custom_comment($comment, $args, $depth) {
        $rsp = Reddit_Style_Posts::get_instance();
        $vote_counts = $rsp->get_vote_counts(get_the_ID(), $comment->comment_ID);
        $user_vote = $rsp->get_user_vote(get_the_ID(), $comment->comment_ID);
        $enable_voting = get_option('rsp_enable_voting', '1') === '1';
        $show_vote_count = get_option('rsp_show_vote_count', '1') === '1';
        
        $GLOBALS['comment'] = $comment;
        ?>
    <li <?php comment_class('rsp-comment'); ?> id="comment-<?php comment_ID(); ?>">
        <article class="rsp-comment-body">
            
            <!-- Comment Voting - Authentic Reddit Icons -->
            <?php if ($enable_voting): ?>
            <div class="rsp-comment-voting">
                <button class="rsp-vote-btn rsp-upvote <?php echo $user_vote === 'upvote' ? 'active' : ''; ?>" 
                        data-post-id="<?php echo get_the_ID(); ?>"
                        data-comment-id="<?php echo $comment->comment_ID; ?>" 
                        data-vote-type="upvote"
                        aria-label="Upvote comment">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.877 19h-1.754c-.108 0-.157-.005-.221-.027-.359-.109-.418-.452-.418-.796v-6.875h-3.486c-.192 0-.393-.024-.535-.142-.134-.111-.195-.296-.195-.488 0-.126.03-.261.088-.38l6.378-13.107c.076-.155.174-.282.305-.372.124-.086.283-.13.451-.13.165 0 .328.044.451.13.132.09.228.217.305.372l6.379 13.107c.058.119.087.254.087.38 0 .192-.062.377-.196.488-.142.118-.344.142-.535.142h-3.485v6.875c0 .344-.059.687-.418.796-.064.022-.112.027-.221.027z"/>
                    </svg>
                </button>
                
                <?php if ($show_vote_count): ?>
                <span class="rsp-vote-count" data-score="<?php echo $vote_counts['score']; ?>">
                    <?php echo number_format_i18n($vote_counts['score']); ?>
                </span>
                <?php endif; ?>
                
                <button class="rsp-vote-btn rsp-downvote <?php echo $user_vote === 'downvote' ? 'active' : ''; ?>" 
                        data-post-id="<?php echo get_the_ID(); ?>"
                        data-comment-id="<?php echo $comment->comment_ID; ?>" 
                        data-vote-type="downvote"
                        aria-label="Downvote comment">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.123 5h1.754c.108 0 .157.005.221.027.359.109.418.452.418.796v6.875h3.486c.192 0 .393.024.535.142.134.111.195.296.195.488 0 .126-.03.261-.088.38l-6.378 13.107c-.076.155-.174.282-.305.372-.124.086-.283.13-.451.13-.165 0-.328-.044-.451-.13-.132-.09-.228-.217-.305-.372l-6.379-13.107c-.058-.119-.087-.254-.087-.38 0-.192.062-.377.196-.488.142-.118.344-.142.535-.142h3.485v-6.875c0-.344.059-.687.418-.796.064-.022.112-.027.221-.027z"/>
                    </svg>
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Comment Content -->
            <div class="rsp-comment-content-wrapper">
                
                <!-- Comment Meta -->
                <div class="rsp-comment-meta">
                    <div class="rsp-comment-author-avatar">
                        <?php echo get_avatar($comment, 32); ?>
                    </div>
                    <div class="rsp-comment-info">
                        <span class="rsp-comment-author-name">
                            <?php echo get_comment_author_link(); ?>
                        </span>
                        <?php if ($comment->user_id == get_post()->post_author): ?>
                            <span class="rsp-author-badge">Author</span>
                        <?php endif; ?>
                        <span class="rsp-comment-date">
                            <?php echo human_time_diff(get_comment_time('U'), current_time('timestamp')) . ' ago'; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Comment Text -->
                <div class="rsp-comment-text">
                    <?php if ($comment->comment_approved == '0'): ?>
                        <em class="rsp-comment-awaiting-moderation"><?php _e('Your comment is awaiting moderation.', 'reddit-style-posts'); ?></em>
                    <?php endif; ?>
                    <?php comment_text(); ?>
                </div>
                
                <!-- Comment Actions -->
                <div class="rsp-comment-actions">
                    <?php
                    comment_reply_link(array_merge($args, array(
                        'add_below' => 'comment',
                        'depth' => $depth,
                        'max_depth' => $args['max_depth'],
                        'before' => '<button class="rsp-reply-btn">',
                        'after' => '</button>',
                    )));
                    ?>
                    
                    <?php if (current_user_can('edit_comment', $comment->comment_ID)): ?>
                        <?php edit_comment_link(__('Edit', 'reddit-style-posts'), '<button class="rsp-edit-btn">', '</button>'); ?>
                    <?php endif; ?>
                </div>
                
            </div>
        </article>
        
        <?php
        // Children comments will be automatically nested by wp_list_comments
        ?>
    <?php
    // Note: </li> is automatically closed by WordPress
    }
}
?>

<div id="comments" class="rsp-comments">
    
    <?php
    // Debug: Show comment form status - ALWAYS show if debug is on
    if (get_option('rsp_debug_mode', '0') === '1' && current_user_can('manage_options')) {
        echo '<div style="background: #e3f2fd; border: 2px solid #2196f3; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; font-size: 12px; z-index: 9999; position: relative;">';
        echo '<strong>💬 Comment Form Section Debug:</strong><br>';
        echo '• This section is executing: Yes<br>';
        echo '• comments_open(): ' . (comments_open() ? 'Yes' : 'No') . '<br>';
        echo '• Current user ID: ' . get_current_user_id() . '<br>';
        echo '• User can comment: ' . (comments_open() || current_user_can('moderate_comments') ? 'Yes' : 'No') . '<br>';
        echo '• About to call comment_form(): ' . (comments_open() ? 'Yes' : 'No - comments closed') . '<br>';
        echo '</div>';
    }
    
    // Comment form at the TOP
    if (comments_open()) {
        // Debug before form
        if (get_option('rsp_debug_mode', '0') === '1' && current_user_can('manage_options')) {
            echo '<div style="background: #c8e6c9; border: 2px solid #4caf50; padding: 10px; margin: 5px 0; border-radius: 5px; font-family: monospace; font-size: 11px;">';
            echo '✅ Calling comment_form() now...';
            echo '</div>';
        }
        
        // Anonymous commenting - no name/email required
        comment_form(array(
            'class_container' => 'rsp-comment-respond rsp-comment-form-top',
            'class_form' => 'rsp-comment-form',
            'title_reply' => __('Post a Comment', 'reddit-style-posts'),
            'title_reply_to' => __('Reply to %s', 'reddit-style-posts'),
            'cancel_reply_link' => __('Cancel', 'reddit-style-posts'),
            'label_submit' => __('Comment', 'reddit-style-posts'),
            'comment_field' => '<p class="comment-form-comment">
                <label for="comment">' . _x('Comment', 'noun', 'reddit-style-posts') . '</label>
                <textarea id="comment" name="comment" cols="15" rows="1" aria-required="true" placeholder="What are your thoughts?"></textarea>
            </p>',
            'fields' => array(), // Empty array = no name/email/url fields
            'logged_in_as' => '', // Remove "logged in as" text
            'comment_notes_before' => '', // Remove notes before form
            'comment_notes_after' => '', // Remove notes after form
        ));
        
        // Debug after form
        if (get_option('rsp_debug_mode', '0') === '1' && current_user_can('manage_options')) {
            echo '<div style="background: #fff9c4; border: 2px solid #fbc02d; padding: 10px; margin: 5px 0; border-radius: 5px; font-family: monospace; font-size: 11px;">';
            echo '✅ comment_form() completed';
            echo '</div>';
        }
        
    } else {
        // Debug if comments closed
        if (get_option('rsp_debug_mode', '0') === '1' && current_user_can('manage_options')) {
            echo '<div style="background: #ffcdd2; border: 2px solid #f44336; padding: 10px; margin: 5px 0; border-radius: 5px; font-family: monospace; font-size: 11px;">';
            echo '❌ Comments are closed - form not rendered';
            echo '</div>';
        }
    }
    ?>
    
    <?php if (have_comments()): ?>
        <ul class="rsp-comment-list">
            <?php
            wp_list_comments(array(
                'style' => 'ul',
                'callback' => 'rsp_custom_comment',
                'max_depth' => 5,
                'avatar_size' => 32,
            ));
            ?>
        </ul>

        <?php
        // Comment pagination
        if (get_comment_pages_count() > 1 && get_option('page_comments')):
        ?>
            <nav class="rsp-comment-navigation">
                <div class="nav-previous"><?php previous_comments_link(__('← Older Comments', 'reddit-style-posts')); ?></div>
                <div class="nav-next"><?php next_comments_link(__('Newer Comments →', 'reddit-style-posts')); ?></div>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <?php if (comments_open()): ?>
            <p class="rsp-no-comments"><?php _e('No comments yet. Be the first to share your thoughts!', 'reddit-style-posts'); ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!comments_open() && get_comments_number() && post_type_supports(get_post_type(), 'comments')): ?>
        <p class="rsp-no-comments"><?php _e('Comments are closed.', 'reddit-style-posts'); ?></p>
    <?php endif; ?>

</div>