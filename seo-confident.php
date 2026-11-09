<?php
/**
 * Plugin Name: SEO Confident
 * Description: A lightweight SEO plugin. Meta tags, XML sitemaps, canonical URLs, Open Graph, Twitter Cards, schema markup, robots.txt, llms.txt, redirects, breadcrumbs, and technical SEO hardening.
 * Version: 1.0.0
 * Author: Digivator
 * Author URI: https://digivator.co.uk
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seo-confident
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SEOC_VERSION', '1.0.0' );
define( 'SEOC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SEOC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SEOC_PLUGIN_FILE', __FILE__ );

// Load plugin components.
require_once SEOC_PLUGIN_DIR . 'includes/seoc-functions.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-settings.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-meta-tags.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-sitemap.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-open-graph.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-schema.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-canonical.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-robots.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-breadcrumbs.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-meta-box.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-redirects.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-llms.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-tech-seo.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-admin-tabs.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-admin-notices.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-import-export.php';
require_once SEOC_PLUGIN_DIR . 'includes/class-seoc-extras.php';

// Switch off the WordPress core sitemap. This has to happen while the plugin file loads:
// core builds its sitemap server (and adds "Sitemap: /wp-sitemap.xml" to robots.txt) on
// init priority 10, which is before anything this plugin could hook on init.
add_filter( 'wp_sitemaps_enabled', '__return_false' );

/**
 * Main plugin class.
 */
final class SEO_Confident {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'boot' ) );
        add_action( 'init', array( $this, 'maybe_upgrade' ), 99 );
        register_activation_hook( SEOC_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( SEOC_PLUGIN_FILE, array( $this, 'deactivate' ) );
    }

    /**
     * Create the components. This is done on plugins_loaded rather than init so that
     * their own init and parse_request hooks are registered in time to run.
     */
    public function boot() {
        SEOC_Settings::instance();
        SEOC_Meta_Tags::instance();
        SEOC_Sitemap::instance();
        SEOC_Open_Graph::instance();
        SEOC_Schema::instance();
        SEOC_Canonical::instance();
        SEOC_Robots::instance();
        SEOC_Breadcrumbs::instance();
        SEOC_Redirects::instance();
        SEOC_LLMS::instance();
        SEOC_Tech_SEO::instance();
        SEOC_Extras::instance();

        if ( is_admin() ) {
            SEOC_Meta_Box::instance();
            SEOC_Admin_Tabs::instance();
            SEOC_Admin_Notices::instance();
            SEOC_Import_Export::instance();
        }
    }

    /**
     * One-off clean-up when the plugin has been updated in place.
     */
    public function maybe_upgrade() {
        $stored = get_option( 'seoc_version', '' );

        if ( SEOC_VERSION === $stored ) {
            return;
        }

        // Version 1.1 saved llms.txt through wp_kses_post(), which turned ">" into "&gt;"
        // and "&" into "&amp;". Put them back so the blockquote line and links read correctly.
        $llms = get_option( 'seoc_llms_txt', '' );
        if ( is_string( $llms ) && '' !== $llms && preg_match( '/&(gt|lt|amp|quot|#0?39);/i', $llms ) ) {
            update_option( 'seoc_llms_txt', wp_specialchars_decode( $llms, ENT_QUOTES ) );
        }

        // Version 1.1 registered sitemap rewrite rules and left this marker behind.
        // The rules are no longer needed, so clear them out of the stored rewrite rules once.
        if ( false !== get_option( 'seoc_sitemap_rules_flushed', false ) ) {
            delete_option( 'seoc_sitemap_rules_flushed' );
            flush_rewrite_rules( false );
        }

        update_option( 'seoc_version', SEOC_VERSION );
    }

    public function activate() {
        $defaults = array(
            'home_title'             => get_bloginfo( 'name' ) . ' - ' . get_bloginfo( 'description' ),
            'home_description'       => get_bloginfo( 'description' ),
            'home_keywords'          => '',
            'separator'              => '|',
            'og_default_image'       => '',
            'twitter_card'           => 'summary_large_image',
            'schema_org_type'        => 'Organization',
            'schema_org_name'        => get_bloginfo( 'name' ),
            'schema_org_logo'        => '',
            'schema_org_email'       => '',
            'schema_org_sameas'      => '',
            'schema_org_area_served' => '',
            'schema_org_logo_width'  => '',
            'schema_org_logo_height' => '',
            'schema_custom_wide'     => '',
            'home_title_mode'        => 'append_site_name',
            'breadcrumbs'            => '1',
            'robots_index'           => '1',
            'robots_follow'          => '1',
            'robots_max_snippet'     => '-1',
            'robots_max_video_preview' => '-1',
            'robots_extra'           => '',
            'tech_lang'              => 'en',
            'tech_security_headers'  => '1',
            'tech_preconnect'        => '0',
            'tech_fix_tel'           => '1',
            'tech_strip_media_link'  => '1',
            'tech_image_alt'         => '0',
        );

        $existing = get_option( 'seoc_settings', array() );
        update_option( 'seoc_settings', wp_parse_args( is_array( $existing ) ? $existing : array(), $defaults ) );

        // llms.txt: with nothing saved, the file is generated from the site's pages and posts.
        update_option( 'seoc_version', SEOC_VERSION );
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

SEO_Confident::instance();
