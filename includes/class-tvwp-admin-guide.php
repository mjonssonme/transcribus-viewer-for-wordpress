<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class TVWP_Admin_Guide
 *
 * Adds a "User Guide" submenu page.
 */
class TVWP_Admin_Guide {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=transkribus_document', // Parent slug
            __( 'User Guide', 'tvwp' ),                 // Page title
            __( 'User Guide', 'tvwp' ),                 // Menu title
            'manage_options',                         // Capability
            'tvwp-guide',                             // Menu slug
            [ $this, 'render_guide_page' ]              // Function
        );
    }

    public function render_guide_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Transcribus Viewer - User Guide', 'tvwp' ); ?></h1>
            <p>Welcome! This guide will walk you through the correct process for exporting from Transkribus and publishing in WordPress.</p>
            
            <hr>

            <h2>Step 1: Required Transkribus Export Settings</h2>
            <p>This plugin will **only** work if you export your document from Transkribus with the following settings. The `mets.xml` file is critical for the processor.</p>

            <ol>
                <li>In Transkribus, select your document and click "Export".</li>
                <li>Choose "Standard Export".</li>
                <li>Ensure the following options are **checked**:
                    <ul>
                        <li><strong>[✓] Images</strong></li>
                        <li><strong>[✓] Page XML</strong></li>
                        <li><strong>[✓] Export structural elements to Mets</strong></li>
                    </ul>
                </li>
                <li>Any "File name pattern" is fine.</li>
            </ol>
            
            <p>Your export settings screen should look like this:</p>
            
            <img src="<?php echo TVWP_PLUGIN_URL . 'assets/images/export-settings.png'; ?>" style="max-width: 600px; border: 1px solid #ddd;" alt="Correct Transkribus export settings.">
            
            <p>Download the resulting ZIP file.</p>

            <hr>

            <h2>Step 2: Upload to WordPress</h2>
            <ol>
                <li>In your WordPress admin, go to <strong>Transkribus Docs &gt; Upload New Document</strong>.</li>
                <li>Choose the ZIP file you just downloaded from Transkribus.</li>
                <li>Click "Upload and Process".</li>
                <li>You will see a success message. The plugin is now processing the document in the background. This can take several minutes for large documents.</li>
            </ol>
            
            <hr>

            <h2>Step 3: Publish Your Document</h2>
            <ol>
                <li>Go to <strong>Transkribus Docs &gt; All Documents</strong>.</li>
                <li>After a minute, you will see your new document appear with the correct title, saved as a <strong>"Draft"</strong>.</li>
                <li>Click "Edit" on the new document.</li>
                <li>The main text editor will be empty. You can write your own introduction or notes about the document here. This text will appear above the viewer.</li>
                <li>When you are ready, click the "Publish" button.</li>
            </ol>

            <hr>

            <h2>Step 4: View Your Document</h2>
            <p>Click "View Document" (or the public permalink) to see your published transcription. The viewer will show your custom text, followed by the interactive image and text panes.</p>

        </div>
        <?php
    }
}