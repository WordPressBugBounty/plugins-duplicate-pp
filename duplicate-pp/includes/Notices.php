<?php
/**
 * Admin notices shown after copy actions.
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
 * Renders dismissible notices on list tables (after Duplicate and
 * the bulk actions) and in the classic editor (after New Draft and
 * Rewrite & Republish). The block editor shows the same messages
 * through assets/js/editor.js.
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
        add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
    }

    /**
     * Strip the notice flags from the address bar after display.
     *
     * @param string[] $args Query args.
     * @return string[]
     */
    public function removable_query_args( $args ) {
        return array_merge( $args, array( 'duplicated', 'dpp_ids', 'dpp_rewrite', 'dpp_skipped', 'dpp_notice' ) );
    }

    /**
     * Render the notice matching the flags in the URL.
     *
     * @return void
     */
    public function maybe_render() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- display flags only.
        $duplicated = isset( $_GET['duplicated'] ) ? absint( wp_unslash( $_GET['duplicated'] ) ) : 0;
        $rewrite    = isset( $_GET['dpp_rewrite'] ) ? absint( wp_unslash( $_GET['dpp_rewrite'] ) ) : 0;
        $skipped    = isset( $_GET['dpp_skipped'] ) ? absint( wp_unslash( $_GET['dpp_skipped'] ) ) : 0;
        $single_id  = isset( $_GET['dpp_ids'] ) ? absint( wp_unslash( $_GET['dpp_ids'] ) ) : 0;
        $notice     = isset( $_GET['dpp_notice'] ) ? sanitize_key( wp_unslash( $_GET['dpp_notice'] ) ) : '';
        // phpcs:enable

        if ( $duplicated ) {
            $message = 1 === $duplicated
                ? __( 'Item duplicated successfully.', 'duplicate-pp' )
                /* translators: %s: number of items. */
                : sprintf( _n( '%s item duplicated.', '%s items duplicated.', $duplicated, 'duplicate-pp' ), number_format_i18n( $duplicated ) );

            $link = '';
            if ( 1 === $duplicated && $single_id && current_user_can( 'edit_post', $single_id ) ) {
                $link = sprintf( ' <a href="%1$s">%2$s</a>', esc_url( get_edit_post_link( $single_id ) ), esc_html__( 'Edit the copy', 'duplicate-pp' ) );
            }
            $this->print_notice( 'success', esc_html( $message ) . $link );
        }

        if ( $rewrite ) {
            /* translators: %s: number of items. */
            $message = sprintf( _n( '%s Rewrite & Republish copy created.', '%s Rewrite & Republish copies created.', $rewrite, 'duplicate-pp' ), number_format_i18n( $rewrite ) );
            $this->print_notice( 'success', esc_html( $message ) );
        }

        if ( $skipped ) {
            /* translators: %s: number of items. */
            $message = sprintf( _n( '%s item was skipped: it cannot be copied, or you are not allowed to copy it.', '%s items were skipped: they cannot be copied, or you are not allowed to copy them.', $skipped, 'duplicate-pp' ), number_format_i18n( $skipped ) );
            $this->print_notice( 'warning', esc_html( $message ) );
        }

        if ( '' !== $notice && ! $this->is_block_editor() ) {
            $this->render_editor_notice( $notice );
        }

        $this->render_rewrite_copy_notice();
    }

    /**
     * Classic editor notices after a redirect.
     *
     * @param string $notice Notice key.
     * @return void
     */
    private function render_editor_notice( $notice ) {
        $messages = array(
            'new_draft'   => __( 'A new draft has been created. You are now editing the copy.', 'duplicate-pp' ),
            'cloned'      => __( 'The item has been duplicated. You are now editing the copy.', 'duplicate-pp' ),
            'republished' => __( 'The original has been republished with your changes.', 'duplicate-pp' ),
        );
        if ( isset( $messages[ $notice ] ) ) {
            $this->print_notice( 'success', esc_html( $messages[ $notice ] ) );
        }
    }

    /**
     * Classic editor: explain what publishing a Rewrite & Republish copy does.
     *
     * @return void
     */
    private function render_rewrite_copy_notice() {
        $screen = get_current_screen();
        if ( ! $screen || 'post' !== $screen->base || $this->is_block_editor() ) {
            return;
        }
        $post = get_post();
        if ( ! $post ) {
            return;
        }
        $original_id = Rewrite_Republish::instance()->get_original_id( $post->ID );
        if ( ! $original_id || ! get_post( $original_id ) ) {
            return;
        }

        $message = sprintf(
            /* translators: %s: original post title (linked). */
            esc_html__( 'You are editing a Rewrite & Republish copy of %s. When you publish it, your changes replace the original and this copy is removed. Schedule it to republish later.', 'duplicate-pp' ),
            sprintf( '<a href="%1$s"><strong>%2$s</strong></a>', esc_url( get_permalink( $original_id ) ), esc_html( _draft_or_post_title( $original_id ) ) )
        );
        $this->print_notice( 'info', $message, false );
    }

    /**
     * Whether the current screen uses the block editor.
     *
     * @return bool
     */
    private function is_block_editor() {
        $screen = get_current_screen();
        return $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
    }

    /**
     * Print one notice.
     *
     * @param string $type        success, warning, error or info.
     * @param string $html        Already-escaped message HTML.
     * @param bool   $dismissible Whether it can be dismissed.
     * @return void
     */
    private function print_notice( $type, $html, $dismissible = true ) {
        printf(
            '<div class="notice notice-%1$s%2$s"><p>%3$s</p></div>',
            esc_attr( $type ),
            $dismissible ? ' is-dismissible' : '',
            wp_kses_post( $html )
        );
    }
}
