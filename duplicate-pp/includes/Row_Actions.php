<?php
/**
 * Row actions: adds a "Duplicate" link to post and page list rows.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

use WP_Post;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Row_Actions
 *
 * Adds a Duplicate action to the post and page list table rows.
 * The link points at admin.php?action=dpp_duplicate_as_draft with
 * a per-blog nonce.
 */
final class Row_Actions {

    /**
     * Single instance.
     *
     * @var Row_Actions|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Row_Actions
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Row_Actions is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Register hooks. Called by Plugin::boot().
     *
     * @return void
     */
    public function register() {
        add_filter( 'post_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
        add_filter( 'page_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
    }

    /**
     * Append the Duplicate link to a row's actions.
     *
     * @param array   $actions Existing row actions.
     * @param WP_Post $post    Current row's post object.
     * @return array
     */
    public function add_duplicate_link( $actions, $post ) {
        if ( ! $post instanceof WP_Post ) {
            return $actions;
        }
        if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
            return $actions;
        }

        $url = $this->build_duplicate_url( $post->ID );

        $actions['duplicate'] = sprintf(
            '<a href="%1$s" aria-label="%2$s">%3$s</a>',
            esc_url( $url ),
            esc_attr( sprintf( /* translators: %s: post title. */ __( 'Duplicate &#8220;%s&#8221;', 'duplicate-pp' ), get_the_title( $post->ID ) ) ),
            esc_html__( 'Duplicate', 'duplicate-pp' )
        );

        return $actions;
    }

    /**
     * Build the URL that triggers the duplication handler.
     *
     * @param int $post_id Source post ID.
     * @return string
     */
    private function build_duplicate_url( $post_id ) {
        $nonce = wp_create_nonce( 'duplicate_post_' . get_current_blog_id() );
        return add_query_arg(
            array(
                'action'          => Duplicator::ACTION,
                'post'            => (int) $post_id,
                'duplicate_nonce' => $nonce,
            ),
            admin_url( 'admin.php' )
        );
    }
}
