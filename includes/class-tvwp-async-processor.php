<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class TVWP_Async_Processor {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'tvwp_process_zip_action', [ $this, 'process_zip' ], 10, 2 );
    }

    public function process_zip( $post_id, $zip_path ) {
        
        if ( ! class_exists( 'ZipArchive' ) ) {
            $this->fail_post( $post_id, 'ZipArchive class not found on server.' );
            return;
        }

        $zip = new ZipArchive;
        $unzip_dir = dirname( $zip_path ) . '/tvwp-unzip-' . $post_id;
        
        if ( $zip->open( $zip_path ) === TRUE ) {
            $zip->extractTo( $unzip_dir );
            $zip->close();
        } else {
            $this->fail_post( $post_id, 'Failed to open ZIP file.' );
            return;
        }

        $mets_path = $this->find_mets_file( $unzip_dir );

        if ( ! $mets_path ) {
            $this->fail_post( $post_id, 'mets.xml not found in ZIP.' );
            $this->cleanup( $zip_path, $unzip_dir );
            return;
        }
        
        $content_root = dirname( $mets_path );
        
        try {
            // Load METS XML
            $mets_xml = simplexml_load_file( $mets_path );
            if ($mets_xml === false) {
                $this->fail_post( $post_id, 'Failed to parse mets.xml.' );
                $this->cleanup( $zip_path, $unzip_dir );
                return;
            }
            
            $mets_xml->registerXPathNamespace( 'ns3', 'http://www.loc.gov/METS/' );
            $mets_xml->registerXPathNamespace( 'ns2', 'http://www.w3.org/1999/xlink' );
            
            $doc_title = (string) $mets_xml->attributes()->LABEL;
            $page_map = [];

            // --- V1.0.1 FEATURE: Get description from metadata.xml ---
            $doc_description = '';
            $metadata_path = $content_root . '/metadata.xml';
            if ( file_exists( $metadata_path ) ) {
                $metadata_xml = simplexml_load_file( $metadata_path );
                if ( $metadata_xml && isset( $metadata_xml->desc ) ) {
                    // Clean up the description text
                    $doc_description = trim( (string) $metadata_xml->desc );
                    // Convert double line breaks to paragraphs
                    $doc_description = preg_replace( "/\r\n\r\n|\n\n/", "</p><p>", $doc_description );
                    $doc_description = '<p>' . $doc_description . '</p>';
                }
            }
            // --- END V1.0.1 FEATURE ---

            // Get all files and their IDs
            $file_nodes = $mets_xml->xpath('//ns3:file');
            $file_id_map = [];
            foreach ($file_nodes as $file_node) {
                $file_id = (string)$file_node->attributes()->ID;
                $file_href_nodes = $file_node->xpath('.//ns3:FLocat');
                if (isset($file_href_nodes[0])) {
                    $file_href = (string)$file_href_nodes[0]->attributes('ns2', true)->href;
                    $file_id_map[$file_id] = $file_href;
                }
            }

            // Get all page sections
            $page_divs = $mets_xml->xpath('//ns3:structMap[@TYPE="MANUSCRIPT"]//ns3:div[@TYPE="SINGLE_PAGE"]');

            if (empty($page_divs)) {
                $this.fail_post( $post_id, 'No pages with TYPE="SINGLE_PAGE" found in mets.xml.' );
                $this.cleanup( $zip_path, $unzip_dir );
                return;
            }

            foreach ($page_divs as $page) {
                $page_order = (string)$page->attributes()->ORDER;
                $page_files = ['img' => '', 'xml' => ''];
                $fptr_nodes = $page->xpath('.//ns3:fptr/ns3:area');
                
                foreach ($fptr_nodes as $fptr) {
                    $file_id = (string)$fptr->attributes()->FILEID;
                    if (isset($file_id_map[$file_id])) {
                        $file_href = $file_id_map[$file_id];
                        if (strpos($file_href, '.xml') !== false) {
                            $page_files['xml'] = $file_href;
                        } elseif (strpos($file_href, '.jpg') !== false || strpos($file_href, '.png') !== false) {
                            $page_files['img'] = $file_href;
                        }
                    }
                }
                $page_map[$page_order] = $page_files;
            }
            ksort( $page_map, SORT_NUMERIC );

            foreach ( $page_map as $page_num => $files ) {
                $image_path = $content_root . '/' . $files['img'];
                $xml_path = $content_root . '/' . $files['xml'];

                if ( empty($files['img']) || empty($files['xml']) || ! file_exists( $image_path ) || ! file_exists( $xml_path ) ) {
                    error_log("TVWP: Missing file for page $page_num. Img: {$files['img']}, XML: {$files['xml']}");
                    continue;
                }

                $attachment_id = $this->upload_image( $image_path, $post_id, $doc_title . " - Page " . $page_num );
                if ( ! $attachment_id ) continue;
                $image_url = wp_get_attachment_url( $attachment_id );

                // Switch to DOMDocument for robust Page XML parsing
                $page_dom = new DOMDocument();
                @$page_dom->load( $xml_path );
                $page_xpath = new DOMXPath( $page_dom );
                
                $page_node_list = $page_xpath->query( "//*[local-name()='Page']" );
                if( $page_node_list->length === 0 ) {
                    error_log("TVWP: Could not find <Page> node in $xml_path");
                    continue; 
                }
                $page_node = $page_node_list[0];
                
                $page_width = (int)$page_node->getAttribute('imageWidth');
                $page_height = (int)$page_node->getAttribute('imageHeight');
                
                $page_data = [
                    'image_url'    => $image_url,
                    'image_width'  => $page_width,
                    'image_height' => $page_height,
                    'lines'        => [],
                ];
                
                $text_lines = $page_xpath->query( "//*[local-name()='TextLine']" );
                
                foreach ( $text_lines as $line ) {
                    
                    $line_text_node = $page_xpath->query( ".//*[local-name()='Unicode']", $line )->item(0);
                    $line_text = $line_text_node ? $line_text_node->nodeValue : '';
                    
                    $coords_node = $page_xpath->query( ".//*[local-name()='Coords']", $line )->item(0);
                    $coords = $coords_node ? $coords_node->getAttribute('points') : '';
                    
                    $page_data['lines'][] = [
                        'id'     => $line->getAttribute('id'),
                        'coords' => $coords,
                        'text'   => $line_text,
                    ];
                }
                
                update_post_meta( $post_id, '_page_data_' . $page_num, $page_data );
            }

            wp_update_post( [
                'ID'           => $post_id,
                'post_title'   => $doc_title,
                'post_name'    => sanitize_title( $doc_title ),
                'post_status'  => 'draft',
                'post_content' => $doc_description, // <-- V1.0.1 FEATURE IS HERE
            ] );
            
            update_post_meta( $post_id, '_page_count', count( $page_map ) );
            
        } catch ( Exception $e ) {
            $this->fail_post( $post_id, 'Error during XML parsing: ' . $e->getMessage() );
        }

        $this->cleanup( $zip_path, $unzip_dir );
    }

    private function find_mets_file( $dir ) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $file ) {
            if ( $file->getFilename() === 'mets.xml' ) {
                return $file->getRealPath();
            }
        }
        return false;
    }

    private function upload_image( $image_path, $post_id, $desc ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        if ( ! file_exists( $image_path ) ) return false;
        
        $file_array = [
            'name'     => basename( $image_path ),
            'tmp_name' => $image_path,
        ];
        
        $temp_file = wp_tempnam( $file_array['name'] );
        copy( $image_path, $temp_file );
        $file_array['tmp_name'] = $temp_file;

        $attachment_id = media_handle_sideload( $file_array, $post_id, $desc );
        
        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $temp_file );
            error_log( 'TVWP Sideload Error: ' . $attachment_id->get_error_message() );
            return false;
        }
        
        return $attachment_id;
    }

    private function fail_post( $post_id, $error_message ) {
        wp_update_post( [
            'ID'           => $post_id,
            'post_status'  => 'failed',
            'post_content' => 'Processing failed: ' . $error_message,
        ] );
    }

    private function cleanup( $zip_path, $unzip_dir ) {
        if ( file_exists( $zip_path ) ) {
            unlink( $zip_path );
        }
        if ( is_dir( $unzip_dir ) ) {
            $this->rrmdir( $unzip_dir );
        }
    }
    
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir. DIRECTORY_SEPARATOR .$object))
                        $this->rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                    else
                        unlink($dir. DIRECTORY_SEPARATOR .$object);
                }
            }
            rmdir($dir);
        }
    }
}