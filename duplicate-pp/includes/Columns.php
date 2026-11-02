<?php
/**
 * List table display of where a copy came from.
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
 * Final class Columns
 *
 * Optional "Original" column and "Copy" post state in the list
 * tables of enabled post types.
 */
final class Columns {

    /**
     * Column key.
     */
    const COLUMN = 'dpp_original';

    /**
     * Single instance.
     *
     * @var Columns|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Columns
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Columns is not allowed.', 'duplicate-pp' ), '4.0.0' );
    }

    /**
     * Register hooks. Called by Plugin::boot().
     *
     * @return void
     */
    public function register() {
        add_action( 'load-edit.php', array( $this, 'register_column' ) );
        add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
    }

    /**
     * Add the column on the list screen of an enabled post type.
     *
     * @return void
     */
    public function register_column() {
        global $typenow;

        $post_type = $typenow ? $typenow : 'post';
        if ( ! Plugin::instance()->settings()->is_on( 'show_original', 'column' )
            || ! Plugin::instance()->permissions()->is_post_type_enabled( $post_type ) ) {
            return;
        }

        add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'add_column' ) );
        add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
    }

    /**
     * Insert the column after the title.
     *
     * @param array $columns Columns.
     * @return array
     */
    public function add_column( $columns ) {
        $result = array();
        foreach ( $columns as $key => $label ) {
            $result[ $key ] = $label;
            if ( 'title' === $key ) {
                $result[ self::COLUMN ] = __( 'Original', 'duplicate-pp' );
            }
        }
        if ( ! isset( $result[ self::COLUMN ] ) ) {
            $result[ self::COLUMN ] = __( 'Original', 'duplicate-pp' );
        }
        return $result;
    }

    /**
     * Print the column cell.
     *
     * @param string $column  Column key.
     * @param int    $post_id Row post ID.
     * @return void
     */
    public function render_column( $column, $post_id ) {
        if ( self::COLUMN !== $column ) {
            return;
        }

        $original_id = (int) get_post_meta( $post_id, Duplicator::ORIGINAL_META, true );
        $original    = $original_id > 0 ? get_post( $original_id ) : null;
        if ( ! $original instanceof WP_Post ) {
            echo '<span aria-hidden="true">&#8212;</span>';
            return;
        }

        $url = current_user_can( 'edit_post', $original->ID ) ? get_edit_post_link( $original->ID ) : get_permalink( $original );
        printf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( Plugin::post_title( $original ) ) );
    }

    /**
     * Mark copies with a "Copy" post state.
     *
     * @param string[] $states Post states.
     * @param WP_Post  $post   Post.
     * @return string[]
     */
    public function post_states( $states, $post ) {
        if ( ! $post instanceof WP_Post
            || isset( $states['dpp_rewrite'] )
            || ! Plugin::instance()->settings()->is_on( 'show_original', 'post_state' )
            || ! get_post_meta( $post->ID, Duplicator::ORIGINAL_META, true ) ) {
            return $states;
        }
        $states['dpp_copy'] = __( 'Copy', 'duplicate-pp' );
        return $states;
    }
}
