<?php
/**
 * Plugin Name: Duplicate PP
 * Description: Duplicate posts, pages and custom post types in one click — with custom fields, terms and page builder data. Includes New Draft, bulk duplicate and Rewrite & Republish.
 * Author:      Zakaria Binsaifullah
 * Author URI:  https://gutenbergkits.com
 * Version:     4.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: duplicate-pp
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 *
 * @package DuplicatePP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Public constants. Kept for backward compatibility with any
 * third-party code that might reference them.
 */
define( 'DPP_VERSION',      '4.0.1' );
define( 'DPP_PLUGIN_FILE',  __FILE__ );
define( 'DPP_PLUGIN_DIR',   plugin_dir_path( __FILE__ ) );
define( 'DPP_PLUGIN_URL',   plugin_dir_url( __FILE__ ) );

/**
 * Class includes — dependency order: leaves first.
 */
require_once DPP_PLUGIN_DIR . 'includes/Activator.php';
require_once DPP_PLUGIN_DIR . 'includes/Settings/Settings_Service.php';
require_once DPP_PLUGIN_DIR . 'includes/Settings/Welcome_Tab.php';
require_once DPP_PLUGIN_DIR . 'includes/Settings/Settings_Page.php';
require_once DPP_PLUGIN_DIR . 'includes/Meta/Meta_Handler.php';
require_once DPP_PLUGIN_DIR . 'includes/Permissions.php';
require_once DPP_PLUGIN_DIR . 'includes/Links.php';
require_once DPP_PLUGIN_DIR . 'includes/Rewrite_Republish.php';
require_once DPP_PLUGIN_DIR . 'includes/Editor.php';
require_once DPP_PLUGIN_DIR . 'includes/Columns.php';
require_once DPP_PLUGIN_DIR . 'includes/Row_Actions.php';
require_once DPP_PLUGIN_DIR . 'includes/Admin_Bar.php';
require_once DPP_PLUGIN_DIR . 'includes/Notices.php';
require_once DPP_PLUGIN_DIR . 'includes/Redirect.php';
require_once DPP_PLUGIN_DIR . 'includes/Duplicator.php';
require_once DPP_PLUGIN_DIR . 'includes/Plugin.php';
require_once DPP_PLUGIN_DIR . 'includes/Cartlet_Card.php';

/**
 * Bootstrap.
 */
\DuplicatePP\Plugin::instance()->boot( DPP_PLUGIN_FILE );

/**
 * Cartlet recommendation on the Welcome tab (WooCommerce sites only).
 */
if ( is_admin() ) {
    ( new DPP_Cartlet_Card(
        'settings_page_' . \DuplicatePP\Settings\Settings_Page::PAGE_SLUG,
        DPP_PLUGIN_URL . 'img/cartlet.png',
        'dpp_welcome_tab_after'
    ) )->register();
}
