<?php
/**
 * Post meta / page-builder data duplication handler.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP\Meta;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Meta_Handler
 *
 * Singleton service that duplicates post meta for a freshly
 * inserted post. Handles Elementor, ACF, Codestar Framework,
 * WPBakery, and Beaver Builder data in addition to standard
 * post meta. Converted from the legacy static utility.
 */
final class Meta_Handler {

    /**
     * Single instance.
     *
     * @var Meta_Handler|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Meta_Handler
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Block cloning.
     *
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Meta_Handler is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Block unserialization.
     *
     * @return void
     * @throws \Exception When invoked.
     */
    public function __wakeup() {
        throw new \Exception( esc_html__( 'Unserializing Meta_Handler is not allowed.', 'duplicate-pp' ) );
    }

    /**
     * Duplicate all post meta from $original_id to $new_id.
     *
     * @param int $original_id Source post ID.
     * @param int $new_id      Destination post ID.
     * @return void
     */
    public function duplicate_post_meta( $original_id, $new_id ) {
        global $wpdb;

        $original_id = (int) $original_id;
        $new_id      = (int) $new_id;

        if ( $original_id <= 0 || $new_id <= 0 ) {
            return;
        }

        // Get all meta fields for the source post.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $post_meta_infos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
                $original_id
            )
        );
        // phpcs:enable

        if ( empty( $post_meta_infos ) ) {
            return;
        }

        // Meta keys that must never be duplicated.
        $exclude_meta = array(
            '_wp_old_slug',
            '_edit_lock',
            '_edit_last',
            '_elementor_css', // Elementor regenerates this.
        );

        $select_parts = array();

        foreach ( $post_meta_infos as $meta_info ) {
            if ( in_array( $meta_info->meta_key, $exclude_meta, true ) ) {
                continue;
            }

            $meta_key   = wp_unslash( $meta_info->meta_key );
            $meta_value = wp_unslash( $meta_info->meta_value );

            switch ( $meta_key ) {
                case '_elementor_data':
                    $this->handle_elementor_data( $new_id, $meta_value );
                    break;

                case '_acf':
                    $this->handle_acf_fields( $new_id, $original_id );
                    break;

                case '_cs_options':
                    $this->handle_codestar_data( $new_id, $meta_value );
                    break;

                default:
                    $select_parts[] = $wpdb->prepare(
                        'SELECT %d, %s, %s',
                        $new_id,
                        $meta_key,
                        $meta_value
                    );
                    break;
            }
        }

        // Insert standard meta in a single round-trip.
        if ( ! empty( $select_parts ) ) {
            $sql = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) " . implode( ' UNION ALL ', $select_parts );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( $sql );
        }

        // Page-builder-specific data.
        $this->handle_builders_data( $new_id, $original_id );
    }

    /**
     * Handle Elementor data specifically.
     *
     * @param int    $new_id         Destination post ID.
     * @param string $elementor_data Serialized elementor data.
     * @return void
     */
    private function handle_elementor_data( $new_id, $elementor_data ) {
        $decoded = json_decode( $elementor_data, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
            return;
        }

        $processed = $this->process_elementor_elements( $decoded );
        update_post_meta( $new_id, '_elementor_data', wp_slash( wp_json_encode( $processed ) ) );

        // Copy other Elementor-specific meta verbatim from the new post
        // (it is already a clone of the source at this stage, but we
        // re-read for clarity and to avoid stale references).
        update_post_meta( $new_id, '_elementor_version', get_post_meta( $new_id, '_elementor_version', true ) );
        update_post_meta( $new_id, '_elementor_edit_mode', get_post_meta( $new_id, '_elementor_edit_mode', true ) );
        update_post_meta( $new_id, '_elementor_template_type', get_post_meta( $new_id, '_elementor_template_type', true ) );
    }

    /**
     * Recursively regenerate Elementor element IDs.
     *
     * @param array $elements Elementor elements tree.
     * @return array
     */
    private function process_elementor_elements( $elements ) {
        foreach ( $elements as &$element ) {
            if ( isset( $element['id'] ) ) {
                $element['id'] = wp_unique_id();
            }

            if ( isset( $element['settings']['background_image']['id'] ) ) {
                $element['settings']['background_image']['id'] = wp_unique_id();
            }

            if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $element['elements'] = $this->process_elementor_elements( $element['elements'] );
            }
        }
        return $elements;
    }

    /**
     * Handle Advanced Custom Fields.
     *
     * @param int $new_id      Destination post ID.
     * @param int $original_id Source post ID.
     * @return void
     */
    private function handle_acf_fields( $new_id, $original_id ) {
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            return;
        }

        $field_groups = acf_get_field_groups( array( 'post_id' => $original_id ) );
        if ( empty( $field_groups ) ) {
            return;
        }

        foreach ( $field_groups as $field_group ) {
            $fields = acf_get_fields( $field_group );
            if ( empty( $fields ) ) {
                continue;
            }
            foreach ( $fields as $field ) {
                $value = get_field( $field['key'], $original_id );
                update_field( $field['key'], $value, $new_id );
            }
        }
    }

    /**
     * Handle Codestar Framework data.
     *
     * @param int    $new_id     Destination post ID.
     * @param string $meta_value Raw _cs_options value.
     * @return void
     */
    private function handle_codestar_data( $new_id, $meta_value ) {
        $cs_data = maybe_unserialize( $meta_value );
        if ( ! $cs_data ) {
            return;
        }
        $cs_data = $this->process_codestar_data( $cs_data );
        update_post_meta( $new_id, '_cs_options', $cs_data );
    }

    /**
     * Recursively rewrite Codestar unique IDs.
     *
     * @param mixed $data Codestar data tree.
     * @return mixed
     */
    private function process_codestar_data( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => &$value ) {
                if ( is_array( $value ) ) {
                    $value = $this->process_codestar_data( $value );
                } elseif ( is_string( $value ) && false !== strpos( $value, 'unique_id_' ) ) {
                    $value = 'unique_id_' . wp_unique_id();
                }
            }
        }
        return $data;
    }

    /**
     * Handle WPBakery and Beaver Builder data.
     *
     * @param int $new_id      Destination post ID.
     * @param int $original_id Source post ID.
     * @return void
     */
    private function handle_builders_data( $new_id, $original_id ) {
        // WPBakery Page Builder.
        if ( defined( 'WPB_VC_VERSION' ) ) {
            $wpb_content = get_post_meta( $original_id, '_wpb_shortcodes_custom_css', true );
            if ( $wpb_content ) {
                update_post_meta( $new_id, '_wpb_shortcodes_custom_css', $wpb_content );
            }
        }

        // Beaver Builder.
        if ( class_exists( 'FLBuilderModel' ) ) {
            $beaver_data = get_post_meta( $original_id, '_fl_builder_data', true );
            if ( $beaver_data ) {
                update_post_meta( $new_id, '_fl_builder_data', $beaver_data );
                update_post_meta( $new_id, '_fl_builder_draft', get_post_meta( $original_id, '_fl_builder_draft', true ) );
            }
        }
    }
}
