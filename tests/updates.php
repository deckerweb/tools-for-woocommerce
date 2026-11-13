<?php
/** Host package guards; no download or live GitHub update. */
define( 'ABSPATH', __DIR__ );
define( 'DDW_TWC_FILE', dirname( __DIR__ ) . '/tools-for-woocommerce.php' );
define( 'DDW_TWC_VERSION', '0.2.0' );
function __( $s, $domain ) { return $s; }
function plugin_basename( $file ) { return 'tools-for-woocommerce/tools-for-woocommerce.php'; }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
class WP_Error { public function __construct( public $code, public $message ) {} }
class Plugin_Upgrader { public $bulk = true; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_site_transient( $key ) { return (object) array( 'response' => array( plugin_basename( DDW_TWC_FILE ) => (object) array( 'new_version' => '0.3.0' ) ) ); }
function is_php_version_compatible( $v ) { return version_compare( PHP_VERSION, $v, '>=' ); }
function is_wp_version_compatible( $v ) { return version_compare( '7.1.2', $v, '>=' ); }
$wp_filesystem = new class {
    public $text = "Plugin Name: Tools for WooCommerce\nVersion: 0.3.0\nUpdate URI: https://github.com/deckerweb/tools-for-woocommerce\nRequires PHP: 8.0\nRequires at least: 7.0\n";
    function is_file( $p ) { return true; }
    function size( $p ) { return strlen( $this->text ); }
    function get_contents( $p ) { return $this->text; }
};
require __DIR__ . '/../includes/class-github-updates.php';
$adapter = new \Deckerweb\ToolsForWooCommerce\GitHubUpdates();
$ctx = array( 'plugin' => plugin_basename( DDW_TWC_FILE ), 'type' => 'plugin', 'action' => 'update' );
$count = 0;
function check( $ok, $label ) { global $count; if ( ! $ok ) { throw new Exception( 'FAIL: ' . $label ); } ++$count; }
check( '/candidate' === $adapter->validate_source( '/candidate', '', null, $ctx ), 'valid offered release' );
$baseline = $wp_filesystem->text;
foreach ( array( array( 'Tools for WooCommerce', 'Other Plugin' ), array( 'Version: 0.3.0', 'Version: 0.1.0' ), array( 'Version: 0.3.0', 'Version: 0.4.0' ), array( 'Requires PHP: 8.0', 'Requires PHP: 99.0' ), array( 'Requires at least: 7.0', 'Requires at least: 99.0' ), array( 'Requires PHP: 8.0', 'Requires PHP:' ), array( 'https://github.com/deckerweb/tools-for-woocommerce', 'https://example.org/other' ) ) as $replace ) {
    $wp_filesystem->text = str_replace( $replace[0], $replace[1], $baseline );
    check( is_wp_error( $adapter->validate_source( '/candidate', '', null, $ctx ) ), 'reject invalid candidate' );
}
check( '/candidate' === $adapter->validate_source( '/candidate', '', null, array( 'plugin' => 'other/other.php', 'type' => 'plugin', 'action' => 'update' ) ), 'other plugin untouched' );
check( '/candidate' === $adapter->validate_source( '/candidate', '', null, array( 'plugin' => plugin_basename( DDW_TWC_FILE ), 'type' => 'plugin', 'action' => 'install' ) ), 'install context untouched' );
check( '/candidate' === $adapter->validate_source( '/candidate', '', null, array( 'plugin' => plugin_basename( DDW_TWC_FILE ) ) ), 'ambiguous context untouched' );
echo "$count host package-guard checks passed.\n";
