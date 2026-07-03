<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class TVWP_Admin_Upload {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_upload' ] );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=transkribus_document',
            __( 'Upload New Document', 'tvwp' ),
            __( 'Upload New Document', 'tvwp' ),
            'manage_options',
            'tvwp-upload',
            [ $this, 'render_upload_page' ]
        );
    }

    public function render_upload_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Upload Transkribus ZIP', 'tvwp' ); ?></h1>
            <p><?php _e( 'Upload the ZIP file exported from Transkribus. This must contain the images, Page XML, and a mets.xml file.', 'tvwp' ); ?></p>
            
            <?php $this->show_admin_notices(); ?>

            <form method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field( 'tvwp_upload_zip', 'tvwp_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e( 'Transkribus ZIP File', 'tvwp' ); ?></th>
                        <td>
                            <input type="file" name="tvwp_zip_file" id="tvwp_zip_file" accept=".zip" required>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Upload and Process', 'tvwp' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function handle_upload() {
        if ( ! isset( $_POST['submit'] ) || ! isset( $_POST['tvwp_nonce'] ) ) {
            return;
        }

        // Nonces guard against CSRF, not authorization - explicitly require the capability too.
        if ( ! current_user_can( 'manage_options' ) ) {
            $this->redirect_with_notice( 'error', 'capability' );
            return;
        }

        if ( ! wp_verify_nonce( $_POST['tvwp_nonce'], 'tvwp_upload_zip' ) ) {
            $this->redirect_with_notice( 'error', 'nonce' );
            return;
        }

        if ( ! isset( $_FILES['tvwp_zip_file'] ) || $_FILES['tvwp_zip_file']['error'] !== UPLOAD_ERR_OK ) {
            $this->redirect_with_notice( 'error', 'file' );
            return;
        }

        // The browser-supplied 'type' field is client-controlled and trivially spoofed, so
        // verify the file extension and, once uploaded, the actual ZIP magic bytes below.
        $filetype = wp_check_filetype( $_FILES['tvwp_zip_file']['name'], [ 'zip' => 'application/zip' ] );
        if ( $filetype['ext'] !== 'zip' ) {
            $this->redirect_with_notice( 'error', 'type' );
            return;
        }

        // --- All checks passed. Start the process. ---

        // 1. Prepare temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/tvwp-temp';
        
        // --- FIX: Only create dir if it doesn't exist ---
        if ( ! file_exists( $temp_dir ) ) {
            wp_mkdir_p( $temp_dir );
        }

        // 2. Create placeholder post *before* moving file
        $post_id = wp_insert_post( [
            'post_type'   => 'transkribus_document',
            'post_title'  => __( 'Processing:', 'tvwp' ) . ' ' . sanitize_file_name( $_FILES['tvwp_zip_file']['name'] ),
            'post_status' => 'processing',
        ] );

        if ( is_wp_error( $post_id ) ) {
            $this->redirect_with_notice( 'error', 'post' );
            return;
        }
        
        $temp_filename = 'tvwp_upload_' . $post_id . '_' . time() . '.zip';
        $temp_zip_path = $temp_dir . '/' . $temp_filename;

        // 3. Move the uploaded file
        if ( ! move_uploaded_file( $_FILES['tvwp_zip_file']['tmp_name'], $temp_zip_path ) ) {
            wp_delete_post( $post_id, true );
            $this->redirect_with_notice( 'error', 'move' );
            return;
        }

        // Verify the actual file content is a ZIP archive - the extension/MIME checks above
        // only look at attacker-controlled metadata, not the bytes that were actually uploaded.
        if ( ! $this->is_zip_file( $temp_zip_path ) ) {
            wp_delete_post( $post_id, true );
            unlink( $temp_zip_path );
            $this->redirect_with_notice( 'error', 'type' );
            return;
        }

        // 4. Schedule the asynchronous background task
        try {
            as_schedule_single_action(
                time(),
                'tvwp_process_zip_action',
                [ 'post_id' => $post_id, 'zip_path' => $temp_zip_path, ]
            );
        } catch ( Exception $e ) {
            wp_delete_post( $post_id, true );
            unlink( $temp_zip_path );
            $this->redirect_with_notice( 'error', 'schedule' );
            return;
        }
        
        $url = add_query_arg(
            [ 'post_type' => 'transkribus_document', 'page' => 'tvwp-upload', 'tvwp_processing' => $post_id ],
            admin_url( 'edit.php' )
        );
        wp_redirect( $url );
        exit;
    }

    /**
     * Checks the actual uploaded bytes for a ZIP local-file-header or end-of-central-directory
     * signature, since the client-supplied filename/MIME type can't be trusted.
     */
    private function is_zip_file( $path ) {
        $handle = fopen( $path, 'rb' );
        if ( ! $handle ) {
            return false;
        }
        $signature = fread( $handle, 4 );
        fclose( $handle );

        $zip_signatures = [ "PK\x03\x04", "PK\x05\x06", "PK\x07\x08" ];
        return in_array( $signature, $zip_signatures, true );
    }

    private function redirect_with_notice( $type, $code ) {
        $url = add_query_arg(
            [ 'post_type' => 'transkribus_document', 'page' => 'tvwp-upload', 'tvwp_notice' => $type, 'code' => $code ],
            admin_url( 'edit.php' )
        );
        wp_redirect( $url );
        exit;
    }

    private function show_admin_notices() {
        if ( isset( $_GET['tvwp_processing'] ) ) {
            $this->render_processing_notice( (int) $_GET['tvwp_processing'] );
            return;
        }

        if ( ! isset( $_GET['tvwp_notice'] ) ) {
            return;
        }

        $type = $_GET['tvwp_notice'];
        $code = $_GET['code'] ?? '';
        $message = '';

        if ( $type === 'error' ) {
            switch ( $code ) {
                case 'capability': $message = __( 'You do not have permission to upload documents.', 'tvwp' ); break;
                case 'nonce': $message = __( 'Security check failed. Please try again.', 'tvwp' ); break;
                case 'file': $message = __( 'No file was uploaded or an error occurred.', 'tvwp' ); break;
                case 'type': $message = __( 'Invalid file type. Please upload a .zip file.', 'tvwp' ); break;
                case 'post': $message = __( 'Could not create a new document post in WordPress.', 'tvwp' ); break;
                case 'move': $message = __( 'Could not save the uploaded file.', 'tvwp' ); break;
                case 'schedule': $message = __( 'Could not schedule the background processing task.', 'tvwp' ); break;
                default: $message = __( 'An unknown error occurred.', 'tvwp' );
            }
        }

        if ( $message ) {
            echo "<div class='notice notice-$type is-dismissible'><p>$message</p></div>";
        }
    }

    /**
     * Shows a progress notice and polls the document's processing status via REST,
     * redirecting to the All Documents list as soon as the background job finishes
     * (published or failed) instead of leaving the admin on a static "success" page.
     */
    private function render_processing_notice( $post_id ) {
        if ( ! $post_id || get_post_type( $post_id ) !== 'transkribus_document' ) {
            return;
        }

        $status_url   = esc_url_raw( rest_url( 'tvwp/v1/document/' . $post_id . '/status' ) );
        $nonce        = wp_create_nonce( 'wp_rest' );
        $redirect_url = admin_url( 'edit.php?post_type=transkribus_document' );
        ?>
        <div class="notice notice-info" id="tvwp-processing-notice">
            <p>
                <span class="spinner is-active" style="float:none;vertical-align:middle;margin:0 8px 0 0;"></span>
                <?php _e( 'Processing your document in the background. You will be redirected automatically when it\'s done - this can take a few minutes for large documents.', 'tvwp' ); ?>
            </p>
        </div>
        <script>
        ( function() {
            var statusUrl = <?php echo wp_json_encode( $status_url ); ?>;
            var nonce = <?php echo wp_json_encode( $nonce ); ?>;
            var redirectUrl = <?php echo wp_json_encode( $redirect_url ); ?>;
            var attempts = 0;
            var maxAttempts = 100; // ~5-8 minutes depending on backoff below

            function poll() {
                attempts++;
                fetch( statusUrl, { headers: { 'X-WP-Nonce': nonce } } )
                    .then( function( response ) { return response.ok ? response.json() : null; } )
                    .then( function( data ) {
                        if ( data && data.status && data.status !== 'processing' ) {
                            window.location.href = redirectUrl;
                            return;
                        }
                        scheduleNext();
                    } )
                    .catch( function() {
                        scheduleNext();
                    } );
            }

            function scheduleNext() {
                if ( attempts >= maxAttempts ) {
                    var notice = document.getElementById( 'tvwp-processing-notice' );
                    if ( notice ) {
                        notice.innerHTML = '<p>' +
                            <?php echo wp_json_encode( __( 'This is taking longer than expected. You can check back on the', 'tvwp' ) ); ?> +
                            ' <a href="' + redirectUrl + '">' + <?php echo wp_json_encode( __( 'All Documents', 'tvwp' ) ); ?> + '</a> ' +
                            <?php echo wp_json_encode( __( 'page later.', 'tvwp' ) ); ?> +
                            '</p>';
                    }
                    return;
                }
                setTimeout( poll, 3000 );
            }

            poll();
        } )();
        </script>
        <?php
    }
}