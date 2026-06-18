<?php
/**
 * Admin bar: adds a "Duplicate This" node to the WP admin bar.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

use WP_Admin_Bar;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Admin_Bar
 *
 * Adds a "Duplicate This" node to the admin bar when viewing a
 * singular post the current user can edit. Prints two inline CSS
 * rules on admin_head to style the node (kept inline because the
 * rules only apply when the admin bar is rendered).
 */
final class Admin_Bar {

    /**
     * Node id.
     */
    const NODE_ID = 'dpp_duplicate_link';

    /**
     * Single instance.
     *
     * @var Admin_Bar|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Admin_Bar
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Admin_Bar is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Register hooks. Called by Plugin::boot().
     *
     * @return void
     */
    public function register() {
        add_action( 'admin_bar_menu', array( $this, 'add_node' ), 999 );
        add_action( 'admin_head', array( $this, 'print_styles' ) );
    }

    /**
     * Add the duplicate node to the admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
     * @return void
     */
    public function add_node( $wp_admin_bar ) {
        if ( ! $wp_admin_bar instanceof WP_Admin_Bar ) {
            return;
        }
        if ( ! is_singular() || ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $post_id = get_the_ID();
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $nonce = wp_create_nonce( 'duplicate_post_' . get_current_blog_id() );
        $url   = add_query_arg(
            array(
                'action'          => Duplicator::ACTION,
                'post'            => (int) $post_id,
                'duplicate_nonce' => $nonce,
            ),
            admin_url( 'admin.php' )
        );

        $wp_admin_bar->add_node(
            array(
                'id'    => self::NODE_ID,
                'title' => __( 'Duplicate This', 'duplicate-pp' ),
                'href'  => esc_url( $url ),
                'meta'  => array(
                    'class' => self::NODE_ID,
                    /* translators: %s: post title. */
                    'title' => sprintf( __( 'Duplicate &#8220;%s&#8221;', 'duplicate-pp' ), get_the_title( $post_id ) ),
                ),
            )
        );
    }

    /**
     * Print the small admin-bar style block.
     *
     * @return void
     */
    public function print_styles() {
        ?>
        <style type="text/css">
            .<?php echo esc_attr( self::NODE_ID ); ?> { display: inline-block; }
            #wp-admin-bar-<?php echo esc_attr( self::NODE_ID ); ?> .ab-item:hover { color: #00a0d2; }
        </style>
        <?php
    }
}
