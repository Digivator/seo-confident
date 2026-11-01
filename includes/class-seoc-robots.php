<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Robots.txt management.
 *
 * Replaces WordPress's own sitemap line with ours and appends custom directives
 * to the virtual robots.txt.
 */
class SEOC_Robots {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Late, so the line WordPress core adds at priority 0 is already in the output.
        add_filter( 'robots_txt', array( $this, 'modify_robots_txt' ), 99, 2 );
    }

    /**
     * Add our sitemap reference and the custom directives.
     *
     * @param string     $output Current robots.txt content.
     * @param string|int $is_public Value of the blog_public option.
     * @return string
     */
    public function modify_robots_txt( $output, $is_public ) {
        // A site that asks search engines to stay away gets WordPress's own "Disallow: /" untouched.
        if ( ! (int) $is_public ) {
            return $output;
        }

        $settings    = get_option( 'seoc_settings', array() );
        $sitemap_url = home_url( '/sitemap.xml' );

        // Core points at /wp-sitemap.xml, which is switched off while SEO Confident is active.
        $output = preg_replace( '#^[ \t]*Sitemap:[ \t]*\S*/wp-sitemap\.xml[ \t]*\r?\n?#mi', '', (string) $output );
        $output = rtrim( (string) $output ) . "\n";

        $extra = isset( $settings['robots_extra'] ) ? trim( (string) $settings['robots_extra'] ) : '';
        if ( '' !== $extra ) {
            $output .= "\n# SEO Confident custom directives\n" . $extra . "\n";
        }

        if ( false === stripos( $output, $sitemap_url ) ) {
            $output .= "\nSitemap: " . esc_url_raw( $sitemap_url ) . "\n";
        }

        return $output;
    }

    /**
     * The robots.txt exactly as WordPress will serve it, for the admin preview.
     *
     * @return string
     */
    public static function preview() {
        $public    = get_option( 'blog_public' );
        $site_path = (string) wp_parse_url( site_url(), PHP_URL_PATH );

        $output = "User-agent: *\n";
        if ( '0' === (string) $public ) {
            $output .= "Disallow: /\n";
        } else {
            $output .= 'Disallow: ' . $site_path . "/wp-admin/\n";
            $output .= 'Allow: ' . $site_path . "/wp-admin/admin-ajax.php\n";
        }

        // robots_txt is a WordPress core filter, so it carries no plugin prefix.
        return (string) apply_filters( 'robots_txt', $output, $public ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
    }
}
