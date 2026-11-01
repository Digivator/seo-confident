<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles meta tag output in <head>.
 */
class SEOC_Meta_Tags {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Handle document title through pre_get_document_title filter.
        add_filter( 'pre_get_document_title', array( $this, 'filter_title' ), 20 );
        add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
    }

    /**
     * Override document title entirely if a custom one is set.
     */
    public function filter_title( $title ) {
        $settings = get_option( 'seoc_settings', array() );
        $post_id  = seoc_context_post_id();

        // A title typed into the page's own SEO box always wins, including on the homepage
        // and on a static posts page.
        if ( $post_id ) {
            $custom = get_post_meta( $post_id, '_seoc_title', true );
            if ( ! empty( $custom ) ) {
                return $custom;
            }
        }

        // Only the real homepage uses the homepage settings. A static posts page is a
        // different URL and must not inherit them.
        if ( is_front_page() ) {
            $custom = $settings['home_title'] ?? '';
            $mode   = $settings['home_title_mode'] ?? 'append_site_name';

            if ( ! empty( $custom ) ) {
                if ( 'append_site_name' === $mode ) {
                    $site_name = get_bloginfo( 'name' );
                    if ( stripos( $custom, $site_name ) === false ) {
                        $separator = ' ' . ( $settings['separator'] ?? '|' ) . ' ';
                        return $custom . $separator . $site_name;
                    }
                }

                return $custom;
            }
        }

        if ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term && ! empty( $term->term_id ) ) {
                $custom = get_term_meta( $term->term_id, '_seoc_title', true );
                if ( ! empty( $custom ) ) {
                    return $custom;
                }
            }
        }

        return $title;
    }

    /**
     * Output meta description and keywords.
     */
    public function output_meta_tags() {
        $settings = get_option( 'seoc_settings', array() );

        echo "\n<!-- SEO Confident v" . esc_html( SEOC_VERSION ) . " -->\n";

        $description = '';
        $keywords    = '';
        $robots      = '';
        $post_id     = seoc_context_post_id();

        if ( $post_id ) {
            $description = get_post_meta( $post_id, '_seoc_description', true );
            $keywords    = get_post_meta( $post_id, '_seoc_keywords', true );
            $robots      = get_post_meta( $post_id, '_seoc_robots', true );
        }

        if ( is_front_page() ) {
            // Per-page values win, the homepage settings fill the gaps.
            if ( empty( $description ) ) {
                $description = $settings['home_description'] ?? '';
            }
            if ( empty( $keywords ) ) {
                $keywords = $settings['home_keywords'] ?? '';
            }
        } elseif ( is_singular() ) {
            // Fallback: use excerpt first, then cleaned content.
            if ( empty( $description ) ) {
                $description = seoc_auto_description( get_post( $post_id ) );
            }
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term && ! empty( $term->term_id ) ) {
                $description = get_term_meta( $term->term_id, '_seoc_description', true );
                if ( empty( $description ) && ! empty( $term->description ) ) {
                    $description = wp_trim_words( seoc_clean_text( $term->description ), 25, '...' );
                }
            }
        } elseif ( is_author() ) {
            $author = get_queried_object();
            if ( $author && ! empty( $author->ID ) ) {
                $description = get_the_author_meta( 'description', $author->ID );
            }
        }

        if ( ! empty( $description ) ) {
            printf( '<meta name="description" content="%s" />' . "\n", esc_attr( seoc_clean_text( $description ) ) );
        }

        if ( ! empty( $keywords ) ) {
            printf( '<meta name="keywords" content="%s" />' . "\n", esc_attr( $keywords ) );
        }

        // Search results and error pages should never be indexed, whatever the site-wide setting is.
        if ( is_404() || is_search() ) {
            $robots = $this->get_default_robots( $settings, true );
        } elseif ( empty( $robots ) ) {
            $robots = $this->get_default_robots( $settings, false );
        }

        if ( ! empty( $robots ) ) {
            printf( '<meta name="robots" content="%s" />' . "\n", esc_attr( $robots ) );
        }

        echo "<!-- / SEO Confident -->\n\n";
    }

    /**
     * Build the default robots meta content.
     *
     * @param array $settings Plugin settings.
     * @param bool  $force_noindex Always output noindex (search and error pages).
     */
    private function get_default_robots( $settings, $force_noindex = false ) {
        $robots_parts = array();

        if ( $force_noindex || ( isset( $settings['robots_index'] ) && '0' === $settings['robots_index'] ) ) {
            $robots_parts[] = 'noindex';
        } else {
            $robots_parts[] = 'index';
        }

        if ( isset( $settings['robots_follow'] ) && '0' === $settings['robots_follow'] ) {
            $robots_parts[] = 'nofollow';
        } else {
            $robots_parts[] = 'follow';
        }

        $max_snippet       = $settings['robots_max_snippet'] ?? '-1';
        $max_video_preview = $settings['robots_max_video_preview'] ?? '-1';

        $robots_parts[] = 'max-snippet:' . ( is_numeric( $max_snippet ) ? intval( $max_snippet ) : -1 );
        $robots_parts[] = 'max-video-preview:' . ( is_numeric( $max_video_preview ) ? intval( $max_video_preview ) : -1 );

        return implode( ', ', $robots_parts );
    }
}
