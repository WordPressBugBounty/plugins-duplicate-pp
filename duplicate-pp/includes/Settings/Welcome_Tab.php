<?php
/**
 * Dashboard tab content for the settings page.
 *
 * @package DuplicatePP
 */

namespace DuplicatePP\Settings;

use DuplicatePP\Duplicator;
use DuplicatePP\Plugin;
use DuplicatePP\Rewrite_Republish;
use WP_Post;
use WP_Query;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Final class Welcome_Tab
 *
 * Renders the Dashboard tab: a quick-duplicate search, usage numbers,
 * a 30-day activity chart, recent copies, rewrites in progress, the
 * state of each feature, and support links. Also answers the quick
 * search AJAX request (hooked by Settings_Page).
 */
final class Welcome_Tab {

    /**
     * AJAX action of the quick-duplicate search.
     */
    const SEARCH_ACTION = 'dpp_quick_search';

    /**
     * Days shown in the activity chart.
     */
    const ACTIVITY_DAYS = 30;

    /**
     * Option holding running counters (currently: republished).
     */
    const STATS_OPTION = 'dpp_stats';

    /**
     * Single instance.
     *
     * @var Welcome_Tab|null
     */
    private static $instance = null;

    /**
     * Private constructor.
     */
    private function __construct() {}

    /**
     * Get the singleton.
     *
     * @return Welcome_Tab
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
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning Welcome_Tab is not allowed.', 'duplicate-pp' ), '3.7.0' );
    }

    /**
     * Render the tab content.
     *
     * @return void
     */
    public function render() {
        $settings  = Plugin::instance()->settings();
        $types     = array_values( array_intersect( (array) $settings->get( 'post_types', array() ), array_keys( $settings->available_post_types() ) ) );
        $activity  = $this->activity( $types );
        $rewrites  = $this->rewrites_in_progress( $types );
        $recent    = $this->recent_copies( $types );
        $stats     = get_option( self::STATS_OPTION, array() );
        $last_week = array_sum( array_slice( $activity, -7 ) );
        $user      = wp_get_current_user();
        $hour      = (int) current_time( 'G' );

        if ( $hour < 12 ) {
            /* translators: %s: user display name. */
            $greeting = __( 'Good morning, %s', 'duplicate-pp' );
        } elseif ( $hour < 18 ) {
            /* translators: %s: user display name. */
            $greeting = __( 'Good afternoon, %s', 'duplicate-pp' );
        } else {
            /* translators: %s: user display name. */
            $greeting = __( 'Good evening, %s', 'duplicate-pp' );
        }

        $kpis = array(
            array(
                'icon'  => 'dashicons-admin-page',
                'value' => number_format_i18n( $this->count_copies() ),
                'label' => __( 'Copies made', 'duplicate-pp' ),
                /* translators: %s: number of copies. */
                'delta' => $last_week ? sprintf( __( '+%s this week', 'duplicate-pp' ), number_format_i18n( $last_week ) ) : '',
            ),
            array(
                'icon'  => 'dashicons-edit-page',
                'value' => number_format_i18n( count( $rewrites ) ),
                'label' => __( 'Rewrites in progress', 'duplicate-pp' ),
                'delta' => '',
            ),
            array(
                'icon'  => 'dashicons-update',
                'value' => number_format_i18n( isset( $stats['republished'] ) ? (int) $stats['republished'] : 0 ),
                'label' => __( 'Posts republished', 'duplicate-pp' ),
                'delta' => '',
            ),
            array(
                'icon'  => 'dashicons-category',
                'value' => number_format_i18n( count( $types ) ),
                /* translators: %s: number of available content types. */
                'label' => sprintf( __( 'of %s content types enabled', 'duplicate-pp' ), number_format_i18n( count( $settings->available_post_types() ) ) ),
                'delta' => '',
            ),
        );
        ?>
        <div class="dpp-welcome">
            <div class="dpp-welcome__intro">
                <p class="dpp-eyebrow"><?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></p>
                <h2 class="dpp-welcome__title"><?php echo esc_html( sprintf( $greeting, $user->display_name ) ); ?></h2>
                <p class="dpp-welcome__lead"><?php esc_html_e( 'Find any post, page or product and copy it in one click.', 'duplicate-pp' ); ?></p>
            </div>

            <div class="dpp-search" data-nonce="<?php echo esc_attr( wp_create_nonce( self::SEARCH_ACTION ) ); ?>" data-action="<?php echo esc_attr( self::SEARCH_ACTION ); ?>"
                data-i18n="<?php echo esc_attr( wp_json_encode( $this->search_strings() ) ); ?>">
                <label class="screen-reader-text" for="dpp-search-input"><?php esc_html_e( 'Search content to duplicate', 'duplicate-pp' ); ?></label>
                <div class="dpp-search__field">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input type="search" id="dpp-search-input" class="dpp-search__input" autocomplete="off"
                        placeholder="<?php esc_attr_e( 'Search posts, pages, products…', 'duplicate-pp' ); ?>"
                        aria-controls="dpp-search-results" aria-describedby="dpp-search-help">
                    <kbd class="dpp-search__kbd" aria-hidden="true">/</kbd>
                </div>
                <p id="dpp-search-help" class="screen-reader-text"><?php esc_html_e( 'Results appear below as you type. Use the arrow keys to pick a result and Enter to copy it to a new draft, or Tab to its buttons.', 'duplicate-pp' ); ?></p>
                <div id="dpp-search-status" class="screen-reader-text" role="status" aria-live="polite"></div>
                <div class="dpp-search__results" id="dpp-search-results" aria-label="<?php esc_attr_e( 'Search results', 'duplicate-pp' ); ?>" hidden></div>
            </div>
        </div>

        <div class="dpp-kpis">
            <?php foreach ( $kpis as $kpi ) : ?>
                <div class="dpp-kpi">
                    <span class="dpp-kpi__icon dashicons <?php echo esc_attr( $kpi['icon'] ); ?>" aria-hidden="true"></span>
                    <div class="dpp-kpi__body">
                        <span class="dpp-kpi__value"><?php echo esc_html( $kpi['value'] ); ?></span>
                        <span class="dpp-kpi__label"><?php echo esc_html( $kpi['label'] ); ?></span>
                    </div>
                    <?php if ( $kpi['delta'] ) : ?>
                        <span class="dpp-kpi__delta"><?php echo esc_html( $kpi['delta'] ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="dpp-grid dpp-grid--sidebar">
            <div class="dpp-stack">
                <?php $this->render_activity( $activity ); ?>

                <?php if ( $rewrites ) : ?>
                    <section class="dpp-card dpp-card--flush">
                        <header class="dpp-card__bar">
                            <div>
                                <h2><?php esc_html_e( 'Rewrites in progress', 'duplicate-pp' ); ?></h2>
                                <p><?php esc_html_e( 'Copies waiting to be republished over their original.', 'duplicate-pp' ); ?></p>
                            </div>
                        </header>
                        <?php $this->render_list( $rewrites, true ); ?>
                    </section>
                <?php endif; ?>

                <section class="dpp-card dpp-card--flush">
                    <header class="dpp-card__bar">
                        <div>
                            <h2><?php esc_html_e( 'Recent copies', 'duplicate-pp' ); ?></h2>
                            <p><?php esc_html_e( 'The latest items created by Duplicate PP.', 'duplicate-pp' ); ?></p>
                        </div>
                    </header>
                    <?php if ( $recent ) : ?>
                        <?php $this->render_list( $recent, false ); ?>
                    <?php else : ?>
                        <div class="dpp-empty">
                            <span class="dpp-empty__icon dashicons dashicons-admin-page" aria-hidden="true"></span>
                            <p class="dpp-empty__title"><?php esc_html_e( 'No copies yet', 'duplicate-pp' ); ?></p>
                            <p><?php esc_html_e( 'Search above, or hover over any title in a post list and click "Duplicate".', 'duplicate-pp' ); ?></p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="dpp-stack">
                <?php $this->render_features(); ?>

                <?php $this->render_hire(); ?>

                <section class="dpp-card">
                    <h2 class="dpp-card__title"><?php esc_html_e( 'Help & support', 'duplicate-pp' ); ?></h2>
                    <nav class="dpp-linklist">
                        <a href="<?php echo esc_url( Settings_Page::url( 'help' ) ); ?>" data-tab-link="help">
                            <span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
                            <span><?php esc_html_e( 'Read the FAQ', 'duplicate-pp' ); ?></span>
                            <span class="dashicons dashicons-arrow-right-alt2 dpp-linklist__chev" aria-hidden="true"></span>
                        </a>
                        <a href="<?php echo esc_url( 'https://gutenbergkits.com/contact' ); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="dashicons dashicons-sos" aria-hidden="true"></span>
                            <span><?php esc_html_e( 'Get support', 'duplicate-pp' ); ?></span>
                            <span class="dashicons dashicons-external dpp-linklist__chev" aria-hidden="true"></span>
                        </a>
                        <a href="<?php echo esc_url( 'mailto:info@gutenbergkits.com' ); ?>">
                            <span class="dashicons dashicons-email" aria-hidden="true"></span>
                            <span><?php esc_html_e( 'Email us', 'duplicate-pp' ); ?></span>
                            <span class="dashicons dashicons-arrow-right-alt2 dpp-linklist__chev" aria-hidden="true"></span>
                        </a>
                    </nav>
                </section>

                <section class="dpp-card dpp-rate">
                    <div class="dpp-rate__stars" aria-hidden="true">
                        <?php echo str_repeat( '<span class="dashicons dashicons-star-filled"></span>', 5 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
                    </div>
                    <h2 class="dpp-card__title"><?php esc_html_e( 'Enjoying Duplicate PP?', 'duplicate-pp' ); ?></h2>
                    <p><?php esc_html_e( 'A short review helps others find the plugin and keeps it free.', 'duplicate-pp' ); ?></p>
                    <a class="dpp-btn dpp-btn--ghost" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/duplicate-pp/reviews/#new-post' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Leave a review', 'duplicate-pp' ); ?></a>
                </section>

                <?php
                /**
                 * Fires at the end of the Welcome tab.
                 *
                 * @since 3.7.1
                 */
                do_action( 'dpp_welcome_tab_after' );
                ?>
            </aside>
        </div>
        <?php
    }

    /**
     * Copies per day for the activity chart, oldest first.
     *
     * @param string[] $types Enabled post types.
     * @return int[] Day (Y-m-d, site time) => count.
     */
    private function activity( $types ) {
        $days  = array();
        $today = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local day buckets.
        for ( $i = self::ACTIVITY_DAYS - 1; $i >= 0; $i-- ) {
            $days[ gmdate( 'Y-m-d', $today - $i * DAY_IN_SECONDS ) ] = 0;
        }
        if ( ! $types ) {
            return $days;
        }

        $query = new WP_Query(
            array(
                'post_type'      => $types,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => 1000,
                'no_found_rows'  => true,
                'fields'         => 'ids',
                'date_query'     => array( array( 'after' => array_key_first( $days ), 'inclusive' => true, 'column' => 'post_date' ) ),
                'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
                    array(
                        'key'     => Duplicator::ORIGINAL_META,
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );

        foreach ( $query->posts as $post_id ) {
            $day = substr( get_post_field( 'post_date', $post_id ), 0, 10 );
            if ( isset( $days[ $day ] ) ) {
                ++$days[ $day ];
            }
        }
        return $days;
    }

    /**
     * Print the 30-day activity column chart (single series).
     *
     * @param int[] $activity Day => count.
     * @return void
     */
    private function render_activity( $activity ) {
        $total = array_sum( $activity );
        $max   = max( 1, max( $activity ) );
        // Clean top tick: the first of 1, 2, 5 × 10^n that fits the busiest day.
        $magnitude = 10 ** (int) floor( log10( $max ) );
        $tick      = 10 * $magnitude;
        foreach ( array( 1, 2, 5 ) as $multiple ) {
            if ( $multiple * $magnitude >= $max ) {
                $tick = $multiple * $magnitude;
                break;
            }
        }
        $keys = array_keys( $activity );
        ?>
        <section class="dpp-card dpp-activity">
            <header class="dpp-card__bar dpp-card__bar--plain">
                <div>
                    <h2><?php esc_html_e( 'Copies, last 30 days', 'duplicate-pp' ); ?></h2>
                    <p>
                        <?php
                        /* translators: %s: number of copies. */
                        echo esc_html( sprintf( _n( '%s copy created', '%s copies created', $total, 'duplicate-pp' ), number_format_i18n( $total ) ) );
                        ?>
                    </p>
                </div>
            </header>

            <div class="dpp-chart" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: number of copies. */ __( 'Column chart: %s copies created in the last 30 days.', 'duplicate-pp' ), number_format_i18n( $total ) ) ); ?>">
                <div class="dpp-chart__ticks" aria-hidden="true">
                    <span><?php echo esc_html( number_format_i18n( $tick ) ); ?></span>
                    <span>0</span>
                </div>
                <div class="dpp-chart__plot">
                    <div class="dpp-chart__grid" aria-hidden="true"></div>
                    <div class="dpp-chart__cols">
                        <?php foreach ( $activity as $day => $count ) : ?>
                            <?php
                            $label = date_i18n( get_option( 'date_format' ), strtotime( $day ) );
                            /* translators: 1: date, 2: number of copies. */
                            $tip = sprintf( _n( '%1$s · %2$s copy', '%1$s · %2$s copies', $count, 'duplicate-pp' ), $label, number_format_i18n( $count ) );
                            ?>
                            <div class="dpp-chart__col" data-tip="<?php echo esc_attr( $tip ); ?>">
                                <?php if ( $count ) : ?>
                                    <span class="dpp-chart__bar" style="height: <?php echo esc_attr( round( $count / $tick * 100, 2 ) ); ?>%"></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( ! $total ) : ?>
                        <p class="dpp-chart__empty"><?php esc_html_e( 'No copies in the last 30 days.', 'duplicate-pp' ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="dpp-chart__axis" aria-hidden="true">
                    <span><?php echo esc_html( date_i18n( 'M j', strtotime( $keys[0] ) ) ); ?></span>
                    <span><?php echo esc_html( date_i18n( 'M j', strtotime( $keys[ (int) floor( count( $keys ) / 2 ) ] ) ) ); ?></span>
                    <span><?php esc_html_e( 'Today', 'duplicate-pp' ); ?></span>
                </div>
            </div>

            <details class="dpp-chart__table">
                <summary><?php esc_html_e( 'View as table', 'duplicate-pp' ); ?></summary>
                <table>
                    <thead><tr><th scope="col"><?php esc_html_e( 'Date', 'duplicate-pp' ); ?></th><th scope="col"><?php esc_html_e( 'Copies', 'duplicate-pp' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( array_reverse( $activity, true ) as $day => $count ) : ?>
                            <tr><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $day ) ) ); ?></td><td><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        </section>
        <?php
    }

    /**
     * Print the on/off state of each feature.
     *
     * @return void
     */
    private function render_features() {
        $settings = Plugin::instance()->settings();
        $features = array(
            array( 'dashicons-list-view', __( 'Post list links', 'duplicate-pp' ), $settings->is_on( 'show_in', 'row' ), 'display' ),
            array( 'dashicons-images-alt2', __( 'Bulk actions', 'duplicate-pp' ), $settings->is_on( 'show_in', 'bulk' ), 'display' ),
            array( 'dashicons-edit', __( 'Editor buttons', 'duplicate-pp' ), $settings->is_on( 'show_in', 'editor' ), 'display' ),
            array( 'dashicons-admin-generic', __( 'Admin bar menu', 'duplicate-pp' ), $settings->is_on( 'show_in', 'admin_bar' ), 'display' ),
            array( 'dashicons-update', __( 'Rewrite & Republish', 'duplicate-pp' ), (bool) $settings->get( 'rewrite_republish' ), 'permissions' ),
            array( 'dashicons-networking', __( 'Copy child pages', 'duplicate-pp' ), $settings->is_on( 'copy', 'children' ), 'copy' ),
        );
        ?>
        <section class="dpp-card">
            <h2 class="dpp-card__title"><?php esc_html_e( 'Features', 'duplicate-pp' ); ?></h2>
            <ul class="dpp-features">
                <?php foreach ( $features as $feature ) : ?>
                    <li>
                        <span class="dashicons <?php echo esc_attr( $feature[0] ); ?>" aria-hidden="true"></span>
                        <span class="dpp-features__name"><?php echo esc_html( $feature[1] ); ?></span>
                        <a class="dpp-state <?php echo $feature[2] ? 'is-on' : 'is-off'; ?>" href="<?php echo esc_url( Settings_Page::url( $feature[3] ) ); ?>" data-tab-link="<?php echo esc_attr( $feature[3] ); ?>">
                            <?php echo $feature[2] ? esc_html__( 'On', 'duplicate-pp' ) : esc_html__( 'Off', 'duplicate-pp' ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
    }

    /**
     * Print the "hire me" card for freelance projects.
     *
     * @return void
     */
    private function render_hire() {
        $services = array(
            __( 'Custom plugins & integrations', 'duplicate-pp' ),
            __( 'Block themes & Gutenberg blocks', 'duplicate-pp' ),
            __( 'WooCommerce stores & extensions', 'duplicate-pp' ),
            __( 'Speed, fixes & maintenance', 'duplicate-pp' ),
        );
        ?>
        <section class="dpp-card dpp-hire">
            <p class="dpp-hire__status"><span class="dpp-hire__dot" aria-hidden="true"></span><?php esc_html_e( 'Available for freelance projects', 'duplicate-pp' ); ?></p>
            <h2 class="dpp-card__title"><?php esc_html_e( 'Need a WordPress developer?', 'duplicate-pp' ); ?></h2>
            <p class="dpp-hire__lead"><?php esc_html_e( 'Hire the developer behind Duplicate PP for your next project.', 'duplicate-pp' ); ?></p>
            <ul class="dpp-hire__services">
                <?php foreach ( $services as $service ) : ?>
                    <li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo esc_html( $service ); ?></li>
                <?php endforeach; ?>
            </ul>
            <a class="dpp-btn dpp-btn--primary dpp-hire__cta" href="<?php echo esc_url( 'https://devzakaria.com/contact' ); ?>" target="_blank" rel="noopener">
                <?php esc_html_e( 'Start a project', 'duplicate-pp' ); ?>
                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
            </a>
            <div class="dpp-hire__alt">
                <a href="<?php echo esc_url( 'https://devzakaria.com' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'devzakaria.com', 'duplicate-pp' ); ?></a>
                <span aria-hidden="true">·</span>
                <a href="<?php echo esc_url( 'https://www.fiverr.com/users/devs_zak/' ); ?>" target="_blank" rel="nofollow noopener noreferrer"><?php esc_html_e( 'Fiverr', 'duplicate-pp' ); ?></a>
                <span aria-hidden="true">·</span>
                <a href="<?php echo esc_url( 'https://www.upwork.com/freelancers/~010af183b3205dc627' ); ?>" target="_blank" rel="nofollow noopener noreferrer"><?php esc_html_e( 'Upwork', 'duplicate-pp' ); ?></a>
            </div>
        </section>
        <?php
    }

    /**
     * Print a list of copies.
     *
     * @param WP_Post[] $posts   Copies.
     * @param bool      $rewrite Whether these are Rewrite & Republish copies.
     * @return void
     */
    private function render_list( $posts, $rewrite ) {
        $meta_key = $rewrite ? Rewrite_Republish::COPY_META : Duplicator::ORIGINAL_META;
        ?>
        <ul class="dpp-list">
            <?php foreach ( $posts as $post ) : ?>
                <?php
                $original_id = (int) get_post_meta( $post->ID, $meta_key, true );
                $original    = $original_id > 0 ? get_post( $original_id ) : null;
                $type        = get_post_type_object( $post->post_type );
                $status      = get_post_status_object( $post->post_status );
                $edit_url    = get_edit_post_link( $post->ID );
                // Drafts have no GMT date; get_post_timestamp() falls back to the local one.
                $when        = (int) get_post_timestamp( $post, $rewrite ? 'modified' : 'date' );
                ?>
                <li class="dpp-list__item">
                    <span class="dpp-list__icon dashicons <?php echo esc_attr( $this->type_icon( $type ) ); ?>" aria-hidden="true"></span>
                    <div class="dpp-list__main">
                        <?php if ( $edit_url ) : ?>
                            <a class="dpp-list__title" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( Plugin::post_title( $post ) ); ?></a>
                        <?php else : ?>
                            <span class="dpp-list__title"><?php echo esc_html( Plugin::post_title( $post ) ); ?></span>
                        <?php endif; ?>
                        <span class="dpp-list__meta">
                            <?php echo esc_html( $type ? $type->labels->singular_name : $post->post_type ); ?>
                            <span aria-hidden="true">·</span>
                            <?php
                            /* translators: %s: human-readable time difference. */
                            echo esc_html( sprintf( __( '%s ago', 'duplicate-pp' ), human_time_diff( $when ) ) );
                            ?>
                            <?php if ( $original instanceof WP_Post ) : ?>
                                <span aria-hidden="true">·</span>
                                <?php echo $rewrite ? esc_html__( 'rewrite of', 'duplicate-pp' ) : esc_html__( 'from', 'duplicate-pp' ); ?>
                                <a href="<?php echo esc_url( get_permalink( $original ) ); ?>"><?php echo esc_html( Plugin::post_title( $original ) ); ?></a>
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="dpp-badge dpp-badge--<?php echo esc_attr( $post->post_status ); ?>"><?php echo esc_html( $status ? $status->label : $post->post_status ); ?></span>
                    <?php if ( $edit_url ) : ?>
                        <a class="dpp-icon-btn" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: post title. */ __( 'Edit “%s”', 'duplicate-pp' ), Plugin::post_title( $post ) ) ); ?>">
                            <span class="dashicons <?php echo $rewrite ? 'dashicons-arrow-right-alt' : 'dashicons-edit'; ?>" aria-hidden="true"></span>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Quick search AJAX: matching items with their copy links.
     *
     * @return void
     */
    public function ajax_search() {
        check_ajax_referer( self::SEARCH_ACTION, 'nonce' );

        $plugin = Plugin::instance();
        if ( ! $plugin->permissions()->user_is_allowed() ) {
            wp_send_json_error( null, 403 );
        }

        $term  = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
        $types = array_values( array_intersect( (array) $plugin->settings()->get( 'post_types', array() ), array_keys( $plugin->settings()->available_post_types() ) ) );
        if ( ! $types ) {
            wp_send_json_success( array() );
        }

        $args = array(
            'post_type'      => $types,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
            'perm'           => 'editable',
            'posts_per_page' => 8,
            'no_found_rows'  => true,
        );
        if ( '' !== $term ) {
            $args['s'] = $term;
        } else {
            $args['orderby'] = 'modified';
            $args['order']   = 'DESC';
        }

        $links = $plugin->links();
        $items = array();
        foreach ( ( new WP_Query( $args ) )->posts as $post ) {
            if ( ! $plugin->permissions()->can_copy( $post ) ) {
                continue;
            }
            $type    = get_post_type_object( $post->post_type );
            $status  = get_post_status_object( $post->post_status );
            $items[] = array(
                'title'    => html_entity_decode( Plugin::post_title( $post ), ENT_QUOTES, 'UTF-8' ),
                'type'     => $type ? $type->labels->singular_name : $post->post_type,
                'icon'     => $this->type_icon( $type ),
                'status'   => $post->post_status,
                'statusLb' => $status ? $status->label : $post->post_status,
                'editUrl'  => (string) get_edit_post_link( $post->ID, 'raw' ),
                'clone'    => $links->clone_url( $post->ID ),
                'draft'    => $links->new_draft_url( $post->ID ),
                'rewrite'  => $plugin->permissions()->can_rewrite( $post ) ? $links->rewrite_url( $post->ID ) : '',
            );
        }

        wp_send_json_success( $items );
    }

    /**
     * Strings used by the quick search script.
     *
     * @return array
     */
    private function search_strings() {
        return array(
            'recent'    => __( 'Recently edited', 'duplicate-pp' ),
            'results'   => __( 'Results', 'duplicate-pp' ),
            'none'      => __( 'Nothing found. Try another word.', 'duplicate-pp' ),
            /* translators: %s: number of results. */
            'count'     => __( '%s results', 'duplicate-pp' ),
            'countOne'  => __( '1 result', 'duplicate-pp' ),
            'error'     => __( 'Search failed. Please try again.', 'duplicate-pp' ),
            'loading'   => __( 'Searching…', 'duplicate-pp' ),
            'duplicate' => __( 'Duplicate', 'duplicate-pp' ),
            'draft'     => __( 'New Draft', 'duplicate-pp' ),
            'rewrite'   => __( 'Rewrite', 'duplicate-pp' ),
        );
    }

    /**
     * Dashicon of a post type.
     *
     * @param \WP_Post_Type|null $type Post type object.
     * @return string
     */
    private function type_icon( $type ) {
        if ( $type && is_string( $type->menu_icon ) && 0 === strpos( $type->menu_icon, 'dashicons-' ) ) {
            return $type->menu_icon;
        }
        if ( $type && 'page' === $type->name ) {
            return 'dashicons-admin-page';
        }
        if ( $type && 'product' === $type->name ) {
            return 'dashicons-products';
        }
        return 'dashicons-admin-post';
    }

    /**
     * Count one more republished post. Hooked to dpp_after_republish.
     *
     * @return void
     */
    public static function count_republish() {
        $stats                = get_option( self::STATS_OPTION, array() );
        $stats                = is_array( $stats ) ? $stats : array();
        $stats['republished'] = ( isset( $stats['republished'] ) ? (int) $stats['republished'] : 0 ) + 1;
        update_option( self::STATS_OPTION, $stats, false );
    }

    /**
     * Number of existing copies (not trashed).
     *
     * @return int
     */
    private function count_copies() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s AND p.post_status NOT IN ( 'trash', 'auto-draft' )",
                Duplicator::ORIGINAL_META
            )
        );
    }

    /**
     * Latest copies of enabled post types.
     *
     * @param string[] $types Enabled post types.
     * @return WP_Post[]
     */
    private function recent_copies( $types ) {
        if ( ! $types ) {
            return array();
        }
        $query = new WP_Query(
            array(
                'post_type'      => $types,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                'posts_per_page' => 6,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'no_found_rows'  => true,
                'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
                    array(
                        'key'     => Duplicator::ORIGINAL_META,
                        'compare' => 'EXISTS',
                    ),
                    // Rewrites in progress have their own list.
                    array(
                        'key'     => Rewrite_Republish::COPY_META,
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            )
        );
        return $query->posts;
    }

    /**
     * Rewrite & Republish copies not yet republished.
     *
     * @param string[] $types Enabled post types.
     * @return WP_Post[]
     */
    private function rewrites_in_progress( $types ) {
        if ( ! $types ) {
            return array();
        }
        $query = new WP_Query(
            array(
                'post_type'      => $types,
                'post_status'    => array( 'draft', 'pending', 'future', 'private' ),
                'posts_per_page' => 20,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'no_found_rows'  => true,
                'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
                    array(
                        'key'     => Rewrite_Republish::COPY_META,
                        'compare' => 'EXISTS',
                    ),
                    array(
                        'key'     => Rewrite_Republish::REPUBLISHED_META,
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            )
        );
        return $query->posts;
    }
}
