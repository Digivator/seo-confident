<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Open Graph and Twitter Card meta tags.
 */
class SEOC_Open_Graph {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', array( $this, 'output_og_tags' ), 2 );
    }

    public function output_og_tags() {
        $settings = get_option( 'seoc_settings', array() );

        $title       = '';
        $description = '';
        $url         = '';
        $image       = $settings['og_default_image'] ?? '';
        $type        = 'website';
        $post_id     = seoc_context_post_id();
        $post        = $post_id ? get_post( $post_id ) : null;

        if ( $post ) {
            // Singular content, the front page when it is a static page, or the static posts page.
            $title = get_post_meta( $post_id, '_seoc_title', true );
            if ( empty( $title ) && is_front_page() && ! empty( $settings['home_title'] ) ) {
                $title = $settings['home_title'];
            }
            if ( empty( $title ) ) {
                $title = get_the_title( $post_id );
            }

            $description = get_post_meta( $post_id, '_seoc_description', true );
            if ( empty( $description ) && is_front_page() ) {
                $description = $settings['home_description'] ?? '';
            }
            if ( empty( $description ) && is_singular() ) {
                $description = seoc_auto_description( $post );
            }

            $url  = get_permalink( $post_id );
            $type = ( is_singular() && 'post' === $post->post_type && ! is_front_page() ) ? 'article' : 'website';

            // Use featured image if available.
            if ( has_post_thumbnail( $post_id ) ) {
                $image = get_the_post_thumbnail_url( $post_id, 'large' );
            }

            // Check for custom OG image.
            $custom_og = get_post_meta( $post_id, '_seoc_og_image', true );
            if ( ! empty( $custom_og ) ) {
                $image = $custom_og;
            }
        } elseif ( is_front_page() ) {
            $title       = ! empty( $settings['home_title'] ) ? $settings['home_title'] : get_bloginfo( 'name' );
            $description = ! empty( $settings['home_description'] ) ? $settings['home_description'] : get_bloginfo( 'description' );
            $url         = home_url( '/' );
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term && ! empty( $term->name ) ) {
                $title = $term->name;
                $link  = get_term_link( $term );
                $url   = is_wp_error( $link ) ? '' : $link;
                $description = ! empty( $term->description )
                    ? wp_trim_words( seoc_clean_text( $term->description ), 25, '...' )
                    : '';
            }
        }

        $title       = seoc_clean_text( $title );
        $description = seoc_clean_text( $description );

        if ( '' === $title ) {
            return;
        }

        // Open Graph tags.
        echo "\n<!-- SEO Confident: Open Graph -->\n";
        printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
        printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $type ) );

        if ( ! empty( $url ) ) {
            printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
        }

        if ( '' !== $description ) {
            printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
        }

        printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( seoc_clean_text( get_bloginfo( 'name' ) ) ) );
        printf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( get_locale() ) );

        if ( ! empty( $image ) ) {
            printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
        }

        // Twitter Card tags.
        $card_type = $settings['twitter_card'] ?? 'summary_large_image';
        echo "\n<!-- SEO Confident: Twitter Card -->\n";
        printf( '<meta name="twitter:card" content="%s" />' . "\n", esc_attr( $card_type ) );
        printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );

        if ( '' !== $description ) {
            printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );
        }

        if ( ! empty( $image ) ) {
            printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );
        }
    }
}
