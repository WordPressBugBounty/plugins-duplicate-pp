<?php
/**
 * "Recommended for your store" card for Cartlet, shown on this plugin's own settings page.
 *
 * Shown only when WooCommerce is active, Cartlet is not active, and the user may install or
 * activate plugins. Install goes through the core plugin-information modal, so the user
 * installs from WordPress.org. Dismissal is stored per user and shared with the other
 * Gutenbergkits plugins, so it stays dismissed everywhere. No tracking, no remote requests.
 *
 * @package DuplicatePP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DPP_Cartlet_Card' ) ) {

    /**
     * Final class DPP_Cartlet_Card
     */
    final class DPP_Cartlet_Card {

        /**
         * User meta flag shared by every Gutenbergkits plugin that ships this card.
         */
        const DISMISSED_META = 'gbk_cartlet_card_dismissed';

        /**
         * AJAX action for dismissing the card.
         */
        const AJAX_ACTION = 'dpp_dismiss_cartlet_card';

        /**
         * Cartlet's main file, relative to the plugins folder.
         */
        const CARTLET_FILE = 'cartlet/cartlet.php';

        /**
         * Admin page hook suffix the card is shown on.
         *
         * @var string
         */
        private $hook_suffix;

        /**
         * URL of the bundled Cartlet icon.
         *
         * @var string
         */
        private $icon_url;

        /**
         * Action the settings page fires where the card belongs.
         *
         * @var string
         */
        private $render_hook;

        /**
         * Constructor.
         *
         * @param string $hook_suffix Hook suffix of this plugin's settings page.
         * @param string $icon_url    URL of the bundled Cartlet icon.
         * @param string $render_hook Action the settings page fires where the card belongs.
         */
        public function __construct( $hook_suffix, $icon_url, $render_hook ) {
            $this->hook_suffix = $hook_suffix;
            $this->icon_url    = $icon_url;
            $this->render_hook = $render_hook;
        }

        /**
         * Register hooks.
         *
         * @return void
         */
        public function register() {
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
            add_action( $this->render_hook, array( $this, 'render' ), 10, 0 );
            add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'dismiss' ) );
        }

        /**
         * What the card offers: 'install', 'activate', or '' when it should not show.
         *
         * @return string
         */
        private function state() {
            if ( ! class_exists( 'WooCommerce' ) || defined( 'CARTLET_VERSION' ) ) {
                return '';
            }
            if ( get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
                return '';
            }
            if ( file_exists( WP_PLUGIN_DIR . '/' . self::CARTLET_FILE ) ) {
                return current_user_can( 'activate_plugins' ) ? 'activate' : '';
            }
            return current_user_can( 'install_plugins' ) ? 'install' : '';
        }

        /**
         * Load the modal script and the card's styles on the settings page only.
         *
         * @param string $hook_suffix Current admin page hook.
         * @return void
         */
        public function enqueue( $hook_suffix ) {
            if ( $this->hook_suffix !== $hook_suffix || '' === $this->state() ) {
                return;
            }

            add_thickbox();
            wp_enqueue_script( 'plugin-install' );

            wp_register_style( 'dpp-cartlet-card', false, array(), '1.0.0' );
            wp_enqueue_style( 'dpp-cartlet-card' );
            wp_add_inline_style( 'dpp-cartlet-card', $this->css() );

            wp_register_script( 'dpp-cartlet-card', false, array(), '1.0.0', true );
            wp_enqueue_script( 'dpp-cartlet-card' );
            wp_add_inline_script( 'dpp-cartlet-card', $this->js() );
        }

        /**
         * Print the card.
         *
         * @return void
         */
        public function render() {
            $state = $this->state();
            if ( '' === $state ) {
                return;
            }

            if ( 'activate' === $state ) {
                $url   = wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=' . self::CARTLET_FILE ), 'activate-plugin_' . self::CARTLET_FILE );
                $label = __( 'Activate Cartlet', 'duplicate-pp' );
                $class = 'button button-primary';
            } else {
                $url   = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=cartlet&TB_iframe=true&width=772&height=600' );
                $label = __( 'Install Cartlet free', 'duplicate-pp' );
                $class = 'button button-primary thickbox open-plugin-details-modal';
            }
            ?>
            <div class="gbk-cartlet-card" data-action="<?php echo esc_attr( self::AJAX_ACTION ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( self::AJAX_ACTION ) ); ?>">
                <button type="button" class="gbk-cartlet-card__close">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this recommendation', 'duplicate-pp' ); ?></span>
                </button>
                <p class="gbk-cartlet-card__eyebrow"><?php esc_html_e( 'Recommended for your store', 'duplicate-pp' ); ?></p>
                <div class="gbk-cartlet-card__head">
                    <img src="<?php echo esc_url( $this->icon_url ); ?>" width="48" height="48" alt="">
                    <div>
                        <h3 class="gbk-cartlet-card__title"><?php esc_html_e( 'Cartlet: share & save carts', 'duplicate-pp' ); ?></h3>
                        <p class="gbk-cartlet-card__by"><?php esc_html_e( 'Free, from the team behind this plugin', 'duplicate-pp' ); ?></p>
                    </div>
                </div>
                <p class="gbk-cartlet-card__lead"><?php echo esc_html( __( 'Duplicating products for a WooCommerce store? Let your shoppers share the cart they build as a link, or save it for later.', 'duplicate-pp' ) ); ?></p>
                <ul class="gbk-cartlet-card__points">
                    <li><?php esc_html_e( 'Cart as a link, QR code or WhatsApp message', 'duplicate-pp' ); ?></li>
                    <li><?php esc_html_e( 'Saved carts, even for guests', 'duplicate-pp' ); ?></li>
                    <li><?php esc_html_e( 'Ready-made cart links with a coupon', 'duplicate-pp' ); ?></li>
                </ul>
                <p class="gbk-cartlet-card__actions">
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>" data-title="Cartlet"><?php echo esc_html( $label ); ?></a>
                </p>
            </div>
            <?php
        }

        /**
         * AJAX: remember that this user dismissed the card.
         *
         * @return void
         */
        public function dismiss() {
            check_ajax_referer( self::AJAX_ACTION, 'nonce' );
            if ( ! current_user_can( 'activate_plugins' ) ) {
                wp_send_json_error( null, 403 );
            }
            update_user_meta( get_current_user_id(), self::DISMISSED_META, time() );
            wp_send_json_success();
        }

        /**
         * Card styles.
         *
         * @return string
         */
        private function css() {
            $c = '.gbk-cartlet-card';
            return $c . '{position:relative;box-sizing:border-box;max-width:560px;margin:32px auto 0;padding:20px;background:#fff;border:1px solid #e0e0e0;border-radius:12px;color:#1d2327;font-size:13px;line-height:1.5}'
                . $c . ' p{margin:0}'
                . $c . ' .gbk-cartlet-card__close{position:absolute;top:10px;right:10px;padding:2px;border:0;background:none;color:#8c8f94;cursor:pointer;line-height:1}'
                . $c . ' .gbk-cartlet-card__close:hover,' . $c . ' .gbk-cartlet-card__close:focus{color:#1d2327}'
                . $c . ' .gbk-cartlet-card__eyebrow{margin:0 0 12px;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#0f7f74}'
                . $c . ' .gbk-cartlet-card__head{display:flex;gap:12px;align-items:center}'
                . $c . ' .gbk-cartlet-card__head img{flex:none;width:44px;height:44px;border-radius:10px}'
                . $c . ' .gbk-cartlet-card__title{margin:0;padding:0;font-size:15px;font-weight:600;line-height:1.3;color:#1d2327}'
                . $c . ' .gbk-cartlet-card__by{margin-top:2px;color:#646970;font-size:12px}'
                . $c . ' .gbk-cartlet-card__lead{margin:14px 0 10px}'
                . $c . ' .gbk-cartlet-card__points{margin:0 0 16px;padding:0;list-style:none}'
                . $c . ' .gbk-cartlet-card__points li{position:relative;margin:0 0 6px;padding-left:20px}'
                . $c . ' .gbk-cartlet-card__points li:before{content:"";position:absolute;left:3px;top:4px;width:8px;height:4px;border-left:2px solid #0f7f74;border-bottom:2px solid #0f7f74;transform:rotate(-45deg)}'
                . $c . ' .gbk-cartlet-card__actions .button{width:100%;justify-content:center;text-align:center;background:#0f7f74;border-color:#0f7f74;color:#fff;border-radius:6px}'
                . $c . ' .gbk-cartlet-card__actions .button:hover,' . $c . ' .gbk-cartlet-card__actions .button:focus{background:#0b665d;border-color:#0b665d;color:#fff}';
        }

        /**
         * Dismiss handler.
         *
         * @return string
         */
        private function js() {
            return '(function(){document.addEventListener("click",function(e){var b=e.target.closest(".gbk-cartlet-card__close");if(!b){return;}var c=b.closest(".gbk-cartlet-card"),d=new FormData();d.append("action",c.dataset.action);d.append("nonce",c.dataset.nonce);fetch(window.ajaxurl,{method:"POST",credentials:"same-origin",body:d});c.remove();});})();';
        }
    }
}
