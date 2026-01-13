# Quick Installation Guide

## Step 1: Activate the Plugin

1. Log in to your WordPress Admin Dashboard
2. Go to **Plugins** → **Installed Plugins**
3. Find "**Reddit Style Posts**"
4. Click **Activate**

✅ That's it! The plugin is now active.

## Step 2: Configure Settings (Optional)

1. Go to **WordPress Admin** → **Reddit Posts** (in the left sidebar)
2. Review the statistics showing vote counts
3. Adjust settings as needed:
   - ✅ Enable Voting System (default: ON)
   - ✅ Show Vote Count (default: ON)
   - ❌ Allow Guest Voting (default: OFF - recommended to keep OFF for quality control)
   - ✅ Enable Share Buttons (default: ON)
   - 📝 Excerpt Length: 150 words (adjust as needed)
   - ✅ Comments Always Visible (default: ON)

4. Click **Save Changes**

## Step 3: Test on a Post

1. Go to any **single blog post** on your site
2. You should see:
   - Featured image at the top
   - Your article title (from theme)
   - Author, date, category info (from theme)
   - Post content with "Read More" button
   - **Action buttons**: Upvote, Comment, Share
   - **Reddit-style comments section** below

## What You'll See

### Before Scrolling (Top of Post)
```
[Site Header - Your Theme]
[Featured Image]
[Article Title]
[Article Meta: Author, Date, Category]
[Article Content Preview...]
[Read More Button]
```

### After Clicking "Read More"
```
[Full Article Content]
[Show Less Button]
```

### Bottom Section (The Reddit Part)
```
┌─────────────────────────────────────┐
│  🔺 [Upvote] 💬 [12 Comments] 📤 [Share] │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│  💬 12 Comments                      │
│  What are your thoughts? Share below!│
│                                     │
│  [Existing threaded comments here]  │
│                                     │
│  [Add a Comment form]               │
└─────────────────────────────────────┘

[Sidebar - Your Theme]
[Footer - Your Theme]
```

## Features Available Immediately

✅ **Upvote/Downvote** - Click the upvote button on any post or comment  
✅ **Threaded Comments** - Reply to comments up to 5 levels deep  
✅ **Social Sharing** - Share on Twitter, Facebook, Reddit, or copy link  
✅ **Read More/Less** - Expandable content to improve page speed  
✅ **Mobile Responsive** - Perfect on all screen sizes  
✅ **Dark Theme** - Automatically matches your dark theme with glassmorphism design  

## Keyboard Shortcuts

- Press **C** key - Jump to comment box and start typing

## Important Notes

### ✅ What Changes
- Single post pages now have Reddit-style voting and comments
- Content is collapsible with "Read More" button
- Comments have voting and improved styling

### ✅ What Stays the Same
- Your site header and navigation
- Your sidebar content
- Your footer
- Post archives and homepage
- All other pages
- Your theme's overall design

### 🎨 Design Integration
The plugin uses **glassmorphism** design that blends beautifully with dark themes:
- Semi-transparent backgrounds
- Subtle borders
- Gradient buttons
- Smooth animations
- Respects your theme's colors

## Performance

The plugin is optimized for speed:
- CSS/JS only loads on single post pages
- Images lazy load
- Animations use GPU acceleration
- No impact on homepage or archives

## Troubleshooting

### Plugin not visible?
1. Make sure you're viewing a **single post** (not homepage)
2. Click on any blog article to view the full post
3. Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)

### Styling looks off?
1. Try a hard refresh: **Ctrl+F5** (Windows) or **Cmd+Shift+R** (Mac)
2. Clear WordPress cache if using a caching plugin
3. Check if another plugin is conflicting

### Voting not working?
1. Ensure voting is enabled in **Admin → Reddit Posts**
2. For guest voting, enable "Allow Guest Voting" in settings
3. Check browser console (F12) for any JavaScript errors

## Next Steps

1. **Test the voting** - Upvote a post and see the count increase
2. **Leave a comment** - Test the comment form
3. **Reply to a comment** - Test threaded replies
4. **Share a post** - Test the share button
5. **Customize styling** - Add custom CSS if needed (see README.md)

## Need Help?

- Check the main **README.md** for detailed documentation
- Review the settings in **Admin → Reddit Posts**
- Test in different browsers to rule out browser-specific issues

---

**You're all set!** Your blog now has Reddit-style engagement features while keeping your theme's beautiful design intact. 🎉
