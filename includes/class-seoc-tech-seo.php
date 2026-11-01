<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Technical SEO hardening.
 *
 * All features are toggle-controlled from the Settings page.
 * Generic and applicable to any WordPress site.
 */
class SEOC_Tech_SEO {

    private static $instance = null;
    private $settings;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = get_option( 'seoc_settings', array() );

        // HTML lang attribute.
        $lang = $this->settings['tech_lang'] ?? '';
        if ( ! empty( $lang ) ) {
            add_filter( 'language_attributes', function( $output ) use ( $lang ) {
                if ( strpos( $output, 'lang=' ) === false ) {
                    $output .= ' lang="' . esc_attr( $lang ) . '"';
                }
                return $output;
            } );
        }

        // Security headers.
        if ( ( $this->settings['tech_security_headers'] ?? '0' ) === '1' ) {
            add_action( 'send_headers', function() {
                if ( is_admin() ) {
                    return;
                }
                header( 'X-Content-Type-Options: nosniff' );
                header( 'X-Frame-Options: SAMEORIGIN' );
                header( 'Referrer-Policy: strict-origin-when-cross-origin' );
                header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
            } );
        }

        // Preconnect hints.
        if ( ( $this->settings['tech_preconnect'] ?? '0' ) === '1' ) {
            add_action( 'wp_head', function() {
                echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin />' . "\n";
                echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />' . "\n";
                echo '<link rel="dns-prefetch" href="//www.google-analytics.com" />' . "\n";
            }, 1 );
        }

        // Strip ?media_link parameter.
        if ( ( $this->settings['tech_strip_media_link'] ?? '0' ) === '1' ) {
            add_action( 'template_redirect', function() {
                if ( ! is_admin() && isset( $_GET['media_link'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks that the parameter is present on a public request.
                    wp_safe_redirect( remove_query_arg( 'media_link' ), 301 );
                    exit;
                }
            }, 1 );
        }

        // Fix tel: link format.
        if ( ( $this->settings['tech_fix_tel'] ?? '0' ) === '1' ) {
            // Start buffering only once WordPress knows it is about to render a front-end page,
            // and skip feeds and anything that is not HTML.
            add_action( 'template_redirect', function() {
                if ( is_admin() || is_feed() || is_robots() || is_trackback() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
                    return;
                }
                ob_start( function( $html ) {
                    if ( false === strpos( $html, 'tel:00' ) ) {
                        return $html;
                    }
                    return preg_replace( '/tel:00(\d+)/', 'tel:+$1', $html );
                } );
            }, 0 );
        }

        // Auto image alt text.
        if ( ( $this->settings['tech_image_alt'] ?? '0' ) === '1' ) {
            add_filter( 'the_content', function( $content ) {
                if ( empty( $content ) ) {
                    return $content;
                }
                $title = get_the_title();
                if ( empty( $title ) ) {
                    return $content;
                }
                $count = 0;
                $content = preg_replace_callback(
                    '/<img\b([^>]*?)alt\s*=\s*""\s*([^>]*?)>/i',
                    function( $matches ) use ( $title, &$count ) {
                        $count++;
                        $alt = esc_attr( $title . ' - Image ' . $count );
                        return '<img ' . $matches[1] . 'alt="' . $alt . '" ' . $matches[2] . '>';
                    },
                    $content
                );
                return $content;
            }, 20 );
        }
    }
}
