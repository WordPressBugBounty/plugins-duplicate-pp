<?php
/**
 * Action URL builder and nonce verification.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Links
 *
 * Builds the nonce-protected admin.php?action=… URLs used by row
 * actions, the admin bar and the editors, and verifies them on the
 * way back in. Each nonce is bound to both the action and the post.
 */
final class Links {

    /**
     * Single instance.
     *
     * @var Links|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Links
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Links is not allowed.', 'duplicate-pp' ), '4.0.0' );
    }

    /**
     * Build an action URL for a post.
     *
     * @param string $action  Admin action name.
     * @param int    $post_id Post ID.
     * @return string Raw (unescaped) URL.
     */
    public function url( $action, $post_id ) {
        return add_query_arg(
            array(
                'action'   => $action,
                'post'     => (int) $post_id,
                '_wpnonce' => wp_create_nonce( $this->nonce_action( $action, $post_id ) ),
            ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * URL that clones a post and returns to the list.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    public function clone_url( $post_id ) {
        return $this->url( Duplicator::ACTION, $post_id );
    }

    /**
     * URL that copies a post to a new draft and opens it.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    public function new_draft_url( $post_id ) {
        return $this->url( Duplicator::ACTION_NEW_DRAFT, $post_id );
    }

    /**
     * URL that makes several copies; the count is added by the list script.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    public function multiple_url( $post_id ) {
        return $this->url( Duplicator::ACTION_MULTIPLE, $post_id );
    }

    /**
     * URL that starts a Rewrite & Republish.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    public function rewrite_url( $post_id ) {
        return $this->url( Rewrite_Republish::ACTION, $post_id );
    }

    /**
     * Verify the nonce of an incoming action request.
     *
     * @param string $action  Admin action name.
     * @param int    $post_id Post ID from the request.
     * @return bool
     */
    public function verify( $action, $post_id ) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- this is the nonce check.
        $nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
        if ( '' !== $nonce && wp_verify_nonce( $nonce, $this->nonce_action( $action, $post_id ) ) ) {
            return true;
        }

        // Links built by 3.x (and any third-party code copying them) carry a per-blog nonce.
        if ( Duplicator::ACTION === $action && isset( $_REQUEST['duplicate_nonce'] ) ) {
            $legacy = sanitize_key( wp_unslash( $_REQUEST['duplicate_nonce'] ) );
            return (bool) wp_verify_nonce( $legacy, 'duplicate_post_' . get_current_blog_id() );
        }
        // phpcs:enable

        return false;
    }

    /**
     * Read the post ID of an incoming action request.
     *
     * @return int Zero when the value is missing or invalid.
     */
    public function request_post_id() {
        // phpcs:disable WordPress.Security.NonceVerification -- read before Links::verify(), which needs the ID.
        if ( isset( $_GET['post'] ) ) {
            return absint( wp_unslash( $_GET['post'] ) );
        }
        if ( isset( $_POST['post'] ) ) {
            return absint( wp_unslash( $_POST['post'] ) );
        }
        // phpcs:enable
        return 0;
    }

    /**
     * Nonce action string for an action/post pair.
     *
     * @param string $action  Admin action name.
     * @param int    $post_id Post ID.
     * @return string
     */
    private function nonce_action( $action, $post_id ) {
        return $action . '_' . (int) $post_id;
    }
}
