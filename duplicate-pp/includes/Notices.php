<?php
/**
 * Admin notices: shows a success notice after a successful duplication.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Notices
 *
 * Renders a dismissible admin notice when ?duplicated=1 is
 * present in the URL on a list-table screen.
 */
final class Notices {

    /**
     * Single instance.
     *
     * @var Notices|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Notices
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Notices is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Register hooks. Called by Plugin::boot().
     *
     * @return void
     */
    public function register() {
        add_action( 'admin_notices', array( $this, 'maybe_render' ) );
    }

    /**
     * Render the success notice when the duplicated flag is set.
     *
     * @return void
     */
    public function maybe_render() {
        $flag = isset( $_GET['duplicated'] ) ? absint( wp_unslash( $_GET['duplicated'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 1 !== $flag ) {
            return;
        }
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Post duplicated successfully.', 'duplicate-pp' ); ?></p>
        </div>
        <?php
    }
}
