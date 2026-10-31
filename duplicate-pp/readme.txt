=== Duplicate PP ===
Contributors: binsaifullah
Tags: post, page, duplicate post, duplicate page, duplicate custom post type
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 4.0.0
Requires PHP: 7.4
License: GPLv2 or later

**Duplicate PP** is a simple plugin which allows you to duplicate any POST,PAGE and Custom POST TYPE Easily.

== Description ==

Duplicate PP copies any **post**, **page**, **WooCommerce product** or **custom post type** in one click — with its custom fields, categories, tags and page builder layouts.

### Ways to copy

* **Duplicate** – one click under any title in the list; you stay on the list.
* **New Draft** – copy to a fresh draft and open it in the editor straight away.
* **Rewrite & Republish** – rework a published post in a hidden copy, then republish it over the original. Same URL, same ID, no downtime. Schedule it to go live later.
* **Copy multiple** – make 2, 5, 10… copies of an item in one go, with optional numbered titles ("Copy 1", "Copy 2"…). Works for one item or many through Bulk actions.
* **Bulk Duplicate / Bulk Rewrite & Republish** – from the Bulk actions menu.
* From the **list table**, the **block editor**, the **classic editor** and the **admin bar** (front end and back end).

### Full control

* **What to copy:** content, excerpt, featured image, template, post format, terms, custom fields, password, menu order, discussion settings, original author, original date, child pages and comments.
* **Title & slug prefix/suffix** with a live preview, status of copies (draft, pending, private, published or same as original), menu order increment.
* **Exclude** custom fields (with `*` wildcards) and taxonomies.
* **Permissions:** choose the post types and user roles that can copy.
* **Display:** choose where the links appear, and show the original in the editor, a list column or as a post label.
* A **dashboard** with usage numbers, recent copies and rewrites in progress.

### Page builders

Elementor (with fresh element IDs), WPBakery, Beaver Builder, ACF and Codestar Framework data are copied.

### For developers

Filters `dpp_can_copy`, `dpp_can_rewrite`, `dpp_max_copies`, `dpp_duplicate_post_args`, `dpp_excluded_meta_keys`, `dpp_available_post_types`, `dpp_settings`; actions `dpp_before_duplicate`, `dpp_after_duplicate`, `dpp_before_republish`, `dpp_after_republish`.

### From the same team

[Cartlet](https://wordpress.org/plugins/cartlet/) – share, save and track WooCommerce carts. Shoppers send their cart as a link, QR code or WhatsApp message, and can save it for later.

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Go to Plugin Menu from your dashboard.
2. Click on Add New
3. Now upload "duplicate-pp.zip" file and click install now.
4. Finally active it. It is done.

== Screenshots ==

1. The Duplicate PP dashboard: copies made, rewrites in progress, a 30-day activity chart, recent copies and the state of every feature.
2. Quick duplicate: search any post, page or product from the dashboard and copy it in one click.
3. Copy links under every title: Duplicate, New Draft, Copy multiple and Rewrite & Republish.
4. Copy multiple: choose how many copies to make, with numbered titles and a live preview.
5. Duplicate multiple times from Bulk actions — the same dialog for every selected item.
6. Block editor: "Copy to a new draft" and "Rewrite & Republish" in the post summary panel, plus a "Duplicate This" admin bar menu.
7. Rewrite & Republish: edit a hidden copy of a live post, then republish it over the original — now or on a schedule.
8. What to copy: title and slug affixes with a live preview, status of copies, copy limits and 14 post elements to switch on or off.
9. Permissions: choose the post types and user roles that can copy, and turn Rewrite & Republish on or off.

== Changelog ==
= 4.0.0 =
* Added: Rewrite & Republish — edit a published item in a copy and republish it over the original, immediately or on a schedule.
* Added: "New Draft" action that opens the copy in the editor.
* Added: "Copy multiple" row action and "Duplicate multiple times…" bulk action — choose how many copies to make, with optional numbered titles, up to a configurable limit.
* Added: Bulk actions — Duplicate and Rewrite & Republish.
* Added: Copy buttons and "Copy of" link in the block editor and the classic editor.
* Added: Admin bar "Duplicate This" menu in the admin as well as the front end.
* Added: "What to copy" settings, excluded custom fields (wildcards) and taxonomies, menu order increment, "same as original" status.
* Added: Permissions for post types and user roles; Display settings; optional "Original" column and "Copy" label.
* Added: Child pages and comments can be copied.
* Added: New settings dashboard with stats, recent copies and rewrites in progress, plus a Help tab.
* Fixed: Content and custom fields are now copied byte for byte (backslashes in block attributes and JSON meta were stripped, and HTML entities such as &lt; in code were decoded).
* Fixed: Title suffix " (Copy)" lost its leading space after saving the settings.
* Fixed: Elementor background images were broken on copies; Elementor meta could be duplicated with empty values.
* Fixed: Hierarchical terms with the same slug are copied correctly.
* Improved: Users who cannot publish get "Pending" copies instead of published ones.
* Improved: WooCommerce SKU and sales/rating counters are not copied to product duplicates.

= 3.7.0 =
* Refactored: Full codebase reorganization — every class moved into the `DuplicatePP` namespace, every main class is now a singleton, and the root `duplicate-pp.php` is a clean bootstrap.
* Improved: Settings read centralized in `DuplicatePP\Settings\Settings_Service` so the `dpp_settings` option has a single source of truth.
* Improved: Inline CSS and JS extracted from the settings page into `assets/css/admin-settings.css` and `assets/js/admin-settings.js`.
* Improved: Every user-facing string is translatable with the `duplicate-pp` text domain; every output is escaped.
* Improved: Meta handler converted from a static utility to a `DuplicatePP\Meta\Meta_Handler` singleton.
* Added: `uninstall.php` cleans up `dpp_settings` and `dpp_activation_redirect` on full uninstall.
* Developer: New constants `DPP_PLUGIN_FILE`; legacy constants and option keys preserved for backward compatibility.

= 3.6.1 =
* Fixed: HTML entities issue fixed for classic editor

= 3.6 =
* Added: Setting page 
* Fixed: Security issues
* Improved: Performance and code quality
* Compatibility Check

= 3.5.6 =
* Fixed: fixed user permission issue

= 3.5.5 =
* Fixed: Security issues for user roles

= 3.5.4 =
* Fixed: Security issues
* Improved: Performance and code quality
* Compatibility Check

= 3.5.3 =
*Compatibility Check*
= 3.5.2 =
*Compatibility Check*

= 3.5.0 =
*Compatibility Check*
*Fixed Post Meta Duplication Issue*

= 3.4.0 =
*Compatibility Check*
*Secure SQL*

= 3.3.0 =
*Adding Frontend Duplicating*
*Updating Support Information*
*Adding Simple Documentation*

= 3.2.2 =
*Testing with WordPress latest version*
*Updating Support Information*

= 3.2.1 =
*Testing with WordPress latest version*

= 3.2.0 =
*Bug Fixing*

= 3.1.0 =
*Bug Fixing*

= 3.0.0 =
*Compatibility Check with Gutenberg Editor*
*Support link is added*

= 2.0.0 =
*Add duplicating any custom post type scope*

= 1.0.0 =
*Release Date - 1st Sept, 2019*
