<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schema.org JSON-LD markup.
 *
 * Outputs site-wide Organisation/Person schema plus per-page custom schema.
 */
class SEOC_Schema {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_head', array( $this, 'output_schema' ), 3 );
    }

    public function output_schema() {
        $settings = get_option( 'seoc_settings', array() );

        // Site-wide schema.
        $this->output_site_schema( $settings );

        if ( is_singular() ) {
            // Page-level auto schema.
            $this->output_page_schema();

            // Custom schema (user-defined JSON-LD per page).
            $this->output_custom_schema();
        }
    }

    /**
     * Organisation / Person / LocalBusiness schema.
     */
    private function output_site_schema( $settings ) {
        $type = ! empty( $settings['schema_org_type'] ) ? $settings['schema_org_type'] : 'Organization';
        $name = ! empty( $settings['schema_org_name'] ) ? $settings['schema_org_name'] : get_bloginfo( 'name' );
        $logo = $settings['schema_org_logo'] ?? '';
        $home = home_url( '/' );

        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => $type,
            '@id'      => $home . '#organization',
            'name'     => seoc_clean_text( $name ),
            'url'      => $home,
        );

        $description = seoc_clean_text( $settings['home_description'] ?? '' );
        if ( '' !== $description ) {
            $schema['description'] = $description;
        }

        // Logo, with optional dimensions. A Person has an image rather than a logo.
        if ( ! empty( $logo ) ) {
            $logo_obj = array(
                '@type' => 'ImageObject',
                'url'   => $logo,
            );

            if ( ! empty( $settings['schema_org_logo_width'] ) ) {
                $logo_obj['width'] = (int) $settings['schema_org_logo_width'];
            }
            if ( ! empty( $settings['schema_org_logo_height'] ) ) {
                $logo_obj['height'] = (int) $settings['schema_org_logo_height'];
            }

            $schema[ 'Person' === $type ? 'image' : 'logo' ] = $logo_obj;
        }

        // Contact details apply to every type, not only LocalBusiness.
        $email       = $settings['schema_org_email'] ?? '';
        $telephone   = $settings['schema_org_telephone'] ?? '';
        $sameas      = $settings['schema_org_sameas'] ?? '';
        $area_served = $settings['schema_org_area_served'] ?? '';

        $street_address   = $settings['schema_org_street_address'] ?? '';
        $address_locality = $settings['schema_org_address_locality'] ?? '';
        $address_region   = $settings['schema_org_address_region'] ?? '';
        $postal_code      = $settings['schema_org_postal_code'] ?? '';
        $address_country  = $settings['schema_org_address_country'] ?? 'GB';

        if ( ! empty( $email ) ) {
            $schema['email'] = $email;
        }

        if ( ! empty( $telephone ) ) {
            $schema['telephone'] = $telephone;
        }

        // Add physical address when supplied.
        if ( ! empty( $street_address ) || ! empty( $address_locality ) || ! empty( $postal_code ) ) {
            $address = array(
                '@type' => 'PostalAddress',
            );

            if ( ! empty( $street_address ) ) {
                $address['streetAddress'] = $street_address;
            }

            if ( ! empty( $address_locality ) ) {
                $address['addressLocality'] = $address_locality;
            }

            if ( ! empty( $address_region ) ) {
                $address['addressRegion'] = $address_region;
            }

            if ( ! empty( $postal_code ) ) {
                $address['postalCode'] = $postal_code;
            }

            if ( ! empty( $address_country ) ) {
                $address['addressCountry'] = $address_country;
            }

            $schema['address'] = $address;
        }

        if ( ! empty( $sameas ) ) {
            // Parse comma or newline-separated URLs.
            $sameas_array = array_map( 'trim', preg_split( '/[\r\n,]+/', $sameas ) );
            $sameas_array = array_filter( $sameas_array );
            if ( ! empty( $sameas_array ) ) {
                $schema['sameAs'] = array_values( $sameas_array );
            }
        }

        if ( ! empty( $area_served ) ) {
            // Parse as comma-separated region codes or areas.
            $area_array = array_map( 'trim', explode( ',', $area_served ) );
            $area_array = array_filter( $area_array );
            if ( ! empty( $area_array ) ) {
                $schema['areaServed'] = array_values( $area_array );
            }
        }

        // WebSite schema with search action.
        $website = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            '@id'             => $home . '#website',
            'name'            => seoc_clean_text( get_bloginfo( 'name' ) ),
            'url'             => $home,
            'publisher'       => array( '@id' => $home . '#organization' ),
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string',
            ),
        );

        seoc_print_json_ld( $schema, 'SEO Confident: Schema' );
        seoc_print_json_ld( $website );

        // Output custom wide schema if present.
        $this->output_custom_wide_schema( $settings );
    }

    /**
     * Output custom site-wide schema from settings.
     */
    private function output_custom_wide_schema( $settings ) {
        $custom_schema = $settings['schema_custom_wide'] ?? '';

        if ( empty( $custom_schema ) ) {
            return;
        }

        // Validate it is valid JSON.
        $decoded = json_decode( $custom_schema, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
            echo '<!-- SEO Confident: Custom schema JSON is invalid -->' . "\n";
            return;
        }

        // Re-encoding makes sure nothing inside a string can close the script tag.
        seoc_print_json_ld( $decoded, 'SEO Confident: Custom Wide Schema' );
    }

    /**
     * Auto-generated Article/WebPage schema for singular content.
     */
    private function output_page_schema() {
        $post_id = get_queried_object_id();
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return;
        }

        $type = ( 'post' === $post->post_type ) ? 'Article' : 'WebPage';

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => $type,
            'headline'      => seoc_clean_text( get_the_title( $post_id ) ),
            'url'           => get_permalink( $post_id ),
            'datePublished' => get_the_date( 'c', $post_id ),
            'dateModified'  => get_the_modified_date( 'c', $post_id ),
            'author'        => array(
                '@type' => 'Person',
                'name'  => seoc_clean_text( get_the_author_meta( 'display_name', $post->post_author ) ),
            ),
        );

        $description = get_post_meta( $post_id, '_seoc_description', true );
        if ( ! empty( $description ) ) {
            $schema['description'] = seoc_clean_text( $description );
        }

        if ( has_post_thumbnail( $post_id ) ) {
            $schema['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
        }

        // Lets other code, such as SEOC_Extras, add to the page schema before it is printed.
        $schema = apply_filters( 'seoc_page_schema', $schema, $post );

        seoc_print_json_ld( $schema );
    }

    /**
     * Output custom user-defined JSON-LD schema.
     */
    private function output_custom_schema() {
        $custom_schema = get_post_meta( get_queried_object_id(), '_seoc_custom_schema', true );

        if ( empty( $custom_schema ) ) {
            return;
        }

        // Validate it is valid JSON.
        $decoded = json_decode( $custom_schema, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
            echo '<!-- SEO Confident: Custom schema JSON is invalid -->' . "\n";
            return;
        }

        // Re-encode to ensure clean, script-safe output.
        seoc_print_json_ld( $decoded );
    }
}
