<?php
/**
 * Core duplication logic.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

use DuplicatePP\Meta\Meta_Handler;
use DuplicatePP\Settings\Settings_Service;
use WP_Post;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Duplicator
 *
 * Handles the admin_action_dpp_duplicate_as_draft request, builds
 * the new post, copies taxonomies, and delegates post meta to
 * Meta_Handler. Settings are read via Settings_Service.
 */
final class Duplicator {

    /**
     * Action name — preserved for backward compatibility.
     */
    const ACTION = 'dpp_duplicate_as_draft';

    /**
     * Single instance.
     *
     * @var Duplicator|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Duplicator
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Duplicator is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Register hooks. Called by Plugin::boot().
     *
     * @return void
     */
    public function register() {
        add_action( 'admin_action_' . self::ACTION, array( $this, 'handle_duplicate' ) );
    }

    /**
     * Main duplication handler.
     *
     * Hooked to admin_action_dpp_duplicate_as_draft. Validates the
     * request, creates the duplicate, copies taxonomies and meta,
     * and redirects to the list table.
     *
     * @return void
     */
    public function handle_duplicate() {
        // 1. Verify the nonce first — before reading any other request data.
        $nonce = isset( $_GET['duplicate_nonce'] )
            ? sanitize_key( wp_unslash( $_GET['duplicate_nonce'] ) )
            : '';
        if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'duplicate_post_' . get_current_blog_id() ) ) {
            wp_die( esc_html__( 'Security check failed.', 'duplicate-pp' ), '', array( 'response' => 403 ) );
        }

        // 2. Now safe to read the source post ID.
        $post_id = $this->resolve_post_id();
        if ( $post_id <= 0 ) {
            wp_die( esc_html__( 'Invalid post ID.', 'duplicate-pp' ), '', array( 'response' => 400 ) );
        }

        // 3. Capability check against the resolved post.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'You do not have permission to duplicate posts.', 'duplicate-pp' ), '', array( 'response' => 403 ) );
        }

        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post ) {
            wp_die( esc_html__( 'Post not found.', 'duplicate-pp' ), '', array( 'response' => 404 ) );
        }

        $settings = Plugin::instance()->settings()->all();
        $args     = $this->build_args( $post, $settings );

        $new_id = wp_insert_post( $args, true );
        if ( is_wp_error( $new_id ) ) {
            wp_die( esc_html( $new_id->get_error_message() ), '', array( 'response' => 500 ) );
        }

        $this->duplicate_taxonomies( $post_id, $new_id );
        Plugin::instance()->meta_handler()->duplicate_post_meta( $post_id, $new_id );

        $this->redirect_after( $post );
    }

    /**
     * Resolve the source post ID from GET/POST.
     *
     * @return int Zero when the value is missing or invalid.
     */
    private function resolve_post_id() {
        if ( isset( $_GET['post'] ) ) {
            return absint( wp_unslash( $_GET['post'] ) );
        }
        if ( isset( $_POST['post'] ) ) {
            return absint( wp_unslash( $_POST['post'] ) );
        }
        return 0;
    }

    /**
     * Build the wp_insert_post() argument array.
     *
     * @param WP_Post $post     Source post.
     * @param array   $settings Settings array from Settings_Service.
     * @return array
     */
    private function build_args( $post, $settings ) {
        // Decode HTML entities once to avoid double-encoding on round-trip
        // (especially relevant for Classic Editor content).
        $post_content = html_entity_decode( $post->post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $post_excerpt = html_entity_decode( $post->post_excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        $new_title = $settings['title_prefix'] . $post->post_title . $settings['title_suffix'];
        $new_slug  = $settings['slug_prefix'] . $post->post_name . $settings['slug_suffix'];

        return array(
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
            'post_author'    => get_current_user_id(),
            'post_content'   => $post_content,
            'post_excerpt'   => $post_excerpt,
            'post_name'      => wp_unique_post_slug( sanitize_title( $new_slug ), 0, 'publish', $post->post_type, $post->post_parent ),
            'post_parent'    => $post->post_parent,
            'post_password'  => $post->post_password,
            'post_status'    => $settings['post_status'],
            'post_title'     => $new_title,
            'post_type'      => $post->post_type,
            'to_ping'        => $post->to_ping,
            'menu_order'     => $post->menu_order,
        );
    }

    /**
     * Copy all object terms from the source post to the duplicate.
     *
     * @param int $original_id Source post ID.
     * @param int $new_id      Destination post ID.
     * @return void
     */
    public function duplicate_taxonomies( $original_id, $new_id ) {
        $taxonomies = get_object_taxonomies( get_post_type( $original_id ) );
        if ( empty( $taxonomies ) ) {
            return;
        }

        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $original_id, $taxonomy, array( 'fields' => 'slugs' ) );
            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }
            wp_set_object_terms( $new_id, $terms, $taxonomy );
        }
    }

    /**
     * Redirect to the list table with a success flag.
     *
     * @param WP_Post $post Source post.
     * @return void
     */
    private function redirect_after( $post ) {
        $redirect_url = add_query_arg(
            array(
                'post_type'  => $post->post_type,
                'duplicated' => 1,
            ),
            admin_url( 'edit.php' )
        );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
