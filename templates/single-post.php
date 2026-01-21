<?php
/**
 * Reddit-Style Single Post Template
 * 
 * This template replaces the default single post template
 * with a Reddit-style layout featuring prominent comments
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$rsp = Reddit_Style_Posts::get_instance();
$post_id = get_the_ID();
$vote_counts = $rsp->get_vote_counts($post_id);
$user_vote = $rsp->get_user_vote($post_id);
$enable_voting = get_option('rsp_enable_voting', '1') === '1';
$show_vote_count = get_option('rsp_show_vote_count', '1') === '1';
$enable_share = get_option('rsp_enable_share_buttons', '1') === '1';

?>

<div class="rsp-container">
    <article id="post-<?php the_ID(); ?>" <?php post_class('rsp-post'); ?>>
        
        <!-- Voting Section (Left Side) -->
        <?php if ($enable_voting): ?>
        <div class="rsp-voting">
            <button class="rsp-vote-btn rsp-upvote <?php echo $user_vote === 'upvote' ? 'active' : ''; ?>" 
                    data-post-id="<?php echo $post_id; ?>" 
                    data-vote-type="upvote"
                    aria-label="Upvote">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4l8 8h-6v8h-4v-8H4z"/>
                </svg>
            </button>
            
            <?php if ($show_vote_count): ?>
            <span class="rsp-vote-count" data-score="<?php echo $vote_counts['score']; ?>">
                <?php echo number_format_i18n($vote_counts['score']); ?>
            </span>
            <?php endif; ?>
            
            <button class="rsp-vote-btn rsp-downvote <?php echo $user_vote === 'downvote' ? 'active' : ''; ?>" 
                    data-post-id="<?php echo $post_id; ?>" 
                    data-vote-type="downvote"
                    aria-label="Downvote">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 20l-8-8h6V4h4v8h6z"/>
                </svg>
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Content Section -->
        <div class="rsp-content-wrapper">
            
            <!-- Post Header -->
            <div class="rsp-post-header">
                <div class="rsp-post-meta">
                    <span class="rsp-post-author">
                        Posted by <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>"><?php the_author(); ?></a>
                    </span>
                    <span class="rsp-post-date">
                        <?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago'; ?>
                    </span>
                    <?php
                    $categories = get_the_category();
                    if ($categories):
                    ?>
                    <span class="rsp-post-category">
                        in <a href="<?php echo esc_url(get_category_link($categories[0]->term_id)); ?>">
                            <?php echo esc_html($categories[0]->name); ?>
                        </a>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Post Title -->
            <header class="rsp-post-title">
                <h1><?php the_title(); ?></h1>
            </header>
            
            <!-- Featured Image -->
            <?php if (has_post_thumbnail()): ?>
            <div class="rsp-featured-image">
                <?php the_post_thumbnail('large'); ?>
            </div>
            <?php endif; ?>
            
            <!-- Post Content (Collapsible) -->
            <div class="rsp-post-content">
                <div class="rsp-excerpt" id="rsp-excerpt">
                    <?php
                    $excerpt_length = get_option('rsp_excerpt_length', 150);
                    $content = get_the_content();
                    $excerpt = wp_trim_words($content, $excerpt_length, '...');
                    echo apply_filters('the_content', $excerpt);
                    ?>
                </div>
                
                <div class="rsp-full-content" id="rsp-full-content" style="display: none;">
                    <?php the_content(); ?>
                </div>
                
                <button class="rsp-read-more-btn" id="rsp-toggle-content">
                    <span class="rsp-expand-text">Read More</span>
                    <span class="rsp-collapse-text" style="display: none;">Show Less</span>
                </button>
            </div>
            
            <!-- Post Actions -->
            <div class="rsp-post-actions">
                <button class="rsp-action-btn rsp-comment-btn" onclick="document.getElementById('rsp-comments').scrollIntoView({behavior: 'smooth'})">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                    </svg>
                    <?php 
                    $comment_count = get_comments_number();
                    echo $comment_count . ' ' . _n('Comment', 'Comments', $comment_count, 'reddit-style-posts');
                    ?>
                </button>
                
                <?php if ($enable_share): ?>
                <button class="rsp-action-btn rsp-share-btn" id="rsp-share-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                        <polyline points="16 6 12 2 8 6"/>
                        <line x1="12" y1="2" x2="12" y2="15"/>
                    </svg>
                    Share
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
            
            <!-- Tags -->
            <?php if (has_tag()): ?>
            <div class="rsp-post-tags">
                <?php the_tags('<span class="rsp-tag-icon">🏷️</span> ', ', ', ''); ?>
            </div>
            <?php endif; ?>
            
        </div>
    </article>
    
    <!-- Comments Section (Always Prominent) -->
    <div class="rsp-comments-container" id="rsp-comments">
        <div class="rsp-comments-header">
            <h2>
                <?php 
                $comment_count = get_comments_number();
                echo $comment_count . ' ' . _n('Comment', 'Comments', $comment_count, 'reddit-style-posts');
                ?>
            </h2>
            <p class="rsp-comments-subtitle">What are your thoughts? Share them below!</p>
        </div>
        
        <?php
        // Load comments template
        if (comments_open() || get_comments_number()) {
            comments_template();
        }
        ?>
    </div>
</div>

<?php
get_footer();

