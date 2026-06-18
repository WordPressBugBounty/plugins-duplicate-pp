=== Duplicate PP ===
Contributors: binsaifullah
Tags: post, page, duplicate post, duplicate page, duplicate custom post type
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 3.7.0
License: GPLv2 or later

**Duplicate PP** is a simple plugin which allows you to duplicate any POST,PAGE and Custom POST TYPE Easily.

== Description ==

Duplicate PP is a simple and light-weight plugin which allows you to duplicate any **Post**,**Page** and Any **Custom Post Type** Easily. The duplicated Post or Page or CPT acts as draft. You can either duplicate the post, page or any custom post type using dashboard at the backend or from the single post view at the frontend.

### Features Include:

* **Duplicate the POST**
* **Duplicate the PAGE**
* **Duplicate Any Custom POST TYPE**
* **Duplicate from Backend**
* **Duplicate from Frontend (Single Post View)**


== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Go to Plugin Menu from your dashboard.
2. Click on Add New
3. Now upload "duplicate-pp.zip" file and click install now.
4. Finally active it. It is done.

== Changelog ==
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
