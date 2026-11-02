<?php
/**
 * Permission checks: who may copy what.
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
 * Final class Permissions
 *
 * Single place that answers "may the current user copy this post?"
 * and "may they start a Rewrite & Republish for it?". Every link,
 * bulk action and request handler goes through these checks.
 */
final class Permissions {

    /**
     * Statuses a post can never be copied from.
     */
    const BLOCKED_STATUSES = array( 'trash', 'auto-draft', 'inherit' );

    /**
     * Single instance.
     *
     * @var Permissions|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Permissions
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Permissions is not allowed.', 'duplicate-pp' ), '4.0.0' );
    }

    /**
     * Whether the current user holds one of the roles allowed to copy.
     * Site administrators are always allowed so they cannot lock themselves out.
     *
     * @return bool
     */
    public function user_is_allowed() {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        if ( current_user_can( 'manage_options' ) || is_super_admin() ) {
            return true;
        }

        $allowed = (array) Plugin::instance()->settings()->get( 'roles', array() );
        $user    = wp_get_current_user();

        return (bool) array_intersect( (array) $user->roles, $allowed );
    }

    /**
     * Whether duplication is switched on for a post type.
     *
     * @param string $post_type Post type name.
     * @return bool
     */
    public function is_post_type_enabled( $post_type ) {
        $enabled = (array) Plugin::instance()->settings()->get( 'post_types', array() );
        return in_array( $post_type, $enabled, true );
    }

    /**
     * Whether the current user may copy a post.
     *
     * @param WP_Post|int|null $post Post object or ID.
     * @return bool
     */
    public function can_copy( $post ) {
        $post = empty( $post ) ? null : get_post( $post );
        $can  = $post instanceof WP_Post
            && ! in_array( $post->post_status, self::BLOCKED_STATUSES, true )
            && $this->is_post_type_enabled( $post->post_type )
            && $this->user_is_allowed()
            && current_user_can( 'edit_post', $post->ID )
            && $this->can_create( $post->post_type )
            && ! Rewrite_Republish::instance()->is_copy( $post->ID );

        /**
         * Filters whether the current user may copy a post.
         *
         * @since 4.0.0
         *
         * @param bool         $can  Whether copying is allowed.
         * @param WP_Post|null $post The post being checked.
         */
        return (bool) apply_filters( 'dpp_can_copy', $can, $post );
    }

    /**
     * Whether the current user may start a Rewrite & Republish for a post.
     *
     * @param WP_Post|int|null $post Post object or ID.
     * @return bool
     */
    public function can_rewrite( $post ) {
        $post = empty( $post ) ? null : get_post( $post );
        $can  = $post instanceof WP_Post
            && Plugin::instance()->settings()->get( 'rewrite_republish' )
            && ! in_array( $post->post_type, $this->rewrite_excluded_post_types(), true )
            && 'publish' === $post->post_status
            && $this->can_copy( $post )
            && $this->can_publish( $post->post_type )
            && 0 === Rewrite_Republish::instance()->get_copy_id( $post->ID );

        /**
         * Filters whether the current user may start a Rewrite & Republish.
         *
         * @since 4.0.0
         *
         * @param bool         $can  Whether rewriting is allowed.
         * @param WP_Post|null $post The post being checked.
         */
        return (bool) apply_filters( 'dpp_can_rewrite', $can, $post );
    }

    /**
     * Post types Rewrite & Republish is never offered for.
     *
     * Products keep live counters (sales, stock, ratings) in meta that a
     * republish would overwrite with the values from when the copy was made.
     *
     * @return string[]
     */
    public function rewrite_excluded_post_types() {
        /**
         * Filters the post types Rewrite & Republish is not available for.
         *
         * @since 4.0.1
         *
         * @param string[] $types Post type names.
         */
        return (array) apply_filters( 'dpp_rewrite_excluded_post_types', array( 'product' ) );
    }

    /**
     * Whether the current user may create posts of a type.
     *
     * @param string $post_type Post type name.
     * @return bool
     */
    public function can_create( $post_type ) {
        $object = get_post_type_object( $post_type );
        return $object && current_user_can( $object->cap->create_posts );
    }

    /**
     * Whether the current user may publish posts of a type.
     *
     * @param string $post_type Post type name.
     * @return bool
     */
    public function can_publish( $post_type ) {
        $object = get_post_type_object( $post_type );
        return $object && current_user_can( $object->cap->publish_posts );
    }
}
