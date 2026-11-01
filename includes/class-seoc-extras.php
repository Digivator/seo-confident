<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Extras: AI crawler rules in robots.txt, hreflang tags and speakable schema.
 *
 * Filters available:
 *  seoc_ai_bots             Array of AI crawler names that get an Allow group.
 *  seoc_output_hreflang     Return false to switch the hreflang tags off.
 *  seoc_hreflang_code       Language code used in the hreflang tags (default: site locale).
 *  seoc_speakable_selectors CSS selectors marked as speakable on the homepage (default: none).
 */
class SEOC_Extras {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // After SEOC_Robots (priority 99), so the custom directives are already in place
        // and the Sitemap line can be kept at the end.
        add_filter( 'robots_txt', array( $this, 'robots' ), 100, 2 );
        add_action( 'wp_head', array( $this, 'hreflang' ), 4 );
        add_filter( 'seoc_page_schema', array( $this, 'speakable' ) );
    }

    /**
     * Give each main AI crawler an explicit Allow group.
     *
     * A crawler that already has its own User-agent line in robots.txt, for example an
     * opt-out typed into the Robots tab, is left alone.
     *
     * @param string     $output    Current robots.txt content.
     * @param string|int $is_public Value of the blog_public option.
     * @return string
     */
    public function robots( $output, $is_public ) {
        if ( ! (int) $is_public ) {
            return $output;
        }

        $output = (string) $output;

        $bots = (array) apply_filters(
            'seoc_ai_bots',
            array(
                'GPTBot',
                'OAI-SearchBot',
                'ChatGPT-User',
                'ClaudeBot',
                'Claude-SearchBot',
                'Claude-User',
                'PerplexityBot',
                'Perplexity-User',
                'Google-Extended',
                'Applebot-Extended',
                'CCBot',
            )
        );

        $missing = array();
        foreach ( $bots as $bot ) {
            $bot = preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $bot );
            if ( '' === $bot ) {
                continue;
            }
            $pattern = '/^[ \t]*User-agent:[ \t]*' . preg_quote( $bot, '/' ) . '[ \t\r]*$/mi';
            if ( ! preg_match( $pattern, $output ) ) {
                $missing[] = $bot;
            }
        }

        if ( empty( $missing ) ) {
            return $output;
        }

        // Lift the Sitemap lines out so they can go back at the end, as the Robots tab describes.
        $sitemaps = array();
        if ( preg_match_all( '/^[ \t]*Sitemap:[^\r\n]*/mi', $output, $found ) ) {
            $sitemaps = array_map( 'trim', $found[0] );
            $output   = preg_replace( '/^[ \t]*Sitemap:[^\r\n]*\r?\n?/mi', '', $output );
        }

        $site_path = (string) wp_parse_url( site_url(), PHP_URL_PATH );

        $rules = "\n# AI crawlers\n";
        foreach ( $missing as $bot ) {
            $rules .= 'User-agent: ' . $bot . "\n";
            $rules .= 'Disallow: ' . $site_path . "/wp-admin/\n";
            $rules .= 'Allow: ' . $site_path . "/wp-admin/admin-ajax.php\n";
            $rules .= "Allow: /\n\n";
        }

        $output = rtrim( $output ) . "\n" . $rules;

        if ( $sitemaps ) {
            $output .= implode( "\n", $sitemaps ) . "\n";
        }

        return $output;
    }

    /**
     * Whether a multilingual plugin is active. Those print their own hreflang tags,
     * and a second set from here would conflict with them.
     *
     * @return bool
     */
    private function multilingual_plugin_active() {
        return defined( 'ICL_SITEPRESS_VERSION' )
            || function_exists( 'pll_languages_list' )
            || class_exists( 'TRP_Translate_Press' );
    }

    /**
     * Self-referencing hreflang tags for the homepage, pages and posts.
     */
    public function hreflang() {
        if ( ! apply_filters( 'seoc_output_hreflang', ! $this->multilingual_plugin_active() ) ) {
            return;
        }

        if ( is_front_page() ) {
            $url = home_url( '/' );
        } elseif ( is_singular() ) {
            $url = get_permalink( get_queried_object_id() );
        } else {
            return;
        }

        if ( ! $url ) {
            return;
        }

        $code = (string) apply_filters( 'seoc_hreflang_code', strtolower( str_replace( '_', '-', get_locale() ) ) );
        if ( '' === $code ) {
            return;
        }

        echo '<link rel="alternate" hreflang="' . esc_attr( $code ) . '" href="' . esc_url( $url ) . '" />' . "\n";
        echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $url ) . '" />' . "\n";
    }

    /**
     * Add speakable markup to the homepage WebPage schema.
     *
     * Nothing is added until selectors are supplied with the seoc_speakable_selectors
     * filter, because the right selectors depend on the theme.
     *
     * @param array $schema The auto-generated page schema.
     * @return array
     */
    public function speakable( $schema ) {
        if ( ! is_front_page() || ! is_array( $schema ) || 'WebPage' !== ( $schema['@type'] ?? '' ) || isset( $schema['speakable'] ) ) {
            return $schema;
        }

        $selectors = (array) apply_filters( 'seoc_speakable_selectors', array() );
        $selectors = array_values( array_filter( array_map( 'strval', $selectors ) ) );

        if ( empty( $selectors ) ) {
            return $schema;
        }

        $schema['speakable'] = array(
            '@type'       => 'SpeakableSpecification',
            'cssSelector' => $selectors,
        );

        return $schema;
    }
}
