<?php
/**
 * Plugin Name: Tools for WooCommerce
 * Plugin URI: https://deckerwebplugins.com/en/plugins/tools-for-woocommerce/
 * Description: A calmer store, free offers, catalog mode and flexible cart rules for WooCommerce.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: David Decker – DECKERWEB
 * Author URI: https://deckerwebplugins.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tools-for-woocommerce
 * Domain Path: /languages/
 * Update URI: https://github.com/deckerweb/tools-for-woocommerce
 * GitHub Plugin URI: https://github.com/deckerweb/tools-for-woocommerce
 *
 * Copyright © 2026 David Decker – DECKERWEB.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;
if ( defined( 'DDW_TWC_FILE' ) ) { return; }
define( 'DDW_TWC_FILE', __FILE__ );
define( 'DDW_TWC_VERSION', '1.0.0' );
require_once __DIR__ . '/includes/write-lock.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/commerce.php';
require_once __DIR__ . '/includes/cart-rules.php';
require_once __DIR__ . '/includes/catalog.php';
require_once __DIR__ . '/includes/donations.php';
require_once __DIR__ . '/includes/integrations.php';
DDW_TWC_Settings::register();
if ( is_admin() ) {
    require_once __DIR__ . '/includes/admin-diagnostics.php';
    add_filter( 'debug_information', array( DDW_TWC_Admin_Diagnostics::class, 'information' ) );
}
add_action( 'init', 'ddw_twc_load_translations', 0 );
add_action( 'before_woocommerce_init', 'ddw_twc_declare_compatibility' );
add_action( 'plugins_loaded', 'ddw_twc_boot', 100 );

if ( ! function_exists( 'ddw_twc_load_translations' ) ) {
/** Load host language files from this direct-distribution plugin. @return void */
function ddw_twc_load_translations(): void {
    load_plugin_textdomain( 'tools-for-woocommerce', false, dirname( plugin_basename( DDW_TWC_FILE ) ) . '/languages' );
}

/** Declare CRUD-based order storage compatibility to WooCommerce. @return void */
function ddw_twc_declare_compatibility(): void {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DDW_TWC_FILE, true );
    }
}

require_once __DIR__ . '/includes/email-designer.php';

/** Start supported shop modules while retaining safe settings without WooCommerce. @return void */
function ddw_twc_boot(): void {
    if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '11.1.2', '<' ) || class_exists( 'Deckerweb_Purify_WooCommerce_V1', false ) || class_exists( 'DDW_Purify_WooCommerce', false ) ) {
        add_shortcode( 'twc_catalog_notice', '__return_empty_string' );
        add_shortcode( 'twc_support_notice', '__return_empty_string' );
        return;
    }
    require_once __DIR__ . '/includes/practice-tools.php';
    add_action( 'admin_init', array( 'DDW_TWC_Practice_Tools', 'register' ) );
    DDW_TWC_Commerce::register();
    if ( is_admin() ) {
        require_once __DIR__ . '/includes/settings-map.php';
        DDW_TWC_Settings_Map::register();
    }
    if ( is_admin() && DDW_TWC_Commerce::enabled( 'split_admin_menu' ) ) {
        require_once __DIR__ . '/includes/admin-menu.php';
        DDW_TWC_Admin_Menu::register();
    }
    DDW_TWC_Cart_Rules::register();
    DDW_TWC_Catalog::register();
    DDW_TWC_Donations::register();
    DDW_TWC_Email_Designer::register();
}

}
