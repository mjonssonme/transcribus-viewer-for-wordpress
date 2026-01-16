=== Transcribus Viewer for WordPress ===
Contributors: mattiasjohnsson
Author: Mattias Johnsson
Author URI: https://mjonsson.me
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Uploads Transkribus exports (ZIP) and displays them in an interactive, line-by-line viewer.

== Description ==

This plugin provides a full-featured system for importing Transkribus documents into WordPress. It creates a 'transkribus_document' post type, includes an async background uploader, and provides two ways to display the viewer:

1.  Directly on the document's own page.
2.  Embedded in any post or page using a custom "Transcribus Document" Gutenberg block.

The viewer displays the document's description followed by the interactive image and text panes.

== Installation ==

1.  Upload the `transcribus-viewer-for-wordpress` folder to your `/wp-content/plugins/` directory.
2.  Install the 'Action Scheduler' library in `/includes/lib/`.
3.  In the plugin's root folder, run `npm install` and then `npm run build` to build the Gutenberg block assets.
4.  Activate the plugin through the 'Plugins' menu in WordPress.
5.  Navigate to "Transkribus Docs" > "User Guide" for next steps.

== Changelog ==

= 1.3.0 =
* Feat: Added zoom functionality using mouse scroll wheel (1x-5x zoom range).
* Feat: Added drag-to-pan functionality when zoomed in on the document image.
* Feat: Click on text lines to automatically center the corresponding area in the image viewer.
* Feat: Text-area polygon overlays remain perfectly aligned during zoom and pan operations.
* Enhancement: Smooth animations when centering on clicked text lines.

= 1.2.0 =
* Feat: Added a custom "Transkribus Document" Gutenberg block for easy embedding in any post or page.
* Feat: The block editor now shows a live, interactive preview of the viewer.
* Fix: Refactored JavaScript initialization to support both the frontend and editor preview.

= 1.0.1 =
* Feat: Import document description from metadata.xml as placeholder content.
* Docs: Update user guide to reflect new feature.

= 1.0.0 =
* Initial Release.

== Credits ==

This plugin uses the following open-source library:

* **Action Scheduler**
    * URL: https://github.com/woocommerce/action-scheduler
    * License: GPLv2 or later