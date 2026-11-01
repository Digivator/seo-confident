<?php
/**
 * Shared helper functions.
 *
 * @package SEO_Confident
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The request path relative to the site's home URL, without leading or trailing slashes.
 *
 * Works for sites installed in a sub-directory, where REQUEST_URI starts with the
 * sub-directory and a plain comparison against "llms.txt" would never match.
 *
 * @return string
 */
function seoc_request_path() {
    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $path = wp_parse_url( $uri, PHP_URL_PATH );

    if ( ! is_string( $path ) ) {
        return '';
    }

    return seoc_strip_home_path( $path );
}

/**
 * Remove the home URL's own path (sub-directory installs) from a path and trim slashes.
 *
 * @param string $path URL path.
 * @return string
 */
function seoc_strip_home_path( $path ) {
    $path = trim( (string) $path, '/' );
    $home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

    if ( '' !== $home ) {
        if ( 0 === strcasecmp( $path, $home ) ) {
            return '';
        }
        if ( 0 === stripos( $path, $home . '/' ) ) {
            return substr( $path, strlen( $home ) + 1 );
        }
    }

    return $path;
}

/**
 * Turn titles and other HTML-ish strings into plain text.
 *
 * Titles from get_the_title() carry entities such as &amp; and &#8217;. Those are correct in
 * HTML but wrong inside JSON-LD and plain text files, so they are decoded here and escaped
 * again by whatever prints the value.
 *
 * @param string $text Raw text.
 * @return string
 */
function seoc_clean_text( $text ) {
    $text = wp_strip_all_tags( (string) $text );
    $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
    $collapsed = preg_replace( '/\s+/u', ' ', $text );
    if ( null === $collapsed ) {
        $collapsed = preg_replace( '/\s+/', ' ', $text );
    }

    return trim( (string) $collapsed );
}

/**
 * Sanitise multi-line plain text such as robots.txt rules or llms.txt content.
 *
 * Unlike sanitize_textarea_field() this keeps percent-encoded paths and angle brackets,
 * and unlike wp_kses_post() it does not turn ">" into "&gt;".
 *
 * @param string $text Raw text, already unslashed.
 * @param int    $max_length Maximum length in bytes.
 * @return string
 */
function seoc_sanitize_plain_text( $text, $max_length = 200000 ) {
    $text = wp_check_invalid_utf8( (string) $text );
    $text = str_replace( array( "\r\n", "\r" ), "\n", $text );
    $clean = preg_replace( '/[^\P{Cc}\n\t]+/u', '', $text );
    $text  = ( null === $clean ) ? $text : $clean;

    $lines = explode( "\n", (string) $text );
    $lines = array_map( 'rtrim', $lines );
    $text  = trim( implode( "\n", $lines ) );

    if ( strlen( $text ) > $max_length ) {
        $text = substr( $text, 0, $max_length );
        $text = wp_check_invalid_utf8( $text );
    }

    return $text;
}

/**
 * JSON-LD encoder that is safe to print inside a script tag.
 *
 * JSON_HEX_TAG turns "<" and ">" into < and >, so a post title containing
 * "</script>" can no longer close the script element.
 *
 * @param mixed $data Data to encode.
 * @return string
 */
function seoc_json_ld( $data ) {
    $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT );

    return is_string( $json ) ? $json : '';
}

/**
 * Print one JSON-LD script block.
 *
 * @param mixed  $data  Data to encode.
 * @param string $label Optional HTML comment placed before the block.
 */
function seoc_print_json_ld( $data, $label = '' ) {
    $json = seoc_json_ld( $data );

    if ( '' === $json ) {
        return;
    }

    if ( '' !== $label ) {
        echo '<!-- ' . esc_html( $label ) . " -->\n";
    }

    echo '<script type="application/ld+json">' . "\n" . $json . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG.
}

/**
 * Slugs of pages that must never be advertised in the sitemap or llms.txt (error pages).
 *
 * @return string[]
 */
function seoc_excluded_slugs() {
    $slugs = apply_filters( 'seoc_excluded_page_slugs', array( 'page-not-found', '404', 'not-found' ) );

    return is_array( $slugs ) ? array_map( 'strval', $slugs ) : array();
}

/**
 * Whether a post should be left out of sitemaps and generated link lists.
 *
 * @param WP_Post|int $post Post object or ID.
 * @return bool
 */
function seoc_is_excluded_post( $post ) {
    $post = get_post( $post );

    if ( ! $post ) {
        return true;
    }

    if ( 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
        return true;
    }

    if ( 'page' === $post->post_type && in_array( $post->post_name, seoc_excluded_slugs(), true ) ) {
        return true;
    }

    $robots = (string) get_post_meta( $post->ID, '_seoc_robots', true );
    if ( false !== strpos( $robots, 'noindex' ) ) {
        return true;
    }

    // A custom canonical that points somewhere else means this URL is not the preferred one.
    $canonical = (string) get_post_meta( $post->ID, '_seoc_canonical', true );
    if ( '' !== $canonical ) {
        $own = get_permalink( $post );
        if ( untrailingslashit( $canonical ) !== untrailingslashit( $own ) ) {
            return true;
        }
    }

    return (bool) apply_filters( 'seoc_exclude_post', false, $post );
}

/**
 * The post that describes the current view: the queried post for singular views,
 * or the static posts page when the blog index is shown on its own page.
 *
 * @return int Post ID or 0.
 */
function seoc_context_post_id() {
    if ( is_singular() ) {
        return (int) get_queried_object_id();
    }

    if ( is_home() && ! is_front_page() ) {
        return (int) get_option( 'page_for_posts' );
    }

    return 0;
}

/**
 * Build a plain text description from a post when no custom one is set.
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function seoc_auto_description( $post ) {
    if ( ! $post ) {
        return '';
    }

    if ( ! empty( $post->post_excerpt ) ) {
        $text = $post->post_excerpt;
    } else {
        $text = $post->post_content;
        $text = preg_replace( '/\[\/?(vc_|et_|fl_|fusion_|elementor|wpb_|cs_|av_|divi_)[^\]]*\]/', '', $text );
        $text = strip_shortcodes( $text );
        $text = preg_replace( '/<!--.*?-->/s', '', $text );
        $text = preg_replace( '/{[^}]*}/', '', $text );
    }

    $text = seoc_clean_text( $text );

    return wp_trim_words( $text, 25, '...' );
}

/**
 * Whether a file physically exists in the WordPress root, which would stop
 * WordPress from ever serving its virtual version of it.
 *
 * @param string $file File name such as robots.txt.
 * @return bool
 */
function seoc_physical_file_exists( $file ) {
    return file_exists( ABSPATH . $file );
}

/**
 * Load the admin script once and give it the values it needs from PHP.
 *
 * The script handles the notice dismissal, the meta box tabs and live preview,
 * the JSON checks and the media picker.
 */
function seoc_enqueue_admin_script() {
    static $done = false;

    if ( $done ) {
        return;
    }
    $done = true;

    wp_enqueue_script( 'seoc-admin', SEOC_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), SEOC_VERSION, true );

    $settings = get_option( 'seoc_settings', array() );
    $post     = get_post();

    $data = array(
        'dismissNonce' => current_user_can( 'manage_options' ) ? wp_create_nonce( 'seoc_dismiss_notice' ) : '',
        'separator'    => isset( $settings['separator'] ) ? (string) $settings['separator'] : '|',
        'siteName'     => get_bloginfo( 'name' ),
        'postTitle'    => $post ? $post->post_title : '',
    );

    wp_add_inline_script(
        'seoc-admin',
        'var seocAdmin = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
        'before'
    );
}
