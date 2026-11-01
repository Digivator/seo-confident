<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin sub-pages: Robots.txt, LLMs.txt, Sitemap.xml
 *
 * Each appears as a submenu under SEO Confident.
 */
class SEOC_Admin_Tabs {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_submenus' ), 20 );
        add_action( 'admin_init', array( $this, 'handle_saves' ) );
    }

    public function add_submenus() {
        add_submenu_page( 'seoc-settings', 'Robots.txt', 'Robots.txt', 'manage_options', 'seoc-robots-txt', array( $this, 'render_robots_page' ) );
        add_submenu_page( 'seoc-settings', 'LLMs.txt', 'LLMs.txt', 'manage_options', 'seoc-llms-txt', array( $this, 'render_llms_page' ) );
        add_submenu_page( 'seoc-settings', 'Sitemap.xml', 'Sitemap.xml', 'manage_options', 'seoc-sitemap-xml', array( $this, 'render_sitemap_page' ) );
    }

    /**
     * Messages shown after a save, keyed by the seoc_msg query argument.
     */
    private function messages() {
        return array(
            'robots_saved'  => __( 'Robots.txt saved.', 'seo-confident' ),
            'robots_filled' => __( 'Robots.txt filled with the recommended directives. Review them, then save if you want to keep them.', 'seo-confident' ),
            'llms_saved'    => __( 'LLMs.txt saved.', 'seo-confident' ),
            'llms_filled'   => __( 'LLMs.txt filled with recommended content. Review it, then save if you want to keep it.', 'seo-confident' ),
        );
    }

    private function show_message() {
        $key      = isset( $_GET['seoc_msg'] ) ? sanitize_key( wp_unslash( $_GET['seoc_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- picks a message to show and changes nothing.
        $messages = $this->messages();

        if ( isset( $messages[ $key ] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
        }
    }

    /**
     * Warn when a real file on disk stops WordPress from serving the virtual one.
     */
    private function physical_file_warning( $file ) {
        if ( ! seoc_physical_file_exists( $file ) ) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
            esc_html( $file ),
            esc_html__( 'exists as a real file in your WordPress folder, so your web server serves that file and ignores what you save here. Delete or rename the file to use this screen.', 'seo-confident' )
        );
    }

    private function redirect_back( $page, $message ) {
        wp_safe_redirect( add_query_arg( 'seoc_msg', $message, admin_url( 'admin.php?page=' . $page ) ) );
        exit;
    }

    public function handle_saves() {
        if ( empty( $_POST ) ) {
            return;
        }

        $is_robots = isset( $_POST['seoc_save_robots'] ) || isset( $_POST['seoc_fill_robots'] );
        $is_llms   = isset( $_POST['seoc_save_llms'] ) || isset( $_POST['seoc_fill_llms'] );

        if ( ! $is_robots && ! $is_llms ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'seo-confident' ), '', array( 'response' => 403 ) );
        }

        if ( $is_robots ) {
            check_admin_referer( 'seoc_robots_save' );

            $settings = get_option( 'seoc_settings', array() );
            $settings = is_array( $settings ) ? $settings : array();

            if ( isset( $_POST['seoc_fill_robots'] ) ) {
                $settings['robots_extra'] = self::generate_recommended_robots();
                $message                  = 'robots_filled';
            } else {
                $settings['robots_extra'] = isset( $_POST['seoc_robots_content'] ) ? wp_unslash( $_POST['seoc_robots_content'] ) : '';
                $message                  = 'robots_saved';
            }

            // The settings sanitiser cleans the text and leaves every other setting as it is.
            update_option( 'seoc_settings', $settings );
            $this->redirect_back( 'seoc-robots-txt', $message );
        }

        if ( $is_llms ) {
            check_admin_referer( 'seoc_llms_save' );

            if ( isset( $_POST['seoc_fill_llms'] ) ) {
                $content = SEOC_LLMS::generate_recommended();
                $message = 'llms_filled';
            } else {
                $content = isset( $_POST['seoc_llms_content'] ) ? wp_unslash( $_POST['seoc_llms_content'] ) : '';
                $message = 'llms_saved';
            }

            // llms.txt is served as plain text. wp_kses_post() would turn ">" into "&gt;".
            update_option( 'seoc_llms_txt', seoc_sanitize_plain_text( $content ) );
            $this->redirect_back( 'seoc-llms-txt', $message );
        }
    }

    // =====================================================================
    // ROBOTS.TXT PAGE
    // =====================================================================
    public function render_robots_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-confident' ) );
        }

        $settings = get_option( 'seoc_settings', array() );
        $content  = $settings['robots_extra'] ?? '';
        $live_url = home_url( '/robots.txt' );
        ?>
        <div class="wrap seoc-wrap">
            <h1>Robots.txt</h1>
            <p class="seoc-subtitle">WordPress generates a virtual robots.txt. Your custom directives are added below the defaults and the sitemap line is added at the end.</p>
            <p><a href="<?php echo esc_url( $live_url ); ?>" target="_blank" rel="noopener">View live robots.txt</a></p>

            <?php
            $this->show_message();
            $this->physical_file_warning( 'robots.txt' );
            ?>

            <div class="seoc-card">
                <h2>Live preview (read-only)</h2>
                <p class="description">This is what WordPress will serve right now, including your saved directives.</p>
                <textarea rows="12" class="large-text code" readonly><?php echo esc_textarea( SEOC_Robots::preview() ); ?></textarea>
            </div>

            <form method="post">
                <?php wp_nonce_field( 'seoc_robots_save' ); ?>
                <div class="seoc-card">
                    <h2>Custom Directives</h2>
                    <textarea name="seoc_robots_content" rows="12" class="large-text code" placeholder="# Add custom directives here, one per line&#10;# e.g. Disallow: /private/&#10;# e.g. User-agent: GPTBot&#10;# Disallow: /"><?php echo esc_textarea( $content ); ?></textarea>
                    <p class="description">These are added after the default directives. One directive per line. Do not add a Sitemap line, it is added for you.</p>
                    <p>
                        <?php submit_button( 'Save Robots.txt', 'primary', 'seoc_save_robots', false ); ?>
                        <?php submit_button( 'Fill Recommended', 'secondary', 'seoc_fill_robots', false ); ?>
                    </p>
                </div>
            </form>
        </div>
        <?php
    }

    // =====================================================================
    // LLMS.TXT PAGE
    // =====================================================================
    public function render_llms_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-confident' ) );
        }

        $content  = get_option( 'seoc_llms_txt', '' );
        $live_url = home_url( '/llms.txt' );
        ?>
        <div class="wrap seoc-wrap">
            <h1>LLMs.txt</h1>
            <p class="seoc-subtitle">Provide structured information about your site for AI language models and crawlers.</p>
            <p><a href="<?php echo esc_url( $live_url ); ?>" target="_blank" rel="noopener">View live llms.txt</a></p>

            <?php
            $this->show_message();
            $this->physical_file_warning( 'llms.txt' );
            ?>

            <form method="post">
                <?php wp_nonce_field( 'seoc_llms_save' ); ?>
                <div class="seoc-card">
                    <h2>LLMs.txt Content</h2>
                    <textarea name="seoc_llms_content" rows="20" class="large-text code" placeholder="# Site Name&#10;&#10;> Site description&#10;&#10;## About&#10;&#10;Your site info here..."><?php echo esc_textarea( $content ); ?></textarea>
                    <p class="description">Markdown format. Only the <code># Title</code> line is required. Use headings (##), lists (-), and links ([text](url)). It is served as plain text exactly as written here. Leave it empty to serve a file generated automatically from your pages and posts.</p>
                    <p>
                        <?php submit_button( 'Save LLMs.txt', 'primary', 'seoc_save_llms', false ); ?>
                        <?php submit_button( 'Fill Recommended', 'secondary', 'seoc_fill_llms', false ); ?>
                    </p>
                </div>
            </form>
        </div>
        <?php
    }

    // =====================================================================
    // SITEMAP.XML PAGE (read-only view)
    // =====================================================================
    public function render_sitemap_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-confident' ) );
        }

        $index_url = home_url( '/sitemap.xml' );
        $summary   = SEOC_Sitemap::instance()->get_summary();
        ?>
        <div class="wrap seoc-wrap">
            <h1>Sitemap.xml</h1>
            <p class="seoc-subtitle">Auto-generated XML sitemaps. New content is added automatically when published.</p>

            <?php $this->physical_file_warning( 'sitemap.xml' ); ?>

            <div class="seoc-card">
                <h2>Sitemap Index</h2>
                <p><a href="<?php echo esc_url( $index_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $index_url ); ?></a></p>
                <table class="widefat striped" style="max-width:600px;">
                    <thead>
                        <tr><th>Sitemap</th><th>Entries</th><th>Link</th></tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $summary ) ) : ?>
                            <tr><td colspan="3">Nothing to list yet. Publish some content first.</td></tr>
                        <?php endif; ?>
                        <?php foreach ( $summary as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row['label'] ); ?></td>
                                <td><?php echo intval( $row['count'] ); ?></td>
                                <td><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['file'] ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="seoc-card">
                <h2>How It Works</h2>
                <p>The XML sitemap is generated dynamically from your published content. You do not need to edit it manually.</p>
                <ul style="list-style:disc;padding-left:20px;">
                    <li>New posts, pages and public custom post types are included automatically when published.</li>
                    <li>Content marked as <code>noindex</code>, password protected, or given a different canonical URL is left out.</li>
                    <li>Error pages (<?php echo esc_html( implode( ', ', seoc_excluded_slugs() ) ); ?>) are left out.</li>
                    <li>The sitemap URL is added to your robots.txt automatically. Submit it in Google Search Console and Bing Webmaster Tools once.</li>
                    <li>Search engines are not pinged. Google and Bing both retired their sitemap ping endpoints.</li>
                </ul>
            </div>
        </div>
        <?php
    }

    // =====================================================================
    // GENERATE RECOMMENDED ROBOTS.TXT
    // =====================================================================
    private static function generate_recommended_robots() {
        $txt  = "# Keep crawlers out of internal search results\n";
        $txt .= "User-agent: *\n";
        $txt .= "Disallow: /?s=\n";
        $txt .= "Disallow: /*?s=\n";
        $txt .= "Disallow: /search/\n\n";
        $txt .= "# AI crawlers are allowed by default. To opt out of one, remove the # from its lines.\n";
        $txt .= "# User-agent: GPTBot\n";
        $txt .= "# Disallow: /\n";
        $txt .= "#\n";
        $txt .= "# User-agent: CCBot\n";
        $txt .= "# Disallow: /\n";
        $txt .= "#\n";
        $txt .= "# User-agent: Google-Extended\n";
        $txt .= "# Disallow: /\n";

        return $txt;
    }
}
