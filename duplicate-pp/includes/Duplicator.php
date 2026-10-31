<?php
/**
 * Core duplication logic.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP;

use WP_Error;
use WP_Post;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Duplicator
 *
 * Builds a copy of a post — fields, taxonomies, meta, and optionally
 * children and comments — according to the "What to copy" settings,
 * and handles the Clone and New Draft admin requests.
 */
final class Duplicator {

    /**
     * Clone action: copy, then go back to the list. Name preserved from 3.x.
     */
    const ACTION = 'dpp_duplicate_as_draft';

    /**
     * New Draft action: copy as a draft, then open it in the editor.
     */
    const ACTION_NEW_DRAFT = 'dpp_new_draft';

    /**
     * Multiple action: make several copies at once, then go back to the list.
     */
    const ACTION_MULTIPLE = 'dpp_duplicate_multiple';

    /**
     * Hard upper limit of copies per item in one request, whatever the setting says.
     */
    const MAX_COPIES_LIMIT = 100;

    /**
     * Meta key on every copy pointing at the post it was made from.
     */
    const ORIGINAL_META = '_dpp_original';

    /**
     * Copy modes.
     */
    const MODE_CLONE   = 'clone';
    const MODE_DRAFT   = 'draft';
    const MODE_REWRITE = 'rewrite';

    /**
     * How deep child posts are followed when copying children.
     */
    const MAX_CHILD_DEPTH = 10;

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
        add_action( 'admin_action_' . self::ACTION_NEW_DRAFT, array( $this, 'handle_new_draft' ) );
        add_action( 'admin_action_' . self::ACTION_MULTIPLE, array( $this, 'handle_multiple' ) );
    }

    /**
     * Clone request handler: copy the post and return to the list table
     * (or open the copy, when the "after cloning" setting says so).
     *
     * @return void
     */
    public function handle_duplicate() {
        $post   = $this->validate_request( self::ACTION );
        $new_id = $this->duplicate_or_die( $post, array( 'mode' => self::MODE_CLONE ) );

        if ( 'edit' === Plugin::instance()->settings()->get( 'after_clone' ) ) {
            $this->redirect_to_editor( $new_id, 'cloned' );
        }

        wp_safe_redirect( $this->list_url( $post->post_type, array( 'duplicated' => 1, 'dpp_ids' => $new_id ) ) );
        exit;
    }

    /**
     * New Draft request handler: copy the post as a draft and open it.
     *
     * @return void
     */
    public function handle_new_draft() {
        $post   = $this->validate_request( self::ACTION_NEW_DRAFT );
        $new_id = $this->duplicate_or_die(
            $post,
            array(
                'mode'   => self::MODE_DRAFT,
                'status' => 'draft',
            )
        );

        $this->redirect_to_editor( $new_id, 'new_draft' );
    }

    /**
     * Multiple request handler: make N copies of one post and return to the list.
     *
     * @return void
     */
    public function handle_multiple() {
        $post  = $this->validate_request( self::ACTION_MULTIPLE );
        $count = $this->requested_count( 'count' );
        if ( ! $count ) {
            wp_die(
                /* translators: %d: maximum number of copies. */
                esc_html( sprintf( __( 'Choose how many copies to make, from 1 to %d.', 'duplicate-pp' ), $this->max_copies() ) ),
                '',
                array( 'response' => 400, 'back_link' => true )
            );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in validate_request().
        $numbered = ! empty( $_REQUEST['numbered'] );
        $made     = $this->duplicate_many( $post, $count, $numbered );

        wp_safe_redirect( $this->list_url( $post->post_type, array_filter( array( 'duplicated' => $made, 'dpp_skipped' => $count - $made ) ) ) );
        exit;
    }

    /**
     * Make several copies of a post.
     *
     * @param \WP_Post $post     Source post.
     * @param int      $count    Number of copies.
     * @param bool     $numbered Whether to number the titles: "(Copy 1)", "(Copy 2)"…
     * @return int Number of copies made.
     */
    public function duplicate_many( $post, $count, $numbered ) {
        $made  = 0;
        $count = min( (int) $count, $this->max_copies() );

        for ( $i = 1; $i <= $count; $i++ ) {
            $result = $this->duplicate(
                $post,
                array(
                    'mode'   => self::MODE_CLONE,
                    'number' => $numbered ? $i : 0,
                )
            );
            if ( ! is_wp_error( $result ) ) {
                ++$made;
            }
        }
        return $made;
    }

    /**
     * Most copies of one item allowed in one go.
     *
     * @return int
     */
    public function max_copies() {
        $max = (int) Plugin::instance()->settings()->get( 'max_copies', 50 );

        /**
         * Filters how many copies of one item can be made at once.
         *
         * @since 4.0.0
         *
         * @param int $max Maximum, between 1 and Duplicator::MAX_COPIES_LIMIT.
         */
        $max = (int) apply_filters( 'dpp_max_copies', $max );

        return max( 1, min( self::MAX_COPIES_LIMIT, $max ) );
    }

    /**
     * Copy count from the request, clamped to the allowed range.
     *
     * @param string $key Request key.
     * @return int Zero when missing or invalid.
     */
    public function requested_count( $key ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- callers verify the nonce.
        $count = isset( $_REQUEST[ $key ] ) ? (int) wp_unslash( $_REQUEST[ $key ] ) : 0;
        return $count > 0 ? min( $count, $this->max_copies() ) : 0;
    }

    /**
     * Title suffix with a copy number: " (Copy)" becomes " (Copy 3)".
     *
     * @param string $suffix Title suffix setting.
     * @param int    $number Copy number; 0 leaves the suffix alone.
     * @return string
     */
    private function numbered_suffix( $suffix, $number ) {
        if ( $number <= 0 ) {
            return $suffix;
        }
        if ( preg_match( '/^(.*?)\s*([)\]])\s*$/s', $suffix, $parts ) ) {
            return $parts[1] . ' ' . $number . $parts[2];
        }
        return rtrim( $suffix ) . ' ' . $number;
    }

    /**
     * Check the nonce, the post and the permissions of an action request.
     * Dies with an error page when anything is off.
     *
     * @param string $action Admin action name.
     * @return WP_Post The source post.
     */
    public function validate_request( $action ) {
        $links   = Plugin::instance()->links();
        $post_id = $links->request_post_id();

        if ( ! $links->verify( $action, $post_id ) ) {
            wp_die( esc_html__( 'Security check failed.', 'duplicate-pp' ), '', array( 'response' => 403, 'back_link' => true ) );
        }
        if ( $post_id <= 0 ) {
            wp_die( esc_html__( 'Invalid post ID.', 'duplicate-pp' ), '', array( 'response' => 400, 'back_link' => true ) );
        }

        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post ) {
            wp_die( esc_html__( 'Post not found.', 'duplicate-pp' ), '', array( 'response' => 404, 'back_link' => true ) );
        }
        if ( ! Plugin::instance()->permissions()->can_copy( $post ) ) {
            wp_die( esc_html__( 'You do not have permission to duplicate this item.', 'duplicate-pp' ), '', array( 'response' => 403, 'back_link' => true ) );
        }

        return $post;
    }

    /**
     * Duplicate a post.
     *
     * @param WP_Post|int $post Source post.
     * @param array       $args {
     *     Optional. Copy arguments.
     *
     *     @type string $mode   One of the MODE_* constants. Default clone.
     *     @type string $status Force the copy's status.
     *     @type int    $parent Force the copy's parent (used for child posts).
     * }
     * @return int|WP_Error New post ID or error.
     */
    public function duplicate( $post, $args = array() ) {
        $post = get_post( $post );
        if ( ! $post instanceof WP_Post ) {
            return new WP_Error( 'dpp_invalid_post', __( 'Post not found.', 'duplicate-pp' ) );
        }

        $args     = wp_parse_args( $args, array( 'mode' => self::MODE_CLONE, 'depth' => 0 ) );
        $settings = $this->effective_settings( $args['mode'] );

        /**
         * Fires before a post is duplicated.
         *
         * @since 4.0.0
         *
         * @param WP_Post $post Source post.
         * @param string  $mode Copy mode: clone, draft or rewrite.
         */
        do_action( 'dpp_before_duplicate', $post, $args['mode'] );

        $data   = $this->build_args( $post, $settings, $args );
        $new_id = wp_insert_post( wp_slash( $data ), true );
        if ( is_wp_error( $new_id ) ) {
            return $new_id;
        }

        $copy = $settings['copy'];

        if ( $copy['taxonomies'] || $copy['format'] ) {
            $this->duplicate_taxonomies( $post->ID, $new_id, $settings );
        }

        if ( $copy['meta'] ) {
            Plugin::instance()->meta_handler()->duplicate_post_meta( $post->ID, $new_id, $this->excluded_meta( $post, $settings, $args['mode'] ) );
        } else {
            $this->copy_essential_meta( $post->ID, $new_id, $copy );
        }

        update_post_meta( $new_id, self::ORIGINAL_META, $post->ID );

        if ( $copy['comments'] ) {
            $this->duplicate_comments( $post->ID, $new_id );
        }

        if ( $copy['children'] && $args['depth'] < self::MAX_CHILD_DEPTH ) {
            $this->duplicate_children( $post, $new_id, $args );
        }

        /**
         * Fires after a post has been duplicated.
         *
         * @since 4.0.0
         *
         * @param int     $new_id New post ID.
         * @param WP_Post $post   Source post.
         * @param string  $mode   Copy mode: clone, draft or rewrite.
         */
        do_action( 'dpp_after_duplicate', $new_id, $post, $args['mode'] );

        return $new_id;
    }

    /**
     * Settings used for a copy. Rewrite & Republish copies are exact
     * copies, so they ignore the "What to copy" choices.
     *
     * @param string $mode Copy mode.
     * @return array
     */
    private function effective_settings( $mode ) {
        $settings = Plugin::instance()->settings()->all();

        if ( self::MODE_REWRITE === $mode ) {
            $settings['title_prefix']         = '';
            $settings['title_suffix']         = '';
            $settings['slug_prefix']          = '';
            $settings['slug_suffix']          = '';
            $settings['menu_order_increment'] = 0;
            $settings['exclude_meta']         = array();
            $settings['exclude_taxonomies']   = array();
            $settings['copy']                 = array_merge(
                array_fill_keys( array_keys( $settings['copy'] ), 1 ),
                array(
                    'author'   => 0,
                    'date'     => 0,
                    'children' => 0,
                    'comments' => 0,
                )
            );
        }

        return $settings;
    }

    /**
     * Build the wp_insert_post() argument array (unslashed).
     *
     * @param WP_Post $post     Source post.
     * @param array   $settings Effective settings.
     * @param array   $args     Copy arguments.
     * @return array
     */
    private function build_args( $post, $settings, $args ) {
        $copy    = $settings['copy'];
        $rewrite = self::MODE_REWRITE === $args['mode'];

        // Status: forced by the caller, else the setting ('same' keeps the original's).
        if ( ! empty( $args['status'] ) ) {
            $status = $args['status'];
        } elseif ( $rewrite ) {
            $status = 'draft';
        } elseif ( 'same' === $settings['post_status'] ) {
            $status = $post->post_status;
        } else {
            $status = $settings['post_status'];
        }

        // Never hand out a status the user could not have set themselves.
        if ( in_array( $status, array( 'publish', 'future', 'private' ), true )
            && ! Plugin::instance()->permissions()->can_publish( $post->post_type ) ) {
            $status = 'pending';
        }

        $data = array(
            'post_author'           => $copy['author'] ? $post->post_author : get_current_user_id(),
            'post_content'          => $copy['content'] ? $post->post_content : '',
            'post_content_filtered' => $copy['content'] ? $post->post_content_filtered : '',
            'post_excerpt'          => $copy['excerpt'] ? $post->post_excerpt : '',
            'post_title'            => $settings['title_prefix'] . $post->post_title . $this->numbered_suffix( $settings['title_suffix'], isset( $args['number'] ) ? (int) $args['number'] : 0 ),
            'post_status'           => $status,
            'post_type'             => $post->post_type,
            'post_parent'           => isset( $args['parent'] ) ? (int) $args['parent'] : $post->post_parent,
            'post_password'         => $copy['password'] ? $post->post_password : '',
            'post_mime_type'        => $post->post_mime_type,
            'menu_order'            => $copy['menu_order'] ? (int) $post->menu_order + (int) $settings['menu_order_increment'] : 0,
            'comment_status'        => $copy['comment_status'] ? $post->comment_status : get_default_comment_status( $post->post_type ),
            'ping_status'           => $copy['comment_status'] ? $post->ping_status : get_default_comment_status( $post->post_type, 'pingback' ),
            'to_ping'               => $post->to_ping,
        );

        // A scheduled copy needs the original's date to stay scheduled.
        if ( $copy['date'] || 'future' === $status ) {
            $data['post_date']     = $post->post_date;
            $data['post_date_gmt'] = $post->post_date_gmt;
        }

        if ( ! $rewrite && '' !== $post->post_name ) {
            $slug              = $settings['slug_prefix'] . $post->post_name . $settings['slug_suffix'];
            $data['post_name'] = wp_unique_post_slug( sanitize_title( $slug ), 0, 'publish', $post->post_type, $data['post_parent'] );
        }

        /**
         * Filters the wp_insert_post() arguments of a copy (unslashed).
         *
         * @since 4.0.0
         *
         * @param array   $data Post data.
         * @param WP_Post $post Source post.
         * @param string  $mode Copy mode: clone, draft or rewrite.
         */
        return apply_filters( 'dpp_duplicate_post_args', $data, $post, $args['mode'] );
    }

    /**
     * Copy object terms from the source post to the duplicate.
     *
     * @param int        $original_id Source post ID.
     * @param int        $new_id      Destination post ID.
     * @param array|null $settings    Effective settings; null copies every taxonomy.
     * @return void
     */
    public function duplicate_taxonomies( $original_id, $new_id, $settings = null ) {
        $taxonomies = get_object_taxonomies( get_post_type( $original_id ) );
        if ( empty( $taxonomies ) ) {
            return;
        }

        $excluded = $settings ? (array) $settings['exclude_taxonomies'] : array();

        foreach ( $taxonomies as $taxonomy ) {
            if ( in_array( $taxonomy, $excluded, true ) ) {
                continue;
            }
            if ( $settings ) {
                $switch = 'post_format' === $taxonomy ? 'format' : 'taxonomies';
                if ( empty( $settings['copy'][ $switch ] ) ) {
                    continue;
                }
            }

            $terms = wp_get_object_terms( $original_id, $taxonomy, array( 'fields' => 'ids', 'orderby' => 'term_order' ) );
            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }
            wp_set_object_terms( $new_id, array_map( 'intval', $terms ), $taxonomy );
        }
    }

    /**
     * Meta keys to skip for a copy, from the settings and the copy switches.
     *
     * @param WP_Post $post     Source post.
     * @param array   $settings Effective settings.
     * @param string  $mode     Copy mode.
     * @return string[]
     */
    private function excluded_meta( $post, $settings, $mode ) {
        $exclude = (array) $settings['exclude_meta'];

        if ( ! $settings['copy']['thumbnail'] ) {
            $exclude[] = '_thumbnail_id';
        }
        if ( ! $settings['copy']['template'] ) {
            $exclude[] = '_wp_page_template';
        }

        // WooCommerce: a SKU must be unique and sales/ratings belong to the original.
        if ( 'product' === $post->post_type && self::MODE_REWRITE !== $mode ) {
            $exclude = array_merge( $exclude, array( '_sku', 'total_sales', '_wc_average_rating', '_wc_rating_count', '_wc_review_count' ) );
        }

        return $exclude;
    }

    /**
     * When custom fields are not copied, still copy the featured image
     * and the page template if those are switched on.
     *
     * @param int   $original_id Source post ID.
     * @param int   $new_id      Destination post ID.
     * @param array $copy        "What to copy" switches.
     * @return void
     */
    private function copy_essential_meta( $original_id, $new_id, $copy ) {
        if ( $copy['thumbnail'] ) {
            $thumbnail_id = get_post_thumbnail_id( $original_id );
            if ( $thumbnail_id ) {
                set_post_thumbnail( $new_id, $thumbnail_id );
            }
        }
        if ( $copy['template'] ) {
            $template = get_post_meta( $original_id, '_wp_page_template', true );
            if ( $template ) {
                update_post_meta( $new_id, '_wp_page_template', wp_slash( $template ) );
            }
        }
    }

    /**
     * Copy comments (with their meta and threading) to the duplicate.
     *
     * @param int $original_id Source post ID.
     * @param int $new_id      Destination post ID.
     * @return void
     */
    private function duplicate_comments( $original_id, $new_id ) {
        $comments = get_comments(
            array(
                'post_id' => $original_id,
                'status'  => 'all',
                'orderby' => 'comment_ID',
                'order'   => 'ASC',
            )
        );

        $map = array();
        foreach ( $comments as $comment ) {
            $data = array(
                'comment_post_ID'      => $new_id,
                'comment_author'       => $comment->comment_author,
                'comment_author_email' => $comment->comment_author_email,
                'comment_author_url'   => $comment->comment_author_url,
                'comment_author_IP'    => $comment->comment_author_IP,
                'comment_date'         => $comment->comment_date,
                'comment_date_gmt'     => $comment->comment_date_gmt,
                'comment_content'      => $comment->comment_content,
                'comment_karma'        => $comment->comment_karma,
                'comment_approved'     => $comment->comment_approved,
                'comment_agent'        => $comment->comment_agent,
                'comment_type'         => $comment->comment_type,
                'comment_parent'       => isset( $map[ $comment->comment_parent ] ) ? $map[ $comment->comment_parent ] : 0,
                'user_id'              => $comment->user_id,
            );

            $new_comment_id = wp_insert_comment( wp_slash( $data ) );
            if ( ! $new_comment_id ) {
                continue;
            }
            $map[ $comment->comment_ID ] = $new_comment_id;

            foreach ( get_comment_meta( $comment->comment_ID ) as $key => $values ) {
                foreach ( $values as $value ) {
                    add_comment_meta( $new_comment_id, $key, wp_slash( maybe_unserialize( $value ) ) );
                }
            }
        }
    }

    /**
     * Copy child posts of the same type under the new parent.
     *
     * @param WP_Post $post   Source post.
     * @param int     $new_id New parent ID.
     * @param array   $args   Copy arguments of the parent.
     * @return void
     */
    private function duplicate_children( $post, $new_id, $args ) {
        $children = get_posts(
            array(
                'post_parent'      => $post->ID,
                'post_type'        => $post->post_type,
                'post_status'      => 'any',
                'posts_per_page'   => -1,
                'orderby'          => 'menu_order',
                'order'            => 'ASC',
                'suppress_filters' => true,
            )
        );

        foreach ( $children as $child ) {
            if ( ! Plugin::instance()->permissions()->can_copy( $child ) ) {
                continue;
            }
            $this->duplicate(
                $child,
                array_merge(
                    $args,
                    array(
                        'parent' => $new_id,
                        'depth'  => $args['depth'] + 1,
                        'number' => 0,
                    )
                )
            );
        }
    }

    /**
     * Duplicate a post or show an error page.
     *
     * @param WP_Post $post Source post.
     * @param array   $args Copy arguments.
     * @return int New post ID.
     */
    private function duplicate_or_die( $post, $args ) {
        $new_id = $this->duplicate( $post, $args );
        if ( is_wp_error( $new_id ) ) {
            wp_die( esc_html( $new_id->get_error_message() ), '', array( 'response' => 500, 'back_link' => true ) );
        }
        return $new_id;
    }

    /**
     * Redirect to the editor of a post with a notice flag, then exit.
     *
     * @param int    $post_id Post to edit.
     * @param string $notice  Notice key read by Notices / the editor script.
     * @return void
     */
    public function redirect_to_editor( $post_id, $notice ) {
        $url = get_edit_post_link( $post_id, 'raw' );
        if ( ! $url ) {
            $url = admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
        }
        wp_safe_redirect( add_query_arg( 'dpp_notice', $notice, $url ) );
        exit;
    }

    /**
     * URL of the list table to return to, keeping the referring list's filters.
     *
     * @param string $post_type Post type of the list.
     * @param array  $args      Query args to add.
     * @return string
     */
    public function list_url( $post_type, $args = array() ) {
        $referer = wp_get_referer();
        // Return to the list (or the plugin dashboard's quick search) the request came from.
        if ( $referer && ( false !== strpos( $referer, 'edit.php' ) || false !== strpos( $referer, 'page=' . Settings\Settings_Page::PAGE_SLUG ) ) ) {
            $url = remove_query_arg( array( 'duplicated', 'dpp_ids', 'dpp_rewrite', 'dpp_skipped', 'trashed', 'untrashed', 'deleted', 'ids' ), $referer );
        } else {
            $url = add_query_arg( 'post_type', $post_type, admin_url( 'edit.php' ) );
        }
        return add_query_arg( $args, $url );
    }
}
