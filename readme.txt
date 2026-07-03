=== Transcribus Viewer for WordPress ===
Contributors: mattiasjohnsson
Author: Mattias Johnsson
Author URI: https://mjonsson.me
Requires at least: 6.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.7.0
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

= 1.7.0 =
* Feat: The upload page now shows a live progress notice and automatically redirects to the All Documents list once background processing finishes, instead of leaving you on a static "success" message with no feedback.
* Fix: Resizing the viewer's image/text panes now actually redraws the highlight overlay - previously only a browser window resize triggered a redraw, so dragging the pane's own resize handle left the overlay misaligned (or missing entirely).
* Fix: The block's "Custom viewer height" setting used `max-height`, which only caps a pane's size, so increasing it past the image's natural height did nothing - only decreasing it worked. It now sets a real fixed height.
* Fix: The image and text panes each had their own independent drag-resize handle, which could leave them at different heights. There is now a single resize handle for both, and the "Custom viewer height" change now reflects instantly in the block editor preview.

= 1.6.1 =
* Fix: The "Used In" column only checked published posts, so a document referenced by a draft post showed no warning at all. It now covers any real, non-trashed status (draft/pending/private/future too), with the warning color/wording adjusted based on whether a published post is actually affected.

= 1.6.0 =
* Feat: The "Transcribus Document" block now has a "Custom viewer height" toggle in its settings panel (below the document selector), letting you set a fixed pixel height for the image/text panes.
* Feat: Visitors can now drag the bottom-right corner of either pane to resize it themselves, whether or not a custom height is set.

= 1.5.0 =
* Feat: Deleting a Transkribus document (regardless of its status) now also deletes its attached page images, instead of leaving them behind as orphaned media library entries.
* Feat: The "All Documents" list now shows a "Used In" column with a warning and links whenever a document is embedded (via block or shortcode) in a published post/page, so you don't accidentally delete something that's live elsewhere.

= 1.4.2 =
* Feat: Documents are now published automatically as soon as processing finishes, instead of being left in Draft waiting for a manual "Publish" click. You can still edit the auto-imported description afterward.
* Fix: The Gutenberg block's live preview now actually renders inside the block editor (image/text/controls), instead of showing an empty shell until you hit Preview - the editor's iframed canvas meant the viewer script was watching the wrong document.
* UX: Removed the default "Add New" submenu under Transkribus Docs - documents can only be created via ZIP upload, so that entry just led to a confusing blank post.

= 1.4.1 =
* Chore: Bumped `@wordpress/scripts` (v27 -> v32) and `@wordpress/server-side-render` to clear most remaining npm audit vulnerabilities in the block editor's build tooling (dev-only, nothing shipped to the live site was affected either way).
* Compat: The new build's Gutenberg block depends on the `react-jsx-runtime` WordPress script, which core only provides since WP 6.6 - "Requires at least" and "Tested up to" bumped to 6.6 to reflect this. Only affects the block editor insertion experience, not the document viewer itself.

= 1.4.0 =
* Feat: Added "First" and "Last" page navigation buttons alongside the existing -5/Previous/Next/+5 controls.

= 1.3.2 =
* Fix: Draft/unpublished documents can no longer be previewed by the logged-in admin via the viewer - the 1.3.1 REST lockdown was stricter than intended. Unpublished documents are now readable by users who could edit them (matches core WordPress preview behavior), while remaining blocked for anonymous requests.
* Fix: The viewer's REST requests now actually send the WordPress REST nonce, which they never did before - required for the above preview fix to work, and generally more correct.

= 1.3.1 =
* Security: Escaped/DOM-safe rendering of transcribed line text and IDs in the viewer, closing a stored XSS vector.
* Security: REST API no longer serves page/TOC data for documents that aren't published (draft/processing/failed are now 404).
* Security: Upload handler now requires the `manage_options` capability independently of the nonce check.
* Security: Blocked path traversal via crafted `mets.xml` file references; extension matching is now anchored instead of substring-based.
* Security: Upload now verifies actual ZIP file signature instead of trusting the client-supplied file type.
* Security: Hardened XML parsing against XXE by explicitly disabling external entity loading.
* Fix: Corrected a typo (`$this.` instead of `$this->`) that caused a fatal error when a `mets.xml` had no `SINGLE_PAGE` divs.
* Fix: Temp upload files are now always cleaned up, including when the ZIP fails to open.
* Housekeeping: Removed debug console logging from the frontend viewer script.
* Housekeeping: De-duplicated viewer markup between the shortcode/block renderer and the single-document template.

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