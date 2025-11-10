=== Transcribus Viewer for WordPress ===
Contributors: Mattias Johnsson
Author: Mattias Johnsson
Author URI: https://mjonsson.me
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Uploads Transkribus exports (ZIP) and displays them in an interactive, line-by-line viewer.

== Description ==

This plugin provides a full-featured system for importing Transkribus documents into WordPress. It creates a 'transkribus_document' post type, includes an async background uploader to handle large ZIP files, and provides a beautiful, interactive frontend viewer.

The viewer displays the transcribed page image and the transcribed text side-by-side, with line-level highlighting that syncs between both panes.

== Installation ==

1.  Upload the `transcribus-viewer-for-wordpress` folder to your `/wp-content/plugins/` directory.
2.  Install the 'Action Scheduler' library in `/includes/lib/`.
3.  Activate the plugin through the 'Plugins' menu in WordPress.
4.  Navigate to "Transkribus Docs" > "User Guide" for next steps.

== Changelog ==

= 1.0.0 =
* Initial Release.

= 1.0.1 =
* Added "Description" metadata from Transcribus to main post in wordpress