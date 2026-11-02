<?php
/**
 * Admin bar: adds a "Duplicate This" menu to the WP admin bar.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

use WP_Admin_Bar;
use WP_Post;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Admin_Bar
 *
 * Adds a "Duplicate This" node to the admin bar when viewing a
 * singular post (front end) or editing one (admin) that the current
 * user may copy, with "Copy to a new draft" and "Rewrite & Republish"
 * entries underneath.
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
    }

    /**
     * Add the duplicate menu to the admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
     * @return void
     */
    public function add_node( $wp_admin_bar ) {
        if ( ! $wp_admin_bar instanceof WP_Admin_Bar || ! Plugin::instance()->settings()->is_on( 'show_in', 'admin_bar' ) ) {
            return;
        }

        $post        = $this->current_post();
        $permissions = Plugin::instance()->permissions();
        if ( ! $post || ! $permissions->can_copy( $post ) ) {
            return;
        }

        $links    = Plugin::instance()->links();
        $new_url  = $links->new_draft_url( $post->ID );
        $title    = Plugin::post_title( $post );

        $wp_admin_bar->add_node(
            array(
                'id'    => self::NODE_ID,
                'title' => '<span class="ab-icon dashicons dashicons-admin-page" aria-hidden="true" style="top:2px"></span>'
                    . '<span class="ab-label">' . esc_html__( 'Duplicate This', 'duplicate-pp' ) . '</span>',
                'href'  => esc_url( $new_url ),
                'meta'  => array(
                    'class' => self::NODE_ID,
                    /* translators: %s: post title. */
                    'title' => sprintf( __( 'Duplicate &#8220;%s&#8221;', 'duplicate-pp' ), $title ),
                ),
            )
        );

        $wp_admin_bar->add_node(
            array(
                'parent' => self::NODE_ID,
                'id'     => self::NODE_ID . '_new_draft',
                'title'  => esc_html__( 'Copy to a new draft', 'duplicate-pp' ),
                'href'   => esc_url( $new_url ),
            )
        );

        if ( $permissions->can_rewrite( $post ) ) {
            $wp_admin_bar->add_node(
                array(
                    'parent' => self::NODE_ID,
                    'id'     => self::NODE_ID . '_rewrite',
                    'title'  => esc_html__( 'Rewrite & Republish', 'duplicate-pp' ),
                    'href'   => esc_url( $links->rewrite_url( $post->ID ) ),
                )
            );
        }
    }

    /**
     * The post being viewed (front end) or edited (admin), if any.
     *
     * @return WP_Post|null
     */
    private function current_post() {
        if ( ! is_admin() ) {
            return is_singular() ? get_post( get_queried_object_id() ) : null;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'post' !== $screen->base ) {
            return null;
        }
        $post = get_post();
        return $post instanceof WP_Post && 'auto-draft' !== $post->post_status ? $post : null;
    }
}
