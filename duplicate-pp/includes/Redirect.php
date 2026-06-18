<?php
/**
 * Activation redirect consumer.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

use DuplicatePP\Settings\Settings_Page;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Redirect
 *
 * Consumes the dpp_activation_redirect option (set by Activator)
 * and redirects the user to the settings page the first time they
 * hit the admin after activation. The option is deleted on read so
 * the redirect is one-shot.
 */
final class Redirect {

    /**
     * Option key — preserved for backward compatibility.
     */
    const OPTION_KEY = 'dpp_activation_redirect';

    /**
     * Single instance.
     *
     * @var Redirect|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Redirect
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Redirect is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Register hooks. Called by Plugin::boot().
     *
     * @return void
     */
    public function register() {
        add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
    }

    /**
     * Perform the one-shot activation redirect.
     *
     * @return void
     */
    public function maybe_redirect() {
        if ( ! get_option( self::OPTION_KEY, false ) ) {
            return;
        }
        delete_option( self::OPTION_KEY );

        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=' . Settings_Page::PAGE_SLUG ) );
        exit;
    }
}
