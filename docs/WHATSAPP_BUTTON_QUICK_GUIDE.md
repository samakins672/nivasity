# WhatsApp Floating Button - Quick Reference

## What is it?
A fixed-position button that appears in the bottom-right corner of most pages, allowing users to quickly contact Nivasity support via WhatsApp.

## Location in Code

### HTML: `partials/_footer.php`
Lines 7-19 contain the button markup with:
- WhatsApp link: `https://wa.me/2349059527495`
- SVG icon (inline)
- Tooltip text

### CSS: `assets/css/dashboard/style.css`
Search for `.whatsapp-float` to find all related styles including:
- Button positioning and appearance
- Hover effects
- Tooltip styling

## Quick Customizations

### Change Phone Number
```php
// In partials/_footer.php, line 10
<a href="https://wa.me/YOUR_NUMBER_HERE" ...>
```

### Change Position
```css
/* In assets/css/dashboard/style.css */
.whatsapp-float {
  right: 20px;  /* Change for horizontal position */
  bottom: 20px; /* Change for vertical position */
}
```

### Change Colors
```css
/* In assets/css/dashboard/style.css */
.whatsapp-float a {
  background-color: #25D366; /* Button background */
  color: #ffffff;            /* Icon color */
}
```

### Change Tooltip Text
```html
<!-- In partials/_footer.php, line 17 -->
<span class="whatsapp-tooltip">Your custom text</span>
```

## Where It Appears
✅ All pages that include `partials/_footer.php`:
- Homepage (index.php)
- User dashboard (user.php)
- Support page (support.php)
- Admin pages (admin/*.php)

❌ Standalone HTML pages without footer include:
- signin.html
- setup.html
- contactus.html

## Key Features
- 🎯 Fixed position (stays visible while scrolling)
- 🎨 WhatsApp brand color (#25D366)
- ✨ Smooth hover animation
- 💬 Tooltip on hover
- ♿ Accessible (ARIA labels, screen reader friendly)
- 🔒 Secure (rel="noopener")
- ⚡ No external dependencies (inline SVG)

## Technical Stack
- **HTML**: Semantic markup with accessibility attributes
- **CSS**: Modern CSS with transforms and transitions
- **PHP**: Conditional rendering (prevents duplicates)
- **SVG**: Inline vector graphics for the WhatsApp icon

## Need More Details?
See the full documentation: `WHATSAPP_BUTTON_DOCUMENTATION.md`
