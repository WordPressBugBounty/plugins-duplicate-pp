<?php
/**
 * Editor integration: copy buttons and copy information in the
 * classic editor's publish box and the block editor's summary panel.
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
 * Final class Editor
 */
final class Editor {

    /**
     * Script/style handle.
     */
    const HANDLE = 'duplicate-pp-editor';

    /**
     * Single instance.
     *
     * @var Editor|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Editor
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Editor is not allowed.', 'duplicate-pp' ), '4.0.0' );
    }

    /**
     * Register hooks. Called by Plugin::boot().
     *
     * @return void
     */
    public function register() {
        add_action( 'post_submitbox_misc_actions', array( $this, 'classic_info' ) );
        add_action( 'post_submitbox_start', array( $this, 'classic_buttons' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_classic' ) );
    }

    /**
     * Data shared by both editors.
     *
     * @param WP_Post $post Post being edited.
     * @return array
     */
    public function data( $post ) {
        $plugin      = Plugin::instance();
        $settings    = $plugin->settings();
        $permissions = $plugin->permissions();
        $links       = $plugin->links();
        $rewrite     = Rewrite_Republish::instance();
        $saved       = 'auto-draft' !== $post->post_status;

        $data = array(
            'newDraftUrl'     => '',
            'rewriteUrl'      => '',
            'rewriteCopyUrl'  => '',
            'original'        => null,
            'isRewriteCopy'   => false,
            'rewriteOriginal' => null,
            'finishUrl'       => '',
            'notice'          => $this->notice_key(),
        );

        if ( $saved && $settings->is_on( 'show_in', 'editor' ) ) {
            if ( $permissions->can_copy( $post ) ) {
                $data['newDraftUrl'] = $links->new_draft_url( $post->ID );
            }
            if ( $permissions->can_rewrite( $post ) ) {
                $data['rewriteUrl'] = $links->rewrite_url( $post->ID );
            } elseif ( $settings->get( 'rewrite_republish' ) ) {
                $copy_id = $rewrite->get_copy_id( $post->ID );
                if ( $copy_id && current_user_can( 'edit_post', $copy_id ) ) {
                    $data['rewriteCopyUrl'] = get_edit_post_link( $copy_id, 'raw' );
                }
            }
        }

        $original_id = $rewrite->get_original_id( $post->ID );
        if ( $original_id && get_post( $original_id ) ) {
            $data['isRewriteCopy']   = true;
            $data['rewriteOriginal'] = $this->post_ref( $original_id );
            $data['finishUrl']       = $rewrite->finish_url( $post->ID );
        } elseif ( $settings->is_on( 'show_original', 'editor' ) ) {
            $source_id = (int) get_post_meta( $post->ID, Duplicator::ORIGINAL_META, true );
            if ( $source_id && get_post( $source_id ) ) {
                $data['original'] = $this->post_ref( $source_id );
            }
        }

        return $data;
    }

    /**
     * Load the block editor script on the post editor (not the site editor).
     *
     * @return void
     */
    public function enqueue_block_editor() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $post   = get_post();
        if ( ! $screen || 'post' !== $screen->base || ! $post instanceof WP_Post ) {
            return;
        }

        wp_enqueue_script(
            self::HANDLE,
            DPP_PLUGIN_URL . 'assets/js/editor.js',
            array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-editor', 'wp-i18n', 'wp-hooks', 'wp-notices', 'wp-dom-ready' ),
            DPP_VERSION,
            true
        );
        wp_add_inline_script( self::HANDLE, 'window.dppEditor = ' . wp_json_encode( $this->data( $post ) ) . ';', 'before' );
        wp_set_script_translations( self::HANDLE, 'duplicate-pp', DPP_PLUGIN_DIR . 'languages' );

        wp_enqueue_style( self::HANDLE, DPP_PLUGIN_URL . 'assets/css/editor.css', array(), DPP_VERSION );
    }

    /**
     * Load the editor styles on the classic editor screen.
     *
     * @param string $hook_suffix Current admin page.
     * @return void
     */
    public function enqueue_classic( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
            return;
        }
        wp_enqueue_style( self::HANDLE, DPP_PLUGIN_URL . 'assets/css/editor.css', array(), DPP_VERSION );
    }

    /**
     * Classic editor: original / copy information in the publish box.
     *
     * @param WP_Post $post Post being edited.
     * @return void
     */
    public function classic_info( $post ) {
        $data = $this->data( $post );
        $ref  = $data['isRewriteCopy'] ? $data['rewriteOriginal'] : $data['original'];
        if ( ! $ref ) {
            return;
        }
        ?>
        <div class="misc-pub-section dpp-misc-original">
            <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
            <?php echo $data['isRewriteCopy'] ? esc_html__( 'Rewrite of:', 'duplicate-pp' ) : esc_html__( 'Copy of:', 'duplicate-pp' ); ?>
            <?php $this->print_ref_link( $ref ); ?>
        </div>
        <?php
    }

    /**
     * Classic editor: copy buttons above Publish / Update.
     *
     * @param WP_Post $post Post being edited.
     * @return void
     */
    public function classic_buttons( $post ) {
        $data = $this->data( $post );
        if ( ! $data['newDraftUrl'] && ! $data['rewriteUrl'] && ! $data['rewriteCopyUrl'] ) {
            return;
        }
        ?>
        <div class="dpp-classic-actions">
            <?php if ( $data['newDraftUrl'] ) : ?>
                <a class="button" href="<?php echo esc_url( $data['newDraftUrl'] ); ?>"><?php esc_html_e( 'Copy to a new draft', 'duplicate-pp' ); ?></a>
            <?php endif; ?>
            <?php if ( $data['rewriteUrl'] ) : ?>
                <a class="button" href="<?php echo esc_url( $data['rewriteUrl'] ); ?>"><?php esc_html_e( 'Rewrite & Republish', 'duplicate-pp' ); ?></a>
            <?php elseif ( $data['rewriteCopyUrl'] ) : ?>
                <a class="button" href="<?php echo esc_url( $data['rewriteCopyUrl'] ); ?>"><?php esc_html_e( 'Continue rewrite', 'duplicate-pp' ); ?></a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Title and links of a related post, for display.
     *
     * @param int $post_id Post ID.
     * @return array
     */
    private function post_ref( $post_id ) {
        return array(
            'id'      => (int) $post_id,
            'title'   => _draft_or_post_title( $post_id ),
            'editUrl' => current_user_can( 'edit_post', $post_id ) ? (string) get_edit_post_link( $post_id, 'raw' ) : '',
            'viewUrl' => (string) get_permalink( $post_id ),
        );
    }

    /**
     * Print a link to a related post.
     *
     * @param array $ref Result of post_ref().
     * @return void
     */
    private function print_ref_link( $ref ) {
        $url = $ref['editUrl'] ? $ref['editUrl'] : $ref['viewUrl'];
        printf( '<a href="%1$s"><strong>%2$s</strong></a>', esc_url( $url ), esc_html( $ref['title'] ) );
    }

    /**
     * Notice flag passed in the URL after a copy action.
     *
     * @return string
     */
    private function notice_key() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag only.
        $key = isset( $_GET['dpp_notice'] ) ? sanitize_key( wp_unslash( $_GET['dpp_notice'] ) ) : '';
        return in_array( $key, array( 'new_draft', 'cloned', 'rewrite', 'republished' ), true ) ? $key : '';
    }
}
