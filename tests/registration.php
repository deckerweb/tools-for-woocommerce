<?php
/** Bootstrap and module registration tests; no WordPress integration claims. */
define( 'ABSPATH', __DIR__ );
if ( ( $argv[1] ?? '' ) !== 'missing' ) { define( 'WC_VERSION', '11.1.2' ); }
$GLOBALS['hooks'] = array();
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][$hook][] = array( $priority, $callback ); }
function is_admin(){return false;}
function add_shortcode($tag,$callback){}
function sanitize_textarea_field($s){return trim(strip_tags($s));}
function esc_url_raw($s,$schemes=[]){return $s;}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { add_action( $hook, $callback, $priority, $accepted_args ); }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function get_option( $key, $default = array() ) { return $GLOBALS['options'] ?? $default; }
function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
function wp_html_excerpt( $text, $length, $more ) { return substr( $text, 0, $length ); }
function do_test_action( $hook ) {
    $callbacks = $GLOBALS['hooks'][$hook] ?? array();
    usort( $callbacks, static function( $a, $b ) { return $a[0] <=> $b[0]; } );
    foreach ( $callbacks as $callback ) {
        // Library lifecycle has its own suite; this test examines the host callback.
        if ( is_string( $callback[1] ) && 0 === strpos( $callback[1], 'deckerweb_library_' ) ) { continue; }
        call_user_func( $callback[1] );
    }
}
function check( $ok, $label ) { if ( ! $ok ) { throw new Exception( 'FAIL: ' . $label ); } $GLOBALS['count'] = ( $GLOBALS['count'] ?? 0 ) + 1; }
$mode = $argv[1] ?? 'default';
require __DIR__ . '/../includes/settings.php';
$GLOBALS['options'] = DDW_TWC_Settings::preset( in_array( $mode, array( 'physical', 'downloads' ), true ) ? 'free' : 'tidy', $mode === 'downloads' ? 'downloads' : 'physical' );
if ( 'off' === $mode ) { foreach ( $GLOBALS['options'] as $key => $value ) { if ( is_bool( $value ) ) { $GLOBALS['options'][$key] = false; } } }
if ( 'ui-only' === $mode ) { $GLOBALS['options']['hide_payments_menu'] = true; $GLOBALS['options']['trim_checkout'] = true; }
if ( 'snippet' === $mode ) { class Deckerweb_Purify_WooCommerce_V1 {} }
require __DIR__ . '/../tools-for-woocommerce.php';
$before = count( $GLOBALS['hooks'], COUNT_RECURSIVE );
include __DIR__ . '/../tools-for-woocommerce.php';
check( count( $GLOBALS['hooks'], COUNT_RECURSIVE ) === $before, 'duplicate main include' );
check( count( $GLOBALS['deckerweb_library_candidates_v1'] ) === 1, 'single Library registration' );
do_test_action( 'plugins_loaded' );
$shop = ! in_array( $mode, array( 'snippet', 'missing' ), true );
$free = in_array( $mode, array( 'physical', 'downloads' ), true );
check( isset( $GLOBALS['hooks']['woocommerce_available_payment_gateways'] ) === ( $shop && $free ), 'gateway module' );
check( isset( $GLOBALS['hooks']['woocommerce_store_api_cart_errors'] ) === ( $shop && $free ), 'Store API module' );
check( isset( $GLOBALS['hooks']['woocommerce_order_button_text'] ) === ( $shop && $free ), 'free text module' );
check( isset( $GLOBALS['hooks']['woocommerce_checkout_fields'] ) === ( $shop && ( $free || 'ui-only' === $mode ) ), 'company removal is independent' );
check( isset( $GLOBALS['hooks']['woocommerce_allow_marketplace_suggestions'] ) === ( $shop && 'off' !== $mode ), 'advertising module' );
check( isset( $GLOBALS['hooks']['wp_dashboard_setup'] ) === ( $shop && $free ), 'renaming module' );
check( isset( $GLOBALS['hooks']['woocommerce_settings_tabs_array'] ) === ( $shop && ( $free || 'ui-only' === $mode ) ), 'payment UI separate from gateways' );
check( isset( $GLOBALS['hooks']['woocommerce_checkout_create_order'] ) === ( $shop && 'physical' === $mode ), 'physical address copy only' );
$sanitized = DDW_TWC_Settings::sanitize( array( 'hide_marketplace_ads' => 'false', 'admin_label' => '<b>CD Shop</b>', 'operation_mode' => 'free_downloads', 'unknown' => 1 ) );
check( false === $sanitized['hide_marketplace_ads'] && 'free_downloads' === $sanitized['operation_mode'] && 'CD Shop' === $sanitized['admin_label'] && ! isset( $sanitized['unknown'] ), 'sanitize settings' );
check( 'WooCommerce' === DDW_TWC_Settings::sanitize( array( 'admin_label' => array() ) )['admin_label'], 'invalid label fallback' );
echo $GLOBALS['count'] . ' bootstrap/module checks passed (' . $mode . ").\n";
