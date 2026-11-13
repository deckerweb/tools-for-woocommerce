<?php
/** Quantity and merchandise-value limits for storefront orders. */
defined( 'ABSPATH' ) || exit;

/** Evaluates complete carts and repeats validation before order processing. */
final class DDW_TWC_Cart_Rules {
    /** Register native classic checkout and Store API hooks. @return void */
    public static function register(): void {
        if ( ! DDW_TWC_Settings::options()['rules_enabled'] ) { return; }
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'add' ), 1000, 6 );
        add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'update' ), 1000, 4 );
        add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'cart_notices' ), 1000 );
        add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'classic' ), 1000, 2 );
        add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store' ), 1000, 2 );
        add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'order' ), 1000 );
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'processed' ), 1000, 3 );
        add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'store_order' ), 1000 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'store_order' ), 1000 );
        add_filter( 'woocommerce_store_api_product_quantity_maximum', array( __CLASS__, 'store_maximum' ), 1000, 3 );
        add_filter( 'woocommerce_quantity_input_args', array( __CLASS__, 'quantity_args' ), 1000, 2 );
    }
    /**
     * Evaluate a cart snapshot without changing quantities or stored data.
     * @param array $items Rows with product_id, quantity and amount (after discounts, excluding tax).
     * @param array $settings Complete site settings.
     * @param bool $minimums Whether minimum quantity/value requirements apply.
     * @return string[] Unique localized violation messages.
     */
    public static function violations( array $items, array $settings, bool $minimums = true ): array {
        if ( ! $settings['rules_enabled'] || ! $items ) { return array(); }
        $errors = array(); $total = 0; $amount = 0.0; $groups = array();
        foreach ( $items as $index => $item ) {
            $quantity = (float) ( $item['quantity'] ?? 0 );
            if ( ! is_finite( $quantity ) || $quantity <= 0 || floor( $quantity ) !== $quantity ) {
                $errors[] = __( 'Please choose positive whole-number quantities.', 'tools-for-woocommerce' ); continue;
            }
            $total += $quantity;
            $amount += (float) ( $item['amount'] ?? 0 );
            $id = (string) ( $item['product_id'] ?? $index );
            $groups[$id] = ( $groups[$id] ?? 0 ) + $quantity;
        }
        if ( $settings['rules_single'] ) {
            if ( $total !== 1.0 && $total !== 1 ) { $errors[] = __( 'Only one item in total can be ordered. Choose one product with quantity one.', 'tools-for-woocommerce' ); }
            return array_values( array_unique( $errors ) );
        }
        $pairs = array( 'rules_min_quantity' => true, 'rules_max_quantity' => false );
        foreach ( $pairs as $key => $min ) {
            $limit = $settings[$key];
            if ( $limit > 0 && ( $min ? ( $minimums && $total < $limit ) : $total > $limit ) ) {
                /* translators: %d: total number of units. */
                $errors[] = sprintf( $min ? __( 'Order at least %d items in total.', 'tools-for-woocommerce' ) : __( 'Order no more than %d items in total.', 'tools-for-woocommerce' ), $limit );
            }
        }
        foreach ( $groups as $quantity ) {
            if ( $minimums && $settings['rules_min_line'] > $quantity ) {
                /* translators: %d: minimum units per product or variation. */
                $errors[] = sprintf( __( 'Choose at least %d units of each selected product or variation.', 'tools-for-woocommerce' ), $settings['rules_min_line'] );
            }
            if ( $settings['rules_max_line'] > 0 && $quantity > $settings['rules_max_line'] ) {
                /* translators: %d: maximum units per product or variation. */
                $errors[] = sprintf( __( 'Choose no more than %d units of each product or variation.', 'tools-for-woocommerce' ), $settings['rules_max_line'] );
            }
            if ( fmod( $quantity, (float) $settings['rules_step'] ) !== 0.0 ) {
                /* translators: %d: quantity increment. */
                $errors[] = sprintf( __( 'Choose product quantities in multiples of %d.', 'tools-for-woocommerce' ), $settings['rules_step'] );
            }
        }
        $decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
        $amount = round( $amount, $decimals );
        foreach ( array( 'rules_min_amount' => true, 'rules_max_amount' => false ) as $key => $min ) {
            $limit = (float) $settings[$key];
            if ( $limit > 0 && ( $min ? ( $minimums && $amount < $limit ) : $amount > $limit ) ) {
                /* translators: %s: amount in the current shop currency. */
                $errors[] = sprintf( $min ? __( 'The minimum product value after discounts is %s, excluding tax, shipping and fees.', 'tools-for-woocommerce' ) : __( 'The maximum product value after discounts is %s, excluding tax, shipping and fees.', 'tools-for-woocommerce' ), wp_strip_all_tags( wc_price( $limit ) ) );
            }
        }
        return array_values( array_unique( $errors ) );
    }
    /**
     * Normalize native cart rows, retaining variation identity across split rows.
     * @param WC_Cart $cart Native cart.
     * @return array Normalized rows keyed by cart item key.
     */
    public static function rows( $cart ): array {
        $rows = array();
        foreach ( $cart->get_cart() as $key => $item ) {
            $rows[$key] = array( 'product_id' => ! empty( $item['variation_id'] ) ? $item['variation_id'] : ( $item['product_id'] ?? $key ), 'quantity' => $item['quantity'], 'amount' => $item['line_total'] ?? 0 );
        }
        return $rows;
    }
    /**
     * Reject additions that would violate upper limits or quantity increments.
     * @param bool $passed Previous result.
     * @param int $product_id Parent or simple product ID.
     * @param float $quantity Requested quantity.
     * @param int $variation_id Optional variation ID.
     * @param array $variations Selected variation attributes.
     * @param array $data Custom item data.
     * @return bool Whether addition remains allowed.
     */
    public static function add( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $data = array() ) {
        if ( ! $passed || ! WC()->cart ) { return $passed; }
        $rows = self::rows( WC()->cart );
        $product = wc_get_product( $variation_id ?: $product_id );
        $rows[] = array( 'product_id' => $variation_id ?: $product_id, 'quantity' => $quantity, 'amount' => $product ? (float) $product->get_price() * (float) $quantity : 0 );
        $settings = DDW_TWC_Settings::options();
        // Native discounts are recalculated after mutation; value limits use final totals.
        $settings['rules_max_amount'] = '0';
        $errors = self::violations( $rows, $settings, false );
        foreach ( $errors as $message ) { self::notice( $message ); }
        return ! $errors;
    }
    /**
     * Check projected quantities without mutating the native cart.
     * @param bool $passed Previous result.
     * @param string $key Cart item key.
     * @param array $values Existing row.
     * @param float $quantity New row quantity; zero removes it.
     * @return bool Whether the update remains allowed.
     */
    public static function update( $passed, $key, $values, $quantity ) {
        if ( ! $passed || ! WC()->cart || 0.0 === (float) $quantity ) { return $passed; }
        $rows = self::rows( WC()->cart );
        if ( isset( $rows[$key] ) ) {
            $old = max( 1, (float) $rows[$key]['quantity'] );
            $rows[$key]['amount'] = $rows[$key]['amount'] / $old * $quantity;
            $rows[$key]['quantity'] = $quantity;
        }
        $settings = DDW_TWC_Settings::options();
        // Native discounts are recalculated after mutation; value limits use final totals.
        $settings['rules_max_amount'] = '0';
        $errors = self::violations( $rows, $settings, false );
        foreach ( $errors as $message ) { self::notice( $message ); }
        return ! $errors;
    }
    /**
     * Add one escaped-by-WooCommerce error notice, without duplicates.
     * @param string $message Plain localized message.
     * @return void
     */
    private static function notice( string $message ): void { if ( ! wc_has_notice( $message, 'error' ) ) { wc_add_notice( $message, 'error' ); } }
    /** Check current cart for native cart-screen notices. @return void */
    public static function cart_notices(): void {
        if ( ! WC()->cart ) { return; }
        foreach ( self::violations( self::rows( WC()->cart ), DDW_TWC_Settings::options() ) as $message ) { self::notice( $message ); }
    }
    /**
     * Append checkout errors to the classic validation result.
     * @param array $data Submitted checkout data.
     * @param WP_Error $errors Shared errors.
     * @return void
     */
    public static function classic( $data, $errors ): void { if ( WC()->cart ) { self::store( $errors, WC()->cart ); } }
    /**
     * Append rule violations to Store API cart validation.
     * @param WP_Error $errors Shared errors.
     * @param WC_Cart $cart Native cart.
     * @return void
     */
    public static function store( $errors, $cart ): void {
        foreach ( self::violations( self::rows( $cart ), DDW_TWC_Settings::options() ) as $i => $message ) { $errors->add( 'ddw_twc_cart_rule_' . $i, $message ); }
    }
    /**
     * Enforce the same rules on actual checkout order items before saving.
     * @param WC_Order $order New checkout order.
     * @return void Throws on invalid orders; admin imports are not globally restricted.
     */
    public static function order( $order ): void {
        $items = array();
        foreach ( $order->get_items( 'line_item' ) as $item ) { $items[] = array( 'product_id' => $item->get_variation_id() ?: $item->get_product_id(), 'quantity' => $item->get_quantity(), 'amount' => $item->get_total() ); }
        if ( ! $items && DDW_TWC_Settings::options()['rules_enabled'] ) { throw new Exception( esc_html__( 'An order must contain at least one item.', 'tools-for-woocommerce' ) ); }
        $errors = self::violations( $items, DDW_TWC_Settings::options() );
        if ( $errors ) { throw new Exception( esc_html( implode( ' ', $errors ) ) ); }
    }
    /**
     * Recheck the completed classic checkout order object before payment.
     * @param int $id Order ID.
     * @param array $data Checkout data.
     * @param WC_Order $order Order object.
     * @return void
     */
    public static function processed( $id, $data, $order ): void { self::order( $order ); }
    /**
     * Convert final order validation to a native Store API failure.
     * @param WC_Order $order Checkout order.
     * @return void Throws a route exception on violation.
     */
    public static function store_order( $order ): void {
        try { self::order( $order ); } catch ( Exception $error ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'ddw_twc_cart_rule', esc_html( $error->getMessage() ), 400 ); }
    }
    /**
     * Bound native Store API quantity updates; checkout validation still checks the whole cart.
     * @param float $maximum Native stock/purchase limit.
     * @param WC_Product $product Current product.
     * @param array|null $cart_item Native row when updating, otherwise null.
     * @return float Effective maximum, retaining stricter native limits.
     */
    public static function store_maximum( $maximum, $product, $cart_item = null ) {
        $s = DDW_TWC_Settings::options();
        $limit = $s['rules_single'] ? 1 : $s['rules_max_line'];
        $current_key = is_array( $cart_item ) ? ( $cart_item['key'] ?? '' ) : '';
        $other_total = 0; $other_product = 0;
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $key => $row ) {
                if ( $current_key === $key ) { continue; }
                $quantity = (float) $row['quantity']; $other_total += $quantity;
                $id = ! empty( $row['variation_id'] ) ? $row['variation_id'] : $row['product_id'];
                if ( (int) $id === $product->get_id() ) { $other_product += $quantity; }
            }
        }
        if ( $limit > 0 ) { $limit = max( 0, $limit - $other_product ); }
        $cart_limit = $s['rules_single'] ? 1 : $s['rules_max_quantity'];
        if ( $cart_limit > 0 ) {
            $remaining = max( 0, $cart_limit - $other_total );
            $limit = $s['rules_single'] || $s['rules_max_line'] > 0 ? min( $limit, $remaining ) : $remaining;
        }
        return ( $s['rules_single'] || $s['rules_max_line'] > 0 || $cart_limit > 0 ) ? min( $maximum, $limit ) : $maximum;
    }
    /**
     * Advertise compatible product input constraints without overriding stricter stock limits.
     * @param array $args Native input arguments.
     * @param WC_Product $product Product being displayed.
     * @return array Updated arguments.
     */
    public static function quantity_args( $args, $product ): array {
        $s = DDW_TWC_Settings::options();
        $max = $s['rules_single'] ? 1 : $s['rules_max_line'];
        if ( $s['rules_max_quantity'] > 0 ) { $max = $max > 0 ? min( $max, $s['rules_max_quantity'] ) : $s['rules_max_quantity']; }
        if ( $max > 0 ) { $args['max_value'] = $args['max_value'] > 0 ? min( $args['max_value'], $max ) : $max; }
        if ( $s['rules_single'] ) { $args['min_value'] = 1; $args['step'] = 1; }
        return $args;
    }
}
