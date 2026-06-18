<?php
/**
 * Plugin Name: Duplicate PP
 * Description: <strong>Duplicate PP</strong> is a simple plugin which allows you to duplicate any POST,PAGE and CPT Easily with full meta data support.
 * Author:      Zakaria Binsaifullah
 * Author URI:  https://gutenbergkits.com
 * Version:     3.7.0
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
define( 'DPP_VERSION',      '3.7.0' );
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
require_once DPP_PLUGIN_DIR . 'includes/Row_Actions.php';
require_once DPP_PLUGIN_DIR . 'includes/Admin_Bar.php';
require_once DPP_PLUGIN_DIR . 'includes/Notices.php';
require_once DPP_PLUGIN_DIR . 'includes/Redirect.php';
require_once DPP_PLUGIN_DIR . 'includes/Duplicator.php';
require_once DPP_PLUGIN_DIR . 'includes/Plugin.php';

/**
 * Bootstrap.
 */
\DuplicatePP\Plugin::instance()->boot( DPP_PLUGIN_FILE );
