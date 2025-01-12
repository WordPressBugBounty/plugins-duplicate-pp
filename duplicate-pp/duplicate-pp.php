<?php
/*
Plugin Name: Duplicate PP
Description: <strong>Duplicate PP</strong> is a simple plugin which allows you to duplicate any POST,PAGE and CPT Easily. The duplicated POST or PAGE CPT acts as draft.
Author: Zakaria Binsaifullah
Author URI: https://gutenbergkits.com
Version: 3.5.6
Text Domain: duplicate-pp
License: GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Domain Path:  /languages
*/

if (!defined("ABSPATH")) {
    exit();
} // Exit if accessed directly

// Require Admin Support File
require_once plugin_dir_path(__FILE__) . "admin/admin.php";

/**
 * Duplicate Post
 */
function dpp_duplicate_as_draft()
{
    global $wpdb;

    // Sanitize and validate post ID
    $dpp_post_id = isset($_GET["post"])
        ? absint($_GET["post"])
        : (isset($_POST["post"])
            ? absint($_POST["post"])
            : 0);

    // Check if post ID is valid
    if ($dpp_post_id === 0) {
        wp_die(esc_html__("Invalid post ID.", "duplicate-pp"), 400);
    }

    // Check if user has sufficient permissions
    if (!current_user_can("edit_post", $dpp_post_id)) {
        wp_die(
            esc_html__(
                "You do not have permission to duplicate posts.",
                "duplicate-pp"
            ),
            403
        );
    }

    // Verify request method
    if (
        !isset($_SERVER["REQUEST_METHOD"]) ||
        ($_SERVER["REQUEST_METHOD"] !== "GET" &&
            $_SERVER["REQUEST_METHOD"] !== "POST")
    ) {
        wp_die(esc_html__("Invalid request method.", "duplicate-pp"), 400);
    }

    // Check if post ID exists in request
    if (
        !(
            isset($_GET["post"]) ||
            isset($_POST["post"]) ||
            (isset($_REQUEST["action"]) &&
                "dpp_duplicate_as_draft" === $_REQUEST["action"])
        )
    ) {
        wp_die(
            esc_html__("No post specified for duplication.", "duplicate-pp"),
            400
        );
    }

    // Enhanced nonce verification with specific action name
    $nonce = "";
    if (isset($_GET["duplicate_nonce"])) {
        $nonce = wp_unslash(sanitize_key($_GET["duplicate_nonce"]));
    }

    if (!wp_verify_nonce($nonce, "duplicate_post_" . get_current_blog_id())) {
        wp_die(esc_html__("Security check failed.", "duplicate-pp"), 403);
    }

    // Get original post with error handling
    $dpp_post = get_post($dpp_post_id);
    if (!$dpp_post instanceof WP_Post) {
        wp_die(esc_html__("Post not found.", "duplicate-pp"), 404);
    }

    // Verify user has permission to edit this specific post type
    if (!current_user_can("edit_post", $dpp_post_id)) {
        wp_die(
            esc_html__(
                "You do not have permission to duplicate this post.",
                "duplicate-pp"
            ),
            403
        );
    }

    // Get current user ID securely
    $dpp_new_post_author = get_current_user_id();

    // Prepare post data with sanitization
    $dpp_args = [
        "comment_status" => sanitize_key($dpp_post->comment_status),
        "ping_status" => sanitize_key($dpp_post->ping_status),
        "post_author" => $dpp_new_post_author,
        "post_content" => wp_kses_post($dpp_post->post_content),
        "post_excerpt" => wp_kses_post($dpp_post->post_excerpt),
        "post_name" => sanitize_title($dpp_post->post_name),
        "post_parent" => absint($dpp_post->post_parent),
        "post_password" => $dpp_post->post_password,
        "post_status" => "draft",
        "post_title" => sanitize_text_field($dpp_post->post_title),
        "post_type" => sanitize_key($dpp_post->post_type),
        "to_ping" => sanitize_text_field($dpp_post->to_ping),
        "menu_order" => absint($dpp_post->menu_order),
    ];

    // Insert new post with error handling
    $dpp_new_post_id = wp_insert_post($dpp_args, true);
    if (is_wp_error($dpp_new_post_id)) {
        wp_die(esc_html($dpp_new_post_id->get_error_message()), 500);
    }

    // Copy taxonomies with error handling
    $dpp_taxonomies = get_object_taxonomies($dpp_post->post_type);
    foreach ($dpp_taxonomies as $ddp_taxonomy) {
        $dpp_post_terms = wp_get_object_terms($dpp_post_id, $ddp_taxonomy, [
            "fields" => "slugs",
        ]);
        if (!is_wp_error($dpp_post_terms)) {
            wp_set_object_terms(
                $dpp_new_post_id,
                $dpp_post_terms,
                $ddp_taxonomy,
                false
            );
        }
    }

    // Prepare and execute meta query using prepare statement
    $query = $wpdb->prepare(
        "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d",
        $dpp_post_id
    );

    // Use get_results with ARRAY_A for better memory efficiency
    $dpp_post_meta_infos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT meta_key, meta_value
            FROM $wpdb->postmeta
            WHERE post_id = %d",
            $dpp_post_id
        ),
        ARRAY_A
    );

    if (!empty($dpp_post_meta_infos)) {
        $excluded_meta_keys = ["_wp_old_slug", "_edit_lock", "_edit_last"];

        foreach ($dpp_post_meta_infos as $dpp_meta_info) {
            // Skip excluded meta keys
            if (
                in_array($dpp_meta_info["meta_key"], $excluded_meta_keys, true)
            ) {
                continue;
            }

            // Add meta data with slashes stripped
            add_post_meta(
                $dpp_new_post_id,
                wp_slash($dpp_meta_info["meta_key"]),
                wp_slash($dpp_meta_info["meta_value"])
            );
        }
    }

    // Redirect with security checks
    $dpp_current_post_type = get_post_type($dpp_post_id);
    if (!$dpp_current_post_type) {
        wp_die(esc_html__("Invalid post type.", "duplicate-pp"), 400);
    }

    // Verify post type is registered
    $dpp_all_post_types = get_post_types(["public" => true], "names");
    if (isset($dpp_all_post_types[$dpp_current_post_type])) {
        // Use wp_safe_redirect instead of wp_redirect
        wp_safe_redirect(
            add_query_arg(
                [
                    "post_type" => sanitize_key($dpp_current_post_type),
                    "duplicated" => 1,
                ],
                admin_url("edit.php")
            )
        );
        exit();
    }

    wp_die(esc_html__("Post duplication failed.", "duplicate-pp"), 500);
}

// Register action with specific priority
add_action(
    "admin_action_dpp_duplicate_as_draft",
    "dpp_duplicate_as_draft",
    10,
    0
);

// Add custom capability check filter
add_filter("user_has_cap", "dpp_add_duplicate_post_cap", 10, 3);
function dpp_add_duplicate_post_cap($allcaps, $caps, $args)
{
    if (isset($args[0]) && $args[0] === "duplicate_post") {
        $allcaps["duplicate_post"] = user_can($args[1], "edit_posts");
    }
    return $allcaps;
}

/**
 * Add duplicate link to post/page row actions
 *
 * @param array    $actions Array of action links
 * @param WP_Post  $post    Post object
 * @return array   Modified actions array
 */
function dpp_duplicate_link($actions, $post)
{
    // Verify post object
    if (!($post instanceof WP_Post)) {
        return $actions;
    }

    // Check both edit_posts capability and post-specific permissions
    if (
        current_user_can("edit_posts") &&
        current_user_can("edit_post", $post->ID)
    ) {
        $nonce = wp_create_nonce("duplicate_post_" . get_current_blog_id());

        // Build URL using proper escaping
        $url = add_query_arg(
            [
                "action" => "dpp_duplicate_as_draft",
                "post" => absint($post->ID),
                "duplicate_nonce" => $nonce,
            ],
            admin_url("admin.php")
        );

        // Properly escape the URL and text
        $actions["duplicate"] = sprintf(
            '<a href="%s" aria-label="%s" class="dpp-duplicate-link">%s</a>',
            esc_url($url),
            sprintf(
                /* translators: %s: Post title */
                esc_attr__('Duplicate "%s"', "duplicate-pp"),
                esc_attr(get_the_title($post->ID))
            ),
            esc_html__("Duplicate", "duplicate-pp")
        );
    }

    return $actions;
}

// Add filters with specific priority and argument count
add_filter("post_row_actions", "dpp_duplicate_link", 10, 2);
add_filter("page_row_actions", "dpp_duplicate_link", 10, 2);

/**
 * Add duplicate link to admin bar
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance
 */
function dpp_admin_bar_duplicate_link($wp_admin_bar)
{
    // Verify admin bar object
    if (!($wp_admin_bar instanceof WP_Admin_Bar)) {
        return;
    }

    // Only proceed if viewing a single post/page
    if (!is_singular()) {
        return;
    }

    $post_id = get_the_ID();

    // Verify post exists and user has permission
    if (!$post_id || !current_user_can("edit_post", $post_id)) {
        return;
    }

    // Create nonce
    $nonce = wp_create_nonce("duplicate_post_" . get_current_blog_id());

    // Build URL using proper escaping and WordPress functions
    $url = add_query_arg(
        [
            "action" => "dpp_duplicate_as_draft",
            "post" => absint($post_id),
            "duplicate_nonce" => $nonce,
        ],
        admin_url("admin.php")
    );

    // Add the admin bar node with proper escaping
    $args = [
        "id" => "ddp_duplicate_link",
        "title" => esc_html__("Duplicate This", "duplicate-pp"),
        "href" => esc_url($url),
        "meta" => [
            "class" => "dpp-duplicate-link",
            "title" => sprintf(
                /* translators: %s: Post title */
                esc_attr__('Duplicate "%s"', "duplicate-pp"),
                esc_attr(get_the_title($post_id))
            ),
        ],
    ];

    $wp_admin_bar->add_node($args);
}

// Add admin bar action with high priority to ensure proper positioning
add_action("admin_bar_menu", "dpp_admin_bar_duplicate_link", 999);

// Add CSS for duplicate link styling
function dpp_add_duplicate_link_styles()
{
    ?>
    <style type="text/css">
        .dpp-duplicate-link {
            display: inline-block;
        }
        #wp-admin-bar-ddp_duplicate_link .ab-item:hover {
            color: #00a0d2;
        }
    </style>
    <?php
}
add_action("admin_head", "dpp_add_duplicate_link_styles");
