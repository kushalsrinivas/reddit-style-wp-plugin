# Reddit Style Posts - WordPress Plugin

A WordPress plugin that adds Reddit-style voting and comments to your blog posts while preserving your theme's design.

## Features

✅ **Injection Mode** - Works with your existing theme (no template override)  
✅ **Dark Theme Compatible** - Beautiful glassmorphism design that blends with dark themes  
✅ **Upvote/Downvote System** - Reddit-style voting for posts and comments  
✅ **Threaded Comments** - Nested replies up to 5 levels deep  
✅ **Read More/Less** - Collapsible content with smooth animations  
✅ **Social Sharing** - Twitter, Facebook, Reddit, Copy Link  
✅ **Mobile Responsive** - Perfect on all devices  
✅ **Fast Performance** - Optimized CSS/JS, GPU acceleration, lazy loading  
✅ **Accessibility** - ARIA labels, keyboard shortcuts, reduced motion support  

## Installation

1. The plugin is already installed at: `wp-content/plugins/reddit-style-posts/`
2. Go to **WordPress Admin → Plugins**
3. Find "Reddit Style Posts" and click **Activate**

## Configuration

After activation, go to **WordPress Admin → Reddit Posts** to configure:

### Settings

- **Enable Voting System** - Allow users to upvote/downvote posts
- **Show Vote Count** - Display the number of votes publicly
- **Allow Guest Voting** - Let non-logged-in users vote (tracked by IP)
- **Enable Share Buttons** - Show social share buttons
- **Excerpt Length** - Number of words to show before "Read More" (default: 150)
- **Comments Always Visible** - Keep comments section visible (Reddit-style)

## Post Page Layout

The plugin injects the following structure into your single post pages:

```
[Your Theme Header/Navigation - Unchanged]

Main Content Area:
  ├─ Featured Image
  ├─ Article Title (from theme)
  ├─ Article Meta (from theme)
  ├─ Author, Date, Category (from theme)
  ├─ Blog Post Content
  ├─ Read More Button
  ├─ Post Actions Bar (40% bottom space)
  │   ├─ Upvote Button (with count)
  │   ├─ Comment Button (with count)
  │   └─ Share Button
  └─ Reddit-Style Comments Section
      ├─ Comment Header
      ├─ Existing Comments (threaded)
      └─ Comment Form

[Your Theme Sidebar - Unchanged]
[Your Theme Footer - Unchanged]
```

## Usage

### For Visitors

1. **Upvoting**: Click the upvote button on posts or comments
2. **Commenting**: Scroll to comments section, fill form, click "Post Comment"
3. **Replying**: Click "Reply" under any comment to create threaded discussion
4. **Sharing**: Click "Share" button to share on social media

### Keyboard Shortcuts

- Press `C` to focus the comment box

### For Administrators

- View voting statistics in **Admin → Reddit Posts**
- Moderate comments as usual in **Admin → Comments**
- All votes are stored in custom database table `wp_rsp_votes`

## Technical Details

### Database Table

The plugin creates a table `wp_rsp_votes` with the following structure:

```sql
- id: Unique vote ID
- post_id: The post being voted on
- comment_id: The comment being voted on (0 for post votes)
- user_id: Logged-in user ID (0 for guests)
- user_ip: IP address (for guest voting)
- vote_type: 'upvote' or 'downvote'
- voted_at: Timestamp
```

### Files Structure

```
reddit-style-posts/
├── assets/
│   ├── style.css          # Main stylesheet (dark theme compatible)
│   ├── script.js          # JavaScript functionality
│   └── admin-style.css    # Admin panel styles
├── templates/
│   ├── single-post.php    # Single post template (not used in injection mode)
│   └── comments.php       # Custom comments template
├── reddit-style-posts.php # Main plugin file
└── README.md              # This file
```

### Hooks & Filters

The plugin uses the following WordPress hooks:

- `the_content` - Inject Reddit-style elements
- `comments_template` - Replace comments with custom template
- `wp_enqueue_scripts` - Load CSS/JS
- `wp_ajax_rsp_vote` - Handle AJAX voting

### Performance Optimizations

✅ CSS/JS only loaded on single post pages  
✅ GPU acceleration for smooth animations  
✅ Intersection Observer for lazy loading  
✅ Debounced comment draft auto-save  
✅ Optimized SQL queries with proper indexing  
✅ Reduced motion support for accessibility  

## Customization

### Styling

To customize the appearance, you can override the CSS by adding to your theme's `style.css`:

```css
/* Example: Change upvote button color */
.rsp-upvote-action {
  background: rgba(255, 215, 0, 0.1) !important;
  border-color: rgba(255, 215, 0, 0.3) !important;
  color: #ffd700 !important;
}

/* Example: Change comment background */
.rsp-comment-body {
  background: rgba(255, 255, 255, 0.05) !important;
}
```

### Age Format Order

The plugin displays timestamps in the following order:
- Seconds ago (< 1 minute)
- Minutes ago (< 1 hour)
- Hours ago (< 1 day)
- Days ago (< 1 week)
- Weeks ago (< 1 month)
- Months ago (< 1 year)
- Years ago (>= 1 year)

This uses WordPress's built-in `human_time_diff()` function.

## Troubleshooting

### Plugin not showing on posts

1. Make sure the plugin is **activated**
2. Visit a **single post page** (not homepage or archive)
3. Check if it's a **'post'** post type (not page or custom post type)
4. Clear your **browser cache** and **WordPress cache**

### Voting not working

1. Go to **Admin → Reddit Posts → Settings**
2. Ensure "Enable Voting System" is checked
3. If using guest voting, make sure "Allow Guest Voting" is enabled
4. Check browser console for JavaScript errors

### Comments not showing

1. Ensure comments are **enabled** for the post
2. Check if theme has conflicts with comment template
3. Try disabling other comment-related plugins temporarily

### Styling conflicts

1. Use browser DevTools to inspect elements
2. Add custom CSS with `!important` if needed
3. Check for theme CSS that overrides plugin styles

## Uninstallation

To completely remove the plugin:

1. Deactivate the plugin
2. Delete the plugin files
3. (Optional) Manually delete the `wp_rsp_votes` table from your database if you want to remove all voting data

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the WordPress admin settings
3. Check browser console for errors
4. Clear all caches (browser + WordPress)

## Credits

- Inspired by Reddit's voting and comment system
- Uses WordPress core functions for compatibility
- Optimized for speed and accessibility

## Version

**Version:** 1.0.0  
**Requires:** WordPress 5.0 or higher  
**PHP Version:** 7.0 or higher  

---

**Enjoy your Reddit-style blog posts!** 🚀
