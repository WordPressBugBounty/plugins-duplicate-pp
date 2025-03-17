<?php
if (!defined('ABSPATH')) {
    exit;
}

class DPP_Meta_Handler {
    /**
     * Duplicate all post meta
     */
    public static function duplicate_post_meta($original_id, $new_id) {
        global $wpdb;

        // Get all meta fields
        $post_meta_infos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d",
                $original_id
            )
        );

        if (empty($post_meta_infos)) {
            return;
        }

        // Meta keys that should not be duplicated
        $exclude_meta = array(
            '_wp_old_slug',
            '_edit_lock',
            '_edit_last',
            '_elementor_css' // Elementor will regenerate this
        );

        $sql_query_sel = array();
        $sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";

        foreach ($post_meta_infos as $meta_info) {
            if (in_array($meta_info->meta_key, $exclude_meta)) {
                continue;
            }

            $meta_key = wp_unslash($meta_info->meta_key);
            $meta_value = wp_unslash($meta_info->meta_value);

            // Handle specific meta data types
            switch ($meta_key) {
                // Elementor data
                case '_elementor_data':
                    self::handle_elementor_data($new_id, $meta_value);
                    break;

                // ACF fields
                case '_acf':
                    self::handle_acf_fields($new_id, $original_id);
                    break;

                // Codestar Framework
                case '_cs_options':
                    self::handle_codestar_data($new_id, $meta_value);
                    break;

                default:
                    $sql_query_sel[] = $wpdb->prepare(
                        "SELECT %d, %s, %s",
                        $new_id,
                        $meta_key,
                        $meta_value
                    );
                    break;
            }
        }

        // Insert all standard meta values in one query
        if (!empty($sql_query_sel)) {
            $sql_query .= implode(" UNION ALL ", $sql_query_sel);
            $wpdb->query($sql_query);
        }

        // Handle specific builder data
        self::handle_builders_data($new_id, $original_id);
    }

    /**
     * Handle Elementor data specifically
     */
    private static function handle_elementor_data($new_id, $elementor_data) {
        // Decode the Elementor data
        $elementor_data = json_decode($elementor_data, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Update internal IDs and links
            $elementor_data = self::process_elementor_elements($elementor_data);
            // Save processed data
            update_post_meta($new_id, '_elementor_data', wp_slash(json_encode($elementor_data)));
            // Copy other Elementor-specific meta
            update_post_meta($new_id, '_elementor_version', get_post_meta($new_id, '_elementor_version', true));
            update_post_meta($new_id, '_elementor_edit_mode', get_post_meta($new_id, '_elementor_edit_mode', true));
            update_post_meta($new_id, '_elementor_template_type', get_post_meta($new_id, '_elementor_template_type', true));
        }
    }

    /**
     * Process Elementor elements recursively
     */
    private static function process_elementor_elements($elements) {
        foreach ($elements as &$element) {
            // Generate new ID for element
            $element['id'] = wp_unique_id();

            // Process settings if they exist
            if (isset($element['settings'])) {
                // Handle background images, links, etc.
                if (isset($element['settings']['background_image'])) {
                    // Maintain image reference but update any unique IDs
                    $element['settings']['background_image']['id'] = wp_unique_id();
                }
            }

            // Process child elements recursively
            if (!empty($element['elements'])) {
                $element['elements'] = self::process_elementor_elements($element['elements']);
            }
        }

        return $elements;
    }

    /**
     * Handle ACF fields
     */
    private static function handle_acf_fields($new_id, $original_id) {
        if (!function_exists('acf_get_field_groups')) {
            return;
        }

        // Get all field groups
        $field_groups = acf_get_field_groups(array('post_id' => $original_id));

        foreach ($field_groups as $field_group) {
            $fields = acf_get_fields($field_group);
            foreach ($fields as $field) {
                $value = get_field($field['key'], $original_id);
                update_field($field['key'], $value, $new_id);
            }
        }
    }

    /**
     * Handle Codestar Framework data
     */
    private static function handle_codestar_data($new_id, $meta_value) {
        // Decode Codestar data
        $cs_data = maybe_unserialize($meta_value);
        if ($cs_data) {
            // Process any internal references or IDs
            $cs_data = self::process_codestar_data($cs_data);
            update_post_meta($new_id, '_cs_options', $cs_data);
        }
    }

    /**
     * Process Codestar Framework data recursively
     */
    private static function process_codestar_data($data) {
        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    $value = self::process_codestar_data($value);
                } elseif (is_string($value) && strpos($value, 'unique_id_') !== false) {
                    // Generate new unique ID for any references
                    $value = 'unique_id_' . wp_unique_id();
                }
            }
        }
        return $data;
    }

    /**
     * Handle various page builder data
     */
    private static function handle_builders_data($new_id, $original_id) {
        // Handle WPBakery Page Builder
        if (defined('WPB_VC_VERSION')) {
            $wpb_content = get_post_meta($original_id, '_wpb_shortcodes_custom_css', true);
            if ($wpb_content) {
                update_post_meta($new_id, '_wpb_shortcodes_custom_css', $wpb_content);
            }
        }

        // Handle Beaver Builder
        if (class_exists('FLBuilderModel')) {
            $beaver_data = get_post_meta($original_id, '_fl_builder_data', true);
            if ($beaver_data) {
                update_post_meta($new_id, '_fl_builder_data', $beaver_data);
                update_post_meta($new_id, '_fl_builder_draft', get_post_meta($original_id, '_fl_builder_draft', true));
            }
        }
    }
}
