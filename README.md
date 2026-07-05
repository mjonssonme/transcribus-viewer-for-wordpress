# Transcribus Viewer for WordPress

Uploads Transkribus exports (ZIP) and displays them in an interactive, line-by-line viewer.

This plugin provides a full-featured system for importing Transkribus documents into WordPress. It creates a `transkribus_document` post type, includes an async background uploader, and provides two ways to display the viewer:

1. Directly on the document's own page.
2. Embedded in any post or page using a custom "Transcribus Document" Gutenberg block.

The viewer displays the document's description followed by the interactive image and text panes, kept in sync as you navigate, zoom, pan, or resize.

**[Download the latest release (.zip)](https://github.com/mjonssonme/transcribus-viewer-for-wordpress/releases/latest)**

## This is what you get

![The Transcribus Document viewer, shown live in the WordPress block editor, with an interactive image/text pane side-by-side and page navigation controls](.github/screenshots/viewer-preview.png)

## Installation

1. Download the [latest release](https://github.com/mjonssonme/transcribus-viewer-for-wordpress/releases/latest) and upload the zip via Plugins > Add New > Upload Plugin (or unzip it into `/wp-content/plugins/` directly).
2. Install the 'Action Scheduler' library in `/includes/lib/`.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Navigate to "Transkribus Docs" > "User Guide" for next steps.

Building from source instead? Clone the repo, run `npm install` and `npm run build` to build the Gutenberg block assets, then follow the same steps above.

See [`readme.txt`](readme.txt) for the full changelog and WordPress.org-style plugin metadata.
