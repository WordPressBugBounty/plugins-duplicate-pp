<?php
/**
 * Root plugin singleton.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Plugin
 *
 * Owns and lazily exposes every feature singleton. All WordPress hook
 * registration is funneled through this class so the rest of the
 * codebase can stay declarative.
 */
final class Plugin {

    /**
     * Single instance of the plugin.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Absolute path to the root plugin file.
     *
     * @var string
     */
    private $plugin_file = '';

    /**
     * Cached feature singletons.
     */
    private $duplicator    = null;
    private $row_actions   = null;
    private $admin_bar     = null;
    private $notices       = null;
    private $redirect      = null;
    private $settings_service = null;
    private $welcome_tab   = null;
    private $settings_page = null;
    private $meta_handler  = null;

    /**
     * Private constructor — use Plugin::instance().
     */
    private function __construct() {}

    /**
     * Block cloning.
     *
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning the Plugin singleton is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Block unserialization.
     *
     * @return void
     * @throws \Exception When invoked.
     */
    public function __wakeup() {
        throw new \Exception( esc_html__( 'Unserializing the Plugin singleton is not allowed.', 'duplicate-pp' ) );
    }

    /**
     * Get the singleton instance.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Boot the plugin: store the file path, register the activation hook,
     * load the text domain, and instantiate every feature singleton.
     *
     * @param string $plugin_file Absolute path to the root plugin file.
     * @return void
     */
    public function boot( $plugin_file ) {
        $this->plugin_file = $plugin_file;

        // Activation hook. Accepts a class-method callable.
        register_activation_hook( $this->plugin_file, array( __NAMESPACE__ . '\\Activator', 'activate' ) );

        // Resolve feature singletons. Every class owns its own construction
        // through a public static instance() method, so we never need to
        // call `new ClassName()` from here (and so we never need to relax
        // their private constructors).
        $this->duplicator       = Duplicator::instance();
        $this->row_actions      = Row_Actions::instance();
        $this->admin_bar        = Admin_Bar::instance();
        $this->notices          = Notices::instance();
        $this->redirect         = Redirect::instance();
        $this->settings_service = Settings\Settings_Service::instance();
        $this->welcome_tab      = Settings\Welcome_Tab::instance();
        $this->settings_page    = Settings\Settings_Page::instance( $this->settings_service, $this->welcome_tab );
        $this->meta_handler     = Meta\Meta_Handler::instance();

        // Give every feature a chance to register its hooks.
        $this->duplicator->register();
        $this->row_actions->register();
        $this->admin_bar->register();
        $this->notices->register();
        $this->redirect->register();
        $this->settings_page->register();
    }

    /**
     * Get the root plugin file path.
     *
     * @return string
     */
    public function plugin_file() {
        return $this->plugin_file;
    }

    /**
     * @return Duplicator
     */
    public function duplicator() {
        return $this->duplicator;
    }

    /**
     * @return Settings\Settings_Service
     */
    public function settings() {
        return $this->settings_service;
    }

    /**
     * @return Meta\Meta_Handler
     */
    public function meta_handler() {
        return $this->meta_handler;
    }
}
