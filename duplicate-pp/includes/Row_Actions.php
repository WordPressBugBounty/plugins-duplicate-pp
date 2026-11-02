<?php
/**
 * List table integration: row actions and bulk actions.
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
 * Adds Duplicate / New Draft / Rewrite & Republish links to list
 * table rows, and Duplicate / Rewrite & Republish to the bulk
 * actions menu of every enabled post type.
 */
final class Row_Actions {

    /**
     * Bulk action names.
     */
    const BULK_CLONE    = 'dpp_bulk_clone';
    const BULK_REWRITE  = 'dpp_bulk_rewrite';
    const BULK_MULTIPLE = 'dpp_bulk_multiple';

    /**
     * Script/style handle of the "copy multiple" dialog.
     */
    const HANDLE = 'duplicate-pp-list';

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
        add_action( 'load-edit.php', array( $this, 'register_bulk_actions' ) );
    }

    /**
     * Append the copy links to a row's actions.
     *
     * @param array   $actions Existing row actions.
     * @param WP_Post $post    Current row's post object.
     * @return array
     */
    public function add_duplicate_link( $actions, $post ) {
        if ( ! $post instanceof WP_Post || ! Plugin::instance()->settings()->is_on( 'show_in', 'row' ) ) {
            return $actions;
        }

        $permissions = Plugin::instance()->permissions();
        $links       = Plugin::instance()->links();
        $title       = Plugin::post_title( $post );

        if ( $permissions->can_copy( $post ) ) {
            $actions['duplicate'] = $this->link(
                $links->clone_url( $post->ID ),
                /* translators: %s: post title. */
                sprintf( __( 'Duplicate &#8220;%s&#8221;', 'duplicate-pp' ), $title ),
                __( 'Duplicate', 'duplicate-pp' )
            );
            $actions['dpp_new_draft'] = $this->link(
                $links->new_draft_url( $post->ID ),
                /* translators: %s: post title. */
                sprintf( __( 'Copy &#8220;%s&#8221; to a new draft', 'duplicate-pp' ), $title ),
                __( 'New Draft', 'duplicate-pp' )
            );
            $actions['dpp_multiple'] = sprintf(
                '<a href="%1$s" class="dpp-copy-multiple" data-title="%2$s" aria-label="%3$s">%4$s</a>',
                esc_url( $links->multiple_url( $post->ID ) ),
                esc_attr( $title ),
                /* translators: %s: post title. */
                esc_attr( sprintf( __( 'Make several copies of &#8220;%s&#8221;', 'duplicate-pp' ), $title ) ),
                esc_html__( 'Copy multiple', 'duplicate-pp' )
            );
        }

        if ( $permissions->can_rewrite( $post ) ) {
            $actions['dpp_rewrite'] = $this->link(
                $links->rewrite_url( $post->ID ),
                /* translators: %s: post title. */
                sprintf( __( 'Rewrite & Republish &#8220;%s&#8221;', 'duplicate-pp' ), $title ),
                __( 'Rewrite & Republish', 'duplicate-pp' )
            );
        } elseif ( Plugin::instance()->settings()->get( 'rewrite_republish' ) ) {
            $copy_id = Rewrite_Republish::instance()->get_copy_id( $post->ID );
            if ( $copy_id && current_user_can( 'edit_post', $copy_id ) ) {
                $actions['dpp_rewrite'] = $this->link(
                    get_edit_post_link( $copy_id, 'raw' ),
                    /* translators: %s: post title. */
                    sprintf( __( 'Continue the rewrite of &#8220;%s&#8221;', 'duplicate-pp' ), $title ),
                    __( 'Continue rewrite', 'duplicate-pp' )
                );
            }
        }

        return $actions;
    }

    /**
     * Add the bulk actions on the list screen of an enabled post type.
     *
     * @return void
     */
    public function register_bulk_actions() {
        global $typenow;

        $post_type = $typenow ? $typenow : 'post';
        $settings  = Plugin::instance()->settings();
        if ( ! Plugin::instance()->permissions()->is_post_type_enabled( $post_type )
            || ! Plugin::instance()->permissions()->user_is_allowed() ) {
            return;
        }

        if ( $settings->is_on( 'show_in', 'row' ) || $settings->is_on( 'show_in', 'bulk' ) ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dialog' ) );
        }
        if ( ! $settings->is_on( 'show_in', 'bulk' ) ) {
            return;
        }

        add_filter( 'bulk_actions-edit-' . $post_type, array( $this, 'bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-' . $post_type, array( $this, 'handle_bulk_action' ), 10, 3 );
    }

    /**
     * Add the entries to the bulk actions dropdown.
     *
     * @param array $actions Bulk actions.
     * @return array
     */
    public function bulk_actions( $actions ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['post_status'] ) && 'trash' === sanitize_key( wp_unslash( $_GET['post_status'] ) ) ) {
            return $actions;
        }

        $actions[ self::BULK_CLONE ]    = __( 'Duplicate', 'duplicate-pp' );
        $actions[ self::BULK_MULTIPLE ] = __( 'Duplicate multiple times…', 'duplicate-pp' );
        if ( Plugin::instance()->settings()->get( 'rewrite_republish' ) ) {
            $actions[ self::BULK_REWRITE ] = __( 'Rewrite & Republish', 'duplicate-pp' );
        }
        return $actions;
    }

    /**
     * Run a bulk action. Core has already checked the bulk-posts nonce.
     *
     * @param string $sendback Redirect URL.
     * @param string $action   Bulk action.
     * @param int[]  $post_ids Selected post IDs.
     * @return string
     */
    public function handle_bulk_action( $sendback, $action, $post_ids ) {
        if ( ! in_array( $action, array( self::BULK_CLONE, self::BULK_REWRITE, self::BULK_MULTIPLE ), true ) ) {
            return $sendback;
        }

        $plugin     = Plugin::instance();
        $duplicator = $plugin->duplicator();
        $post_ids   = array_values( array_unique( array_map( 'intval', (array) $post_ids ) ) );
        $sendback   = remove_query_arg( array( 'duplicated', 'dpp_ids', 'dpp_rewrite', 'dpp_skipped' ), $sendback );
        $done       = 0;
        $skipped    = 0;
        $copies     = 1;
        $numbered   = false;

        if ( self::BULK_MULTIPLE === $action ) {
            $copies = $duplicator->requested_count( 'dpp_count' );
            if ( ! $copies ) {
                return add_query_arg( 'dpp_skipped', count( $post_ids ), $sendback );
            }
            // Keep one request within reason, however many items were selected.
            $copies = min( $copies, $duplicator->max_copies_for_bulk( count( $post_ids ) ) );
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- core checked the bulk-posts nonce.
            $numbered = ! empty( $_REQUEST['dpp_numbered'] );
        }

        if ( self::BULK_REWRITE === $action ) {
            foreach ( $post_ids as $post_id ) {
                $post = get_post( $post_id );
                if ( ! $plugin->permissions()->can_rewrite( $post ) || is_wp_error( Rewrite_Republish::instance()->create_copy( $post ) ) ) {
                    ++$skipped;
                } else {
                    ++$done;
                }
            }
            return add_query_arg( array_filter( array( 'dpp_rewrite' => $done, 'dpp_skipped' => $skipped ) ), $sendback );
        }

        // Items the user may copy. When child pages are copied with their parent,
        // a selected child whose ancestor is also being copied is left to the parent.
        $copyable = array_filter(
            $post_ids,
            function ( $post_id ) use ( $plugin ) {
                return $plugin->permissions()->can_copy( $post_id );
            }
        );
        $skipped += count( $post_ids ) - count( $copyable );
        $with_children = $plugin->settings()->is_on( 'copy', 'children' );

        foreach ( $copyable as $post_id ) {
            if ( $with_children && array_intersect( get_post_ancestors( $post_id ), $copyable ) ) {
                continue;
            }
            if ( self::BULK_MULTIPLE === $action ) {
                $made     = $duplicator->duplicate_many( get_post( $post_id ), $copies, $numbered );
                $done    += $made;
                $skipped += $copies - $made;
                continue;
            }
            if ( is_wp_error( $duplicator->duplicate( $post_id, array( 'mode' => Duplicator::MODE_CLONE ) ) ) ) {
                ++$skipped;
            } else {
                ++$done;
            }
        }

        return add_query_arg( array_filter( array( 'duplicated' => $done, 'dpp_skipped' => $skipped ) ), $sendback );
    }

    /**
     * Load the "copy multiple" dialog on the list screen.
     *
     * @return void
     */
    public function enqueue_dialog() {
        wp_enqueue_style( self::HANDLE, DPP_PLUGIN_URL . 'assets/css/list.css', array(), DPP_VERSION );
        wp_enqueue_script( self::HANDLE, DPP_PLUGIN_URL . 'assets/js/list.js', array(), DPP_VERSION, true );

        $max = Plugin::instance()->duplicator()->max_copies();
        wp_add_inline_script(
            self::HANDLE,
            'window.dppList = ' . wp_json_encode(
                array(
                    'max'        => $max,
                    'bulkTotal'  => max( 1, (int) apply_filters( 'dpp_max_bulk_copies', Duplicator::MAX_BULK_TOTAL ) ),
                    'presets'    => array_values( array_filter( array( 2, 5, 10, 25 ), function ( $n ) use ( $max ) { return $n <= $max; } ) ),
                    'bulkAction' => self::BULK_MULTIPLE,
                    'suffix'     => (string) Plugin::instance()->settings()->get( 'title_suffix', '' ),
                    'i18n'       => array(
                        'title'      => __( 'Make multiple copies', 'duplicate-pp' ),
                        /* translators: %s: post title. */
                        'of'         => __( 'of “%s”', 'duplicate-pp' ),
                        /* translators: %s: number of selected items. */
                        'ofSelected' => __( 'of each of the %s selected items', 'duplicate-pp' ),
                        'count'      => __( 'Number of copies', 'duplicate-pp' ),
                        'less'       => __( 'One fewer', 'duplicate-pp' ),
                        'more'       => __( 'One more', 'duplicate-pp' ),
                        'numbered'   => __( 'Number the titles', 'duplicate-pp' ),
                        /* translators: %d: maximum number of copies. */
                        'limit'      => __( 'Up to %d at a time. Copies follow your "What to copy" settings.', 'duplicate-pp' ),
                        /* translators: 1: maximum copies per item, 2: total limit. */
                        'limitBulk'  => __( 'Up to %1$d per item for this selection (%2$d copies in total). Copies follow your "What to copy" settings.', 'duplicate-pp' ),
                        /* translators: %s: total number of copies. */
                        'total'      => __( '%s copies will be created in total.', 'duplicate-pp' ),
                        'cancel'     => __( 'Cancel', 'duplicate-pp' ),
                        /* translators: %s: number of copies. */
                        'submit'     => __( 'Create %s copies', 'duplicate-pp' ),
                        'submitOne'  => __( 'Create 1 copy', 'duplicate-pp' ),
                        'working'    => __( 'Creating copies…', 'duplicate-pp' ),
                        'example'    => __( 'Hello world', 'duplicate-pp' ),
                    ),
                ),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) . ';',
            'before'
        );
    }

    /**
     * Build one row-action link.
     *
     * @param string $url   Raw URL.
     * @param string $label Accessible label.
     * @param string $text  Visible text.
     * @return string
     */
    private function link( $url, $label, $text ) {
        return sprintf(
            '<a href="%1$s" aria-label="%2$s">%3$s</a>',
            esc_url( $url ),
            esc_attr( $label ),
            esc_html( $text )
        );
    }
}
