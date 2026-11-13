<?php
/** WordPress Site Health information for troubleshooting TfWC. */
defined( 'ABSPATH' ) || exit;

final class DDW_TWC_Admin_Diagnostics {
    public static function information( array $info ): array {
        $ready = has_action( 'admin_menu', array( DDW_TWC_Email_Designer::class, 'menu' ) );
        $settings = DDW_TWC_Settings::options();
        $mode = $settings['operation_mode'] ?? 'standard';
        $active = array();
        if ( $ready ) {
            foreach ( $settings as $key => $enabled ) {
                if ( str_starts_with( $key, 'hide_' ) && true === $enabled ) {
                    $active[] = __( 'Tidy shop', 'tools-for-woocommerce' );
                    break;
                }
            }
            if ( ! empty( $settings['split_admin_menu'] ) ) { $active[] = __( 'Menu split', 'tools-for-woocommerce' ); }
            if ( in_array( $mode, array( 'free_physical', 'free_downloads' ), true ) ) { $active[] = __( 'Free offers', 'tools-for-woocommerce' ); }
            if ( ! empty( $settings['catalog_enabled'] ) ) { $active[] = __( 'Catalog', 'tools-for-woocommerce' ); }
            if ( ! empty( $settings['rules_enabled'] ) ) { $active[] = __( 'Cart Rules', 'tools-for-woocommerce' ); }
            if ( ! empty( $settings['donation_enabled'] ) ) { $active[] = __( 'Voluntary support', 'tools-for-woocommerce' ); }
        }
        $design = get_option( DDW_TWC_Email_Designer::OPTION, array() );
        $published = is_array( $design['live'] ?? null );
        $info['tools-for-woocommerce'] = array(
            'label' => 'Tools for WooCommerce',
            'fields' => array(
                'plugin_version' => array( 'label' => __( 'Version', 'tools-for-woocommerce' ), 'value' => DDW_TWC_VERSION ),
                'woocommerce_version' => array( 'label' => 'WooCommerce', 'value' => defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Inactive', 'tools-for-woocommerce' ) ),
                'connection' => array( 'label' => __( 'WooCommerce connection', 'tools-for-woocommerce' ), 'value' => $ready ? __( 'Active', 'tools-for-woocommerce' ) : __( 'Paused', 'tools-for-woocommerce' ) ),
                'modules' => array( 'label' => __( 'Enabled shop features', 'tools-for-woocommerce' ), 'value' => $active ? implode( ', ', $active ) : __( 'None', 'tools-for-woocommerce' ) ),
                'email_design' => array( 'label' => __( 'Email Designer', 'tools-for-woocommerce' ), 'value' => $published ? __( 'Design published', 'tools-for-woocommerce' ) : __( 'No published design', 'tools-for-woocommerce' ) ),
            ),
        );
        return $info;
    }
}
