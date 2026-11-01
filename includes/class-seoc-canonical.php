<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Canonical URL management.
 *
 * Ensures every indexable page has a proper canonical URL to prevent
 * duplicate content issues. Allows per-page overrides.
 */
class SEOC_Canonical {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Remove WordPress default canonical (we handle it ourselves).
        remove_action( 'wp_head', 'rel_canonical' );
        add_action( 'wp_head', array( $this, 'output_canonical' ), 1 );
    }

    public function output_canonical() {
        $canonical = '';
        $post_id   = seoc_context_post_id();

        if ( is_front_page() ) {
            $custom    = is_singular() ? get_post_meta( $post_id, '_seoc_canonical', true ) : '';
            $canonical = ! empty( $custom ) ? $custom : home_url( '/' );
        } elseif ( $post_id ) {
            // Singular content, or the static posts page: use the custom canonical if one is set.
            $custom = get_post_meta( $post_id, '_seoc_canonical', true );
            if ( ! empty( $custom ) ) {
                $canonical = $custom;
            } else {
                $canonical = get_permalink( $post_id );
            }
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term ) {
                $link = get_term_link( $term );
                if ( ! is_wp_error( $link ) ) {
                    $canonical = $link;
                }
            }
        } elseif ( is_author() ) {
            $canonical = get_author_posts_url( get_queried_object_id() );
        } elseif ( is_post_type_archive() ) {
            $link = get_post_type_archive_link( get_query_var( 'post_type' ) );
            if ( $link ) {
                $canonical = $link;
            }
        }

        // Handle pagination of archives and the blog index.
        $paged = (int) get_query_var( 'paged' );
        if ( $paged > 1 && ! empty( $canonical ) && ! is_singular() ) {
            if ( get_option( 'permalink_structure' ) ) {
                $canonical = trailingslashit( $canonical ) . 'page/' . $paged . '/';
            } else {
                $canonical = add_query_arg( 'paged', $paged, $canonical );
            }
        }

        if ( ! empty( $canonical ) ) {
            printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
        }

        // Prev/next for paginated archives.
        if ( is_archive() || is_home() ) {
            global $wp_query;
            $total   = $wp_query->max_num_pages;
            $current = max( 1, $paged );

            if ( $current > 1 ) {
                $prev_url = get_pagenum_link( $current - 1 );
                printf( '<link rel="prev" href="%s" />' . "\n", esc_url( $prev_url ) );
            }

            if ( $current < $total ) {
                $next_url = get_pagenum_link( $current + 1 );
                printf( '<link rel="next" href="%s" />' . "\n", esc_url( $next_url ) );
            }
        }
    }
}
