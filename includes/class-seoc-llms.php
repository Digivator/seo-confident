<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LLMs.txt support.
 *
 * Serves /llms.txt for AI crawlers and assistants. The content is edited on the
 * LLMs.txt admin screen. When nothing has been saved, a file is generated from the
 * site's own pages and posts.
 */
class SEOC_LLMS {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Answer before WordPress decides the URL is a 404.
        add_action( 'parse_request', array( $this, 'serve_llms_txt' ), 0 );
    }

    public function serve_llms_txt() {
        if ( 'llms.txt' !== seoc_request_path() ) {
            return;
        }

        $content = (string) get_option( 'seoc_llms_txt', '' );

        if ( '' === trim( $content ) ) {
            $content = self::generate_recommended();
        }

        $content = (string) apply_filters( 'seoc_llms_txt_content', $content );

        while ( ob_get_level() ) {
            ob_end_clean();
        }

        status_header( 200 );
        header( 'Content-Type: text/plain; charset=UTF-8' );
        header( 'X-Content-Type-Options: nosniff' );

        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' !== $_SERVER['REQUEST_METHOD'] ) {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text response with nosniff.
        }

        exit;
    }

    /**
     * One markdown list item: - [Title](url): optional note
     */
    private static function link_line( $title, $url, $note = '' ) {
        $title = str_replace( array( '[', ']' ), array( '(', ')' ), $title );
        $url   = str_replace( array( '(', ')', ' ' ), array( '%28', '%29', '%20' ), $url );
        $line  = '- [' . $title . '](' . $url . ')';

        if ( '' !== $note ) {
            $line .= ': ' . $note;
        }

        return $line . "\n";
    }

    /**
     * A page's own meta description as a one-line note, if it has one.
     */
    private static function note_for( $post ) {
        $note = seoc_clean_text( (string) get_post_meta( $post->ID, '_seoc_description', true ) );

        if ( mb_strlen( $note ) > 200 ) {
            $note = rtrim( mb_substr( $note, 0, 197 ) ) . '...';
        }

        return $note;
    }

    /**
     * Generate llms.txt from the site's data, following the llmstxt.org layout:
     * an H1 title, a short blockquote summary, then H2 sections of link lists.
     * The final "Optional" section is the one the format reserves for secondary links.
     *
     * @return string
     */
    public static function generate_recommended() {
        $settings = get_option( 'seoc_settings', array() );
        $url      = home_url( '/' );

        $site_name = seoc_clean_text( get_bloginfo( 'name' ) );
        $org_name  = ! empty( $settings['schema_org_name'] ) ? seoc_clean_text( $settings['schema_org_name'] ) : $site_name;

        $desc = ! empty( $settings['home_description'] ) ? $settings['home_description'] : get_bloginfo( 'description' );
        $desc = seoc_clean_text( $desc );

        $txt = '# ' . $org_name . "\n\n";

        if ( '' !== $desc ) {
            $txt .= '> ' . $desc . "\n\n";
        }

        // About.
        $txt .= "## About\n\n";
        if ( '' !== $desc ) {
            $about = ( '' !== $org_name && false === stripos( $desc, $org_name ) ) ? $org_name . ' - ' . $desc : $desc;
            $txt  .= $about . "\n\n";
        }
        $txt .= self::link_line( 'Website', $url );
        $txt .= "\n";

        // Pages.
        $pages = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'menu_order, post_title' ) );
        $lines = '';
        foreach ( $pages as $page ) {
            if ( seoc_is_excluded_post( $page ) ) {
                continue;
            }
            $lines .= self::link_line( seoc_clean_text( get_the_title( $page ) ), get_permalink( $page ), self::note_for( $page ) );
        }
        if ( '' !== $lines ) {
            $txt .= "## Pages\n\n" . $lines . "\n";
        }

        // Latest blog posts.
        $posts = get_posts(
            array(
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 30,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );
        $lines = '';
        foreach ( $posts as $post ) {
            if ( seoc_is_excluded_post( $post ) ) {
                continue;
            }
            $lines .= self::link_line( seoc_clean_text( get_the_title( $post ) ), get_permalink( $post ), self::note_for( $post ) );
        }
        if ( '' !== $lines ) {
            $txt .= "## Blog Posts\n\n" . $lines . "\n";
        }

        // Secondary links.
        $txt .= "## Optional\n\n";
        $txt .= self::link_line( 'Sitemap', home_url( '/sitemap.xml' ) );

        $feed_url = get_bloginfo( 'rss2_url' );
        if ( ! empty( $feed_url ) ) {
            $txt .= self::link_line( 'RSS Feed', $feed_url );
        }

        return $txt;
    }
}
