<?php
/**
 * Plugin activation handler.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Activator
 *
 * Registered as the activation_hook callback from Plugin::boot().
 * The single responsibility here is to mark the option that
 * Redirect::maybe_redirect() consumes on the next admin_init.
 */
final class Activator {

    /**
     * Single instance.
     *
     * @var Activator|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Activator
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Activation callback: set the redirect flag.
     *
     * @return void
     */
    public static function activate() {
        // The flag is consumed and deleted by Redirect::maybe_redirect().
        add_option( 'dpp_activation_redirect', true );
    }
}
