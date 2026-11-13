<?php
/** Read-only diagnostics and packing list for WooCommerce. */
defined( 'ABSPATH' ) || exit;

final class DDW_TWC_Practice_Tools {
    private const PACK = 'ddw_twc_pack_list';
    private const LIMIT = 100;

    public static function register(): void {
        if ( ! function_exists( 'wc_get_page_screen_id' ) ) { return; }
        require_once __DIR__ . '/shop-checks.php';
        add_action( 'add_meta_boxes', array( DDW_TWC_Shop_Checks::class, 'boxes' ) );
        add_action( 'add_meta_boxes', array( self::class, 'order_box' ), 20, 2 );
        foreach ( array( 'edit-shop_order', wc_get_page_screen_id( 'shop-order' ) ) as $screen ) {
            add_filter( 'bulk_actions-' . $screen, array( self::class, 'pack_action' ) );
            add_filter( 'handle_bulk_actions-' . $screen, array( self::class, 'pack_redirect' ), 10, 3 );
        }
        add_action( 'admin_post_' . self::PACK, array( self::class, 'pack_page' ) );
    }

    public static function order_box( $screen, $object ): void {
        if ( ! in_array( $screen, array( 'shop_order', wc_get_page_screen_id( 'shop-order' ) ), true ) ) { return; }
        $order = $object instanceof WP_Post ? wc_get_order( $object->ID ) : $object;
        if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) { return; }
        add_meta_box( 'tfwc-order-next-step', __( 'Order status and next step', 'tools-for-woocommerce' ), array( DDW_TWC_Shop_Checks::class, 'order' ), $screen, 'side', 'default', $order );
        $rows = self::download_rows( $order );
        if ( $rows ) { add_meta_box( 'ddw-twc-download-check', __( 'Download check', 'tools-for-woocommerce' ), array( self::class, 'download_box' ), $screen, 'side', 'default', array( 'order' => $order, 'rows' => $rows ) ); }
    }

    public static function download_box( $object, array $box ): void {
        $order = $box['args']['order'];
        $rows = $box['args']['rows'];
        if ( ! $order->is_download_permitted() ) {
            echo '<p><strong>' . esc_html__( 'WooCommerce currently blocks downloads for this order status.', 'tools-for-woocommerce' ) . '</strong></p>';
        }
        echo '<ul>';
        foreach ( $rows as $row ) {
            echo '<li><strong>' . esc_html( $row['name'] ) . '</strong><br>' . esc_html( implode( ' · ', $row['checks'] ) ) . '</li>';
        }
        echo '</ul><p><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=downloadable' ) ) . '">' . esc_html__( 'WooCommerce download settings', 'tools-for-woocommerce' ) . '</a></p>';
        if ( ! DDW_TWC_Commerce::enabled( 'hide_downloads_box' ) || 'free_downloads' === DDW_TWC_Commerce::mode() ) {
            echo '<p><a href="#woocommerce-order-downloads">' . esc_html__( 'Downloadable product permissions', 'tools-for-woocommerce' ) . '</a></p>';
        }
        echo '<p><a href="#woocommerce-order-actions">' . esc_html__( 'Open order actions to regenerate permissions or resend order details', 'tools-for-woocommerce' ) . '</a></p>';
    }

    public static function download_rows( WC_Order $order ): array {
        if ( ! $order->get_id() ) { return array(); }
        $by_product = array();
        foreach ( WC_Data_Store::load( 'customer-download' )->get_downloads( array( 'order_id' => $order->get_id() ) ) as $permission ) {
            $by_product[ $permission->get_product_id() ][ $permission->get_download_id() ][] = $permission;
        }
        $rows = array();
        $seen = array();
        foreach ( $order->get_items() as $item ) {
            $id = $item->get_variation_id() ?: $item->get_product_id();
            if ( isset( $seen[ $id ] ) ) { continue; }
            $seen[ $id ] = true;
            $product = $item->get_product();
            $permissions = $by_product[ $id ] ?? array();
            if ( ! $permissions && ( ! $product || ! $product->is_downloadable() ) ) { continue; }
            $files = $product && $product->is_downloadable() ? $product->get_downloads() : array();
            if ( ! $files ) {
                $rows[] = array( 'name' => $item->get_name(), 'checks' => array( __( 'The product has no configured download files.', 'tools-for-woocommerce' ) ) );
            }
            foreach ( $files as $file_id => $file ) {
                $checks = array();
                $exists = self::file_status( $file );
                if ( false === $exists ) {
                    $checks[] = __( 'Download file missing.', 'tools-for-woocommerce' );
                } elseif ( null === $exists ) {
                    $checks[] = __( 'File reference configured. Reachability has not been checked.', 'tools-for-woocommerce' );
                }
                $matching = $permissions[ $file_id ] ?? array();
                $states = array();
                foreach ( $matching as $permission ) {
                    $expires = $permission->get_access_expires();
                    $left = $permission->get_downloads_remaining();
                    $state = match ( true ) {
                        $permission->get_order_key() !== $order->get_order_key() => __( 'Permission refers to a different order key.', 'tools-for-woocommerce' ),
                        0 !== strcasecmp( $permission->get_user_email(), $order->get_billing_email() ) => __( 'Permission email differs from the order email.', 'tools-for-woocommerce' ),
                        $expires && $expires->getTimestamp() < strtotime( 'midnight', time() ) => __( 'Permission expired.', 'tools-for-woocommerce' ),
                        '' !== $left && (int) $left <= 0 => __( 'Download limit reached.', 'tools-for-woocommerce' ),
                        default => '',
                    };
                    $states[] = $state;
                }
                if ( ! $states ) {
                    $checks[] = __( 'No permission for this file.', 'tools-for-woocommerce' );
                } elseif ( ! in_array( '', $states, true ) ) {
                    $checks = array_merge( $checks, array_unique( $states ) );
                } elseif ( ! $checks ) {
                    $checks[] = __( 'File and permission configured.', 'tools-for-woocommerce' );
                }
                $rows[] = array( 'name' => $item->get_name() . ' · ' . $file->get_name(), 'checks' => $checks );
            }
            if ( array_diff_key( $permissions, $files ) ) {
                $rows[] = array( 'name' => $item->get_name(), 'checks' => array( __( 'Some permissions refer to files no longer assigned to this product.', 'tools-for-woocommerce' ) ) );
            }
        }
        return $rows;
    }

    private static function file_status( WC_Product_Download $file ): ?bool {
        $path = trim( $file->get_file() );
        if ( '' === $path ) { return false; }
        $type = $file->get_type_of_file_path();
        if ( 'relative' === $type ) { return $file->file_exists(); }
        if ( 'absolute' !== $type ) { return null; }

        // Verify URLs within this site's uploads directory without making an HTTP request.
        $uploads = wp_get_upload_dir();
        $base = wp_parse_url( $uploads['baseurl'] );
        $url = wp_parse_url( $path );
        $prefix = trailingslashit( $base['path'] ?? '' );
        if ( empty( $base['host'] ) || ( $url['host'] ?? null ) !== $base['host']
            || ( $url['port'] ?? null ) !== ( $base['port'] ?? null ) || ! str_starts_with( $url['path'] ?? '', $prefix ) ) { return null; }
        $relative = rawurldecode( substr( $url['path'], strlen( $prefix ) ) );
        if ( '' === $relative || str_contains( $relative, chr( 0 ) ) || in_array( '..', explode( '/', str_replace( '\\', '/', $relative ) ), true ) ) { return null; }
        $root = realpath( $uploads['basedir'] );
        if ( false === $root ) { return null; }
        $candidate = realpath( trailingslashit( $root ) . $relative );
        if ( false === $candidate ) { return false; }
        return str_starts_with( $candidate, trailingslashit( $root ) ) ? is_file( $candidate ) : null;
    }

    public static function product_check( WC_Product $product, int $quantity, array $settings ): array {
        if ( $product->is_type( 'variable' ) ) {
            return array( __( 'Select a specific variation to check its price, stock and download settings.', 'tools-for-woocommerce' ) );
        }
        $checks = array();
        if ( ! empty( $settings['catalog_enabled'] ) ) { $checks[] = __( 'Catalog mode blocks purchases.', 'tools-for-woocommerce' ); }
        if ( '' === $product->get_price() ) { $checks[] = __( 'This product has no price.', 'tools-for-woocommerce' ); }
        if ( 'publish' !== $product->get_status() || ! $product->is_purchasable() || ! $product->is_in_stock() || ! $product->has_enough_stock( $quantity ) ) {
            $checks[] = __( 'This product is not currently available for the selected quantity.', 'tools-for-woocommerce' );
        }
        if ( $product->is_sold_individually() && $quantity > 1 ) {
            $checks[] = __( 'WooCommerce limits this product to one unit.', 'tools-for-woocommerce' );
        }
        if ( DDW_TWC_Commerce::free() && ( $error = DDW_TWC_Commerce::product_error( $product ) ) ) { $checks[] = $error; }
        return array_merge( $checks, DDW_TWC_Cart_Rules::violations( array(
            array( 'product_id' => $product->get_id(), 'quantity' => $quantity, 'amount' => '' === $product->get_price() ? 0 : wc_get_price_excluding_tax( $product, array( 'qty' => $quantity ) ) ),
        ), $settings ) );
    }

    public static function offer_check( array $settings ): void {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $ids = isset( $_GET['tfwc_products'] ) && is_array( $_GET['tfwc_products'] ) ? wp_parse_id_list( array_filter( array_slice( wp_unslash( $_GET['tfwc_products'] ), 0, 20 ), 'is_scalar' ) ) : array();
        $quantity = isset( $_GET['tfwc_quantity'] ) && is_scalar( $_GET['tfwc_quantity'] ) ? max( 1, min( 1000000, absint( $_GET['tfwc_quantity'] ) ) ) : 1;
        echo '<section class="ddw-twc-settings-form"><fieldset><legend><h2>' . esc_html__( 'Check free offers', 'tools-for-woocommerce' ) . '</h2></legend><p>' . esc_html__( 'Checks each selected product or variation as the only item in an order, using saved settings. Shipping, discounts and checkout extensions are evaluated in a real checkout.', 'tools-for-woocommerce' ) . '</p>';
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="tools-for-woocommerce"><input type="hidden" name="tab" value="offers">';
        echo '<p><label for="tfwc-products">' . esc_html__( 'Products or variations (up to 20)', 'tools-for-woocommerce' ) . '</label><br><select id="tfwc-products" name="tfwc_products[]" class="wc-product-search ddw-twc-offer-products" multiple data-action="woocommerce_json_search_products_and_variations" data-placeholder="' . esc_attr__( 'Search by product name or SKU', 'tools-for-woocommerce' ) . '">';
        $products = array();
        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) { continue; }
            $products[] = $product;
            echo '<option selected value="' . esc_attr( $id ) . '">' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
        }
        echo '</select></p><p><label for="tfwc-quantity">' . esc_html__( 'Quantity to check', 'tools-for-woocommerce' ) . '</label> <input id="tfwc-quantity" name="tfwc_quantity" type="number" min="1" max="1000000" value="' . esc_attr( $quantity ) . '"></p>';
        submit_button( __( 'Check products', 'tools-for-woocommerce' ), 'secondary', 'submit', false );
        echo '</form>';
        if ( $products ) {
            echo '<ul>';
            foreach ( $products as $product ) {
                $checks = self::product_check( $product, $quantity, $settings );
                echo '<li><strong>' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</strong><br>' . esc_html( $checks ? implode( ' · ', $checks ) : __( 'Passes the product and quantity checks.', 'tools-for-woocommerce' ) ) . '</li>';
            }
            echo '</ul>';
        }
        echo '</fieldset></section>';
    }

    public static function pack_action( array $actions ): array {
        if ( current_user_can( 'edit_shop_orders' ) ) { $actions[ self::PACK ] = __( 'Print packing list', 'tools-for-woocommerce' ); }
        return $actions;
    }

    public static function pack_redirect( string $redirect, string $action, array $ids ): string {
        if ( self::PACK !== $action || ! $ids || ! current_user_can( 'edit_shop_orders' ) ) { return $redirect; }
        return add_query_arg( array( 'action' => self::PACK, 'orders' => implode( ',', array_slice( wp_parse_id_list( $ids ), 0, self::LIMIT + 1 ) ), '_wpnonce' => wp_create_nonce( self::PACK ) ), admin_url( 'admin-post.php' ) );
    }

    public static function packing_rows( array $orders ): array {
        $rows = array();
        foreach ( $orders as $order ) {
            if ( $order->has_status( array( 'cancelled', 'failed', 'refunded', 'trash', 'checkout-draft' ) ) ) { continue; }
            $refunded = array();
            foreach ( $order->get_refunds() as $refund ) {
                foreach ( $refund->get_items() as $line ) {
                    $item_id = absint( $line->get_meta( '_refunded_item_id' ) );
                    $refunded[ $item_id ] = ( $refunded[ $item_id ] ?? 0 ) + $line->get_quantity();
                }
            }
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                $quantity = max( 0, $item->get_quantity() + ( $refunded[ $item->get_id() ] ?? 0 ) );
                if ( ! $product || ! $product->needs_shipping() || ! $quantity ) { continue; }
                $id = $product->get_id();
                $rows[ $id ] ??= array( 'name' => $product->get_name(), 'sku' => $product->get_sku(), 'quantity' => 0 );
                $rows[ $id ]['quantity'] += $quantity;
            }
        }
        uasort( $rows, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
        return $rows;
    }

    public static function pack_page(): void {
        if ( ! current_user_can( 'edit_shop_orders' ) ) { wp_die( '', '', array( 'response' => 403 ) ); }
        check_admin_referer( self::PACK );
        $ids = isset( $_GET['orders'] ) && is_string( $_GET['orders'] ) ? wp_parse_id_list( substr( wp_unslash( $_GET['orders'] ), 0, 4096 ) ) : array();
        if ( count( $ids ) > self::LIMIT ) {
            wp_die( esc_html__( 'Select at most 100 orders per packing list.', 'tools-for-woocommerce' ), '', array( 'response' => 400, 'back_link' => true ) );
        }
        $orders = array();
        foreach ( $ids as $id ) {
            $order = wc_get_order( $id );
            if ( $order instanceof WC_Order && current_user_can( 'edit_shop_order', $id ) ) { $orders[] = $order; }
        }
        $title = __( 'Packing list', 'tools-for-woocommerce' );
        nocache_headers();
        echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '" dir="' . ( is_rtl() ? 'rtl' : 'ltr' ) . '"><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html( $title ) . '</title>';
        wp_enqueue_style( 'ddw-twc-packing-list', plugins_url( 'assets/packing-list.css', DDW_TWC_FILE ), array(), DDW_TWC_VERSION );
        wp_print_styles( array( 'ddw-twc-packing-list' ) );
        echo '</head><body><main><h1>' . esc_html( $title ) . '</h1>';
        echo '<p>' . esc_html__( 'Print this page using your browser. Only shippable items with remaining quantities are included. Cancelled, failed, refunded and draft orders are skipped.', 'tools-for-woocommerce' ) . '</p>';
        $rows = self::packing_rows( $orders );
        if ( ! $rows ) { echo '<p>' . esc_html__( 'No shippable items in the selected orders.', 'tools-for-woocommerce' ) . '</p>'; }
        else {
            echo '<table><thead><tr><th scope="col">' . esc_html__( 'Product', 'tools-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'SKU', 'tools-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Quantity', 'tools-for-woocommerce' ) . '</th></tr></thead><tbody>';
            foreach ( $rows as $row ) {
                echo '<tr><th scope="row">' . esc_html( $row['name'] ) . '</th><td>' . esc_html( $row['sku'] ) . '</td><td>' . esc_html( wc_format_localized_decimal( $row['quantity'] ) ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</main></body></html>';
        exit;
    }
}
