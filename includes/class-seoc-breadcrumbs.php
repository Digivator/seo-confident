<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Breadcrumbs with BreadcrumbList schema.
 *
 * Outputs JSON-LD breadcrumb schema automatically.
 * Provides seoc_breadcrumbs() template function for visible breadcrumbs.
 */
class SEOC_Breadcrumbs {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $settings = get_option( 'seoc_settings', array() );

        if ( ( $settings['breadcrumbs'] ?? '0' ) === '1' ) {
            add_action( 'wp_head', array( $this, 'output_breadcrumb_schema' ), 4 );
        }
    }

    /**
     * Build the breadcrumb trail.
     */
    public function get_breadcrumbs() {
        $crumbs = array();

        // Home is always first.
        $crumbs[] = array(
            'name' => 'Home',
            'url'  => home_url( '/' ),
        );

        if ( is_singular() ) {
            $post = get_post();

            // If it's a post, add the category.
            if ( 'post' === $post->post_type ) {
                $categories = get_the_category();
                if ( ! empty( $categories ) ) {
                    $cat = $categories[0];
                    // Add parent categories.
                    $parents = array();
                    $parent_id = $cat->parent;
                    while ( $parent_id ) {
                        $parent_cat = get_category( $parent_id );
                        $parents[]  = array(
                            'name' => $parent_cat->name,
                            'url'  => get_category_link( $parent_cat ),
                        );
                        $parent_id = $parent_cat->parent;
                    }
                    $crumbs = array_merge( $crumbs, array_reverse( $parents ) );

                    $crumbs[] = array(
                        'name' => $cat->name,
                        'url'  => get_category_link( $cat ),
                    );
                }
            }

            // Hierarchical post types (pages).
            if ( is_page() && $post->post_parent ) {
                $ancestors = get_post_ancestors( $post );
                $ancestors = array_reverse( $ancestors );
                foreach ( $ancestors as $ancestor_id ) {
                    $crumbs[] = array(
                        'name' => get_the_title( $ancestor_id ),
                        'url'  => get_permalink( $ancestor_id ),
                    );
                }
            }

            // Current page/post.
            $crumbs[] = array(
                'name' => get_the_title(),
                'url'  => get_permalink(),
            );

        } elseif ( is_home() && ! is_front_page() ) {
            // The blog index when it lives on its own page.
            $posts_page = (int) get_option( 'page_for_posts' );
            if ( $posts_page ) {
                $crumbs[] = array(
                    'name' => get_the_title( $posts_page ),
                    'url'  => get_permalink( $posts_page ),
                );
            }

        } elseif ( is_category() ) {
            $cat = get_queried_object();
            // Parent categories.
            $parents   = array();
            $parent_id = $cat->parent;
            while ( $parent_id ) {
                $parent_cat = get_category( $parent_id );
                $parents[]  = array(
                    'name' => $parent_cat->name,
                    'url'  => get_category_link( $parent_cat ),
                );
                $parent_id = $parent_cat->parent;
            }
            $crumbs = array_merge( $crumbs, array_reverse( $parents ) );

            $crumbs[] = array(
                'name' => $cat->name,
                'url'  => get_category_link( $cat ),
            );

        } elseif ( is_tag() ) {
            $crumbs[] = array(
                'name' => single_tag_title( '', false ),
                'url'  => get_tag_link( get_queried_object_id() ),
            );

        } elseif ( is_author() ) {
            $crumbs[] = array(
                'name' => get_the_author(),
                'url'  => get_author_posts_url( get_queried_object_id() ),
            );

        } elseif ( is_search() ) {
            $crumbs[] = array(
                'name' => 'Search: ' . get_search_query(),
                'url'  => get_search_link(),
            );

        } elseif ( is_archive() ) {
            $crumbs[] = array(
                'name' => get_the_archive_title(),
                'url'  => '',
            );
        }

        return $crumbs;
    }

    /**
     * Output BreadcrumbList JSON-LD schema.
     */
    public function output_breadcrumb_schema() {
        if ( is_front_page() ) {
            return;
        }

        $crumbs = $this->get_breadcrumbs();

        if ( count( $crumbs ) < 2 ) {
            return;
        }

        $items = array();
        foreach ( array_values( $crumbs ) as $i => $crumb ) {
            $item = array(
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => seoc_clean_text( $crumb['name'] ),
            );
            if ( ! empty( $crumb['url'] ) ) {
                $item['item'] = $crumb['url'];
            }
            $items[] = $item;
        }

        $schema = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        );

        seoc_print_json_ld( $schema, 'SEO Confident: Breadcrumbs' );
    }

    /**
     * Render visible HTML breadcrumbs.
     * Use in theme templates: <?php if ( function_exists( 'seoc_breadcrumbs' ) ) seoc_breadcrumbs(); ?>
     */
    public function render_html() {
        if ( is_front_page() ) {
            return;
        }

        $crumbs = $this->get_breadcrumbs();

        if ( count( $crumbs ) < 2 ) {
            return;
        }

        echo '<nav class="seoc-breadcrumbs" aria-label="Breadcrumb">';
        echo '<ol>';

        $last = count( $crumbs ) - 1;
        foreach ( $crumbs as $i => $crumb ) {
            if ( $i === $last ) {
                echo '<li class="seoc-breadcrumb-current" aria-current="page">' . esc_html( $crumb['name'] ) . '</li>';
            } else {
                echo '<li><a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a></li>';
            }
        }

        echo '</ol>';
        echo '</nav>';
    }
}

/**
 * Template tag for visible breadcrumbs.
 */
function seoc_breadcrumbs() {
    SEOC_Breadcrumbs::instance()->render_html();
}
