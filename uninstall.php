<?php
/** Optional per-site host cleanup and the shared Library's last-host lifecycle. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove this website's settings only when its administrator opted in.
 * @return void Does not delete old plugin settings, orders or products.
 */
function ddw_twc_uninstall_site(): void {
    $settings = get_option( 'ddw_twc_settings', array() );
    if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) { return; }
    delete_option( 'ddw_twc_email_design' );
    if ( null !== get_option( 'ddw_twc_email_design', null ) ) {
        error_log( 'Tools for WooCommerce: email cleanup incomplete for site ' . get_current_blog_id() );
        return;
    }
    delete_option( 'ddw_twc_settings' );
    if ( null !== get_option( 'ddw_twc_settings', null ) ) {
        error_log( 'Tools for WooCommerce: settings cleanup incomplete for site ' . get_current_blog_id() );
    }
}
if ( is_multisite() ) {
    $ddw_twc_offset = 0;
    do {
        $ddw_twc_sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $ddw_twc_offset ) );
        foreach ( $ddw_twc_sites as $ddw_twc_site_id ) { switch_to_blog( $ddw_twc_site_id ); ddw_twc_uninstall_site(); restore_current_blog(); }
        $ddw_twc_offset += 100;
    } while ( 100 === count( $ddw_twc_sites ) );
} else { ddw_twc_uninstall_site(); }
require_once __DIR__ . '/includes/deckerweb-plugin-library/lifecycle.php';
deckerweb_library_uninstall_v4( __DIR__ . '/tools-for-woocommerce.php' );
