/**
 * Reddit Style Posts - Main JavaScript
 * Handles voting, content expansion, and interactions
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // =====================================================================
        // Content Toggle (Read More / Show Less)
        // =====================================================================
        
        $('#rsp-toggle-content').on('click', function() {
            var $excerpt = $('#rsp-excerpt');
            var $fullContent = $('#rsp-full-content');
            var $expandText = $('.rsp-expand-text');
            var $collapseText = $('.rsp-collapse-text');
            
            if ($fullContent.is(':visible')) {
                // Collapse
                $fullContent.slideUp(300);
                $excerpt.slideDown(300);
                $expandText.show();
                $collapseText.hide();
                
                // Scroll to top of post
                $('html, body').animate({
                    scrollTop: $('.rsp-content-injection').offset().top - 100
                }, 300);
            } else {
                // Expand
                $excerpt.slideUp(300);
                $fullContent.slideDown(300);
                $expandText.hide();
                $collapseText.show();
            }
        });
        
        // =====================================================================
        // Voting System (Posts and Comments)
        // =====================================================================
        
        $(document).on('click', '.rsp-vote-btn, .rsp-vote-action', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var $container = $btn.closest('.rsp-voting, .rsp-comment-voting, .rsp-post-actions');
            var $voteCount = $container.find('.rsp-vote-count');
            var postId = $btn.data('post-id');
            var commentId = $btn.data('comment-id') || 0;
            var voteType = $btn.data('vote-type');
            
            // Check if user is logged in (if guest voting is disabled)
            if (!rspAjax.is_user_logged_in && !$btn.closest('.rsp-post').length) {
                // For now, we'll allow it - server will check guest_voting option
            }
            
            // Disable buttons during request
            $container.find('.rsp-vote-btn, .rsp-vote-action').prop('disabled', true);
            
            $.ajax({
                url: rspAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rsp_vote',
                    nonce: rspAjax.nonce,
                    post_id: postId,
                    comment_id: commentId,
                    vote_type: voteType
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Update vote count
                        $voteCount.text(formatNumber(data.score));
                        $voteCount.attr('data-score', data.score);
                        
                        // Update button states
                        $container.find('.rsp-upvote, .rsp-upvote-action').removeClass('active');
                        $container.find('.rsp-downvote, .rsp-downvote-action').removeClass('active');
                        
                        if (data.action === 'added' || data.action === 'changed') {
                            $btn.addClass('active');
                        }
                        
                        // Animate the vote
                        $voteCount.addClass('rsp-vote-animated');
                        setTimeout(function() {
                            $voteCount.removeClass('rsp-vote-animated');
                        }, 300);
                        
                    } else {
                        alert(response.data || 'Failed to vote. Please try again.');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $container.find('.rsp-vote-btn, .rsp-vote-action').prop('disabled', false);
                }
            });
        });
        
        // =====================================================================
        // Share Functionality
        // =====================================================================
        
        $('#rsp-share-btn').on('click', function(e) {
            e.preventDefault();
            $('#rsp-share-dropdown').toggle();
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#rsp-share-btn, #rsp-share-dropdown').length) {
                $('#rsp-share-dropdown').hide();
            }
        });
        
        // Copy link functionality
        $('#rsp-copy-link').on('click', function(e) {
            e.preventDefault();
            
            var url = window.location.href;
            
            // Try modern clipboard API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    showNotification('Link copied to clipboard!');
                    $('#rsp-share-dropdown').hide();
                }).catch(function() {
                    fallbackCopyText(url);
                });
            } else {
                fallbackCopyText(url);
            }
        });
        
        // =====================================================================
        // Comment Reply Enhancement
        // =====================================================================
        
        $(document).on('click', '.rsp-reply-btn', function(e) {
            // WordPress handles the reply functionality
            // We just need to scroll to the form
            setTimeout(function() {
                var $form = $('#respond');
                if ($form.length) {
                    $('html, body').animate({
                        scrollTop: $form.offset().top - 20
                    }, 300);
                    $('#comment').focus();
                }
            }, 100);
        });
        
        // =====================================================================
        // Smooth Scroll to Comments
        // =====================================================================
        
        $('.rsp-comment-btn').on('click', function(e) {
            e.preventDefault();
            var $comments = $('#rsp-comments');
            if ($comments.length) {
                $('html, body').animate({
                    scrollTop: $comments.offset().top - 20
                }, 500);
            }
        });
        
        // =====================================================================
        // Comment Form Enhancement
        // =====================================================================
        
        // Add placeholder to comment textarea if not already set
        if ($('#comment').length && !$('#comment').attr('placeholder')) {
            $('#comment').attr('placeholder', 'What are your thoughts?');
        }
        
        // Auto-expand textarea as user types
        $('#comment').on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // =====================================================================
        // Keyboard Shortcuts (Optional Enhancement)
        // =====================================================================
        
        $(document).on('keydown', function(e) {
            // Press 'C' to focus comment box
            if (e.key === 'c' && !$(e.target).is('input, textarea')) {
                e.preventDefault();
                $('#comment').focus();
                $('html, body').animate({
                    scrollTop: $('#respond').offset().top - 20
                }, 300);
            }
        });
        
        // =====================================================================
        // Utility Functions
        // =====================================================================
        
        /**
         * Format number for display (e.g., 1.2k, 10k)
         */
        function formatNumber(num) {
            num = parseInt(num);
            
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'm';
            } else if (num >= 10000) {
                return (num / 1000).toFixed(0) + 'k';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'k';
            }
            
            return num.toString();
        }
        
        /**
         * Show notification message
         */
        function showNotification(message) {
            var $notification = $('<div class="rsp-notification">' + message + '</div>');
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.addClass('rsp-notification-show');
            }, 10);
            
            setTimeout(function() {
                $notification.removeClass('rsp-notification-show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 2000);
        }
        
        /**
         * Fallback copy to clipboard function
         */
        function fallbackCopyText(text) {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                document.execCommand('copy');
                showNotification('Link copied to clipboard!');
                $('#rsp-share-dropdown').hide();
            } catch (err) {
                alert('Failed to copy link. Please copy manually: ' + text);
            }
            
            $temp.remove();
        }
        
        // =====================================================================
        // Lazy Load Images (Performance Enhancement)
        // =====================================================================
        
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });
            
            $('.rsp-post-content img[data-src]').each(function() {
                imageObserver.observe(this);
            });
        }
        
        // =====================================================================
        // Vote Animation CSS
        // =====================================================================
        
        // Add animation styles dynamically
        if (!$('#rsp-dynamic-styles').length) {
            $('<style id="rsp-dynamic-styles">' +
                '.rsp-vote-animated { ' +
                '    animation: rsp-vote-pulse 0.3s ease; ' +
                '} ' +
                '@keyframes rsp-vote-pulse { ' +
                '    0% { transform: scale(1); } ' +
                '    50% { transform: scale(1.3); } ' +
                '    100% { transform: scale(1); } ' +
                '} ' +
                '.rsp-notification { ' +
                '    position: fixed; ' +
                '    bottom: 20px; ' +
                '    right: 20px; ' +
                '    background: #0079D3; ' +
                '    color: white; ' +
                '    padding: 12px 20px; ' +
                '    border-radius: 4px; ' +
                '    box-shadow: 0 4px 12px rgba(0,0,0,0.15); ' +
                '    z-index: 10000; ' +
                '    opacity: 0; ' +
                '    transform: translateY(20px); ' +
                '    transition: all 0.3s ease; ' +
                '} ' +
                '.rsp-notification-show { ' +
                '    opacity: 1; ' +
                '    transform: translateY(0); ' +
                '}' +
            '</style>').appendTo('head');
        }
        
        // =====================================================================
        // Track Comment Visibility (for analytics/SEO)
        // =====================================================================
        
        if ('IntersectionObserver' in window) {
            var commentsObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        // Comments are visible - could track this event
                        console.log('Comments section is now visible');
                        commentsObserver.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            
            var $commentsSection = $('#rsp-comments');
            if ($commentsSection.length) {
                commentsObserver.observe($commentsSection[0]);
            }
        }
        
        // =====================================================================
        // Mobile Touch Enhancement
        // =====================================================================
        
        if ('ontouchstart' in window) {
            // Add touch-friendly hover states
            $('.rsp-vote-btn, .rsp-action-btn').on('touchstart', function() {
                $(this).addClass('rsp-touch-active');
            }).on('touchend', function() {
                var $this = $(this);
                setTimeout(function() {
                    $this.removeClass('rsp-touch-active');
                }, 150);
            });
            
            // Add touch styles
            $('<style>' +
                '.rsp-touch-active { ' +
                '    background-color: rgba(0,0,0,0.1) !important; ' +
                '}' +
            '</style>').appendTo('head');
        }
        
        // =====================================================================
        // Auto-save Comment Draft (Local Storage)
        // =====================================================================
        
        var commentDraftKey = 'rsp_comment_draft_' + rspAjax.post_id;
        
        // Load draft on page load
        var savedDraft = localStorage.getItem(commentDraftKey);
        if (savedDraft && $('#comment').val() === '') {
            $('#comment').val(savedDraft);
            showNotification('Draft restored');
        }
        
        // Save draft as user types
        var draftTimeout;
        $('#comment').on('input', function() {
            clearTimeout(draftTimeout);
            var content = $(this).val();
            
            draftTimeout = setTimeout(function() {
                if (content.length > 0) {
                    localStorage.setItem(commentDraftKey, content);
                } else {
                    localStorage.removeItem(commentDraftKey);
                }
            }, 1000);
        });
        
        // Clear draft on successful submission
        $('#commentform').on('submit', function() {
            setTimeout(function() {
                localStorage.removeItem(commentDraftKey);
            }, 1000);
        });
        
        // =====================================================================
        // Scroll Progress Indicator (Optional)
        // =====================================================================
        
        var $progressBar = $('<div class="rsp-scroll-progress"></div>');
        $('body').append($progressBar);
        
        $(window).on('scroll', function() {
            var scrollTop = $(window).scrollTop();
            var docHeight = $(document).height();
            var winHeight = $(window).height();
            var scrollPercent = (scrollTop) / (docHeight - winHeight);
            var scrollPercentRounded = Math.round(scrollPercent * 100);
            
            $progressBar.css('width', scrollPercentRounded + '%');
        });
        
        $('<style>' +
            '.rsp-scroll-progress { ' +
            '    position: fixed; ' +
            '    top: 0; ' +
            '    left: 0; ' +
            '    height: 3px; ' +
            '    background: #FF4500; ' +
            '    z-index: 9999; ' +
            '    transition: width 0.1s ease; ' +
            '}' +
        '</style>').appendTo('head');
        
        // =====================================================================
        // Initialize Complete
        // =====================================================================
        
        console.log('Reddit Style Posts: Initialized');
        
    });
    
})(jQuery);

