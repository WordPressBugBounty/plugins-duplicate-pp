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
    private function __construct() {}

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
     * @return array
     */
    public function defaults() {
        return array(
            'post_status'  => 'draft',
            'title_prefix' => '',
            'title_suffix' => ' (Copy)',
            'slug_prefix'  => '',
            'slug_suffix'  => '-copy',
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
            $this->cache = wp_parse_args( $stored, $this->defaults() );
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
}
