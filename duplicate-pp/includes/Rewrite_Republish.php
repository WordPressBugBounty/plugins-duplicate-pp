<?php
/**
 * Rewrite & Republish: edit a published post in a hidden copy, then
 * merge the copy back into the original when it is (re)published.
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
 * Final class Rewrite_Republish
 *
 * Flow:
 * 1. "Rewrite & Republish" creates an exact draft copy of a published
 *    post and links the two through post meta.
 * 2. When the copy is published (directly, or later by cron when it
 *    was scheduled) its fields, terms and meta are written onto the
 *    original, which keeps its ID, URL, date and author.
 * 3. The copy is then deleted. In the classic editor and for cron
 *    this happens at the end of the request. The block editor saves
 *    legacy meta boxes in a second request after publishing, so there
 *    the copy is only demoted to draft and the editor script calls the
 *    finish action, which syncs late meta and deletes the copy.
 */
final class Rewrite_Republish {

    /**
     * Start action.
     */
    const ACTION = 'dpp_rewrite_republish';

    /**
     * Finish action called by the block editor after republishing.
     */
    const FINISH_ACTION = 'dpp_rewrite_finish';

    /**
     * Meta on the copy: ID of the original.
     */
    const COPY_META = '_dpp_rewrite_original';

    /**
     * Meta on the original: ID of its copy.
     */
    const HAS_COPY_META = '_dpp_rewrite_copy';

    /**
     * Meta on the copy once it has been merged into the original.
     */
    const REPUBLISHED_META = '_dpp_republished';

    /**
     * Meta keys on the original that republishing never touches.
     */
    const PROTECTED_META = array(
        '_dpp_*',
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_wp_old_date',
        '_wp_trash_meta_status',
        '_wp_trash_meta_time',
        '_wp_desired_post_slug',
    );

    /**
     * Single instance.
     *
     * @var Rewrite_Republish|null
     */
    private static $instance = null;

    /**
     * Guard against re-entry while the original is being updated.
     *
     * @var bool
     */
    private $republishing = false;

    /**
     * Copies republished in this request: copy ID => original ID.
     *
     * @var int[]
     */
    private $republished = array();

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Rewrite_Republish
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Rewrite_Republish is not allowed.', 'duplicate-pp' ), '4.0.0' );
    }

    /**
     * Register hooks. Called by Plugin::boot().
     *
     * @return void
     */
    public function register() {
        add_action( 'admin_action_' . self::ACTION, array( $this, 'handle_create' ) );
        add_action( 'admin_action_' . self::FINISH_ACTION, array( $this, 'handle_finish' ) );
        add_action( 'wp_insert_post', array( $this, 'maybe_republish' ), 9999, 2 );
        add_action( 'before_delete_post', array( $this, 'on_delete' ) );
        add_filter( 'redirect_post_location', array( $this, 'classic_redirect' ), 10, 2 );
        add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
    }

    /**
     * Whether a post is a Rewrite & Republish copy.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public function is_copy( $post_id ) {
        return $this->get_original_id( $post_id ) > 0;
    }

    /**
     * ID of the original a copy was made from.
     *
     * @param int $copy_id Copy ID.
     * @return int Zero when the post is not a copy.
     */
    public function get_original_id( $copy_id ) {
        return (int) get_post_meta( (int) $copy_id, self::COPY_META, true );
    }

    /**
     * The original a copy was made from.
     *
     * Never pass a possibly-zero ID to get_post(): it would return the
     * global post, i.e. whatever is being edited.
     *
     * @param int $copy_id Copy ID.
     * @return WP_Post|null
     */
    public function get_original( $copy_id ) {
        $original_id = $this->get_original_id( $copy_id );
        $original    = $original_id > 0 ? get_post( $original_id ) : null;
        return $original instanceof WP_Post ? $original : null;
    }

    /**
     * ID of the live (not trashed) copy of an original.
     *
     * @param int $original_id Original ID.
     * @return int Zero when there is none.
     */
    public function get_copy_id( $original_id ) {
        $copy_id = (int) get_post_meta( (int) $original_id, self::HAS_COPY_META, true );
        if ( $copy_id <= 0 ) {
            return 0;
        }

        $copy = get_post( $copy_id );
        if ( ! $copy instanceof WP_Post
            || 'trash' === $copy->post_status
            || $this->get_original_id( $copy_id ) !== (int) $original_id
            || get_post_meta( $copy_id, self::REPUBLISHED_META, true ) ) {
            return 0;
        }
        return $copy_id;
    }

    /**
     * Start request handler: create the copy and open it in the editor.
     *
     * @return void
     */
    public function handle_create() {
        $links   = Plugin::instance()->links();
        $post_id = $links->request_post_id();
        $post    = $post_id > 0 ? get_post( $post_id ) : null;

        if ( ! $links->verify( self::ACTION, $post_id ) ) {
            wp_die( esc_html__( 'Security check failed.', 'duplicate-pp' ), '', array( 'response' => 403, 'back_link' => true ) );
        }
        if ( ! $post instanceof WP_Post ) {
            wp_die( esc_html__( 'Post not found.', 'duplicate-pp' ), '', array( 'response' => 404, 'back_link' => true ) );
        }

        // A copy already in progress: just open it.
        $existing = $this->get_copy_id( $post->ID );
        if ( $existing && current_user_can( 'edit_post', $existing ) ) {
            Plugin::instance()->duplicator()->redirect_to_editor( $existing, 'rewrite' );
        }

        if ( ! Plugin::instance()->permissions()->can_rewrite( $post ) ) {
            wp_die( esc_html__( 'You do not have permission to rewrite and republish this item.', 'duplicate-pp' ), '', array( 'response' => 403, 'back_link' => true ) );
        }

        $copy_id = $this->create_copy( $post );
        if ( is_wp_error( $copy_id ) ) {
            wp_die( esc_html( $copy_id->get_error_message() ), '', array( 'response' => 500, 'back_link' => true ) );
        }

        Plugin::instance()->duplicator()->redirect_to_editor( $copy_id, 'rewrite' );
    }

    /**
     * Create a Rewrite & Republish copy of a published post.
     *
     * @param WP_Post $post Original post.
     * @return int|\WP_Error Copy ID or error.
     */
    public function create_copy( $post ) {
        $copy_id = Plugin::instance()->duplicator()->duplicate( $post, array( 'mode' => Duplicator::MODE_REWRITE ) );
        if ( is_wp_error( $copy_id ) ) {
            return $copy_id;
        }

        update_post_meta( $copy_id, self::COPY_META, $post->ID );
        update_post_meta( $post->ID, self::HAS_COPY_META, $copy_id );

        return $copy_id;
    }

    /**
     * After any save: when a copy reaches "publish", merge it into the original.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @return void
     */
    public function maybe_republish( $post_id, $post ) {
        if ( $this->republishing || 'publish' !== $post->post_status || isset( $this->republished[ $post_id ] ) ) {
            return;
        }

        $original = $this->get_original( $post_id );
        if ( ! $original || get_post_meta( $post_id, self::REPUBLISHED_META, true ) ) {
            return;
        }

        // REST saves set terms and meta after wp_insert_post: wait for them.
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            add_action( 'rest_after_insert_' . $post->post_type, array( $this, 'republish_after_rest' ), 9999 );
            return;
        }

        $this->republish( $post, $original );
        add_action( 'shutdown', array( $this, 'delete_republished_copies' ) );
    }

    /**
     * REST (block editor) save finished: merge, then take the copy offline.
     *
     * @param WP_Post $post Saved post.
     * @return void
     */
    public function republish_after_rest( $post ) {
        $post = get_post( $post );
        if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || isset( $this->republished[ $post->ID ] ) ) {
            return;
        }
        $original = $this->get_original( $post->ID );
        if ( ! $original ) {
            return;
        }

        $this->republish( $post, $original );
        add_action( 'shutdown', array( $this, 'demote_republished_copies' ) );
    }

    /**
     * Write a copy's content, terms and meta onto its original.
     *
     * The original keeps its ID, slug, status, date and author.
     *
     * @param WP_Post $copy     The copy.
     * @param WP_Post $original The original.
     * @return void
     */
    public function republish( $copy, $original ) {
        $this->republishing = true;

        /**
         * Fires before a Rewrite & Republish copy is merged into its original.
         *
         * @since 4.0.0
         *
         * @param int $original_id Original ID.
         * @param int $copy_id     Copy ID.
         */
        do_action( 'dpp_before_republish', $original->ID, $copy->ID );

        wp_update_post(
            wp_slash(
                array(
                    'ID'                    => $original->ID,
                    'post_title'            => $copy->post_title,
                    'post_content'          => $copy->post_content,
                    'post_content_filtered' => $copy->post_content_filtered,
                    'post_excerpt'          => $copy->post_excerpt,
                    'post_password'         => $copy->post_password,
                    'menu_order'            => $copy->menu_order,
                    'comment_status'        => $copy->comment_status,
                    'ping_status'           => $copy->ping_status,
                )
            )
        );

        $this->sync_terms_and_meta( $copy->ID, $original->ID );

        update_post_meta( $copy->ID, self::REPUBLISHED_META, time() );
        $this->republished[ $copy->ID ] = $original->ID;

        /**
         * Fires after a Rewrite & Republish copy has been merged into its original.
         *
         * @since 4.0.0
         *
         * @param int $original_id Original ID.
         * @param int $copy_id     Copy ID.
         */
        do_action( 'dpp_after_republish', $original->ID, $copy->ID );

        $this->republishing = false;
    }

    /**
     * Replace the original's terms and meta with the copy's.
     *
     * @param int $copy_id     Copy ID.
     * @param int $original_id Original ID.
     * @return void
     */
    private function sync_terms_and_meta( $copy_id, $original_id ) {
        foreach ( get_object_taxonomies( get_post_type( $original_id ) ) as $taxonomy ) {
            $terms = wp_get_object_terms( $copy_id, $taxonomy, array( 'fields' => 'ids', 'orderby' => 'term_order' ) );
            if ( ! is_wp_error( $terms ) ) {
                wp_set_object_terms( $original_id, array_map( 'intval', $terms ), $taxonomy );
            }
        }

        $meta = Plugin::instance()->meta_handler();

        foreach ( array_keys( get_post_meta( $original_id ) ) as $key ) {
            if ( ! $meta->matches( $key, self::PROTECTED_META ) ) {
                delete_post_meta( $original_id, $key );
            }
        }

        $skip = array_merge( self::PROTECTED_META, array( '_elementor_css', Duplicator::ORIGINAL_META ) );
        foreach ( get_post_meta( $copy_id ) as $key => $values ) {
            if ( $meta->matches( $key, $skip ) ) {
                continue;
            }
            foreach ( $values as $value ) {
                add_post_meta( $original_id, $key, wp_slash( maybe_unserialize( $value ) ) );
            }
        }

        clean_post_cache( $original_id );
    }

    /**
     * Shutdown: delete copies republished during this request.
     *
     * @return void
     */
    public function delete_republished_copies() {
        foreach ( array_keys( $this->republished ) as $copy_id ) {
            wp_delete_post( $copy_id, true );
        }
    }

    /**
     * Shutdown (REST): take republished copies offline without firing save
     * hooks, so the copy never sits published next to the original. The
     * editor script deletes it through the finish action.
     *
     * @return void
     */
    public function demote_republished_copies() {
        global $wpdb;
        foreach ( array_keys( $this->republished ) as $copy_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $copy_id ) );
            clean_post_cache( $copy_id );
        }
    }

    /**
     * URL the block editor opens after republishing.
     *
     * @param int $copy_id Copy ID.
     * @return string
     */
    public function finish_url( $copy_id ) {
        return Plugin::instance()->links()->url( self::FINISH_ACTION, $copy_id );
    }

    /**
     * Finish request handler: sync meta saved after the REST request
     * (legacy meta boxes), delete the copy, open the original.
     *
     * @return void
     */
    public function handle_finish() {
        $links   = Plugin::instance()->links();
        $copy_id = $links->request_post_id();

        if ( ! $links->verify( self::FINISH_ACTION, $copy_id ) ) {
            wp_die( esc_html__( 'Security check failed.', 'duplicate-pp' ), '', array( 'response' => 403, 'back_link' => true ) );
        }

        $original_id = $this->get_original_id( $copy_id );
        if ( ! $original_id || ! get_post( $original_id ) ) {
            wp_die( esc_html__( 'The original of this copy could not be found.', 'duplicate-pp' ), '', array( 'response' => 404, 'back_link' => true ) );
        }

        if ( ! get_post_meta( $copy_id, self::REPUBLISHED_META, true ) ) {
            // Scheduled or not yet published: back to the copy.
            Plugin::instance()->duplicator()->redirect_to_editor( $copy_id, 'rewrite' );
        }

        if ( current_user_can( 'edit_post', $original_id ) ) {
            $this->republishing = true;
            $this->sync_terms_and_meta( $copy_id, $original_id );
            $this->republishing = false;
        }
        if ( current_user_can( 'delete_post', $copy_id ) ) {
            wp_delete_post( $copy_id, true );
        }

        Plugin::instance()->duplicator()->redirect_to_editor( $original_id, 'republished' );
    }

    /**
     * Classic editor: after republishing, land on the original instead
     * of the (about to be deleted) copy.
     *
     * @param string $location Redirect URL.
     * @param int    $post_id  Saved post ID.
     * @return string
     */
    public function classic_redirect( $location, $post_id ) {
        if ( ! isset( $this->republished[ $post_id ] ) ) {
            return $location;
        }
        $url = get_edit_post_link( $this->republished[ $post_id ], 'raw' );
        return $url ? add_query_arg( 'dpp_notice', 'republished', $url ) : $location;
    }

    /**
     * Keep the link between original and copy consistent on deletion.
     *
     * @param int $post_id Post being deleted.
     * @return void
     */
    public function on_delete( $post_id ) {
        $original_id = $this->get_original_id( $post_id );
        if ( $original_id ) {
            if ( (int) get_post_meta( $original_id, self::HAS_COPY_META, true ) === (int) $post_id ) {
                delete_post_meta( $original_id, self::HAS_COPY_META );
            }
            return;
        }

        // Deleting an original permanently also deletes its unfinished copy.
        $copy_id = (int) get_post_meta( $post_id, self::HAS_COPY_META, true );
        if ( $copy_id && $this->get_original_id( $copy_id ) === (int) $post_id ) {
            wp_delete_post( $copy_id, true );
        }
    }

    /**
     * Label copies in the list table.
     *
     * @param string[] $states Post states.
     * @param WP_Post  $post   Post.
     * @return string[]
     */
    public function post_states( $states, $post ) {
        if ( $post instanceof WP_Post && $this->is_copy( $post->ID ) ) {
            $states['dpp_rewrite'] = __( 'Rewrite & Republish copy', 'duplicate-pp' );
        }
        return $states;
    }
}
