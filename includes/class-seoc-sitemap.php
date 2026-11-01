<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * XML and HTML sitemap generator.
 *
 * Requests for /sitemap.xml and its children are answered directly on parse_request,
 * so no rewrite rules are needed and nothing has to be flushed.
 */
class SEOC_Sitemap {

    private static $instance = null;

    /**
     * Sitemaps built during this request, keyed by file slug.
     *
     * @var array|null
     */
    private $built = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'parse_request', array( $this, 'maybe_serve' ), 0 );
        add_shortcode( 'seoc_html_sitemap', array( $this, 'html_sitemap_shortcode' ) );
    }

    // =====================================================================
    // REQUEST HANDLING
    // =====================================================================

    /**
     * Serve the sitemap if the request is for one.
     */
    public function maybe_serve() {
        $path = seoc_request_path();

        if ( '' === $path || false === stripos( $path, 'sitemap' ) ) {
            return;
        }

        // The core sitemap is switched off, so send old links to ours.
        if ( preg_match( '#^wp-sitemap(-[a-z0-9_-]+)*\.xml$#i', $path ) ) {
            wp_redirect( home_url( '/sitemap.xml' ), 301, 'SEO Confident' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
            exit;
        }

        if ( ! preg_match( '#^sitemap(-([a-z0-9_-]+))?\.xml$#i', $path, $m ) ) {
            return;
        }

        $slug = isset( $m[2] ) ? strtolower( $m[2] ) : '';

        if ( '' === $slug ) {
            $xml = $this->generate_index();
        } else {
            $maps = $this->get_sitemaps();
            if ( ! isset( $maps[ $slug ] ) ) {
                // Not one of ours. Let WordPress carry on and show its normal 404.
                return;
            }
            $xml = $this->generate_urlset( $maps[ $slug ]['entries'] );
        }

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        status_header( 200 );
        header( 'Content-Type: application/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex, follow' );
        header( 'X-Content-Type-Options: nosniff' );

        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' !== $_SERVER['REQUEST_METHOD'] ) {
            echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped values.
        }

        exit;
    }

    // =====================================================================
    // DATA
    // =====================================================================

    /**
     * File slug used for a post type: posts and pages keep their original names.
     */
    private function slug_for_post_type( $post_type ) {
        if ( 'post' === $post_type ) {
            return 'posts';
        }
        if ( 'page' === $post_type ) {
            return 'pages';
        }
        return $post_type;
    }

    /**
     * Post types that get a sitemap.
     */
    private function get_post_types() {
        $types = get_post_types( array( 'public' => true ), 'names' );
        unset( $types['attachment'] );

        $types = apply_filters( 'seoc_sitemap_post_types', array_values( $types ) );

        return is_array( $types ) ? $types : array( 'post', 'page' );
    }

    /**
     * Build every sitemap, keeping only those that contain at least one URL.
     *
     * @return array Keyed by file slug: label, entries (loc, lastmod), lastmod.
     */
    public function get_sitemaps() {
        if ( null !== $this->built ) {
            return $this->built;
        }

        $maps = array();

        foreach ( $this->get_post_types() as $post_type ) {
            $object = get_post_type_object( $post_type );
            if ( ! $object ) {
                continue;
            }

            $entries = $this->get_post_type_entries( $post_type );
            if ( empty( $entries ) ) {
                continue;
            }

            $maps[ $this->slug_for_post_type( $post_type ) ] = array(
                'label'   => $object->labels->name,
                'entries' => $entries,
            );
        }

        $term_entries = $this->get_taxonomy_entries();
        if ( ! empty( $term_entries ) ) {
            $maps['taxonomies'] = array(
                'label'   => __( 'Taxonomies', 'seo-confident' ),
                'entries' => $term_entries,
            );
        }

        foreach ( $maps as $slug => $map ) {
            $latest = '';
            foreach ( $map['entries'] as $entry ) {
                if ( ! empty( $entry['lastmod'] ) && $entry['lastmod'] > $latest ) {
                    $latest = $entry['lastmod'];
                }
            }
            $maps[ $slug ]['lastmod'] = $latest;
        }

        $this->built = $maps;

        return $this->built;
    }

    /**
     * Counts for the admin screen.
     *
     * @return array List of label, count and url.
     */
    public function get_summary() {
        $summary = array();

        foreach ( $this->get_sitemaps() as $slug => $map ) {
            $summary[] = array(
                'label' => $map['label'],
                'count' => count( $map['entries'] ),
                'url'   => home_url( '/sitemap-' . $slug . '.xml' ),
                'file'  => 'sitemap-' . $slug . '.xml',
            );
        }

        return $summary;
    }

    private function format_lastmod( $mysql_gmt ) {
        if ( empty( $mysql_gmt ) || '0000-00-00 00:00:00' === $mysql_gmt ) {
            return '';
        }

        $time = strtotime( $mysql_gmt . ' UTC' );

        return $time ? gmdate( 'Y-m-d\TH:i:s+00:00', $time ) : '';
    }

    private function get_post_type_entries( $post_type ) {
        $max = (int) apply_filters( 'seoc_sitemap_max_urls', 5000, $post_type );

        $posts = get_posts(
            array(
                'post_type'              => $post_type,
                'post_status'            => 'publish',
                'posts_per_page'         => max( 1, $max ),
                'orderby'                => 'modified',
                'order'                  => 'DESC',
                'has_password'           => false,
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
            )
        );

        $entries = array();

        // With a "latest posts" front page there is no page to list, so add the homepage itself.
        if ( 'page' === $post_type && 'posts' === get_option( 'show_on_front' ) ) {
            $entries[ home_url( '/' ) ] = array(
                'loc'     => home_url( '/' ),
                'lastmod' => $this->format_lastmod( get_lastpostmodified( 'gmt', 'post' ) ),
            );
        }

        foreach ( $posts as $post ) {
            if ( seoc_is_excluded_post( $post ) ) {
                continue;
            }

            $loc = get_permalink( $post );
            if ( ! $loc ) {
                continue;
            }

            // Keyed by URL so the homepage can never appear twice.
            $entries[ $loc ] = array(
                'loc'     => $loc,
                'lastmod' => $this->format_lastmod( $post->post_modified_gmt ),
            );
        }

        return array_values( $entries );
    }

    private function get_taxonomy_entries() {
        $entries    = array();
        $taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
        unset( $taxonomies['post_format'] );

        $max = (int) apply_filters( 'seoc_sitemap_max_urls', 5000, 'taxonomies' );

        foreach ( $taxonomies as $taxonomy ) {
            $terms = get_terms(
                array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => true,
                    'number'     => max( 1, $max ),
                )
            );

            if ( is_wp_error( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $link = get_term_link( $term );
                if ( is_wp_error( $link ) || ! $link ) {
                    continue;
                }
                $entries[ $link ] = array(
                    'loc'     => $link,
                    'lastmod' => '',
                );
            }
        }

        return array_values( $entries );
    }

    // =====================================================================
    // XML
    // =====================================================================

    private function xml_url( $url ) {
        return htmlspecialchars( esc_url_raw( $url ), ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }

    private function generate_index() {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ( $this->get_sitemaps() as $slug => $map ) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . $this->xml_url( home_url( '/sitemap-' . $slug . '.xml' ) ) . '</loc>' . "\n";
            if ( ! empty( $map['lastmod'] ) ) {
                $xml .= '    <lastmod>' . esc_html( $map['lastmod'] ) . '</lastmod>' . "\n";
            }
            $xml .= '  </sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>' . "\n";

        return $xml;
    }

    private function generate_urlset( $entries ) {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ( $entries as $entry ) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $this->xml_url( $entry['loc'] ) . '</loc>' . "\n";
            if ( ! empty( $entry['lastmod'] ) ) {
                $xml .= '    <lastmod>' . esc_html( $entry['lastmod'] ) . '</lastmod>' . "\n";
            }
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    // =====================================================================
    // HTML SITEMAP: [seoc_html_sitemap]
    //
    // Order: Posts (latest first, no grouping) > Pages > Categories > Tags
    // No dates displayed.
    // =====================================================================
    public function html_sitemap_shortcode( $atts ) {
        ob_start();
        echo '<div class="seoc-html-sitemap">';

        // --- POSTS: latest first, no grouping, no dates ---
        $posts = get_posts(
            array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- the HTML sitemap lists the latest 500 posts and no more.
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        $posts = array_filter(
            $posts,
            function ( $p ) {
                return ! seoc_is_excluded_post( $p );
            }
        );

        if ( ! empty( $posts ) ) {
            echo '<h2>' . esc_html__( 'Posts', 'seo-confident' ) . '</h2>';
            echo '<ul>';
            foreach ( $posts as $p ) {
                echo '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
            }
            echo '</ul>';
        }

        // --- PAGES: hierarchical tree ---
        $pages = get_pages( array( 'sort_column' => 'menu_order, post_title', 'post_status' => 'publish' ) );

        if ( ! empty( $pages ) ) {
            echo '<h2>' . esc_html__( 'Pages', 'seo-confident' ) . '</h2>';
            echo '<ul>';
            $this->render_page_tree( $pages, 0 );
            echo '</ul>';
        }

        // --- CATEGORIES ---
        $categories = get_categories( array( 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' ) );

        if ( ! empty( $categories ) ) {
            echo '<h2>' . esc_html__( 'Categories', 'seo-confident' ) . '</h2>';
            echo '<ul>';
            foreach ( $categories as $cat ) {
                echo '<li><a href="' . esc_url( get_category_link( $cat ) ) . '">' . esc_html( $cat->name ) . '</a> (' . intval( $cat->count ) . ')</li>';
            }
            echo '</ul>';
        }

        // --- TAGS ---
        $tags = get_tags( array( 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' ) );

        if ( ! empty( $tags ) ) {
            echo '<h2>' . esc_html__( 'Tags', 'seo-confident' ) . '</h2>';
            echo '<ul>';
            foreach ( $tags as $tag ) {
                echo '<li><a href="' . esc_url( get_tag_link( $tag ) ) . '">' . esc_html( $tag->name ) . '</a> (' . intval( $tag->count ) . ')</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    private function render_page_tree( $pages, $parent_id ) {
        $children = array_filter(
            $pages,
            function ( $p ) use ( $parent_id ) {
                return (int) $p->post_parent === (int) $parent_id;
            }
        );

        foreach ( $children as $page ) {
            if ( seoc_is_excluded_post( $page ) ) {
                continue;
            }

            echo '<li>';
            echo '<a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a>';

            $has_children = array_filter(
                $pages,
                function ( $p ) use ( $page ) {
                    return (int) $p->post_parent === (int) $page->ID;
                }
            );

            if ( ! empty( $has_children ) ) {
                echo '<ul>';
                $this->render_page_tree( $pages, $page->ID );
                echo '</ul>';
            }

            echo '</li>';
        }
    }
}
