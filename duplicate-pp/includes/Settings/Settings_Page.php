<?php
/**
 * Settings page — admin menu, registration, asset enqueue, render.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP\Settings;

use DuplicatePP\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Settings_Page
 *
 * Owns the Settings page. Reads settings through Settings_Service;
 * delegates the Welcome tab markup to Welcome_Tab. Inline CSS/JS
 * from the legacy implementation have been moved to external files
 * under assets/.
 */
final class Settings_Page {

    /**
     * Page slug — preserved for backward compatibility.
     */
    const PAGE_SLUG = 'duplicate-pp-settings';

    /**
     * register_setting() option group — preserved.
     */
    const OPTION_GROUP = 'dpp_settings';

    /**
     * Single instance.
     *
     * @var Settings_Page|null
     */
    private static $instance = null;

    /**
     * Settings service dependency.
     *
     * @var Settings_Service
     */
    private $settings_service;

    /**
     * Welcome tab dependency.
     *
     * @var Welcome_Tab
     */
    private $welcome_tab;

    /**
     * Private constructor.
     *
     * @param Settings_Service $settings_service Settings read service.
     * @param Welcome_Tab      $welcome_tab      Welcome tab renderer.
     */
    private function __construct( Settings_Service $settings_service, Welcome_Tab $welcome_tab ) {
        $this->settings_service = $settings_service;
        $this->welcome_tab      = $welcome_tab;
    }

    /**
     * Get the singleton. The first call passes the dependencies so
     * the constructor signature stays explicit; subsequent calls
     * return the already-built instance.
     *
     * @param Settings_Service|null $settings_service Optional initial service.
     * @param Welcome_Tab|null      $welcome_tab      Optional initial welcome tab.
     * @return Settings_Page
     */
    public static function instance( ?Settings_Service $settings_service = null, ?Welcome_Tab $welcome_tab = null ) {
        if ( null === self::$instance ) {
            if ( null === $settings_service ) {
                $settings_service = Settings_Service::instance();
            }
            if ( null === $welcome_tab ) {
                $welcome_tab = Welcome_Tab::instance();
            }
            self::$instance = new self( $settings_service, $welcome_tab );
        }
        return self::$instance;
    }

    /**
     * Block cloning.
     *
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Settings_Page is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Register all hooks. Called by Plugin::boot().
     *
     * @return void
     */
    public function register() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register the options page.
     *
     * @return void
     */
    public function add_menu() {
        add_options_page(
            __( 'Duplicate PP Settings', 'duplicate-pp' ),
            __( 'Duplicate PP', 'duplicate-pp' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue CSS/JS only on our settings page.
     *
     * @param string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueue_assets( $hook_suffix ) {
        // The hook for an options page added with add_options_page() is
        // 'settings_page_{page_slug}'. Guard accordingly.
        if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'duplicate-pp-admin',
            DPP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DPP_VERSION
        );

        wp_enqueue_style(
            'duplicate-pp-admin-settings',
            DPP_PLUGIN_URL . 'assets/css/admin-settings.css',
            array( 'duplicate-pp-admin' ),
            DPP_VERSION
        );

        wp_enqueue_script(
            'duplicate-pp-admin-settings',
            DPP_PLUGIN_URL . 'assets/js/admin-settings.js',
            array(),
            DPP_VERSION,
            true
        );
    }

    /**
     * Register the setting, the section, and the fields.
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            Settings_Service::OPTION_KEY,
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'dpp_general_section',
            __( 'General Settings', 'duplicate-pp' ),
            array( $this, 'section_description' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'post_status',
            __( 'Default Post Status', 'duplicate-pp' ),
            array( $this, 'post_status_field' ),
            self::PAGE_SLUG,
            'dpp_general_section'
        );

        $text_fields = array(
            'title_prefix' => __( 'Title Prefix', 'duplicate-pp' ),
            'title_suffix' => __( 'Title Suffix', 'duplicate-pp' ),
            'slug_prefix'  => __( 'Slug Prefix', 'duplicate-pp' ),
            'slug_suffix'  => __( 'Slug Suffix', 'duplicate-pp' ),
        );

        foreach ( $text_fields as $field_key => $field_label ) {
            add_settings_field(
                $field_key,
                $field_label,
                array( $this, 'text_field' ),
                self::PAGE_SLUG,
                'dpp_general_section',
                array( 'field' => $field_key )
            );
        }
    }

    /**
     * Section description callback.
     *
     * @return void
     */
    public function section_description() {
        echo '<p>' . esc_html__( 'Configure how duplicated posts should be handled.', 'duplicate-pp' ) . '</p>';
    }

    /**
     * Render the post status <select>.
     *
     * @return void
     */
    public function post_status_field() {
        $status = $this->settings_service->get( 'post_status', 'draft' );
        $options = array(
            'draft'   => __( 'Draft', 'duplicate-pp' ),
            'publish' => __( 'Published', 'duplicate-pp' ),
            'private' => __( 'Private', 'duplicate-pp' ),
            'pending' => __( 'Pending', 'duplicate-pp' ),
        );
        ?>
        <select name="<?php echo esc_attr( Settings_Service::OPTION_KEY ); ?>[post_status]">
            <?php foreach ( $options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     * Render a generic text input field.
     *
     * @param array $args Field arguments; expects 'field' => string.
     * @return void
     */
    public function text_field( $args ) {
        $field = isset( $args['field'] ) ? sanitize_key( $args['field'] ) : '';
        if ( '' === $field ) {
            return;
        }
        $value = $this->settings_service->get( $field, '' );
        ?>
        <input
            type="text"
            name="<?php echo esc_attr( Settings_Service::OPTION_KEY ); ?>[<?php echo esc_attr( $field ); ?>]"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
        >
        <?php
    }

    /**
     * Sanitize submitted settings before they are written to the option.
     *
     * Preserves the exact option shape for backward compatibility.
     *
     * @param array $input Raw submitted input.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $defaults = $this->settings_service->defaults();
        $sanitized = array();

        // post_status: pick from a known whitelist.
        $allowed_statuses = array( 'draft', 'publish', 'private', 'pending' );
        $raw_status       = isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : $defaults['post_status'];
        $sanitized['post_status'] = in_array( $raw_status, $allowed_statuses, true ) ? $raw_status : $defaults['post_status'];

        // Text fields: sanitize_text_field + fall back to defaults.
        $text_keys = array( 'title_prefix', 'title_suffix', 'slug_prefix', 'slug_suffix' );
        foreach ( $text_keys as $key ) {
            $sanitized[ $key ] = isset( $input[ $key ] )
                ? sanitize_text_field( wp_unslash( $input[ $key ] ) )
                : $defaults[ $key ];
        }

        return $sanitized;
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'duplicate-pp' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active" data-tab="dpp-promotions">
                    <?php esc_html_e( 'Welcome', 'duplicate-pp' ); ?>
                </a>
                <a href="#" class="nav-tab" data-tab="dpp-settings">
                    <?php esc_html_e( 'Settings', 'duplicate-pp' ); ?>
                </a>
            </h2>

            <div id="dpp-promotions" class="tab-content active">
                <?php $this->welcome_tab->render(); ?>
            </div>

            <div id="dpp-settings" class="tab-content">
                <form action="options.php" method="post">
                    <?php
                    settings_fields( self::OPTION_GROUP );
                    do_settings_sections( self::PAGE_SLUG );
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }
}
