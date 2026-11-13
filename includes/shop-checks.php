<?php
/** Read-only configuration checks for shop administration. */
defined( 'ABSPATH' ) || exit;

final class DDW_TWC_Shop_Checks {
    public static function pages(): void {
        echo '<details><summary>' . esc_html__( 'Check shop pages and cache exclusions', 'tools-for-woocommerce' ) . '</summary><ul>';
        $paths = array();
        foreach ( array( 'cart' => __( 'Cart', 'woocommerce' ), 'checkout' => __( 'Checkout', 'woocommerce' ), 'myaccount' => __( 'My account', 'woocommerce' ) ) as $key => $label ) {
            $id = wc_get_page_id( $key );
            $post = $id > 0 ? get_post( $id ) : null;
            $status = __( 'Page missing', 'tools-for-woocommerce' );
            if ( $post ) {
                $status = 'publish' !== $post->post_status ? __( 'Page is not published', 'tools-for-woocommerce' ) : __( 'Published · check layout manually', 'tools-for-woocommerce' );
                if ( 'publish' === $post->post_status ) {
                    if ( has_block( 'woocommerce/' . $key, $post ) ) { $status = __( 'Published · WooCommerce block', 'tools-for-woocommerce' ); }
                    if ( has_shortcode( $post->post_content, 'woocommerce_' . $key ) ) { $status = __( 'Published · classic shortcode', 'tools-for-woocommerce' ); }
                    $paths[] = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
                }
            }
            echo '<li>' . esc_html( $label . ' · ' . $status );
            if ( $post && ( $edit = get_edit_post_link( $post->ID ) ) ) { echo ' · <a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit page', 'tools-for-woocommerce' ) . '</a>'; }
            echo '</li>';
        }
        echo '</ul><p>' . esc_html__( 'Review these paths and WooCommerce requests in your cache settings. With plain permalinks, use the page URLs instead of excluding the entire site.', 'tools-for-woocommerce' ) . '</p><pre>' . esc_html( implode( "\n", array_merge( array_unique( array_filter( $paths, static fn( $path ) => $path && '/' !== $path ) ), array( '?wc-ajax=*', rest_get_url_prefix() . '/wc/store/*' ) ) ) ) . '</pre></details>';
    }

    private static function query( string $key ): string {
        return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
    }

    public static function shipping(): void {
        $countries = WC()->countries->get_shipping_countries();
        $country = self::query( 'tfwc_country' );
        $state = self::query( 'tfwc_state' );
        $postcode = self::query( 'tfwc_postcode' );
        echo '<details' . ( $country ? ' open' : '' ) . '><summary>' . esc_html__( 'Find the matching shipping zone', 'tools-for-woocommerce' ) . '</summary><form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="tfwc-settings-map">';
        echo '<p><label for="tfwc-country">' . esc_html__( 'Country', 'tools-for-woocommerce' ) . '</label> <select required name="tfwc_country" id="tfwc-country"><option value=""></option>';
        foreach ( $countries as $code => $name ) { echo '<option value="' . esc_attr( $code ) . '"' . selected( $code, $country, false ) . '>' . esc_html( $name ) . '</option>'; }
        echo '</select></p><p><label for="tfwc-state">' . esc_html__( 'State or region code', 'tools-for-woocommerce' ) . '</label> <input id="tfwc-state" name="tfwc_state" value="' . esc_attr( $state ) . '" size="8"></p>';
        echo '<p><label for="tfwc-postcode">' . esc_html__( 'Postcode', 'tools-for-woocommerce' ) . '</label> <input id="tfwc-postcode" name="tfwc_postcode" value="' . esc_attr( $postcode ) . '" size="10"></p>';
        submit_button( __( 'Check shipping zone', 'tools-for-woocommerce' ), 'secondary', 'submit', false );
        echo '</form>';
        if ( isset( $countries[ $country ] ) ) {
            $states = WC()->countries->get_states( $country );
            if ( $states && ! isset( $states[ $state ] ) ) {
                echo '<p>' . esc_html( implode( ' · ', array_map( static fn( $code, $name ) => $code . ' – ' . $name, array_keys( $states ), $states ) ) ) . '</p>';
            } else {
                $zone = WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => compact( 'country', 'state', 'postcode' ) ) );
                echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&zone_id=' . $zone->get_id() ) ) . '">' . esc_html( $zone->get_zone_name() ) . '</a></p><ul>';
                $free = false;
                foreach ( $zone->get_shipping_methods( true ) as $method ) {
                    $free = $free || 'free_shipping' === $method->id;
                    echo '<li>' . esc_html( $method->get_title() ) . '</li>';
                }
                echo '</ul><p>' . esc_html( $free ? __( 'A free shipping method is configured. Its conditions still apply.', 'tools-for-woocommerce' ) : __( 'No enabled WooCommerce free shipping method is configured in this zone.', 'tools-for-woocommerce' ) ) . '</p>';
            }
        }
        echo '</details>';
    }

    public static function boxes(): void {
        add_meta_box( 'tfwc-product-check', __( 'Purchase check', 'tools-for-woocommerce' ), array( self::class, 'product' ), 'product', 'side' );
        add_meta_box( 'tfwc-coupon-check', __( 'Coupon check', 'tools-for-woocommerce' ), array( self::class, 'coupon' ), 'shop_coupon', 'side' );
    }

    public static function product( WP_Post $post ): void {
        $product = wc_get_product( $post->ID );
        if ( ! $product || ! current_user_can( 'edit_post', $post->ID ) ) { return; }
        $checks = DDW_TWC_Practice_Tools::product_check( $product, 1, DDW_TWC_Settings::options() );
        if ( $product->is_type( 'variable' ) ) {
            $attributes = array_filter( $product->get_attributes(), static fn( $attribute ) => $attribute->get_variation() );
            if ( ! $attributes ) { $checks[] = __( 'No attributes are configured for variations.', 'tools-for-woocommerce' ); }
            $children = $product->get_children();
            if ( ! $children ) { $checks[] = __( 'No variations configured.', 'tools-for-woocommerce' ); }
            foreach ( array_slice( $children, 0, 50 ) as $id ) {
                $variation = wc_get_product( $id );
                if ( ! $variation ) { continue; }
                if ( 'publish' !== $variation->get_status() || '' === $variation->get_price() || ! $variation->is_in_stock() ) {
                    $checks[] = $variation->get_formatted_name() . ' · ' . __( 'Check status, price and stock.', 'tools-for-woocommerce' );
                }
            }
            if ( count( $children ) > 50 ) { $checks[] = __( 'Checked the first 50 variations.', 'tools-for-woocommerce' ); }
        }
        echo '<p>' . esc_html( wc_get_stock_html( $product ) ? wp_strip_all_tags( wc_get_stock_html( $product ) ) : wc_get_product_stock_status_options()[ $product->get_stock_status() ] ?? '' ) . '</p>';
        echo '<ul>';
        foreach ( $checks as $check ) { echo '<li>' . esc_html( wp_strip_all_tags( $check ) ) . '</li>'; }
        echo '</ul>';
    }

    public static function coupon( WP_Post $post ): void {
        if ( ! current_user_can( 'edit_post', $post->ID ) ) { return; }
        $coupon = new WC_Coupon( $post->ID );
        $expiry = $coupon->get_date_expires();
        printf( '<p>%s</p><p>%s</p><p>%s</p>', esc_html( __( 'Expiry date', 'woocommerce' ) . ': ' . ( $expiry ? wc_format_datetime( $expiry ) : '—' ) ), esc_html( __( 'Usage limit per coupon', 'woocommerce' ) . ': ' . $coupon->get_usage_count() . ' / ' . ( $coupon->get_usage_limit() ?: '∞' ) ), esc_html( __( 'Minimum spend', 'woocommerce' ) . ': ' . html_entity_decode( wp_strip_all_tags( wc_price( (float) $coupon->get_minimum_amount() ) ) ) ) );
    }

    public static function order( $object, array $box ): void {
        $order = $box['args'];
        $message = match ( $order->get_status() ) {
            'processing' => $order->needs_shipping_address() ? __( 'Check whether the shipment is still outstanding.', 'tools-for-woocommerce' ) : __( 'Review fulfillment and any download permissions.', 'tools-for-woocommerce' ),
            'pending', 'on-hold', 'failed' => __( 'Review the order notes and the payment method for the next step.', 'tools-for-woocommerce' ),
            default => __( 'Review the order notes for the status history.', 'tools-for-woocommerce' ),
        };
        echo '<p>' . esc_html( wc_get_order_status_name( $order->get_status() ) . ' · ' . $message ) . '</p>';
        if ( $order->get_payment_method() ) {
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode( $order->get_payment_method() ) ) ) . '">' . esc_html( $order->get_payment_method_title() ?: __( 'Payment methods', 'woocommerce' ) ) . '</a> · <a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ) . '">' . esc_html__( 'Logs', 'woocommerce' ) . '</a></p>';
        }
        if ( current_user_can( 'manage_woocommerce' ) ) {
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ) . '">' . esc_html__( 'Email settings', 'tools-for-woocommerce' ) . '</a>';
            if ( current_user_can( 'manage_options' ) ) {
                $template = match ( $order->get_status() ) {
                    'processing' => 'WC_Email_Customer_Processing_Order',
                    'completed' => 'WC_Email_Customer_Completed_Order',
                    'on-hold' => 'WC_Email_Customer_On_Hold_Order',
                    'refunded' => 'WC_Email_Customer_Refunded_Order',
                    default => '',
                };
                $mail = WC()->mailer()->get_emails()[ $template ] ?? null;
                if ( $mail instanceof WC_Email ) {
                    echo ' · ' . esc_html( wp_strip_all_tags( $mail->get_title() ) . ' (' . ( $mail->is_enabled() ? __( 'Active', 'tools-for-woocommerce' ) : __( 'Inactive', 'tools-for-woocommerce' ) ) . ')' );
                }
                if ( $mail instanceof WC_Email && isset( DDW_TWC_Email_Designer::settings_map_links()[ spl_object_id( $mail ) ] ) ) { echo ' · <a href="' . esc_url( add_query_arg( 'template', $template, DDW_TWC_Email_Designer::url() ) ) . '">' . esc_html__( 'Edit design', 'tools-for-woocommerce' ) . '</a>'; }
            }
            echo '</p>';
        }
    }
}
