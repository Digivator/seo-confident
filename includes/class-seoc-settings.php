<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SEOC_Settings {

    private static $instance = null;

    /**
     * Organisation types offered in the schema settings.
     */
    const SCHEMA_TYPES = array( 'Organization', 'LocalBusiness', 'ProfessionalService', 'Person' );

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_filter( 'admin_footer_text', array( $this, 'footer_credit' ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'SEO Confident', 'seo-confident' ),
            __( 'SEO Confident', 'seo-confident' ),
            'manage_options',
            'seoc-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-search',
            80
        );
    }

    public function register_settings() {
        register_setting(
            'seoc_settings_group',
            'seoc_settings',
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'type'              => 'object',
                'description'       => __( 'SEO Confident plugin settings', 'seo-confident' ),
            )
        );
    }

    /**
     * Value for a key from the submitted data, falling back to what is already stored.
     * Returns null when neither has it.
     */
    private function pick( $key, $input, $existing, $callback ) {
        if ( array_key_exists( $key, $input ) ) {
            $value = is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
            return call_user_func( $callback, $value );
        }

        if ( array_key_exists( $key, $existing ) ) {
            return $existing[ $key ];
        }

        return null;
    }

    /**
     * Sanitise the settings array.
     *
     * This runs on every update_option( 'seoc_settings' ), whether the data came from the
     * settings form, the import screen or code, so it only changes keys that are present in
     * the data it is given and keeps everything else as stored. Running it twice on the same
     * data gives the same result.
     *
     * The settings form adds a "_form" marker. Browsers leave unticked checkboxes out of a
     * submission, so with the marker present a missing checkbox means "off".
     *
     * Note: options.php has already removed the slashes WordPress adds to form data, so
     * values must not be unslashed again here.
     *
     * @param mixed $input Submitted settings.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $input    = is_array( $input ) ? $input : array();
        $existing = get_option( 'seoc_settings', array() );
        $existing = is_array( $existing ) ? $existing : array();
        $is_form  = array_key_exists( '_form', $input );
        $out      = array();

        $set = function ( $key, $callback ) use ( &$out, $input, $existing ) {
            $value = $this->pick( $key, $input, $existing, $callback );
            if ( null !== $value ) {
                $out[ $key ] = $value;
            }
        };

        // Plain text fields.
        $text_fields = array(
            'home_title', 'home_description', 'home_keywords',
            'schema_org_name', 'schema_org_telephone', 'schema_org_area_served',
            'schema_org_street_address', 'schema_org_address_locality',
            'schema_org_address_region', 'schema_org_postal_code',
        );
        foreach ( $text_fields as $field ) {
            $set( $field, 'sanitize_text_field' );
        }

        // Values restricted to a fixed list.
        $choices = array(
            'home_title_mode' => array( 'append_site_name', 'full' ),
            'twitter_card'    => array( 'summary', 'summary_large_image' ),
            'schema_org_type' => self::SCHEMA_TYPES,
            'separator'       => array( '|', '-', "\xE2\x80\x93", "\xE2\x80\x94", "\xC2\xB7", "\xC2\xBB", '/' ),
        );
        foreach ( $choices as $field => $allowed ) {
            $set(
                $field,
                function ( $value ) use ( $allowed ) {
                    $value = sanitize_text_field( $value );
                    return in_array( $value, $allowed, true ) ? $value : $allowed[0];
                }
            );
        }

        // URLs and email.
        $set( 'og_default_image', 'esc_url_raw' );
        $set( 'schema_org_logo', 'esc_url_raw' );
        $set( 'schema_org_email', 'sanitize_email' );

        // Numbers.
        foreach ( array( 'schema_org_logo_width', 'schema_org_logo_height' ) as $field ) {
            $set(
                $field,
                function ( $value ) {
                    $number = absint( $value );
                    return $number > 0 ? (string) $number : '';
                }
            );
        }
        foreach ( array( 'robots_max_snippet', 'robots_max_video_preview' ) as $field ) {
            $set(
                $field,
                function ( $value ) {
                    return is_numeric( $value ) ? (string) max( -1, (int) $value ) : '-1';
                }
            );
        }

        // Country code and language.
        $set(
            'schema_org_address_country',
            function ( $value ) {
                $value = sanitize_text_field( $value );
                return preg_match( '/^[A-Za-z]{2}$/', $value ) ? strtoupper( $value ) : $value;
            }
        );
        $set(
            'tech_lang',
            function ( $value ) {
                $value = sanitize_text_field( $value );
                return preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $value ) ? $value : '';
            }
        );

        // Social profiles: one URL per line, commas also accepted.
        $set(
            'schema_org_sameas',
            function ( $value ) {
                $urls = array();
                foreach ( preg_split( '/[\r\n,]+/', $value ) as $url ) {
                    $url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
                    if ( '' !== $url ) {
                        $urls[ $url ] = $url;
                    }
                }
                return implode( "\n", array_values( $urls ) );
            }
        );

        // Robots.txt directives (plain text, written by the Robots.txt screen).
        $set(
            'robots_extra',
            function ( $value ) {
                return seoc_sanitize_plain_text( $value, 50000 );
            }
        );

        // Checkboxes.
        $checkboxes = array(
            'breadcrumbs', 'robots_index', 'robots_follow',
            'tech_security_headers', 'tech_preconnect',
            'tech_fix_tel', 'tech_strip_media_link', 'tech_image_alt',
        );
        foreach ( $checkboxes as $checkbox ) {
            if ( array_key_exists( $checkbox, $input ) ) {
                $out[ $checkbox ] = ( '1' === (string) $input[ $checkbox ] || true === $input[ $checkbox ] ) ? '1' : '0';
            } elseif ( $is_form ) {
                $out[ $checkbox ] = '0';
            } elseif ( array_key_exists( $checkbox, $existing ) ) {
                $out[ $checkbox ] = $existing[ $checkbox ];
            }
        }

        // Custom wide schema: it has to be valid JSON, otherwise the last good value is kept.
        if ( array_key_exists( 'schema_custom_wide', $input ) ) {
            $schema = trim( is_scalar( $input['schema_custom_wide'] ) ? (string) $input['schema_custom_wide'] : '' );

            if ( '' === $schema ) {
                $out['schema_custom_wide'] = '';
            } else {
                $decoded = json_decode( $schema, true );
                if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                    $out['schema_custom_wide'] = $schema;
                } else {
                    $out['schema_custom_wide'] = isset( $existing['schema_custom_wide'] ) ? $existing['schema_custom_wide'] : '';
                    add_settings_error(
                        'seoc_settings',
                        'seoc_schema_invalid',
                        __( 'The Custom Wide Schema is not valid JSON, so the previous schema was kept. Your other settings were saved.', 'seo-confident' ),
                        'warning'
                    );
                }
            }
        } elseif ( array_key_exists( 'schema_custom_wide', $existing ) ) {
            $out['schema_custom_wide'] = $existing['schema_custom_wide'];
        }

        return $out;
    }

    /**
     * Add a small credit line to the footer of this plugin's own admin screens.
     *
     * Every other screen keeps the footer text it already has, and nothing is added
     * to the front end of the site.
     *
     * @param string $text Current footer text.
     * @return string
     */
    public function footer_credit( $text ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || false === strpos( (string) $screen->id, 'seoc' ) ) {
            return $text;
        }

        $link = '<a href="' . esc_url( 'https://digivator.co.uk' ) . '" target="_blank" rel="noopener noreferrer">Digivator.co.uk</a>';

        /* translators: %s: link to the developer's website */
        $credit = sprintf( esc_html__( 'Made by %s', 'seo-confident' ), $link );

        return '' === (string) $text ? $credit : $text . ' | ' . $credit;
    }

    public function enqueue_admin_assets( $hook ) {
        if ( false === strpos( $hook, 'seoc' ) && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'seoc-admin', SEOC_PLUGIN_URL . 'assets/admin.css', array(), SEOC_VERSION );
        wp_enqueue_media();
        seoc_enqueue_admin_script();
    }

    /**
     * One text input row of the schema table.
     */
    private function schema_row( $settings, $key, $label, $args = array() ) {
        $args  = wp_parse_args(
            $args,
            array(
                'type'        => 'text',
                'placeholder' => '',
                'description' => '',
                'default'     => '',
            )
        );
        $id    = 'seoc_schema_' . $key;
        $value = isset( $settings[ 'schema_org_' . $key ] ) ? $settings[ 'schema_org_' . $key ] : $args['default'];
        ?>
        <tr>
            <th><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <input type="<?php echo esc_attr( $args['type'] ); ?>" id="<?php echo esc_attr( $id ); ?>" name="seoc_settings[schema_org_<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="large-text" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>" />
                <?php if ( '' !== $args['description'] ) : ?>
                    <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public function render_settings_page() {
        // Check user capabilities.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-confident' ) );
        }

        $settings = get_option( 'seoc_settings', array() );
        ?>
        <div class="wrap seoc-wrap">
            <h1><?php echo esc_html__( 'SEO Confident Settings', 'seo-confident' ); ?></h1>
            <p class="seoc-subtitle"><?php echo esc_html__( 'Lightweight SEO for WordPress.', 'seo-confident' ); ?></p>

            <?php
            settings_errors();

            // Show import success message.
            if ( isset( $_GET['seoc_import_success'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether to show a success message.
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html__( 'Settings imported successfully!', 'seo-confident' ); ?></p>
                </div>
                <?php
            }
            ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'seoc_settings_group' ); ?>
                <input type="hidden" name="seoc_settings[_form]" value="1" />

                <div class="seoc-card">
                    <h2><?php echo esc_html__( 'Homepage Meta Tags', 'seo-confident' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="seoc_home_title"><?php echo esc_html__( 'Title', 'seo-confident' ); ?></label></th>
                            <td><input type="text" id="seoc_home_title" name="seoc_settings[home_title]" value="<?php echo esc_attr( $settings['home_title'] ?? '' ); ?>" class="large-text" /></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Homepage Title Mode', 'seo-confident' ); ?></th>
                            <td>
                                <label><input type="radio" name="seoc_settings[home_title_mode]" value="append_site_name" <?php checked( $settings['home_title_mode'] ?? 'append_site_name', 'append_site_name' ); ?> /> <?php echo esc_html__( 'Append site name automatically', 'seo-confident' ); ?></label><br />
                                <label><input type="radio" name="seoc_settings[home_title_mode]" value="full" <?php checked( $settings['home_title_mode'] ?? 'append_site_name', 'full' ); ?> /> <?php echo esc_html__( 'Use the title exactly as entered', 'seo-confident' ); ?></label>
                                <p class="description"><?php echo esc_html__( 'Use the first option to let SEO Confident append the site name. Use the second option if your title already includes the site name.', 'seo-confident' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="seoc_home_desc"><?php echo esc_html__( 'Meta Description', 'seo-confident' ); ?></label></th>
                            <td><textarea id="seoc_home_desc" name="seoc_settings[home_description]" rows="3" class="large-text"><?php echo esc_textarea( $settings['home_description'] ?? '' ); ?></textarea>
                            <p class="description"><?php echo esc_html__( 'Recommended: 150-160 characters.', 'seo-confident' ); ?></p></td>
                        </tr>
                        <tr>
                            <th><label for="seoc_home_keywords"><?php echo esc_html__( 'Keywords', 'seo-confident' ); ?></label></th>
                            <td><input type="text" id="seoc_home_keywords" name="seoc_settings[home_keywords]" value="<?php echo esc_attr( $settings['home_keywords'] ?? '' ); ?>" class="large-text" /></td>
                        </tr>
                    </table>
                </div>

                <div class="seoc-card">
                    <h2><?php echo esc_html__( 'Title Format', 'seo-confident' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="seoc_separator"><?php echo esc_html__( 'Title Separator', 'seo-confident' ); ?></label></th>
                            <td>
                                <select id="seoc_separator" name="seoc_settings[separator]">
                                    <?php foreach ( array( '|', '-', "\xE2\x80\x93", "\xE2\x80\x94", "\xC2\xB7", "\xC2\xBB", '/' ) as $sep ) : ?>
                                        <option value="<?php echo esc_attr( $sep ); ?>" <?php selected( $settings['separator'] ?? '|', $sep ); ?>><?php echo esc_html( $sep ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="seoc-card">
                    <h2><?php echo esc_html__( 'Social / Open Graph', 'seo-confident' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="seoc_og_image"><?php echo esc_html__( 'Default OG Image URL', 'seo-confident' ); ?></label></th>
                            <td>
                                <input type="text" id="seoc_og_image" name="seoc_settings[og_default_image]" value="<?php echo esc_attr( $settings['og_default_image'] ?? '' ); ?>" class="large-text" />
                                <button type="button" class="button seoc-upload-btn" data-target="seoc_og_image"><?php echo esc_html__( 'Select Image', 'seo-confident' ); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="seoc_twitter_card"><?php echo esc_html__( 'Twitter Card Type', 'seo-confident' ); ?></label></th>
                            <td>
                                <select id="seoc_twitter_card" name="seoc_settings[twitter_card]">
                                    <option value="summary" <?php selected( $settings['twitter_card'] ?? '', 'summary' ); ?>><?php echo esc_html__( 'Summary', 'seo-confident' ); ?></option>
                                    <option value="summary_large_image" <?php selected( $settings['twitter_card'] ?? '', 'summary_large_image' ); ?>><?php echo esc_html__( 'Summary with Large Image', 'seo-confident' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="seoc-card">
                    <h2><?php echo esc_html__( 'Site-wide Schema', 'seo-confident' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="seoc_schema_type"><?php echo esc_html__( 'Organisation Type', 'seo-confident' ); ?></label></th>
                            <td>
                                <select id="seoc_schema_type" name="seoc_settings[schema_org_type]">
                                    <option value="Organization" <?php selected( $settings['schema_org_type'] ?? '', 'Organization' ); ?>><?php echo esc_html__( 'Organisation', 'seo-confident' ); ?></option>
                                    <option value="LocalBusiness" <?php selected( $settings['schema_org_type'] ?? '', 'LocalBusiness' ); ?>><?php echo esc_html__( 'Local Business', 'seo-confident' ); ?></option>
                                    <option value="ProfessionalService" <?php selected( $settings['schema_org_type'] ?? '', 'ProfessionalService' ); ?>><?php echo esc_html__( 'Professional Service', 'seo-confident' ); ?></option>
                                    <option value="Person" <?php selected( $settings['schema_org_type'] ?? '', 'Person' ); ?>><?php echo esc_html__( 'Person', 'seo-confident' ); ?></option>
                                </select>
                                <p class="description"><?php echo esc_html__( 'The contact details below are output for every type.', 'seo-confident' ); ?></p>
                            </td>
                        </tr>
                        <?php
                        $this->schema_row( $settings, 'name', __( 'Name', 'seo-confident' ) );
                        ?>
                        <tr>
                            <th><label for="seoc_schema_logo"><?php echo esc_html__( 'Logo URL', 'seo-confident' ); ?></label></th>
                            <td>
                                <input type="text" id="seoc_schema_logo" name="seoc_settings[schema_org_logo]" value="<?php echo esc_attr( $settings['schema_org_logo'] ?? '' ); ?>" class="large-text" />
                                <button type="button" class="button seoc-upload-btn" data-target="seoc_schema_logo"><?php echo esc_html__( 'Select Image', 'seo-confident' ); ?></button>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="seoc_schema_logo_width"><?php echo esc_html__( 'Logo Dimensions', 'seo-confident' ); ?></label></th>
                            <td>
                                <input type="number" id="seoc_schema_logo_width" name="seoc_settings[schema_org_logo_width]" value="<?php echo esc_attr( $settings['schema_org_logo_width'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Width (px)', 'seo-confident' ); ?>" class="small-text" style="width: 120px;" />
                                <span style="margin: 0 8px;">&times;</span>
                                <input type="number" id="seoc_schema_logo_height" name="seoc_settings[schema_org_logo_height]" value="<?php echo esc_attr( $settings['schema_org_logo_height'] ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Height (px)', 'seo-confident' ); ?>" class="small-text" style="width: 120px;" />
                                <p class="description"><?php echo esc_html__( 'Logo width and height in pixels. Optional for schema validation.', 'seo-confident' ); ?></p>
                            </td>
                        </tr>
                        <?php
                        $this->schema_row( $settings, 'email', __( 'Email', 'seo-confident' ), array( 'type' => 'email', 'placeholder' => 'contact@example.com' ) );
                        $this->schema_row( $settings, 'telephone', __( 'Telephone', 'seo-confident' ), array( 'placeholder' => __( 'e.g., 020 4615 8500', 'seo-confident' ) ) );
                        $this->schema_row( $settings, 'street_address', __( 'Street Address', 'seo-confident' ) );
                        $this->schema_row( $settings, 'address_locality', __( 'City', 'seo-confident' ) );
                        $this->schema_row( $settings, 'address_region', __( 'Region', 'seo-confident' ) );
                        $this->schema_row( $settings, 'postal_code', __( 'Postcode', 'seo-confident' ) );
                        $this->schema_row(
                            $settings,
                            'address_country',
                            __( 'Country', 'seo-confident' ),
                            array(
                                'placeholder' => 'GB',
                                'default'     => 'GB',
                                'description' => __( 'Two-letter ISO country code, e.g. GB.', 'seo-confident' ),
                            )
                        );
                        ?>
                        <tr>
                            <th><label for="seoc_schema_sameas"><?php echo esc_html__( 'Social Profiles (sameAs)', 'seo-confident' ); ?></label></th>
                            <td>
                                <textarea id="seoc_schema_sameas" name="seoc_settings[schema_org_sameas]" rows="4" class="large-text" placeholder="<?php echo esc_attr__( 'One URL per line:', 'seo-confident' ); ?>&#10;https://www.facebook.com/yourpage&#10;https://www.linkedin.com/company/yourcompany&#10;https://www.instagram.com/yourprofile"><?php echo esc_textarea( $settings['schema_org_sameas'] ?? '' ); ?></textarea>
                                <p class="description"><?php echo esc_html__( 'One social profile URL per line. Commonly used: Facebook, LinkedIn, Instagram, YouTube, X.', 'seo-confident' ); ?></p>
                            </td>
                        </tr>
                        <?php
                        $this->schema_row(
                            $settings,
                            'area_served',
                            __( 'Area Served', 'seo-confident' ),
                            array(
                                'placeholder' => __( 'e.g., US, Worldwide, London, UK', 'seo-confident' ),
                                'description' => __( 'Geographic areas or region codes where your business operates. Comma-separated for multiple areas.', 'seo-confident' ),
                            )
                        );
                        ?>
                    </table>
                </div>

                <div class="seoc-card">
                    <h2><?php echo esc_html__( 'Custom Wide Schema', 'seo-confident' ); ?></h2>
                    <p class="description" style="margin-bottom: 16px;">
                        <?php echo esc_html__( 'Add custom JSON-LD schema that will be merged with or extend the standard site-wide schema. This allows you to add complex schema structures, additional organization details, or any other schema.org types.', 'seo-confident' ); ?>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th><label for="seoc_custom_wide_schema"><?php echo esc_html__( 'Custom JSON-LD Schema', 'seo-confident' ); ?></label></th>
                            <td>
                                <textarea id="seoc_custom_wide_schema" name="seoc_settings[schema_custom_wide]" rows="12" class="large-text" style="font-family: Consolas, Monaco, monospace; font-size: 13px; background: #f6f7f7; border: 1px solid #c3c4c7;" placeholder='{
  "@context": "https://schema.org",
  "@type": "AggregateRating",
  "ratingValue": "4.5",
  "bestRating": "5",
  "worstRating": "1",
  "ratingCount": "250"
}'><?php echo esc_textarea( $settings['schema_custom_wide'] ?? '' ); ?></textarea>
                                <p class="description">
                                    <?php echo esc_html__( 'Paste valid JSON-LD here. This will be output as a separate <script type="application/ld+json"> block. If the JSON is not valid on save, the previous schema is kept and you will see a warning.', 'seo-confident' ); ?>
                                </p>
                                <p class="description" id="seoc-schema-validation" style="font-weight: 600;"></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="seoc-card">
                    <h2><?php echo esc_html__( 'Robots Meta Tag', 'seo-confident' ); ?></h2>
                    <p class="description" style="margin-bottom: 16px;">
                        <?php echo esc_html__( 'Control site-wide robots meta tag settings. These apply to all pages and archives unless overridden per-page. Search results and 404 pages are always set to noindex.', 'seo-confident' ); ?>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th><?php echo esc_html__( 'Index', 'seo-confident' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="seoc_settings[robots_index]" value="1" <?php checked( $settings['robots_index'] ?? '1', '1' ); ?> />
                                    <?php echo esc_html__( 'Allow search engines to index this site', 'seo-confident' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Follow', 'seo-confident' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="seoc_settings[robots_follow]" value="1" <?php checked( $settings['robots_follow'] ?? '1', '1' ); ?> />
                                    <?php echo esc_html__( 'Allow search engines to follow links', 'seo-confident' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="seoc_robots_max_snippet"><?php echo esc_html__( 'Max Snippet Length', 'seo-confident' ); ?></label></th>
                            <td>
                                <input type="number" id="seoc_robots_max_snippet" name="seoc_settings[robots_max_snippet]" value="<?php echo esc_attr( $settings['robots_max_snippet'] ?? '-1' ); ?>" class="small-text" />
                                <p class="description"><?php echo esc_html__( 'Use -1 for unlimited, 0 to disable, or enter character limit.', 'seo-confident' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="seoc_robots_max_video"><?php echo esc_html__( 'Max Video Preview Length', 'seo-confident' ); ?></label></th>
                            <td>
                                <input type="number" id="seoc_robots_max_video" name="seoc_settings[robots_max_video_preview]" value="<?php echo esc_attr( $settings['robots_max_video_preview'] ?? '-1' ); ?>" class="small-text" />
                                <p class="description"><?php echo esc_html__( 'Use -1 for unlimited, 0 to disable, or enter seconds limit.', 'seo-confident' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="seoc-card">
                    <h2><?php echo esc_html__( 'Breadcrumbs', 'seo-confident' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php echo esc_html__( 'Enable Breadcrumbs', 'seo-confident' ); ?></th>
                            <td><label><input type="checkbox" name="seoc_settings[breadcrumbs]" value="1" <?php checked( $settings['breadcrumbs'] ?? '0', '1' ); ?> /> <?php echo esc_html__( 'Output breadcrumb schema markup', 'seo-confident' ); ?></label></td>
                        </tr>
                    </table>
                </div>

                <div class="seoc-card">
                    <h2><?php echo esc_html__( 'Technical SEO', 'seo-confident' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="seoc_tech_lang"><?php echo esc_html__( 'HTML Language', 'seo-confident' ); ?></label></th>
                            <td><input type="text" id="seoc_tech_lang" name="seoc_settings[tech_lang]" value="<?php echo esc_attr( $settings['tech_lang'] ?? 'en' ); ?>" class="small-text" /> <span class="description"><?php echo esc_html__( 'e.g. en, en-GB, fr', 'seo-confident' ); ?></span></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Security Headers', 'seo-confident' ); ?></th>
                            <td><label><input type="checkbox" name="seoc_settings[tech_security_headers]" value="1" <?php checked( $settings['tech_security_headers'] ?? '0', '1' ); ?> /> <?php echo esc_html__( 'Add X-Frame-Options, Referrer-Policy, X-Content-Type-Options', 'seo-confident' ); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Preconnect Hints', 'seo-confident' ); ?></th>
                            <td>
                                <label><input type="checkbox" name="seoc_settings[tech_preconnect]" value="1" <?php checked( $settings['tech_preconnect'] ?? '0', '1' ); ?> /> <?php echo esc_html__( 'Add preconnect for Google Fonts and analytics', 'seo-confident' ); ?></label>
                                <p class="description"><?php echo esc_html__( 'Only useful if your site loads Google Fonts or Google Analytics.', 'seo-confident' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Fix tel: Links', 'seo-confident' ); ?></th>
                            <td><label><input type="checkbox" name="seoc_settings[tech_fix_tel]" value="1" <?php checked( $settings['tech_fix_tel'] ?? '0', '1' ); ?> /> <?php echo esc_html__( 'Convert tel:009... to tel:+... format', 'seo-confident' ); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Strip ?media_link', 'seo-confident' ); ?></th>
                            <td><label><input type="checkbox" name="seoc_settings[tech_strip_media_link]" value="1" <?php checked( $settings['tech_strip_media_link'] ?? '0', '1' ); ?> /> <?php echo esc_html__( '301 redirect URLs with ?media_link parameter to clean version', 'seo-confident' ); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Auto Image Alt Text', 'seo-confident' ); ?></th>
                            <td>
                                <label><input type="checkbox" name="seoc_settings[tech_image_alt]" value="1" <?php checked( $settings['tech_image_alt'] ?? '0', '1' ); ?> /> <?php echo esc_html__( 'Generate alt text for images with empty alt attributes', 'seo-confident' ); ?></label>
                                <p class="description"><?php echo esc_html__( 'This also fills in images that were left empty on purpose because they are decorative. Writing proper alt text in the media library is better.', 'seo-confident' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button( __( 'Save Settings', 'seo-confident' ) ); ?>
            </form>

            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccc;">
                <h2><?php echo esc_html__( 'Settings Management', 'seo-confident' ); ?></h2>

                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Export Settings -->
                    <div style="flex: 1; min-width: 300px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h3><?php echo esc_html__( 'Export Settings', 'seo-confident' ); ?></h3>
                        <p><?php echo esc_html__( 'Download your settings as a JSON file. Useful for backup or moving to another site.', 'seo-confident' ); ?></p>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=seoc-settings&action=seoc_export_settings' ), 'seoc_export_settings' ) ); ?>" class="button button-primary">
                            <?php echo esc_html__( 'Export Settings', 'seo-confident' ); ?>
                        </a>
                    </div>

                    <!-- Import Settings -->
                    <div style="flex: 1; min-width: 300px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <h3><?php echo esc_html__( 'Import Settings', 'seo-confident' ); ?></h3>
                        <p><?php echo esc_html__( 'Upload a previously exported JSON settings file to restore settings.', 'seo-confident' ); ?></p>
                        <form method="post" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: flex-end;">
                            <?php wp_nonce_field( 'seoc_import_settings', 'seoc_import_nonce' ); ?>
                            <input type="hidden" name="action" value="seoc_import_settings" />
                            <input type="file" name="seoc_import_file" accept=".json,application/json" required />
                            <button type="submit" class="button button-secondary">
                                <?php echo esc_html__( 'Import Settings', 'seo-confident' ); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php
    }
}
