<?php
/**
 * Settings page — admin menu, registration, asset enqueue, render.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP\Settings;

use DuplicatePP\Duplicator;
use DuplicatePP\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Settings_Page
 *
 * Owns the Settings → Duplicate PP page: a dashboard tab (rendered by
 * Welcome_Tab), three settings tabs saved through one Settings API
 * form, and a help tab.
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
     * admin-post action that restores the defaults.
     */
    const RESET_ACTION = 'dpp_reset_settings';

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
        add_action( 'admin_post_' . self::RESET_ACTION, array( $this, 'handle_reset' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( DPP_PLUGIN_FILE ), array( $this, 'action_links' ) );
        add_action( 'wp_ajax_' . Welcome_Tab::SEARCH_ACTION, array( $this->welcome_tab, 'ajax_search' ) );
        add_action( 'dpp_after_republish', array( Welcome_Tab::class, 'count_republish' ) );
    }

    /**
     * URL of the page, optionally on a given tab.
     *
     * @param string $tab Tab key.
     * @return string
     */
    public static function url( $tab = '' ) {
        $url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
        return $tab ? add_query_arg( 'tab', $tab, $url ) : $url;
    }

    /**
     * Tabs: key => [ label, dashicon ].
     *
     * @return array
     */
    private function tabs() {
        return array(
            'dashboard'   => array( __( 'Dashboard', 'duplicate-pp' ), 'dashicons-dashboard' ),
            'copy'        => array( __( 'What to copy', 'duplicate-pp' ), 'dashicons-admin-page' ),
            'permissions' => array( __( 'Permissions', 'duplicate-pp' ), 'dashicons-lock' ),
            'display'     => array( __( 'Display', 'duplicate-pp' ), 'dashicons-visibility' ),
            'help'        => array( __( 'Help', 'duplicate-pp' ), 'dashicons-editor-help' ),
        );
    }

    /**
     * Status choices for copies.
     *
     * @return array
     */
    private function status_options() {
        return array(
            'draft'   => __( 'Draft', 'duplicate-pp' ),
            'pending' => __( 'Pending review', 'duplicate-pp' ),
            'private' => __( 'Private', 'duplicate-pp' ),
            'publish' => __( 'Published', 'duplicate-pp' ),
            'same'    => __( 'Same as the original', 'duplicate-pp' ),
        );
    }

    /**
     * "What to copy" switches: key => [ label, description ].
     *
     * @return array
     */
    private function copy_options() {
        return array(
            'content'        => array( __( 'Content', 'duplicate-pp' ), __( 'Blocks, text and media in the body.', 'duplicate-pp' ) ),
            'excerpt'        => array( __( 'Excerpt', 'duplicate-pp' ), __( 'The manual excerpt.', 'duplicate-pp' ) ),
            'thumbnail'      => array( __( 'Featured image', 'duplicate-pp' ), __( 'Reuses the same media file.', 'duplicate-pp' ) ),
            'template'       => array( __( 'Template', 'duplicate-pp' ), __( 'The page or post template.', 'duplicate-pp' ) ),
            'format'         => array( __( 'Post format', 'duplicate-pp' ), __( 'Aside, gallery, quote…', 'duplicate-pp' ) ),
            'taxonomies'     => array( __( 'Categories, tags & terms', 'duplicate-pp' ), __( 'Every taxonomy not excluded below.', 'duplicate-pp' ) ),
            'meta'           => array( __( 'Custom fields', 'duplicate-pp' ), __( 'Post meta, including ACF and page builder data (Elementor, WPBakery, Beaver Builder).', 'duplicate-pp' ) ),
            'password'       => array( __( 'Password', 'duplicate-pp' ), __( 'Keeps password protection.', 'duplicate-pp' ) ),
            'menu_order'     => array( __( 'Menu order', 'duplicate-pp' ), __( 'The order attribute of pages.', 'duplicate-pp' ) ),
            'comment_status' => array( __( 'Discussion settings', 'duplicate-pp' ), __( 'Whether comments and pings are open.', 'duplicate-pp' ) ),
            'author'         => array( __( 'Original author', 'duplicate-pp' ), __( 'Off: you become the author of the copy.', 'duplicate-pp' ) ),
            'date'           => array( __( 'Original date', 'duplicate-pp' ), __( 'Off: the copy gets the current date.', 'duplicate-pp' ) ),
            'children'       => array( __( 'Child pages', 'duplicate-pp' ), __( 'Copies the whole branch under the new parent.', 'duplicate-pp' ) ),
            'comments'       => array( __( 'Comments', 'duplicate-pp' ), __( 'Copies comments with their replies.', 'duplicate-pp' ) ),
        );
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
     * Add a Settings link on the Plugins screen.
     *
     * @param array $links Action links.
     * @return array
     */
    public function action_links( $links ) {
        array_unshift(
            $links,
            sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'duplicate-pp' ) )
        );
        return $links;
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

        wp_enqueue_script(
            'duplicate-pp-admin-settings',
            DPP_PLUGIN_URL . 'assets/js/admin-settings.js',
            array(),
            DPP_VERSION,
            true
        );
    }

    /**
     * Register the setting.
     *
     * The fields are rendered by render_page() rather than through
     * add_settings_field(), so the page can use its own layout.
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            Settings_Service::OPTION_KEY,
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitize submitted settings before they are written to the option.
     *
     * Keys from 3.x keep their names and meaning.
     *
     * @param array $input Raw submitted input (already unslashed by options.php).
     * @return array
     */
    public function sanitize_settings( $input ) {
        $defaults = $this->settings_service->defaults();
        if ( ! is_array( $input ) ) {
            return $defaults;
        }

        $sanitized = array();

        // post_status: pick from a known whitelist.
        $raw_status               = isset( $input['post_status'] ) ? sanitize_key( $input['post_status'] ) : '';
        $sanitized['post_status'] = array_key_exists( $raw_status, $this->status_options() ) ? $raw_status : $defaults['post_status'];

        // Title affixes keep their spaces: " (Copy)" must not become "(Copy)".
        foreach ( array( 'title_prefix', 'title_suffix' ) as $key ) {
            $sanitized[ $key ] = isset( $input[ $key ] ) ? $this->sanitize_affix( $input[ $key ] ) : $defaults[ $key ];
        }
        foreach ( array( 'slug_prefix', 'slug_suffix' ) as $key ) {
            $sanitized[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $defaults[ $key ];
        }

        $sanitized['after_clone']          = isset( $input['after_clone'] ) && 'edit' === $input['after_clone'] ? 'edit' : 'list';
        $sanitized['menu_order_increment'] = isset( $input['menu_order_increment'] ) ? max( -1000, min( 1000, (int) $input['menu_order_increment'] ) ) : 0;
        $sanitized['max_copies']           = isset( $input['max_copies'] ) ? max( 1, min( Duplicator::MAX_COPIES_LIMIT, (int) $input['max_copies'] ) ) : $defaults['max_copies'];
        $sanitized['rewrite_republish']    = empty( $input['rewrite_republish'] ) ? 0 : 1;

        foreach ( Settings_Service::SWITCH_GROUPS as $group ) {
            $sanitized[ $group ] = array();
            foreach ( array_keys( $defaults[ $group ] ) as $key ) {
                $sanitized[ $group ][ $key ] = empty( $input[ $group ][ $key ] ) ? 0 : 1;
            }
        }

        // Excluded meta: one key or pattern per line (commas also work).
        $raw_meta = isset( $input['exclude_meta'] ) ? $input['exclude_meta'] : array();
        if ( is_array( $raw_meta ) ) {
            $raw_meta = implode( "\n", $raw_meta );
        }
        $meta_keys                 = preg_split( '/[\r\n,]+/', (string) $raw_meta );
        $meta_keys                 = array_map( 'sanitize_text_field', $meta_keys );
        $sanitized['exclude_meta'] = array_values( array_unique( array_filter( $meta_keys, 'strlen' ) ) );

        $sanitized['exclude_taxonomies'] = $this->pick( $input, 'exclude_taxonomies', array_keys( get_taxonomies() ) );
        $sanitized['post_types']         = $this->pick( $input, 'post_types', array_keys( $this->settings_service->available_post_types() ) );
        $sanitized['roles']              = $this->pick( $input, 'roles', array_keys( wp_roles()->roles ) );

        return $sanitized;
    }

    /**
     * Keep the submitted values of a list field that are in $allowed.
     *
     * @param array    $input   Submitted input.
     * @param string   $key     Field key.
     * @param string[] $allowed Allowed values.
     * @return string[]
     */
    private function pick( $input, $key, $allowed ) {
        $values = isset( $input[ $key ] ) ? (array) $input[ $key ] : array();
        $values = array_map( 'sanitize_key', $values );
        return array_values( array_intersect( $values, $allowed ) );
    }

    /**
     * Sanitize a title prefix/suffix without trimming its spaces.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private function sanitize_affix( $value ) {
        $value = wp_check_invalid_utf8( (string) $value );
        $value = wp_strip_all_tags( '.' . $value . '.' );
        $value = preg_replace( '/[\r\n\t ]+/', ' ', substr( $value, 1, -1 ) );
        return (string) $value;
    }

    /**
     * Restore the default settings.
     *
     * @return void
     */
    public function handle_reset() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'duplicate-pp' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( self::RESET_ACTION );

        delete_option( Settings_Service::OPTION_KEY );

        wp_safe_redirect( add_query_arg( 'dpp_reset', 1, self::url( 'copy' ) ) );
        exit;
    }

    /**
     * Active tab from the URL.
     *
     * @return string
     */
    private function current_tab() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
        return array_key_exists( $tab, $this->tabs() ) ? $tab : 'dashboard';
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

        $current  = $this->current_tab();
        $settings = $this->settings_service->all();
        $is_form  = in_array( $current, array( 'copy', 'permissions', 'display' ), true );
        ?>
        <div class="wrap dpp-wrap">
            <header class="dpp-header">
                <div class="dpp-header__inner">
                    <a class="dpp-brand" href="<?php echo esc_url( self::url() ); ?>" data-tab-link="dashboard">
                        <span class="dpp-brand__logo" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6.5A2.5 2.5 0 0 0 13.5 4h-7A2.5 2.5 0 0 0 4 6.5v7A2.5 2.5 0 0 0 6.5 16H8"/></svg>
                        </span>
                        <span class="dpp-brand__title"><?php esc_html_e( 'Duplicate PP', 'duplicate-pp' ); ?></span>
                        <span class="dpp-pill"><?php echo esc_html( DPP_VERSION ); ?></span>
                    </a>
                    <nav class="dpp-tabs" aria-label="<?php esc_attr_e( 'Duplicate PP sections', 'duplicate-pp' ); ?>">
                        <?php foreach ( $this->tabs() as $key => $tab ) : ?>
                            <a href="<?php echo esc_url( self::url( $key ) ); ?>"
                                class="dpp-tab<?php echo $current === $key ? ' is-active' : ''; ?>"
                                data-tab="<?php echo esc_attr( $key ); ?>"
                                <?php echo $current === $key ? 'aria-current="page"' : ''; ?>>
                                <span class="dashicons <?php echo esc_attr( $tab[1] ); ?>" aria-hidden="true"></span>
                                <span><?php echo esc_html( $tab[0] ); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <div class="dpp-header__links">
                        <a class="dpp-icon-btn" href="<?php echo esc_url( 'https://gutenbergkits.com/contact' ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Support', 'duplicate-pp' ); ?>">
                            <span class="dashicons dashicons-sos" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Support', 'duplicate-pp' ); ?></span>
                        </a>
                        <a class="dpp-btn dpp-btn--ghost dpp-btn--sm" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/duplicate-pp/reviews/#new-post' ); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Rate', 'duplicate-pp' ); ?>
                        </a>
                    </div>
                </div>
            </header>

            <div class="dpp-body">
                <h1 class="screen-reader-text"><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <hr class="wp-header-end">
                <?php $this->render_reset_notice(); ?>

                <section class="dpp-panel<?php echo 'dashboard' === $current ? ' is-active' : ''; ?>" data-panel="dashboard">
                    <?php $this->welcome_tab->render(); ?>
                </section>

                <form action="options.php" method="post" class="dpp-form<?php echo $is_form ? ' is-active' : ''; ?>">
                    <?php settings_fields( self::OPTION_GROUP ); ?>

                    <section class="dpp-panel<?php echo 'copy' === $current ? ' is-active' : ''; ?>" data-panel="copy">
                        <?php $this->render_copy_tab( $settings ); ?>
                    </section>
                    <section class="dpp-panel<?php echo 'permissions' === $current ? ' is-active' : ''; ?>" data-panel="permissions">
                        <?php $this->render_permissions_tab( $settings ); ?>
                    </section>
                    <section class="dpp-panel<?php echo 'display' === $current ? ' is-active' : ''; ?>" data-panel="display">
                        <?php $this->render_display_tab( $settings ); ?>
                    </section>

                    <div class="dpp-savebar">
                        <p class="dpp-savebar__hint"><?php esc_html_e( 'Settings on all tabs are saved together.', 'duplicate-pp' ); ?></p>
                        <a class="button button-link dpp-savebar__reset" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::RESET_ACTION ), self::RESET_ACTION ) ); ?>"
                            data-confirm="<?php esc_attr_e( 'Restore all Duplicate PP settings to their defaults?', 'duplicate-pp' ); ?>">
                            <?php esc_html_e( 'Reset to defaults', 'duplicate-pp' ); ?>
                        </a>
                        <?php submit_button( __( 'Save changes', 'duplicate-pp' ), 'primary large', 'submit', false ); ?>
                    </div>
                </form>

                <section class="dpp-panel<?php echo 'help' === $current ? ' is-active' : ''; ?>" data-panel="help">
                    <?php $this->render_help_tab(); ?>
                </section>
            </div>
        </div>
        <?php
    }

    /**
     * Notice after restoring defaults.
     *
     * @return void
     */
    private function render_reset_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
        if ( empty( $_GET['dpp_reset'] ) ) {
            return;
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings restored to their defaults.', 'duplicate-pp' ) . '</p></div>';
    }

    /**
     * "What to copy" tab.
     *
     * @param array $settings Current settings.
     * @return void
     */
    private function render_copy_tab( $settings ) {
        $taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
        ?>
        <div class="dpp-grid dpp-grid--2">
            <div class="dpp-card">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'Title & slug', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'Make copies easy to tell apart from their original.', 'duplicate-pp' ); ?></p>
                </div>
                <div class="dpp-fields dpp-fields--pair">
                    <?php
                    $this->text_input( 'title_prefix', __( 'Title prefix', 'duplicate-pp' ), $settings, __( 'e.g. Draft:', 'duplicate-pp' ) );
                    $this->text_input( 'title_suffix', __( 'Title suffix', 'duplicate-pp' ), $settings, __( 'e.g. (Copy)', 'duplicate-pp' ) );
                    $this->text_input( 'slug_prefix', __( 'Slug prefix', 'duplicate-pp' ), $settings, __( 'e.g. copy-', 'duplicate-pp' ) );
                    $this->text_input( 'slug_suffix', __( 'Slug suffix', 'duplicate-pp' ), $settings, __( 'e.g. -copy', 'duplicate-pp' ) );
                    ?>
                </div>
                <div class="dpp-preview" aria-live="polite">
                    <span class="dpp-preview__label"><?php esc_html_e( 'Preview', 'duplicate-pp' ); ?></span>
                    <span class="dpp-preview__title" data-sample="<?php esc_attr_e( 'Hello world', 'duplicate-pp' ); ?>"></span>
                    <code class="dpp-preview__slug" data-sample="hello-world"></code>
                </div>
            </div>

            <div class="dpp-card">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'Status & behavior', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'How a new copy starts its life.', 'duplicate-pp' ); ?></p>
                </div>
                <div class="dpp-fields">
                    <div class="dpp-field">
                        <label class="dpp-field__label" for="dpp-post-status"><?php esc_html_e( 'Status of copies', 'duplicate-pp' ); ?></label>
                        <select id="dpp-post-status" name="<?php echo esc_attr( $this->name( 'post_status' ) ); ?>">
                            <?php foreach ( $this->status_options() as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['post_status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="dpp-field__help"><?php esc_html_e( '"New Draft" always creates a draft. Users who cannot publish get "Pending review" instead of a published copy.', 'duplicate-pp' ); ?></p>
                    </div>

                    <fieldset class="dpp-field">
                        <legend class="dpp-field__label"><?php esc_html_e( 'After "Duplicate"', 'duplicate-pp' ); ?></legend>
                        <div class="dpp-choices">
                            <?php
                            $choices = array(
                                'list' => array( __( 'Stay on the list', 'duplicate-pp' ), 'dashicons-list-view' ),
                                'edit' => array( __( 'Open the copy', 'duplicate-pp' ), 'dashicons-edit' ),
                            );
                            foreach ( $choices as $value => $choice ) :
                                ?>
                                <label class="dpp-choice">
                                    <input type="radio" name="<?php echo esc_attr( $this->name( 'after_clone' ) ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['after_clone'], $value ); ?>>
                                    <span class="dpp-choice__box">
                                        <span class="dashicons <?php echo esc_attr( $choice[1] ); ?>" aria-hidden="true"></span>
                                        <?php echo esc_html( $choice[0] ); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="dpp-field">
                        <label class="dpp-field__label" for="dpp-menu-order"><?php esc_html_e( 'Increase menu order by', 'duplicate-pp' ); ?></label>
                        <input id="dpp-menu-order" type="number" class="small-text" min="-1000" max="1000" step="1"
                            name="<?php echo esc_attr( $this->name( 'menu_order_increment' ) ); ?>"
                            value="<?php echo esc_attr( (int) $settings['menu_order_increment'] ); ?>">
                        <p class="dpp-field__help"><?php esc_html_e( 'Places copies after their original in ordered lists. Applies when menu order is copied.', 'duplicate-pp' ); ?></p>
                    </div>

                    <div class="dpp-field">
                        <label class="dpp-field__label" for="dpp-max-copies"><?php esc_html_e( 'Maximum copies at once', 'duplicate-pp' ); ?></label>
                        <input id="dpp-max-copies" type="number" class="small-text" min="1" max="<?php echo esc_attr( Duplicator::MAX_COPIES_LIMIT ); ?>" step="1"
                            name="<?php echo esc_attr( $this->name( 'max_copies' ) ); ?>"
                            value="<?php echo esc_attr( (int) $settings['max_copies'] ); ?>">
                        <p class="dpp-field__help">
                            <?php
                            /* translators: %d: hard limit of copies. */
                            echo esc_html( sprintf( __( 'Upper limit for "Copy multiple" and "Duplicate multiple times", per item (1–%d).', 'duplicate-pp' ), Duplicator::MAX_COPIES_LIMIT ) );
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="dpp-card">
            <div class="dpp-card__head dpp-card__head--split">
                <div>
                    <h2><?php esc_html_e( 'Post elements', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'Choose what goes into a copy. The title is always copied. Rewrite & Republish copies always include everything.', 'duplicate-pp' ); ?></p>
                </div>
                <div class="dpp-bulk-toggle" data-target="dpp-copy-switches">
                    <button type="button" class="button-link" data-state="1"><?php esc_html_e( 'All', 'duplicate-pp' ); ?></button>
                    <span aria-hidden="true">·</span>
                    <button type="button" class="button-link" data-state="0"><?php esc_html_e( 'None', 'duplicate-pp' ); ?></button>
                </div>
            </div>
            <div class="dpp-switches" id="dpp-copy-switches">
                <?php
                foreach ( $this->copy_options() as $key => $option ) {
                    $this->toggle( $this->name( 'copy', $key ), ! empty( $settings['copy'][ $key ] ), $option[0], $option[1] );
                }
                ?>
            </div>
        </div>

        <div class="dpp-grid dpp-grid--2">
            <div class="dpp-card">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'Do not copy these fields', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'One meta key per line. Use * as a wildcard, e.g. _yoast_wpseo_* or custom_field_*.', 'duplicate-pp' ); ?></p>
                </div>
                <textarea class="dpp-textarea" rows="7" spellcheck="false"
                    name="<?php echo esc_attr( $this->name( 'exclude_meta' ) ); ?>"
                    placeholder="<?php echo esc_attr( "_my_counter\nlegacy_*" ); ?>"><?php echo esc_textarea( implode( "\n", (array) $settings['exclude_meta'] ) ); ?></textarea>
            </div>

            <div class="dpp-card">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'Do not copy these taxonomies', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'Terms of the checked taxonomies stay with the original.', 'duplicate-pp' ); ?></p>
                </div>
                <div class="dpp-checks">
                    <?php
                    foreach ( $taxonomies as $taxonomy ) {
                        if ( 'post_format' === $taxonomy->name ) {
                            continue;
                        }
                        $this->check(
                            $this->name( 'exclude_taxonomies' ) . '[]',
                            $taxonomy->name,
                            in_array( $taxonomy->name, (array) $settings['exclude_taxonomies'], true ),
                            $taxonomy->labels->name,
                            $taxonomy->name
                        );
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * "Permissions" tab.
     *
     * @param array $settings Current settings.
     * @return void
     */
    private function render_permissions_tab( $settings ) {
        ?>
        <div class="dpp-card">
            <div class="dpp-card__head dpp-card__head--split">
                <div>
                    <h2><?php esc_html_e( 'Enabled post types', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'Copy links and bulk actions appear only for these content types.', 'duplicate-pp' ); ?></p>
                </div>
                <div class="dpp-bulk-toggle" data-target="dpp-post-types">
                    <button type="button" class="button-link" data-state="1"><?php esc_html_e( 'All', 'duplicate-pp' ); ?></button>
                    <span aria-hidden="true">·</span>
                    <button type="button" class="button-link" data-state="0"><?php esc_html_e( 'None', 'duplicate-pp' ); ?></button>
                </div>
            </div>
            <div class="dpp-checks" id="dpp-post-types">
                <?php
                foreach ( $this->settings_service->available_post_types() as $type ) {
                    $this->check(
                        $this->name( 'post_types' ) . '[]',
                        $type->name,
                        in_array( $type->name, (array) $settings['post_types'], true ),
                        $type->labels->name,
                        $type->name
                    );
                }
                ?>
            </div>
        </div>

        <div class="dpp-grid dpp-grid--2">
            <div class="dpp-card">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'Roles allowed to copy', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'Users also need permission to edit the item and to create items of its type. Administrators can always copy.', 'duplicate-pp' ); ?></p>
                </div>
                <div class="dpp-checks">
                    <?php
                    foreach ( wp_roles()->roles as $role_key => $role ) {
                        if ( empty( $role['capabilities']['edit_posts'] ) ) {
                            continue;
                        }
                        $is_admin = 'administrator' === $role_key;
                        $this->check(
                            $this->name( 'roles' ) . '[]',
                            $role_key,
                            $is_admin || in_array( $role_key, (array) $settings['roles'], true ),
                            translate_user_role( $role['name'] ),
                            '',
                            $is_admin
                        );
                    }
                    ?>
                </div>
            </div>

            <div class="dpp-card dpp-card--feature">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'Rewrite & Republish', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'Update a published post safely: edit a copy behind the scenes, then republish it over the original — same URL, same ID, no downtime. Schedule it to go live later.', 'duplicate-pp' ); ?></p>
                </div>
                <div class="dpp-switches dpp-switches--single">
                    <?php $this->toggle( $this->name( 'rewrite_republish' ), ! empty( $settings['rewrite_republish'] ), __( 'Enable Rewrite & Republish', 'duplicate-pp' ), __( 'Available to users who can publish the item.', 'duplicate-pp' ) ); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * "Display" tab.
     *
     * @param array $settings Current settings.
     * @return void
     */
    private function render_display_tab( $settings ) {
        $show_in = array(
            'row'       => array( __( 'Post list rows', 'duplicate-pp' ), __( 'Duplicate, New Draft and Rewrite & Republish under each title.', 'duplicate-pp' ) ),
            'bulk'      => array( __( 'Bulk actions', 'duplicate-pp' ), __( 'Copy many items at once from the list.', 'duplicate-pp' ) ),
            'admin_bar' => array( __( 'Admin bar', 'duplicate-pp' ), __( 'A "Duplicate This" menu while viewing or editing.', 'duplicate-pp' ) ),
            'editor'    => array( __( 'Editor', 'duplicate-pp' ), __( 'Buttons in the block editor summary panel and the classic publish box.', 'duplicate-pp' ) ),
        );
        $show_original = array(
            'editor'     => array( __( 'In the editor', 'duplicate-pp' ), __( 'A "Copy of" link to the original.', 'duplicate-pp' ) ),
            'column'     => array( __( 'In a list column', 'duplicate-pp' ), __( 'An "Original" column in the post lists.', 'duplicate-pp' ) ),
            'post_state' => array( __( 'As a label', 'duplicate-pp' ), __( 'Adds "— Copy" after the title in lists.', 'duplicate-pp' ) ),
        );
        ?>
        <div class="dpp-grid dpp-grid--2">
            <div class="dpp-card">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'Show copy links in', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'Where people find the duplicate actions.', 'duplicate-pp' ); ?></p>
                </div>
                <div class="dpp-switches dpp-switches--stack">
                    <?php
                    foreach ( $show_in as $key => $option ) {
                        $this->toggle( $this->name( 'show_in', $key ), ! empty( $settings['show_in'][ $key ] ), $option[0], $option[1] );
                    }
                    ?>
                </div>
            </div>

            <div class="dpp-card">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'Show the original', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'Help editors see where a copy came from.', 'duplicate-pp' ); ?></p>
                </div>
                <div class="dpp-switches dpp-switches--stack">
                    <?php
                    foreach ( $show_original as $key => $option ) {
                        $this->toggle( $this->name( 'show_original', $key ), ! empty( $settings['show_original'][ $key ] ), $option[0], $option[1] );
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * "Help" tab.
     *
     * @return void
     */
    private function render_help_tab() {
        $faq = array(
            array(
                __( 'What is the difference between Duplicate and New Draft?', 'duplicate-pp' ),
                __( 'Duplicate makes a copy with the status from "What to copy" and keeps you on the list, so you can copy several items in a row. New Draft always makes a draft and opens it in the editor straight away.', 'duplicate-pp' ),
            ),
            array(
                __( 'Can I make several copies at once?', 'duplicate-pp' ),
                __( 'Yes. Click "Copy multiple" under a title, or select items and choose "Duplicate multiple times…" from Bulk actions, then pick how many copies to make. Turn on "Number the titles" to get "(Copy 1)", "(Copy 2)" and so on. The limit per item is set under What to copy → Maximum copies at once.', 'duplicate-pp' ),
            ),
            array(
                __( 'How does Rewrite & Republish work?', 'duplicate-pp' ),
                __( 'It creates a hidden draft copy of a published item. Edit it as long as you need — visitors keep seeing the original. When you click Republish (or the scheduled date arrives) the original is updated with the copy\'s title, content, terms and custom fields, and the copy is deleted. The original keeps its URL, ID, date, author and comments, and a revision is saved.', 'duplicate-pp' ),
            ),
            array(
                __( 'Are page builder layouts copied?', 'duplicate-pp' ),
                __( 'Yes. With "Custom fields" on, Elementor (with fresh element IDs), WPBakery, Beaver Builder, ACF and Codestar data are copied.', 'duplicate-pp' ),
            ),
            array(
                __( 'What about WooCommerce products?', 'duplicate-pp' ),
                __( 'Simple products copy fine; the SKU and sales/rating counters are left out so the copy does not clash with the original. For variable products, use WooCommerce\'s own "Duplicate" link so the variations are copied too.', 'duplicate-pp' ),
            ),
            array(
                __( 'Why can\'t an author see the links?', 'duplicate-pp' ),
                __( 'Check that their role is allowed under Permissions, the post type is enabled, and they can edit the item. Rewrite & Republish also needs permission to publish.', 'duplicate-pp' ),
            ),
        );

        $hooks = array(
            array( 'dpp_can_copy', __( 'Filter whether the current user may copy a post.', 'duplicate-pp' ) ),
            array( 'dpp_can_rewrite', __( 'Filter whether Rewrite & Republish is offered.', 'duplicate-pp' ) ),
            array( 'dpp_duplicate_post_args', __( 'Filter the data of a new copy before it is inserted.', 'duplicate-pp' ) ),
            array( 'dpp_excluded_meta_keys', __( 'Filter the meta keys that are not copied.', 'duplicate-pp' ) ),
            array( 'dpp_after_duplicate', __( 'Action after a copy is made: ( $new_id, $post, $mode ).', 'duplicate-pp' ) ),
            array( 'dpp_after_republish', __( 'Action after a copy is merged into its original: ( $original_id, $copy_id ).', 'duplicate-pp' ) ),
            array( 'dpp_available_post_types', __( 'Filter the post types offered under Permissions.', 'duplicate-pp' ) ),
            array( 'dpp_max_copies', __( 'Filter how many copies of one item can be made at once (capped at 100).', 'duplicate-pp' ) ),
        );
        ?>
        <div class="dpp-grid dpp-grid--sidebar">
            <div class="dpp-card">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'Frequently asked questions', 'duplicate-pp' ); ?></h2>
                </div>
                <div class="dpp-faq">
                    <?php foreach ( $faq as $index => $item ) : ?>
                        <details class="dpp-faq__item" <?php echo 0 === $index ? 'open' : ''; ?>>
                            <summary><?php echo esc_html( $item[0] ); ?></summary>
                            <p><?php echo esc_html( $item[1] ); ?></p>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dpp-card">
                <div class="dpp-card__head">
                    <h2><?php esc_html_e( 'For developers', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'Hooks to extend or restrict copying.', 'duplicate-pp' ); ?></p>
                </div>
                <ul class="dpp-hooks">
                    <?php foreach ( $hooks as $hook ) : ?>
                        <li><code><?php echo esc_html( $hook[0] ); ?></code><span><?php echo esc_html( $hook[1] ); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Field name for a setting, e.g. dpp_settings[copy][content].
     *
     * @param string $key    Setting key.
     * @param string $subkey Optional key inside a group.
     * @return string
     */
    private function name( $key, $subkey = '' ) {
        $name = Settings_Service::OPTION_KEY . '[' . $key . ']';
        return '' === $subkey ? $name : $name . '[' . $subkey . ']';
    }

    /**
     * Print a labelled text input.
     *
     * @param string $key         Setting key.
     * @param string $label       Label.
     * @param array  $settings    Current settings.
     * @param string $placeholder Placeholder.
     * @return void
     */
    private function text_input( $key, $label, $settings, $placeholder ) {
        $id = 'dpp-' . str_replace( '_', '-', $key );
        ?>
        <div class="dpp-field">
            <label class="dpp-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
            <input type="text" id="<?php echo esc_attr( $id ); ?>" class="regular-text" data-preview="<?php echo esc_attr( $key ); ?>"
                name="<?php echo esc_attr( $this->name( $key ) ); ?>"
                value="<?php echo esc_attr( $settings[ $key ] ); ?>"
                placeholder="<?php echo esc_attr( $placeholder ); ?>">
        </div>
        <?php
    }

    /**
     * Print a toggle switch.
     *
     * @param string $name        Input name.
     * @param bool   $checked     Whether it is on.
     * @param string $label       Label.
     * @param string $description Description.
     * @return void
     */
    private function toggle( $name, $checked, $label, $description = '' ) {
        ?>
        <label class="dpp-switch">
            <input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
            <span class="dpp-switch__track" aria-hidden="true"></span>
            <span class="dpp-switch__text">
                <span class="dpp-switch__label"><?php echo esc_html( $label ); ?></span>
                <?php if ( $description ) : ?>
                    <span class="dpp-switch__desc"><?php echo esc_html( $description ); ?></span>
                <?php endif; ?>
            </span>
        </label>
        <?php
    }

    /**
     * Print a checkbox chip.
     *
     * @param string $name     Input name.
     * @param string $value    Input value.
     * @param bool   $checked  Whether it is checked.
     * @param string $label    Label.
     * @param string $meta     Small secondary text.
     * @param bool   $locked   Show as checked and not editable.
     * @return void
     */
    private function check( $name, $value, $checked, $label, $meta = '', $locked = false ) {
        ?>
        <label class="dpp-check<?php echo $locked ? ' is-locked' : ''; ?>">
            <?php if ( $locked ) : ?>
                <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
                <input type="checkbox" checked disabled>
            <?php else : ?>
                <input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $checked ); ?>>
            <?php endif; ?>
            <span class="dpp-check__box">
                <span class="dpp-check__label"><?php echo esc_html( $label ); ?></span>
                <?php if ( $meta ) : ?>
                    <code class="dpp-check__meta"><?php echo esc_html( $meta ); ?></code>
                <?php endif; ?>
            </span>
        </label>
        <?php
    }
}
