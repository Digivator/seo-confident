<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple redirects manager.
 *
 * Stores redirects as an option (list of from, to and code) and intercepts
 * matching requests on template_redirect.
 */
class SEOC_Redirects {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'template_redirect', array( $this, 'do_redirects' ), 1 );

        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'add_submenu' ) );
            add_action( 'admin_init', array( $this, 'handle_form' ) );
        }
    }

    /**
     * Reduce a "from" value to a comparable path.
     *
     * Accepts a path (old-page, /old-page/) or a full URL on this site
     * (https://example.com/old-page/). A query string or fragment is ignored.
     *
     * @param string $from Value entered by the user or stored earlier.
     * @return string Lower-case decoded path with no leading or trailing slash.
     */
    public static function normalise_from( $from ) {
        $from = trim( (string) $from );

        if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $from ) ) {
            $from = (string) wp_parse_url( $from, PHP_URL_PATH );
        } else {
            $from = preg_replace( '/[?#].*$/', '', $from );
        }

        $from = seoc_strip_home_path( $from );

        return strtolower( rawurldecode( $from ) );
    }

    /**
     * Check incoming request against stored redirects.
     */
    public function do_redirects() {
        if ( is_admin() ) {
            return;
        }

        $redirects = get_option( 'seoc_redirects', array() );
        if ( empty( $redirects ) || ! is_array( $redirects ) ) {
            return;
        }

        $request_path = strtolower( rawurldecode( seoc_request_path() ) );
        $home_host    = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

        foreach ( $redirects as $redirect ) {
            if ( empty( $redirect['from'] ) || empty( $redirect['to'] ) ) {
                continue;
            }

            if ( self::normalise_from( $redirect['from'] ) !== $request_path ) {
                continue;
            }

            // Never send a page to itself.
            $to_host = wp_parse_url( $redirect['to'], PHP_URL_HOST );
            if ( ( empty( $to_host ) || 0 === strcasecmp( (string) $to_host, (string) $home_host ) )
                && self::normalise_from( (string) wp_parse_url( $redirect['to'], PHP_URL_PATH ) ) === $request_path ) {
                continue;
            }

            $code = isset( $redirect['code'] ) ? (int) $redirect['code'] : 301;
            if ( ! in_array( $code, array( 301, 302, 307 ), true ) ) {
                $code = 301;
            }

            wp_redirect( $redirect['to'], $code, 'SEO Confident' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- redirects to other sites are the point of this feature.
            exit;
        }
    }

    /**
     * Add Redirects submenu under SEO Confident.
     */
    public function add_submenu() {
        add_submenu_page(
            'seoc-settings',
            'Redirects',
            'Redirects',
            'manage_options',
            'seoc-redirects',
            array( $this, 'render_page' )
        );
    }

    /**
     * Handle add/delete form submissions.
     */
    public function handle_form() {
        if ( ! isset( $_POST['seoc_redirect_action'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'seo-confident' ), '', array( 'response' => 403 ) );
        }

        check_admin_referer( 'seoc_redirects_nonce', 'seoc_redirects_nonce' );

        $redirects = get_option( 'seoc_redirects', array() );
        $redirects = is_array( $redirects ) ? $redirects : array();
        $action    = sanitize_key( wp_unslash( $_POST['seoc_redirect_action'] ) );
        $status    = 'added';

        // Add new redirect.
        if ( 'add' === $action ) {
            $from = self::normalise_from( sanitize_text_field( wp_unslash( $_POST['seoc_redirect_from'] ?? '' ) ) );
            $to   = esc_url_raw( trim( wp_unslash( $_POST['seoc_redirect_to'] ?? '' ) ) );
            $code = (int) ( $_POST['seoc_redirect_code'] ?? 301 );
            $code = in_array( $code, array( 301, 302, 307 ), true ) ? $code : 301;

            if ( '' === $from || '' === $to ) {
                $status = 'invalid';
            } elseif ( self::normalise_from( (string) wp_parse_url( $to, PHP_URL_PATH ) ) === $from
                && ( ! wp_parse_url( $to, PHP_URL_HOST ) || 0 === strcasecmp( (string) wp_parse_url( $to, PHP_URL_HOST ), (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) ) {
                $status = 'loop';
            } else {
                $found = false;
                foreach ( $redirects as $i => $existing ) {
                    if ( isset( $existing['from'] ) && self::normalise_from( $existing['from'] ) === $from ) {
                        $redirects[ $i ] = array( 'from' => $from, 'to' => $to, 'code' => $code );
                        $found           = true;
                        $status          = 'updated';
                        break;
                    }
                }
                if ( ! $found ) {
                    $redirects[] = array( 'from' => $from, 'to' => $to, 'code' => $code );
                }
                update_option( 'seoc_redirects', array_values( $redirects ) );
            }
        }

        // Delete redirect.
        if ( 'delete' === $action ) {
            $index = (int) ( $_POST['seoc_redirect_index'] ?? -1 );
            if ( isset( $redirects[ $index ] ) ) {
                array_splice( $redirects, $index, 1 );
                update_option( 'seoc_redirects', array_values( $redirects ) );
            }
            $status = 'deleted';
        }

        wp_safe_redirect( admin_url( 'admin.php?page=seoc-redirects&seoc_status=' . $status ) );
        exit;
    }

    /**
     * Render the redirects admin page.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-confident' ) );
        }

        $redirects = get_option( 'seoc_redirects', array() );
        $status    = isset( $_GET['seoc_status'] ) ? sanitize_key( wp_unslash( $_GET['seoc_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- picks a message to show and changes nothing.

        $notices = array(
            'added'   => array( 'success', 'Redirect added.' ),
            'updated' => array( 'success', 'A redirect for that path already existed, so it was updated.' ),
            'deleted' => array( 'success', 'Redirect removed.' ),
            'invalid' => array( 'error', 'Both a From path and a To URL are needed.' ),
            'loop'    => array( 'error', 'That redirect would send the page to itself, so it was not saved.' ),
        );
        ?>
        <div class="wrap seoc-wrap">
            <h1>SEO Confident &mdash; Redirects</h1>
            <p class="seoc-subtitle">Manage 301/302 redirects. Paths are relative to your site root. A full URL on this site also works in the From box.</p>

            <?php if ( isset( $notices[ $status ] ) ) : ?>
                <div class="notice notice-<?php echo esc_attr( $notices[ $status ][0] ); ?> is-dismissible"><p><?php echo esc_html( $notices[ $status ][1] ); ?></p></div>
            <?php endif; ?>

            <!-- Add new redirect -->
            <div class="seoc-card">
                <h2>Add Redirect</h2>
                <form method="post">
                    <?php wp_nonce_field( 'seoc_redirects_nonce', 'seoc_redirects_nonce' ); ?>
                    <input type="hidden" name="seoc_redirect_action" value="add" />

                    <table class="form-table">
                        <tr>
                            <th><label for="seoc_redirect_from">From (path)</label></th>
                            <td>
                                <input type="text" id="seoc_redirect_from" name="seoc_redirect_from"
                                    class="regular-text" placeholder="old-page" />
                                <p class="description">Relative path, e.g. <code>blog/old-post</code>. No leading slash needed. Matching ignores letter case and query strings.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="seoc_redirect_to">To (URL)</label></th>
                            <td>
                                <input type="text" id="seoc_redirect_to" name="seoc_redirect_to"
                                    class="large-text" placeholder="https://www.example.com/new-page/" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="seoc_redirect_code">Type</label></th>
                            <td>
                                <select id="seoc_redirect_code" name="seoc_redirect_code">
                                    <option value="301" selected>301 (Permanent)</option>
                                    <option value="302">302 (Temporary)</option>
                                    <option value="307">307 (Temporary, preserve method)</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button( 'Add Redirect', 'primary', 'submit', true ); ?>
                </form>
            </div>

            <!-- Existing redirects -->
            <div class="seoc-card">
                <h2>Active Redirects</h2>
                <?php if ( empty( $redirects ) || ! is_array( $redirects ) ) : ?>
                    <p>No redirects configured.</p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>To</th>
                                <th>Type</th>
                                <th style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $redirects as $i => $r ) : ?>
                                <tr>
                                    <td><code>/<?php echo esc_html( self::normalise_from( $r['from'] ?? '' ) ); ?></code></td>
                                    <td><a href="<?php echo esc_url( $r['to'] ?? '' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['to'] ?? '' ); ?></a></td>
                                    <td><?php echo esc_html( $r['code'] ?? 301 ); ?></td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field( 'seoc_redirects_nonce', 'seoc_redirects_nonce' ); ?>
                                            <input type="hidden" name="seoc_redirect_action" value="delete" />
                                            <input type="hidden" name="seoc_redirect_index" value="<?php echo intval( $i ); ?>" />
                                            <button type="submit" class="button button-link-delete"
                                                onclick="return confirm('Remove this redirect?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
