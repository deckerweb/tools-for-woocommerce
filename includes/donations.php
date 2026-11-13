<?php
/** Optional informational support text; no payment collection or order fees. */
defined( 'ABSPATH' ) || exit;

/** Displays a voluntary support message only for configured free offers. */
final class DDW_TWC_Donations {
    /** Register native placements and the optional custom-layout shortcode. @return void */
    public static function register(): void {
        add_shortcode( 'twc_support_notice', array( __CLASS__, 'shortcode' ) );
        $s = DDW_TWC_Settings::options();
        if ( ! $s['donation_enabled'] || ! DDW_TWC_Commerce::free() || $s['catalog_enabled'] ) { return; }
        if ( $s['donation_email'] ) { add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'completion_email' ), 20, 4 ); }
        add_filter( 'render_block', array( __CLASS__, 'block' ), 30, 2 );
        $hooks = array( 'donation_shop' => 'woocommerce_before_shop_loop', 'donation_product' => 'woocommerce_single_product_summary', 'donation_cart' => 'woocommerce_before_cart', 'donation_checkout' => 'woocommerce_before_checkout_form', 'donation_thankyou' => 'woocommerce_thankyou' );
        foreach ( $hooks as $key => $hook ) { if ( $s[$key] ) { add_action( $hook, array( __CLASS__, 'output' ), 25 ); } }
    }
    /**
     * Place the informational notice before native Cart/Checkout blocks.
     * @param string $html Existing native block markup.
     * @param array $block Parsed block metadata.
     * @return string Markup with the selected optional notice.
     */
    public static function block( $html, $block ): string {
        $s = DDW_TWC_Settings::options(); $name = $block['blockName'] ?? '';
        if ( ( 'woocommerce/cart' === $name && $s['donation_cart'] ) || ( 'woocommerce/checkout' === $name && $s['donation_checkout'] ) ) { return self::shortcode() . $html; }
        return $html;
    }
    /**
     * Return escaped support text for native templates or a Shortcode block.
     * @return string Safe markup; empty outside enabled free-offer mode.
     */
    public static function shortcode(): string {
        $s = DDW_TWC_Settings::options();
        if ( ! $s['donation_enabled'] || ! DDW_TWC_Commerce::free() || $s['catalog_enabled'] ) { return ''; }
        $text = $s['donation_text'] ?: ( DDW_TWC_Commerce::physical() ? __( 'Products and shipping are free. A voluntary contribution toward shipping costs is welcome.', 'tools-for-woocommerce' ) : __( 'These downloads are free. A voluntary tip to support this service is welcome.', 'tools-for-woocommerce' ) );
        $html = '<div class="woocommerce-info ddw-twc-support">' . nl2br( esc_html( $text ) );
        if ( $s['donation_url'] ) { $html .= ' <a href="' . esc_url( $s['donation_url'] ) . '">' . esc_html( $s['donation_label'] ?: __( 'Support this service', 'tools-for-woocommerce' ) ) . '</a>'; }
        return $html . '</div>';
    }
    public static function order_kind( WC_Order $order ): string {
        if ( 0.0 !== (float) $order->get_total() || 0.0 !== (float) $order->get_shipping_total() || ! $order->get_items() ) { return ''; }
        foreach ( $order->get_fees() as $fee ) {
            if ( 0.0 !== (float) $fee->get_total() || 0.0 !== (float) $fee->get_total_tax() ) { return ''; }
        }
        $kind = '';
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product || 0.0 !== (float) $item->get_subtotal() || 0.0 !== (float) $item->get_total() || 0.0 !== (float) $item->get_total_tax() ) { return ''; }
            $type = match ( true ) {
                $product->is_virtual() && $product->is_downloadable() => 'downloads',
                ! $product->is_virtual() && ! $product->is_downloadable() => 'physical',
                default => '',
            };
            if ( '' === $type || ( $kind && $kind !== $type ) ) { return ''; }
            $kind = $type;
        }
        return $kind;
    }

    public static function completion_email( $order, $sent_to_admin, $plain_text, $email ): void {
        if ( $sent_to_admin || ! $order instanceof WC_Order || ! $email instanceof WC_Email_Customer_Completed_Order ) { return; }
        $kind = self::order_kind( $order );
        if ( '' === $kind ) { return; }
        $s = DDW_TWC_Settings::options();
        $fallback = 'physical' === $kind
            ? __( 'Products and shipping are free. A voluntary contribution toward shipping costs is welcome.', 'tools-for-woocommerce' )
            : __( 'These downloads are free. A voluntary tip to support this service is welcome.', 'tools-for-woocommerce' );
        $text = $s[ 'donation_email_' . $kind ] ?: ( $s['donation_text'] ?: $fallback );
        $label = $s['donation_label'] ?: __( 'Support this service', 'tools-for-woocommerce' );
        if ( $plain_text ) {
            echo "\n" . esc_html( $text ) . "\n";
            if ( $s['donation_url'] ) { echo esc_html( $label ) . ': ' . esc_url_raw( $s['donation_url'] ) . "\n"; }
            return;
        }
        echo '<p class="ddw-twc-support-email">' . nl2br( esc_html( $text ) );
        if ( $s['donation_url'] ) { echo ' <a href="' . esc_url( $s['donation_url'] ) . '">' . esc_html( $label ) . '</a>'; }
        echo '</p>';
    }

    /**
     * Output a voluntary support message; hooks may pass an order ID or template argument.
     * @param mixed $context Optional native hook argument, unused.
     * @return void
     */
    public static function output( $context = null ): void { echo wp_kses_post( self::shortcode() ); }
}
