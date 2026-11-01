<?php
/**
 * Plugin uninstall routine.
 *
 * @package SEO_Confident
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove every option, transient and piece of post or term meta the plugin created.
 */
function seoc_uninstall_cleanup() {
    global $wpdb;

    $options_to_delete = array(
        'seoc_settings',
        'seoc_llms_txt',
        'seoc_redirects',
        'seoc_version',
        'seoc_sitemap_rules_flushed',
    );

    foreach ( $options_to_delete as $option_name ) {
        delete_option( $option_name );
        delete_site_option( $option_name ); // For multisite.
    }

    delete_transient( 'seoc_setup_notice_dismissed' );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    // Delete post meta.
    $wpdb->query(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (
            '_seoc_title',
            '_seoc_description',
            '_seoc_keywords',
            '_seoc_canonical',
            '_seoc_robots',
            '_seoc_og_image',
            '_seoc_custom_schema'
        )"
    );

    // Delete term meta.
    $wpdb->query(
        "DELETE FROM {$wpdb->termmeta} WHERE meta_key IN (
            '_seoc_title',
            '_seoc_description'
        )"
    );

    // Delete leftover "invalid schema" notices.
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_seoc\_schema\_error\_%' OR option_name LIKE '\_transient\_timeout\_seoc\_schema\_error\_%'"
    );

    // phpcs:enable
}

seoc_uninstall_cleanup();
