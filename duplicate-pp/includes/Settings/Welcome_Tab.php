<?php
/**
 * Welcome / Promotions tab content for the settings page.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Welcome_Tab
 *
 * Renders the Promotions / Welcome tab. All strings are translatable
 * with the 'duplicate-pp' text domain. All attributes are escaped.
 * No hooks are registered by this class.
 */
final class Welcome_Tab {

    /**
     * Single instance.
     *
     * @var Welcome_Tab|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Welcome_Tab
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Welcome_Tab is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Render the tab content.
     *
     * @return void
     */
    public function render() {
        $img_url = DPP_PLUGIN_URL . 'img/dpp.jpg';
        ?>
        <div class="admin_page_container">
            <div class="plugin_head">
                <div class="head_container">
                    <h1 class="plugin_title"><?php esc_html_e( 'Duplicate PP', 'duplicate-pp' ); ?></h1>
                    <h4 class="plugin_subtitle"><?php esc_html_e( 'A Light-weight Plugin to Duplicate Any Post Type', 'duplicate-pp' ); ?></h4>
                    <div class="support_btn">
                        <a class="dpp-btn dpp-btn--support" href="<?php echo esc_url( 'https://gutenbergkits.com/contact' ); ?>" target="_blank" rel="nofollow noreferrer">
                            <?php esc_html_e( 'Get Support', 'duplicate-pp' ); ?>
                        </a>
                        <a class="dpp-btn dpp-btn--rate" href="<?php echo esc_url( 'https://wordpress.org/plugins/duplicate-pp/#reviews' ); ?>" target="_blank" rel="nofollow noreferrer">
                            <?php esc_html_e( 'Rate Plugin', 'duplicate-pp' ); ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="plugin_body">
                <div class="doc_video_area">
                    <div class="doc_video">
                        <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr__( 'Duplicate PP screenshot', 'duplicate-pp' ); ?>">
                    </div>
                </div>

                <div class="support_area">
                    <div class="single_support">
                        <h4 class="support_title"><?php esc_html_e( 'Freelance Work', 'duplicate-pp' ); ?></h4>
                        <div class="support_btn">
                            <a class="dpp-btn dpp-btn--fiverr" href="<?php echo esc_url( 'https://www.fiverr.com/users/devs_zak/' ); ?>" target="_blank" rel="nofollow noreferrer">
                                <?php esc_html_e( '@Fiverr', 'duplicate-pp' ); ?>
                            </a>
                            <a class="dpp-btn dpp-btn--upwork" href="<?php echo esc_url( 'https://www.upwork.com/freelancers/~010af183b3205dc627' ); ?>" target="_blank" rel="nofollow noreferrer">
                                <?php esc_html_e( '@UpWork', 'duplicate-pp' ); ?>
                            </a>
                        </div>
                    </div>

                    <div class="single_support">
                        <h4 class="support_title"><?php esc_html_e( 'Get Support', 'duplicate-pp' ); ?></h4>
                        <div class="support_btn">
                            <a class="dpp-btn dpp-btn--contact" href="<?php echo esc_url( 'https://gutenebrgkits.com/contact' ); ?>" target="_blank" rel="nofollow noreferrer">
                                <?php esc_html_e( 'Contact', 'duplicate-pp' ); ?>
                            </a>
                            <a class="dpp-btn dpp-btn--mail" href="<?php echo esc_url( 'mailto:info@gutenbergkits.com' ); ?>">
                                <?php esc_html_e( 'Send Mail', 'duplicate-pp' ); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
