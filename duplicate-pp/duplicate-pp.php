<?php
/*
Plugin Name: Duplicate PP - Duplicate Posts, Pages and Custom Post Types
Description: <strong>Duplicate PP</strong> is a simple plugin which allows you to duplicate any POST,PAGE and CPT Easily with full meta data support.
Author: Zakaria Binsaifullah
Author URI: https://gutenbergkits.com
Version: 3.6.0
Text Domain: duplicate-pp
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
*/

if ( !defined( "ABSPATH" ) ) {
    exit();
}

// Define plugin constants
define('DPP_VERSION', '3.6.0');
define('DPP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DPP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once DPP_PLUGIN_DIR . 'includes/admin-settings.php';
require_once DPP_PLUGIN_DIR . 'includes/meta-handler.php';

/**
 * Main duplication function
 */
function dpp_duplicate_as_draft() {
    global $wpdb;

    // Sanitize and validate post ID
    $dpp_post_id = isset($_GET["post"]) 
        ? absint($_GET["post"]) 
        : (isset($_POST["post"]) ? absint($_POST["post"]) : 0);

    // Check if post ID is valid
    if ($dpp_post_id === 0) {
        wp_die(esc_html__("Invalid post ID.", "duplicate-pp"), 400);
    }

    // Security checks
    if (!current_user_can("edit_post", $dpp_post_id)) {
        wp_die(esc_html__("You do not have permission to duplicate posts.", "duplicate-pp"), 403);
    }

    $nonce = isset($_GET["duplicate_nonce"]) ? wp_unslash(sanitize_key($_GET["duplicate_nonce"])) : '';
    if (!wp_verify_nonce($nonce, "duplicate_post_" . get_current_blog_id())) {
        wp_die(esc_html__("Security check failed.", "duplicate-pp"), 403);
    }

    // Get original post
    $dpp_post = get_post($dpp_post_id);
    if (!$dpp_post instanceof WP_Post) {
        wp_die(esc_html__("Post not found.", "duplicate-pp"), 404);
    }

    // Get settings
    $settings = get_option('dpp_settings', array(
        'post_status' => 'draft',
        'title_prefix' => '',
        'title_suffix' => ' (Copy)',
        'slug_prefix' => '',
        'slug_suffix' => '-copy'
    ));

    // Prepare new title and slug
    $new_title = $settings['title_prefix'] . $dpp_post->post_title . $settings['title_suffix'];
    $new_slug = $settings['slug_prefix'] . $dpp_post->post_name . $settings['slug_suffix'];

    // Prepare post data
    $dpp_args = array(
        'comment_status' => $dpp_post->comment_status,
        'ping_status'    => $dpp_post->ping_status,
        'post_author'    => get_current_user_id(),
        'post_content'   => $dpp_post->post_content,
        'post_excerpt'   => $dpp_post->post_excerpt,
        'post_name'      => wp_unique_post_slug($new_slug, 0, 'publish', $dpp_post->post_type, $dpp_post->post_parent),
        'post_parent'    => $dpp_post->post_parent,
        'post_password'  => $dpp_post->post_password,
        'post_status'    => $settings['post_status'],
        'post_title'     => $new_title,
        'post_type'      => $dpp_post->post_type,
        'to_ping'        => $dpp_post->to_ping,
        'menu_order'     => $dpp_post->menu_order
    );

    // Insert new post
    $dpp_new_post_id = wp_insert_post($dpp_args);
    if (is_wp_error($dpp_new_post_id)) {
        wp_die($dpp_new_post_id->get_error_message(), 500);
    }

    // Handle taxonomies
    dpp_duplicate_taxonomies($dpp_post_id, $dpp_new_post_id);

    // Handle meta data using enhanced meta handler
    DPP_Meta_Handler::duplicate_post_meta($dpp_post_id, $dpp_new_post_id);

    // Redirect with success message
    $redirect_url = add_query_arg(
        array(
            'post_type' => $dpp_post->post_type,
            'duplicated' => 1
        ),
        admin_url('edit.php')
    );

    wp_safe_redirect($redirect_url);
    exit();
}
add_action('admin_action_dpp_duplicate_as_draft', 'dpp_duplicate_as_draft');

/**
 * Duplicate taxonomies
 */
function dpp_duplicate_taxonomies($original_id, $new_id) {
    $taxonomies = get_object_taxonomies(get_post_type($original_id));
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($original_id, $taxonomy, array('fields' => 'slugs'));
        if (!is_wp_error($terms)) {
            wp_set_object_terms($new_id, $terms, $taxonomy);
        }
    }
}

/**
 * Add duplicate link to post row actions
 */
function dpp_duplicate_link($actions, $post) {
    if (current_user_can('edit_posts') && current_user_can('edit_post', $post->ID)) {
        $nonce = wp_create_nonce("duplicate_post_" . get_current_blog_id());
        $url = add_query_arg(
            array(
                'action' => 'dpp_duplicate_as_draft',
                'post' => $post->ID,
                'duplicate_nonce' => $nonce
            ),
            admin_url('admin.php')
        );

        $actions['duplicate'] = sprintf(
            '<a href="%s" aria-label="%s">%s</a>',
            esc_url($url),
            esc_attr(sprintf(__('Duplicate "%s"', 'duplicate-pp'), get_the_title($post->ID))),
            esc_html__('Duplicate', 'duplicate-pp')
        );
    }
    return $actions;
}
add_filter('post_row_actions', 'dpp_duplicate_link', 10, 2);
add_filter('page_row_actions', 'dpp_duplicate_link', 10, 2);

/**
 * Add admin notices for successful duplication
 */
function dpp_admin_notices() {
    if (isset($_GET['duplicated']) && $_GET['duplicated'] == 1) {
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             esc_html__('Post duplicated successfully.', 'duplicate-pp') . 
             '</p></div>';
    }
}
add_action('admin_notices', 'dpp_admin_notices');

/**
 * Add duplicate button to admin bar
 */
function dpp_admin_bar_duplicate_link($wp_admin_bar) {
    if (!is_singular() || !current_user_can('edit_posts')) {
        return;
    }

    $post_id = get_the_ID();
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $nonce = wp_create_nonce("duplicate_post_" . get_current_blog_id());
    $url = add_query_arg(
        array(
            'action' => 'dpp_duplicate_as_draft',
            'post' => $post_id,
            'duplicate_nonce' => $nonce
        ),
        admin_url('admin.php')
    );

    $wp_admin_bar->add_node(array(
        'id' => 'dpp_duplicate_link',
        'title' => __('Duplicate This', 'duplicate-pp'),
        'href' => esc_url($url),
        'meta' => array(
            'class' => 'dpp-duplicate-link',
            'title' => sprintf(__('Duplicate "%s"', 'duplicate-pp'), get_the_title($post_id))
        )
    ));
}
add_action('admin_bar_menu', 'dpp_admin_bar_duplicate_link', 999);

/**
 * Add styles for duplicate link
 */
function dpp_add_duplicate_link_styles() {
    ?>
    <style type="text/css">
        .dpp-duplicate-link { display: inline-block; }
        #wp-admin-bar-dpp_duplicate_link .ab-item:hover { color: #00a0d2; }
    </style>
    <?php
}
add_action('admin_head', 'dpp_add_duplicate_link_styles');

// Function to set redirect option on plugin activation
function dpp_activation_redirect() {
    add_option('dpp_activation_redirect', true);
}
register_activation_hook(__FILE__, 'dpp_activation_redirect');

// Function to handle the redirect
function dpp_redirect_to_admin_page() {
    if (get_option('dpp_activation_redirect', false)) {
        delete_option('dpp_activation_redirect');
        if (is_admin() && current_user_can('manage_options')) {
            wp_safe_redirect(admin_url('admin.php?page=duplicate-pp-settings'));
            exit;
        }
    }
}
add_action('admin_init', 'dpp_redirect_to_admin_page');