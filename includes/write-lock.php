<?php
/** Serialize settings and email changes for each site. */
defined( 'ABSPATH' ) || exit;

final class DDW_TWC_Write_Lock {
    public static function run( callable $write ) {
        global $wpdb;
        $key = 'ddw_twc_write_lock';
        $token = time() . ':' . wp_generate_uuid4();
        // A unique option name makes insertion atomic, including with persistent object caches.
        $acquired = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $key, $token
        ) );
        if ( 1 !== $acquired ) {
            return new WP_Error( 'ddw_twc_busy', __( 'Another save is in progress. Please try again.', 'tools-for-woocommerce' ), array( 'status' => 409 ) );
        }
        $table = $wpdb->options;
        $release = static function () use ( $wpdb, $table, $key, $token ): void {
            $wpdb->delete( $table, array( 'option_name' => $key, 'option_value' => $token ) );
        };
        register_shutdown_function( $release );
        try {
            foreach ( array( 'alloptions', 'notoptions', DDW_TWC_Settings::OPTION, DDW_TWC_Email_Designer::OPTION ) as $cache_key ) {
                wp_cache_delete( $cache_key, 'options' );
            }
            return $write();
        } finally {
            $release();
        }
    }
}
