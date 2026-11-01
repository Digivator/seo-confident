<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin notices and validation.
 *
 * Handles setup notifications and settings validation.
 */
class SEOC_Admin_Notices {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_notices', array( $this, 'show_setup_notice' ) );
        add_action( 'wp_ajax_seoc_dismiss_setup_notice', array( $this, 'handle_setup_notice_dismiss' ) );
    }

    /**
     * Show setup notice if no settings have been configured.
     */
    public function show_setup_notice() {
        // Only show to admins.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if dismissed.
        if ( get_transient( 'seoc_setup_notice_dismissed' ) ) {
            return;
        }

        $settings = get_option( 'seoc_settings', array() );

        // Show notice if no schema name is set.
        if ( empty( $settings['schema_org_name'] ) ) {
            seoc_enqueue_admin_script();
            ?>
            <div class="notice notice-info is-dismissible" id="seoc-setup-notice">
                <p>
                    <strong><?php echo esc_html__( 'SEO Confident', 'seo-confident' ); ?></strong>
                    <?php echo wp_kses_post( sprintf(
                        /* translators: %s: Settings page link */
                        __( 'is almost ready! Please <a href="%s">configure your SEO settings</a> to get started.', 'seo-confident' ),
                        esc_url( admin_url( 'admin.php?page=seoc-settings' ) )
                    ) ); ?>
                </p>
            </div>
            <?php
        }

        // Check for invalid custom schema.
        if ( ! empty( $settings['schema_custom_wide'] ) ) {
            $decoded = json_decode( $settings['schema_custom_wide'], true );
            if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong><?php echo esc_html__( 'SEO Confident:', 'seo-confident' ); ?></strong>
                        <?php echo wp_kses_post( sprintf(
                            /* translators: %s: Settings page link */
                            __( 'Your Custom Wide Schema contains invalid JSON. Please <a href="%s">check and fix it</a>.', 'seo-confident' ),
                            esc_url( admin_url( 'admin.php?page=seoc-settings' ) )
                        ) ); ?>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Handle the AJAX request that dismisses the setup notice.
     */
    public function handle_setup_notice_dismiss() {
        check_ajax_referer( 'seoc_dismiss_notice', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }

        set_transient( 'seoc_setup_notice_dismissed', true, WEEK_IN_SECONDS );
        wp_send_json_success();
    }
}
