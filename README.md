# YT 404 Logger & Insights

A WordPress plugin that logs 404 errors and provides insights into missing pages, with optional auto-redirect functionality.

## Features

- **Automatic 404 Logging**: Captures all 404 errors including path, referrer, user agent, IP address, and timestamp
- **Top Missing Pages**: View summary of most frequently accessed missing URLs
- **Recent 404 Errors**: Browse recent 404 errors with detailed information
- **Statistics Dashboard**: Quick overview of total errors and unique URLs
- **Auto-Redirect Rules**: Set up custom redirect rules for commonly missing pages
- **AJAX-Powered Management**: Delete individual logs or clear all logs without page refresh
- **Spam Protection**: Prevents duplicate logging within 1-minute intervals
- **Secure & WPCS Compliant**: Follows WordPress coding standards with proper sanitization and escaping

## Installation

1. Upload the `yt-404-logger-insights` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Tools → 404 Logs** to view logged errors
4. Configure auto-redirect rules at **Settings → 404 Logger**

## Usage

### Viewing 404 Logs

Navigate to **Tools → 404 Logs** in your WordPress admin to see:

- **Statistics**: Total 404 errors and unique URLs
- **Top Missing Pages**: Most frequently accessed missing pages with hit counts
- **Recent 404 Errors**: Latest 404 errors with referrer, IP address, and timestamp

### Managing Logs

- **Delete Single Entry**: Click the "Delete" button next to any log entry
- **Clear All Logs**: Use the "Clear All Logs" button to remove all entries at once

### Setting Up Auto-Redirects

1. Go to **Settings → 404 Logger**
2. Enable "Enable Auto-Redirect" checkbox
3. Add redirect rules in the format: `/old-url => /new-url`
4. Example:
   ```
   /old-blog-post => /new-blog-post
   /products/discontinued-item => /products
   /contact-us => /contact
   ```
5. Save changes

When enabled, visitors accessing a 404 URL with a matching redirect rule will be automatically redirected (301) to the specified page.

## Database

The plugin creates a custom table `wp_yt_404_logs` with the following structure:

- `id`: Unique log entry ID
- `path`: The 404 URL path
- `referer`: HTTP referrer (where the visitor came from)
- `user_agent`: Browser user agent string
- `ip_address`: Visitor's IP address
- `logged_at`: Timestamp when error occurred

## File Structure

```
yt-404-logger-insights/
├── class-yt-404-logger-insights.php  # Main plugin file (~500 lines)
├── assets/
│   ├── css/
│   │   └── yt-404-logger-admin.css   # Admin page styles
│   └── js/
│       └── yt-404-logger-admin.js    # Admin AJAX functionality
└── README.md                          # This file
```

## Prefix Convention

All functions, classes, and database elements use the `yt_404_logger` prefix:

- Class: `YT_404_Logger_Insights`
- Constants: `YT_404_LOGGER_*`
- Functions: `yt_404_logger_*`
- Options: `yt_404_logger_options`
- Database: `wp_yt_404_logs`
- Text Domain: `yt-404-logger-insights`

## Security Features

- Capability checks (`manage_options`)
- Nonce verification for AJAX requests
- Input sanitization (`sanitize_text_field`, `sanitize_textarea_field`)
- Output escaping (`esc_html`, `esc_url`, `esc_attr`)
- Prepared SQL statements (via `$wpdb->prepare()`)
- IP address validation
- Spam protection (rate limiting)

## Browser Support

The plugin uses standard WordPress admin UI components and jQuery for compatibility with all modern browsers.

## Uninstallation

When the plugin is deleted via WordPress admin:

1. All plugin options are removed
2. The custom database table is dropped
3. WordPress cache is flushed

No data is left behind after uninstallation.

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Complexity

- **Total Lines**: ~500 lines (PHP)
- **Single File Architecture**: Main plugin in one PHP file
- **Additional Assets**: 1 CSS file (~100 lines) + 1 JS file (~100 lines)

## License

GPL v2 or later

## Author

Krasen Slavov
- Website: https://krasenslavov.com
- GitHub: https://github.com/krasenslavov

## Support

For issues and feature requests, please visit the GitHub repository.
