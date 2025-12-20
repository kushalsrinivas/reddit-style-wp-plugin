# Reddit Style Posts - WordPress Plugin

## 🎯 Overview

Transform your WordPress blog into a Reddit-style commenting powerhouse! This plugin replaces the default blog post template with a mobile-first, engagement-focused design that puts comments front and center.

## 🚀 Why This Plugin?

### The Problem
- Traditional blog layouts hide comments at the bottom
- Visitors rarely scroll down to engage
- Low comment engagement hurts SEO (no User Generated Content)
- Google favors sites with active UGC

### The Solution
- **Reddit-inspired UI** that makes comments impossible to miss
- **40% of screen space** dedicated to comments section
- **Upvote/downvote system** increases engagement
- **Collapsible content** keeps readers engaged longer
- **Mobile-first responsive design** for all devices

## ✨ Features

### 1. Reddit-Style Layout
- **Voting sidebar**: Upvote/downvote buttons on every post
- **Prominent comments**: Always visible, never hidden
- **Collapsible content**: "Read More" expands full article
- **Featured images**: Beautiful, responsive images
- **Post metadata**: Author, time, category (Reddit-style)

### 2. Upvote/Downvote System
- Track votes in custom database table
- Guest voting (optional - can require login)
- Vote on posts AND individual comments
- Real-time vote count updates via AJAX
- IP-based tracking for guests
- User ID tracking for logged-in users

### 3. Enhanced Comments
- **Threaded replies**: Up to 5 levels deep
- **Comment voting**: Upvote/downvote individual comments
- **Author badges**: "OP" badge for post authors
- **Time display**: "X minutes/hours ago" format
- **Auto-save drafts**: Save comment drafts in local storage
- **Smooth animations**: Professional UX

### 4. Share Buttons
- Twitter, Facebook, Reddit sharing
- Copy link to clipboard
- Dropdown share menu
- Mobile-friendly

### 5. Mobile-First Design
- Responsive at all breakpoints:
  - Mobile: < 768px
  - Tablet: 768px - 1024px
  - Desktop: 1024px+
- Touch-optimized buttons
- Smooth scrolling
- Dark mode support

### 6. Performance Features
- Lazy loading images
- Efficient AJAX calls
- Minimal JavaScript footprint
- CSS animations (hardware-accelerated)
- Scroll progress indicator

### 7. Admin Dashboard
- Total votes statistics
- Upvotes vs downvotes
- Configuration settings
- Easy enable/disable options

## 📦 Installation

1. Upload the `reddit-style-posts` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Go to **Reddit Posts** in the admin menu
4. Configure your settings
5. Visit any blog post to see the new design!

## ⚙️ Configuration

### Admin Settings (Reddit Posts menu)

**Enable Voting System**
- Toggle on/off for the entire voting feature
- Default: ON

**Show Vote Count**
- Display number of votes publicly
- Default: ON

**Allow Guest Voting**
- Let non-logged-in users vote (tracked by IP)
- Default: OFF (login required)

**Enable Share Buttons**
- Show social sharing options
- Default: ON

**Excerpt Length**
- Number of words shown before "Read More"
- Default: 150 words
- Range: 50-500

**Comments Always Visible**
- Keep comments prominent (Reddit-style)
- Default: ON

## 🎨 Design Features

### Post Layout
```
┌────────────────────────────────┐
│ [↑]  VOTING    POST CONTENT   │
│ 42   SIDEBAR   - Title         │
│ [↓]            - Image         │
│                - Excerpt       │
│                - [Read More]   │
│                - Actions       │
└────────────────────────────────┘
│ COMMENTS SECTION (Prominent)  │
│ - Add Comment Form             │
│ - Existing Comments            │
│   [↑] Comment Text [Reply]     │
│   42  → Nested Reply           │
│   [↓]                          │
└────────────────────────────────┘
```

### Color Scheme
- **Primary**: #0079D3 (Reddit Blue)
- **Upvote**: #FF4500 (Reddit Orange)
- **Downvote**: #7193FF (Blue)
- **Background**: #DAE0E6 (Light Gray)
- **Cards**: #FFFFFF (White)

### Typography
- System fonts for fast loading
- Font sizes scale with screen size
- Readable line heights (1.5-1.6)

## 💻 Technical Details

### Database Schema

**Table**: `wp_rsp_votes`
```sql
id              BIGINT(20)      Auto-increment primary key
post_id         BIGINT(20)      WordPress post ID
comment_id      BIGINT(20)      Comment ID (0 for post votes)
user_id         BIGINT(20)      User ID (0 for guests)
user_ip         VARCHAR(100)    IP address for guest tracking
vote_type       VARCHAR(10)     'upvote' or 'downvote'
voted_at        DATETIME        Timestamp
```

**Unique Index**: Prevents duplicate votes (post_id, comment_id, user_id, user_ip)

### File Structure
```
reddit-style-posts/
├── reddit-style-posts.php    # Main plugin file
├── assets/
│   ├── style.css             # Frontend styles
│   ├── script.js             # Frontend JavaScript
│   └── admin-style.css       # Admin dashboard styles
├── templates/
│   ├── single-post.php       # Custom post template
│   └── comments.php          # Custom comments template
└── README.md                 # This file
```

### WordPress Hooks Used

**Filters:**
- `template_include` - Override single post template
- `comments_template` - Custom comments template

**Actions:**
- `wp_enqueue_scripts` - Load frontend assets
- `admin_enqueue_scripts` - Load admin assets
- `admin_menu` - Add admin menu
- `admin_init` - Register settings
- `wp_insert_post` - Initialize post meta
- `wp_ajax_*` - AJAX handlers for voting

### AJAX Endpoints

1. **`rsp_vote`**
   - Handle upvote/downvote for posts and comments
   - Check permissions (logged in / guest voting)
   - Toggle votes (click again to remove)
   - Return updated vote counts

2. **`rsp_load_comments`**
   - Dynamically load comments
   - For future pagination features

### JavaScript Features

- **jQuery-based** (uses WordPress's included jQuery)
- **ES5 compatible** (works in older browsers)
- **Namespaced** to avoid conflicts
- **Event delegation** for dynamic content
- **Local storage** for draft saving
- **Intersection Observer** for lazy loading

### Security Features

✅ **Nonce verification** on all AJAX calls
✅ **Prepared SQL statements** to prevent injection
✅ **Capability checks** for admin functions
✅ **Input sanitization** on all user input
✅ **Output escaping** to prevent XSS
✅ **Direct access prevention** in all PHP files

## 🔧 Customization

### For Developers

#### Modify Vote Display
Edit `templates/single-post.php` line ~25 to change vote button SVGs

#### Change Color Scheme
Edit `assets/style.css` - search for color codes:
- `#0079D3` - Primary blue
- `#FF4500` - Upvote orange
- `#7193FF` - Downvote blue

#### Adjust Excerpt Length Programmatically
```php
add_filter('option_rsp_excerpt_length', function($value) {
    return 200; // Override to 200 words
});
```

#### Custom Comment Callback
The `rsp_custom_comment()` function in `templates/comments.php` can be modified for custom comment layouts.

#### Add Custom Vote Types
Extend the database to support additional vote types (e.g., "funny", "helpful")

### CSS Classes Reference

**Post Classes:**
- `.rsp-container` - Main wrapper
- `.rsp-post` - Post article
- `.rsp-voting` - Vote sidebar
- `.rsp-content-wrapper` - Main content
- `.rsp-post-actions` - Action buttons

**Comment Classes:**
- `.rsp-comments-container` - Comments wrapper
- `.rsp-comment` - Individual comment
- `.rsp-comment-voting` - Comment vote buttons
- `.rsp-comment-body` - Comment content

## 📱 Responsive Breakpoints

```css
Mobile:     0px - 767px    (Base styles)
Tablet:     768px - 1023px (Medium adjustments)
Desktop:    1024px+        (Full features)
```

### Mobile Optimizations
- Reduced padding and margins
- Smaller vote buttons (24px)
- Smaller fonts (14px base)
- Stack layouts vertically
- Touch-friendly tap targets (44px+)

### Desktop Enhancements
- Larger vote buttons (28px)
- More whitespace
- Larger fonts (16px base)
- Wider containers (980px max)

## 🎯 SEO Benefits

1. **User Generated Content**: Comments are highly visible, encouraging more UGC
2. **Engagement Metrics**: Longer time on page, more interactions
3. **Social Signals**: Easy sharing increases social mentions
4. **Internal Linking**: Comment discussions create natural links
5. **Fresh Content**: Active comments keep content "fresh"

## 🐛 Troubleshooting

### Comments not showing
1. Check if commenting is enabled: Settings → Discussion
2. Ensure post has comments enabled
3. Check theme compatibility

### Votes not working
1. Go to **Reddit Posts** settings
2. Enable "Enable Voting System"
3. Check if guest voting is enabled (if not logged in)
4. Clear browser cache

### Design conflicts
1. Check for theme CSS conflicts
2. Try switching to a default theme (Twenty Twenty-Four)
3. Check browser console for JavaScript errors

### Database errors
1. Deactivate and reactivate the plugin
2. Check database table exists: `wp_rsp_votes`
3. Ensure user has database CREATE permissions

## 🔄 Updates & Compatibility

- **Requires**: WordPress 5.0+
- **Tested up to**: WordPress 6.4
- **PHP Version**: 7.0+
- **Mobile Browsers**: All modern browsers
- **Desktop Browsers**: Chrome, Firefox, Safari, Edge

## 📈 Performance

### Metrics
- **Page Load**: Minimal impact (< 50KB total assets)
- **AJAX Calls**: Optimized, debounced
- **Database Queries**: Efficient with indexes
- **Mobile Score**: 90+ on Google PageSpeed

### Best Practices
- ✅ Minify CSS/JS in production
- ✅ Use CDN for assets
- ✅ Enable WordPress caching
- ✅ Optimize images before upload

## 🤝 Contributing Ideas

Want to extend this plugin? Here are some ideas:

1. **Comment sorting**: Top, New, Controversial
2. **Comment search**: Search within comments
3. **User profiles**: Track user voting history
4. **Badges system**: Award badges for engagement
5. **Email notifications**: Notify on replies
6. **Markdown support**: Rich text in comments
7. **GIF picker**: Add GIF support to comments
8. **Awards system**: Reddit Gold-style awards
9. **Moderation tools**: Flag inappropriate content
10. **Analytics dashboard**: Track engagement metrics

## 📝 Changelog

### Version 1.0.0
- Initial release
- Reddit-style post layout
- Upvote/downvote system for posts and comments
- Threaded comments (5 levels)
- Mobile-first responsive design
- Admin settings page
- Share functionality
- Dark mode support
- Performance optimizations

## 📄 License

GPL2 - Same as WordPress

## 🙏 Credits

- Inspired by Reddit's mobile web interface
- Built for WordPress community
- Icons: Custom SVG icons
- Fonts: System font stack

---

**Made with ❤️ for better blog engagement**

Need help? Found a bug? Want to contribute? Let us know!

# reddit-style-wp-plugin
# reddit-style-wp-plugin
