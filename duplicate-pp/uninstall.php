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
delete_option( 'dpp_stats' );

// Rewrite & Republish links between originals and copies. The copies
// themselves are regular drafts and are left for the site owner.
delete_post_meta_by_key( '_dpp_rewrite_copy' );
delete_post_meta_by_key( '_dpp_rewrite_original' );
delete_post_meta_by_key( '_dpp_republished' );
