<?php
/**
 * Plugin uninstall handler.
 *
 * Removes the options the plugin writes to wp_options when the
 * plugin is fully uninstalled (deleted from the Plugins screen).
 * Not loaded on regular deactivation.
 *
 * @package DuplicatePP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'dpp_settings' );
delete_option( 'dpp_activation_redirect' );
