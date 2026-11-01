<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Post/page meta box for per-page SEO fields.
 *
 * Adds a meta box to the editor with fields for:
 * - Custom title
 * - Meta description
 * - Keywords
 * - Canonical URL
 * - Robots directives
 * - Custom OG image
 * - Custom JSON-LD schema
 */
class SEOC_Meta_Box {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'maybe_show_schema_error' ) );
    }

    /**
     * Tell the editor when the custom schema they typed was not saved.
     */
    public function maybe_show_schema_error() {
        $key = 'seoc_schema_error_' . get_current_user_id();

        if ( ! get_transient( $key ) ) {
            return;
        }

        delete_transient( $key );

        echo '<div class="notice notice-warning is-dismissible"><p><strong>SEO Confident:</strong> '
            . esc_html__( 'The custom JSON-LD schema was not saved because it is not valid JSON. The previous schema was kept.', 'seo-confident' )
            . '</p></div>';
    }

    public function add_meta_box() {
        $post_types = get_post_types( array( 'public' => true ), 'names' );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'seoc_meta_box',
                'SEO Confident',
                array( $this, 'render_meta_box' ),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'seoc_save_meta', 'seoc_meta_nonce' );

        $title        = get_post_meta( $post->ID, '_seoc_title', true );
        $description  = get_post_meta( $post->ID, '_seoc_description', true );
        $keywords     = get_post_meta( $post->ID, '_seoc_keywords', true );
        $canonical    = get_post_meta( $post->ID, '_seoc_canonical', true );
        $robots       = get_post_meta( $post->ID, '_seoc_robots', true );
        $og_image     = get_post_meta( $post->ID, '_seoc_og_image', true );
        $custom_schema = get_post_meta( $post->ID, '_seoc_custom_schema', true );

        $settings  = get_option( 'seoc_settings', array() );
        $separator = $settings['separator'] ?? '|';
        $site_name = get_bloginfo( 'name' );
        ?>
        <div class="seoc-metabox">

            <!-- Search Preview -->
            <div class="seoc-preview">
                <div class="seoc-preview-title" id="seoc-preview-title">
                    <?php echo esc_html( ! empty( $title ) ? $title : $post->post_title . ' ' . $separator . ' ' . $site_name ); ?>
                </div>
                <div class="seoc-preview-url"><?php echo esc_url( get_permalink( $post->ID ) ); ?></div>
                <div class="seoc-preview-desc" id="seoc-preview-desc">
                    <?php echo esc_html( ! empty( $description ) ? $description : wp_trim_words( wp_strip_all_tags( $post->post_content ), 25, '...' ) ); ?>
                </div>
            </div>

            <!-- Tabs -->
            <div class="seoc-tabs">
                <button type="button" class="seoc-tab active" data-tab="general">General</button>
                <button type="button" class="seoc-tab" data-tab="social">Social</button>
                <button type="button" class="seoc-tab" data-tab="advanced">Advanced</button>
                <button type="button" class="seoc-tab" data-tab="schema">Schema</button>
            </div>

            <!-- General Tab -->
            <div class="seoc-tab-content active" id="seoc-tab-general">
                <div class="seoc-field">
                    <label for="seoc_title">
                        SEO Title
                        <span class="seoc-char-count" id="seoc-title-count">
                            <?php echo intval( mb_strlen( (string) $title ) ); ?>/60
                        </span>
                    </label>
                    <input type="text" id="seoc_title" name="seoc_title"
                        value="<?php echo esc_attr( $title ); ?>"
                        placeholder="<?php echo esc_attr( $post->post_title . ' ' . $separator . ' ' . $site_name ); ?>" />
                    <p class="description">Leave blank to auto-generate from post title.</p>
                </div>

                <div class="seoc-field">
                    <label for="seoc_description">
                        Meta Description
                        <span class="seoc-char-count" id="seoc-desc-count">
                            <?php echo intval( mb_strlen( (string) $description ) ); ?>/160
                        </span>
                    </label>
                    <textarea id="seoc_description" name="seoc_description"
                        rows="3" placeholder="Auto-generated from content if left blank."><?php echo esc_textarea( $description ); ?></textarea>
                </div>

                <div class="seoc-field">
                    <label for="seoc_keywords">Keywords</label>
                    <input type="text" id="seoc_keywords" name="seoc_keywords"
                        value="<?php echo esc_attr( $keywords ); ?>"
                        placeholder="keyword1, keyword2, keyword3" />
                </div>
            </div>

            <!-- Social Tab -->
            <div class="seoc-tab-content" id="seoc-tab-social">
                <div class="seoc-field">
                    <label for="seoc_og_image">Custom OG Image URL</label>
                    <input type="text" id="seoc_og_image" name="seoc_og_image"
                        value="<?php echo esc_attr( $og_image ); ?>" />
                    <button type="button" class="button seoc-upload-btn" data-target="seoc_og_image">Select Image</button>
                    <p class="description">Overrides the featured image for social sharing.</p>
                </div>
            </div>

            <!-- Advanced Tab -->
            <div class="seoc-tab-content" id="seoc-tab-advanced">
                <div class="seoc-field">
                    <label for="seoc_canonical">Canonical URL</label>
                    <input type="text" id="seoc_canonical" name="seoc_canonical"
                        value="<?php echo esc_attr( $canonical ); ?>"
                        placeholder="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" />
                    <p class="description">Override the canonical URL. Leave blank to use the post permalink.</p>
                </div>

                <div class="seoc-field">
                    <label>Robots Directives</label>
                    <div class="seoc-robots-options">
                        <?php
                        $robots_options = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
                        $current_robots = array_map( 'trim', explode( ',', $robots ) );
                        foreach ( $robots_options as $opt ) {
                            printf(
                                '<label><input type="checkbox" name="seoc_robots[]" value="%s" %s /> %s</label>',
                                esc_attr( $opt ),
                                checked( in_array( $opt, $current_robots, true ), true, false ),
                                esc_html( $opt )
                            );
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Schema Tab -->
            <div class="seoc-tab-content" id="seoc-tab-schema">
                <div class="seoc-field">
                    <label for="seoc_custom_schema">Custom JSON-LD Schema</label>
                    <textarea id="seoc_custom_schema" name="seoc_custom_schema"
                        rows="12" class="seoc-schema-editor"
                        placeholder='{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": []
}'><?php echo esc_textarea( $custom_schema ); ?></textarea>
                    <p class="description">
                        Paste valid JSON-LD here. This is output as-is in a &lt;script type="application/ld+json"&gt; block.
                        The auto-generated Article/WebPage schema will still be output alongside this.
                    </p>
                    <p class="description" id="seoc-schema-validation" style="font-weight: 600;"></p>
                </div>
            </div>
        </div>

        <?php
    }

    public function save_meta_box( $post_id, $post ) {
        // Verify nonce.
        if ( ! isset( $_POST['seoc_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['seoc_meta_nonce'] ) ), 'seoc_save_meta' ) ) {
            return;
        }

        // Revisions are copies, the real post is saved separately.
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Check autosave.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save text fields.
        $text_fields = array(
            'seoc_title'       => '_seoc_title',
            'seoc_description' => '_seoc_description',
            'seoc_keywords'    => '_seoc_keywords',
            'seoc_canonical'   => '_seoc_canonical',
            'seoc_og_image'    => '_seoc_og_image',
        );

        $url_fields = array( 'seoc_canonical', 'seoc_og_image' );

        foreach ( $text_fields as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) ) {
                $raw   = wp_unslash( $_POST[ $field ] );
                $value = in_array( $field, $url_fields, true ) ? esc_url_raw( trim( $raw ) ) : sanitize_text_field( $raw );
                if ( ! empty( $value ) ) {
                    update_post_meta( $post_id, $meta_key, wp_slash( $value ) );
                } else {
                    delete_post_meta( $post_id, $meta_key );
                }
            }
        }

        // Robots directives.
        if ( isset( $_POST['seoc_robots'] ) && is_array( $_POST['seoc_robots'] ) ) {
            $allowed  = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
            $selected = array_intersect( array_map( 'sanitize_text_field', wp_unslash( $_POST['seoc_robots'] ) ), $allowed );
            update_post_meta( $post_id, '_seoc_robots', implode( ', ', $selected ) );
        } else {
            delete_post_meta( $post_id, '_seoc_robots' );
        }

        // Custom schema (allow JSON, sanitise carefully).
        if ( isset( $_POST['seoc_custom_schema'] ) ) {
            $schema = wp_unslash( $_POST['seoc_custom_schema'] );
            $schema = trim( $schema );

            if ( ! empty( $schema ) ) {
                // Validate it's proper JSON before saving.
                $decoded = json_decode( $schema, true );
                if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                    // update_post_meta() removes slashes, so add them first or every
                    // escaped quote inside the JSON loses its backslash and breaks it.
                    update_post_meta( $post_id, '_seoc_custom_schema', wp_slash( $schema ) );
                } else {
                    // Invalid JSON: keep the previous value and say so on the next screen.
                    set_transient( 'seoc_schema_error_' . get_current_user_id(), 1, 120 );
                }
            } else {
                delete_post_meta( $post_id, '_seoc_custom_schema' );
            }
        }
    }
}
