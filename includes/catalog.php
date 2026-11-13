<?php
/** Presentation-only storefront using WooCommerce's native purchase gates. */
defined( 'ABSPATH' ) || exit;

/** Keeps products visible while rejecting new storefront purchases. */
final class DDW_TWC_Catalog {
    /** Register catalog behavior only when selected on this website. @return void */
    public static function register(): void {
        add_shortcode( 'twc_catalog_notice', array( __CLASS__, 'shortcode' ) );
        if ( ! self::enabled() ) { return; }
        add_filter( 'woocommerce_is_purchasable', '__return_false', 1000 );
        add_filter( 'woocommerce_variation_is_purchasable', '__return_false', 1000 );
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'reject_add' ), 1001 );
        add_action( 'woocommerce_store_api_validate_add_to_cart', array( __CLASS__, 'reject_store' ), 1001, 2 );
        add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'classic' ), 1001, 2 );
        add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store' ), 1001, 2 );
        add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'reject_order' ), 1001 );
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'processed' ), 1001, 3 );
        add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'reject_store' ), 1001 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'reject_store' ), 1001 );
        add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'output' ), 31 );
        add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'output' ), 5 );
        add_action( 'woocommerce_before_cart', array( __CLASS__, 'output' ), 5 );
        add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'output' ), 5 );
        add_filter( 'woocommerce_loop_add_to_cart_link', '__return_empty_string', 1000 );
        add_filter( 'render_block', array( __CLASS__, 'block' ), 1000, 3 );
        if ( DDW_TWC_Settings::options()['catalog_hide_prices'] ) { add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 1000, 2 ); }
    }
    /**
     * Hide storefront prices while retaining product administration values.
     * @param string $html Native formatted price.
     * @param WC_Product|null $product Optional product context.
     * @return string Effective display price.
     */
    public static function price_html( $html, $product = null ): string { return is_admin() && ! wp_doing_ajax() ? $html : ''; }
    /** Return the current website's catalog state. @return bool */
    public static function enabled(): bool { return DDW_TWC_Settings::options()['catalog_enabled']; }
    /** Provide the public catalog explanation. @return string Plain text. */
    public static function message(): string { return DDW_TWC_Settings::options()['catalog_message'] ?: __( 'This shop is a catalog. Online orders are currently unavailable.', 'tools-for-woocommerce' ); }
    /**
     * Stop a classic add-to-cart request without changing the existing cart.
     * @param bool $passed Previous validation result.
     * @return bool False in catalog mode.
     */
    public static function reject_add( $passed ): bool {
        if ( ! wc_has_notice( self::message(), 'error' ) ) { wc_add_notice( self::message(), 'error' ); }
        return false;
    }
    /**
     * Add a checkout error for a pre-existing cart.
     * @param array $data Checkout data.
     * @param WP_Error $errors Shared error object.
     * @return void
     */
    public static function classic( $data, $errors ): void { $errors->add( 'ddw_twc_catalog', self::message() ); }
    /**
     * Block Store API checkout for a pre-existing cart.
     * @param WP_Error $errors Shared errors.
     * @param WC_Cart $cart Native cart.
     * @return void
     */
    public static function store( $errors, $cart ): void { if ( ! $cart->is_empty() ) { $errors->add( 'ddw_twc_catalog', self::message() ); } }
    /**
     * Stop new classic checkout order creation.
     * @param WC_Order $order New checkout order.
     * @return void Throws; existing orders/account payments are not hooked.
     */
    public static function reject_order( $order ): void { throw new Exception( esc_html( self::message() ) ); }
    /**
     * Repeat catalog protection immediately before classic order payment.
     * @param int $id Order ID.
     * @param array $data Checkout data.
     * @param WC_Order $order New order.
     * @return void
     */
    public static function processed( $id, $data, $order ): void { self::reject_order( $order ); }
    /**
     * Return a native Store API failure for new purchases.
     * @param mixed $object Product or order supplied by the native hook.
     * @param array $request Optional add-to-cart request.
     * @return void Throws a localized route error.
     */
    public static function reject_store( $object, $request = array() ): void { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'ddw_twc_catalog', esc_html( self::message() ), 400 ); }
    /** Return safe catalog notice markup for custom layouts. @return string */
    public static function inquiry_url( string $url, WC_Product $product ): string {
        $context = array(
            'tfwc_product' => $product->get_name(),
            'tfwc_sku' => $product->get_sku(),
            'tfwc_product_url' => $product->get_permalink(),
        );
        if ( str_starts_with( strtolower( $url ), 'mailto:' ) ) {
            parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
            $body = is_string( $query['body'] ?? null ) ? rtrim( $query['body'] ) . "\r\n\r\n" : '';
            $body .= __( 'Product', 'tools-for-woocommerce' ) . ': ' . $context['tfwc_product'] . "\r\n";
            if ( '' !== $context['tfwc_sku'] ) { $body .= __( 'SKU', 'tools-for-woocommerce' ) . ': ' . $context['tfwc_sku'] . "\r\n"; }
            return add_query_arg( 'body', rawurlencode( $body . $context['tfwc_product_url'] ), $url );
        }
        return add_query_arg( array_map( 'rawurlencode', array_filter( $context, static fn( $value ) => '' !== $value ) ), $url );
    }

    public static function shortcode(): string {
        $product = is_product() ? wc_get_product( get_queried_object_id() ) : null;
        return self::notice( $product instanceof WC_Product ? $product : null );
    }

    private static function notice( ?WC_Product $product ): string {
        if ( ! self::enabled() ) { return ''; }
        $s = DDW_TWC_Settings::options();
        $html = '<div class="woocommerce-info ddw-twc-catalog">' . esc_html( self::message() );
        $url = $s['catalog_contact_url'];
        if ( $url && $product && $s['catalog_product_context'] ) { $url = self::inquiry_url( $url, $product ); }
        if ( $url ) { $html .= ' <a href="' . esc_url( $url ) . '">' . esc_html( $s['catalog_contact_label'] ?: __( 'Send an inquiry', 'tools-for-woocommerce' ) ) . '</a>'; }
        return $html . '</div>';
    }
    /** Output the same notice in native classic shop templates. @return void */
    public static function output(): void { echo wp_kses_post( self::shortcode() ); }
    /**
     * Remove native purchase blocks and supply a notice on product purchase blocks.
     * @param string $content Rendered block HTML.
     * @param array $block Parsed block metadata.
     * @return string Effective HTML; generic third-party blocks remain untouched.
     */
    public static function block( $content, $block, $instance = null ): string {
        $name = $block['blockName'] ?? '';
        if ( in_array( $name, array( 'woocommerce/product-button', 'woocommerce/add-to-cart-form', 'woocommerce/add-to-cart-with-options' ), true ) ) {
            $id = absint( $instance->context['postId'] ?? 0 );
            $product = $id ? wc_get_product( $id ) : null;
            return $product instanceof WC_Product ? self::notice( $product ) : self::shortcode();
        }
        if ( DDW_TWC_Settings::options()['catalog_hide_prices'] && 'woocommerce/product-price' === $name ) { return ''; }
        return $content;
    }
}
