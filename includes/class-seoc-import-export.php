<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings import/export functionality.
 *
 * Handles exporting and importing plugin settings.
 */
class SEOC_Import_Export {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_init', array( $this, 'handle_export' ) );
        add_action( 'admin_init', array( $this, 'handle_import' ) );
    }

    /**
     * Export settings as JSON.
     */
    public function handle_export() {
        if ( ! isset( $_GET['action'] ) || 'seoc_export_settings' !== $_GET['action'] ) {
            return;
        }

        // Check capabilities.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'seo-confident' ), '', array( 'response' => 403 ) );
        }

        // Verify nonce.
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'seoc_export_settings' ) ) {
            wp_die( esc_html__( 'Security check failed', 'seo-confident' ), '', array( 'response' => 403 ) );
        }

        // Get settings.
        $settings = get_option( 'seoc_settings', array() );

        // Prepare export data.
        $export_data = array(
            'plugin'   => 'seo-confident',
            'version'  => SEOC_VERSION,
            'exported' => current_time( 'Y-m-d H:i:s' ),
            'settings' => $settings,
        );

        // Send file.
        $filename = 'seo-confident-settings-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-store, no-cache' );
        header( 'Expires: 0' );

        echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Import settings from JSON file.
     */
    public function handle_import() {
        if ( ! isset( $_POST['action'] ) || 'seoc_import_settings' !== $_POST['action'] ) {
            return;
        }

        // Check capabilities.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'seo-confident' ), '', array( 'response' => 403 ) );
        }

        // Verify nonce.
        if ( ! isset( $_POST['seoc_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['seoc_import_nonce'] ) ), 'seoc_import_settings' ) ) {
            wp_die( esc_html__( 'Security check failed', 'seo-confident' ), '', array( 'response' => 403 ) );
        }

        // Check for file.
        if ( ! isset( $_FILES['seoc_import_file'] ) || ! is_array( $_FILES['seoc_import_file'] ) ) {
            wp_die( esc_html__( 'No file uploaded', 'seo-confident' ) );
        }

        $uploaded_file = $_FILES['seoc_import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        if ( ! isset( $uploaded_file['error'] ) || UPLOAD_ERR_OK !== (int) $uploaded_file['error'] ) {
            wp_die( esc_html__( 'The file could not be uploaded. Please try again.', 'seo-confident' ) );
        }

        // The browser-supplied MIME type can be anything, so check the real upload and its name.
        if ( empty( $uploaded_file['tmp_name'] ) || ! is_uploaded_file( $uploaded_file['tmp_name'] ) ) {
            wp_die( esc_html__( 'Invalid upload.', 'seo-confident' ) );
        }

        $extension = strtolower( pathinfo( (string) $uploaded_file['name'], PATHINFO_EXTENSION ) );
        if ( 'json' !== $extension ) {
            wp_die( esc_html__( 'Invalid file type. Please upload a JSON file.', 'seo-confident' ) );
        }

        if ( ! empty( $uploaded_file['size'] ) && (int) $uploaded_file['size'] > 1048576 ) {
            wp_die( esc_html__( 'That file is too large to be a settings file.', 'seo-confident' ) );
        }

        // Read file.
        $file_content = file_get_contents( $uploaded_file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $import_data  = json_decode( (string) $file_content, true );

        if ( ! is_array( $import_data ) || ! isset( $import_data['plugin'] ) || 'seo-confident' !== $import_data['plugin'] ) {
            wp_die( esc_html__( 'Invalid import file. This does not appear to be a SEO Confident settings file.', 'seo-confident' ) );
        }

        // Import settings.
        if ( isset( $import_data['settings'] ) && is_array( $import_data['settings'] ) ) {
            $incoming = $import_data['settings'];

            // "_form" is only meant to come from the settings form.
            unset( $incoming['_form'] );

            // The sanitiser keeps any setting the file does not mention, and update_option()
            // runs it again, which is safe because it gives the same result when repeated.
            $sanitized = SEOC_Settings::instance()->sanitize_settings( $incoming );
            update_option( 'seoc_settings', $sanitized );

            // Redirect with success message.
            wp_safe_redirect( admin_url( 'admin.php?page=seoc-settings&seoc_import_success=1' ) );
            exit;
        }

        wp_die( esc_html__( 'No settings found in import file.', 'seo-confident' ) );
    }
}
