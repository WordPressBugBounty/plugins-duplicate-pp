<?php
/**
 * Settings service — single read sink for the dpp_settings option.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Settings_Service
 *
 * Encapsulates the dpp_settings option. All other classes should
 * ask this service for settings values rather than calling
 * get_option('dpp_settings', ...) themselves. Writes still go
 * through the Settings API (Settings_Page::sanitize_settings).
 */
final class Settings_Service {

    /**
     * The option key — preserved exactly to keep stored data backward-compatible.
     */
    const OPTION_KEY = 'dpp_settings';

    /**
     * Setting keys that hold a map of on/off switches. Stored maps are
     * merged with the defaults so a newly added switch gets its default.
     */
    const SWITCH_GROUPS = array( 'copy', 'show_in', 'show_original' );

    /**
     * Post types that are never offered for duplication.
     */
    const EXCLUDED_POST_TYPES = array(
        'attachment',
        'wp_navigation',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_font_family',
        'wp_font_face',
        'shop_order',
        'shop_order_refund',
        'shop_order_placehold',
        'product_variation',
    );

    /**
     * Single instance.
     *
     * @var Settings_Service|null
     */
    private static $instance = null;

    /**
     * Cached option value.
     *
     * @var array|null
     */
    private $cache = null;

    /**
     * Private constructor.
     */
    private function __construct() {
        // Drop the cache whenever the option changes within the request.
        add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'flush' ) );
        add_action( 'add_option_' . self::OPTION_KEY, array( $this, 'flush' ) );
        add_action( 'delete_option_' . self::OPTION_KEY, array( $this, 'flush' ) );
    }

    /**
     * Get the singleton.
     *
     * @return Settings_Service
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Settings_Service is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Block unserialization.
     *
     * @return void
     * @throws \Exception When invoked.
     */
    public function __wakeup() {
        throw new \Exception( esc_html__( 'Unserializing Settings_Service is not allowed.', 'duplicate-pp' ) );
    }

    /**
     * Get the canonical defaults used when the option is empty or missing keys.
     *
     * The defaults reproduce the behavior of 3.x so upgrading sites keep
     * duplicating exactly as before until they change a setting.
     *
     * @return array
     */
    public function defaults() {
        return array(
            'post_status'          => 'draft',
            'title_prefix'         => '',
            'title_suffix'         => ' (Copy)',
            'slug_prefix'          => '',
            'slug_suffix'          => '-copy',
            'after_clone'          => 'list',
            'copy'                 => array(
                'content'        => 1,
                'excerpt'        => 1,
                'thumbnail'      => 1,
                'template'       => 1,
                'format'         => 1,
                'taxonomies'     => 1,
                'meta'           => 1,
                'password'       => 1,
                'menu_order'     => 1,
                'comment_status' => 1,
                'author'         => 0,
                'date'           => 0,
                'children'       => 0,
                'comments'       => 0,
            ),
            'menu_order_increment' => 0,
            'max_copies'           => 50,
            'exclude_meta'         => array(),
            'exclude_taxonomies'   => array(),
            'post_types'           => array_keys( $this->available_post_types() ),
            'roles'                => $this->default_roles(),
            'rewrite_republish'    => 1,
            'show_in'              => array(
                'row'       => 1,
                'bulk'      => 1,
                'admin_bar' => 1,
                'editor'    => 1,
            ),
            'show_original'        => array(
                'editor'     => 1,
                'column'     => 0,
                'post_state' => 0,
            ),
        );
    }

    /**
     * Get the full settings array, merged with defaults so every key is present.
     *
     * @return array
     */
    public function all() {
        if ( null === $this->cache ) {
            $stored = get_option( self::OPTION_KEY, array() );
            if ( ! is_array( $stored ) ) {
                $stored = array();
            }
            $defaults = $this->defaults();
            $merged   = wp_parse_args( $stored, $defaults );

            foreach ( self::SWITCH_GROUPS as $group ) {
                $value            = is_array( $merged[ $group ] ) ? $merged[ $group ] : array();
                $merged[ $group ] = wp_parse_args( $value, $defaults[ $group ] );
            }

            /**
             * Filters the resolved Duplicate PP settings.
             *
             * @since 4.0.0
             *
             * @param array $merged Settings merged with defaults.
             */
            $this->cache = apply_filters( 'dpp_settings', $merged );
        }
        return $this->cache;
    }

    /**
     * Get a single setting by key.
     *
     * @param string $key      Option key.
     * @param mixed  $fallback Fallback value if the key is missing.
     * @return mixed
     */
    public function get( $key, $fallback = null ) {
        $all = $this->all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
    }

    /**
     * Whether a switch inside one of the switch groups is on.
     *
     * @param string $group Group key: copy, show_in or show_original.
     * @param string $key   Switch key inside the group.
     * @return bool
     */
    public function is_on( $group, $key ) {
        $values = $this->get( $group, array() );
        return is_array( $values ) && ! empty( $values[ $key ] );
    }

    /**
     * Drop the cached option value.
     *
     * @return void
     */
    public function flush() {
        $this->cache = null;
    }

    /**
     * Post types that can be enabled for duplication, keyed by name.
     *
     * @return \WP_Post_Type[]
     */
    public function available_post_types() {
        $types = get_post_types( array( 'show_ui' => true ), 'objects' );
        foreach ( self::EXCLUDED_POST_TYPES as $excluded ) {
            unset( $types[ $excluded ] );
        }

        /**
         * Filters the post types Duplicate PP can be enabled for.
         *
         * @since 4.0.0
         *
         * @param \WP_Post_Type[] $types Post type objects keyed by name.
         */
        return apply_filters( 'dpp_available_post_types', $types );
    }

    /**
     * Roles that may duplicate by default: every role able to edit posts,
     * which matches the capability check used before 4.0.
     *
     * @return string[]
     */
    public function default_roles() {
        $roles = array();
        foreach ( wp_roles()->roles as $role_key => $role ) {
            if ( ! empty( $role['capabilities']['edit_posts'] ) ) {
                $roles[] = $role_key;
            }
        }
        return $roles;
    }
}
