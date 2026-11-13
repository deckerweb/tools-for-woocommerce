<?php
/** Runtime rules adapted from the customer-confirmed V1.4.2. */
defined( 'ABSPATH' ) || exit;

// Die Klasse MUSS bedingt deklariert werden. Eine unbedingte Deklaration
// wird von PHP vorab registriert und würde ihre eigene Schutzprüfung auslösen.
if ( ! class_exists( 'DDW_TWC_Commerce', false ) ) {

/** Applies reversible shop cleanup and guarded free-offer checkout rules. */
final class DDW_TWC_Commerce {
    /**
     * Read one effective boolean feature switch.
     * @param string $key Settings or native option key.
     * @return bool
     */
    public static function enabled( $key ) { return ! empty( self::options()[ $key ] ); }
    /**
     * Return the configured shop administration name.
     * @return string
     */
    public static function label() { return self::options()['admin_label']; }

    /**
     * Return the configured order mode.
     * @return string
     */
    public static function mode() { return self::options()['operation_mode']; }
    /**
     * Identify a selected free-offer mode.
     * @return bool
     */
    public static function free() { return 'standard' !== self::mode(); }
    /**
     * Identify the free physical shipping variant.
     * @return bool
     */
    public static function physical() { return 'free_physical' === self::mode(); }
    /**
     * Read current settings and translate earlier configuration without a database write.
     * @return array
     */
    public static function options() { return DDW_TWC_Settings::options(); }

    /**
     * Register the native hooks owned by this module.
     * @return void
     */
    public static function register() {
        static $registered = false;
        if ( $registered ) { return; }
        $registered = true;
        $groups = array(
            'hide_marketplace_ads' => array(
                'pre_option_woocommerce_show_marketplace_suggestions' => array( __CLASS__, 'no' ),
                'woocommerce_allow_marketplace_suggestions' => '__return_false',
                'woocommerce_marketplace_suppress_promotions' => '__return_true',
            ),
            'hide_payment_ads' => array(
                'woocommerce_admin_payment_gateway_suggestion_specs' => '__return_empty_array',
                'woocommerce_admin_allowed_promo_notes' => '__return_empty_array',
            ),
            'hide_shipping_ads' => array(
                'woocommerce_allow_shipping_partner_suggestions' => '__return_false',
                'woocommerce_admin_shipping_partner_suggestions_specs' => '__return_empty_array',
            ),
            'hide_promo_notes' => array( 'woocommerce_rest_notes_object_query' => array( __CLASS__, 'notes_query' ) ),
            'hide_setup_tasks' => array(
                'woocommerce_admin_onboarding_tasks' => '__return_empty_array',
                'woocommerce_admin_experimental_onboarding_tasklists' => '__return_empty_array',
            ),
            'hide_setup_redirect' => array( 'woocommerce_prevent_automatic_wizard_redirect' => '__return_true' ),
            'hide_footer_prompt' => array( 'woocommerce_display_admin_footer_text' => '__return_false' ),
            'hide_marketplace_menu' => array( 'woocommerce_show_addons_page' => '__return_false', 'woocommerce_marketplace_menu_items' => '__return_empty_array' ),
            'hide_marketing_menu' => array( 'woocommerce_marketing_menu_items' => '__return_empty_array' ),
        );
        foreach ( $groups as $key => $hooks ) {
            if ( self::enabled( $key ) ) { foreach ( $hooks as $hook => $callback ) { add_filter( $hook, $callback, 999 ); } }
        }
        if ( is_admin() && self::enabled( 'rename_admin_texts' ) ) { add_filter( 'gettext_woocommerce', array( __CLASS__, 'admin_translation' ), 20, 3 ); }
        if ( self::enabled( 'rename_dashboard' ) ) {
            add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_titles' ), PHP_INT_MAX );
            add_action( 'wp_network_dashboard_setup', array( __CLASS__, 'dashboard_titles' ), PHP_INT_MAX );
            add_action( 'admin_head', array( __CLASS__, 'dashboard_titles' ), PHP_INT_MAX );
        }
        add_action( 'admin_menu', array( __CLASS__, 'menus' ), PHP_INT_MAX );
        add_action( 'admin_head', array( __CLASS__, 'menus' ), PHP_INT_MAX );
        add_action( 'admin_init', array( __CLASS__, 'redirect_admin' ), 20 );
        if ( self::enabled( 'hide_payments_menu' ) ) { add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'settings_tabs' ), 999 ); }
        if ( self::enabled( 'checkout_texts' ) && self::free() ) {
            add_filter( 'woocommerce_order_button_text', array( __CLASS__, 'order_button' ), 999 );
            add_filter( 'gettext_woocommerce', array( __CLASS__, 'customer_translation' ), 30, 3 );
            add_filter( 'the_title', array( __CLASS__, 'checkout_title' ), 30, 2 );
            add_filter( 'woocommerce_get_privacy_policy_text', array( __CLASS__, 'privacy_text' ), 30, 2 );
            add_filter( 'woocommerce_thankyou_order_received_text', array( __CLASS__, 'received_text' ), 30, 2 );
        }
        add_action( 'add_meta_boxes', array( __CLASS__, 'order_boxes' ), 999, 2 );
        if ( self::enabled( 'shipping_notice' ) && self::physical() ) { add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'shipping_notice' ) ); }
        if ( self::free() ) {
            if ( self::physical() ) {
                add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'copy_delivery_address' ), 900, 2 );
                add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'copy_delivery_address' ), 900 );
            }
            // Gateway classes and stored payment histories remain intact.
            add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', 999 );
            add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ), 999 );
            add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'classic_validation' ), 999, 2 );
            add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store_validation' ), 999, 2 );
            add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'classic_order' ), 999, 3 );
            add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'store_order' ), 999 );
        }
        if ( self::enabled( 'trim_checkout' ) ) { add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 999 ); }
    }

    /**
     * Return the native disabled-option string without persisting it.
     * @return string
     */
    public static function no() { return 'no'; }

    /**
     * Exclude marketing and survey note types while retaining technical notices.
     * @param array $args Existing native query or form arguments.
     * @return array
     */
    public static function notes_query( $args ) {
        // Core-Typen nach Abzug von „marketing“ und „survey“. Bei einer reinen Promo-Anfrage
        // einen unbekannten Typ verwenden: leere Typenliste würde ALLE liefern.
        $types = isset( $args['type'] ) ? (array) $args['type'] : array();
        if ( ! $types ) {
            $types = array( 'error', 'warning', 'update', 'info', 'email' );
        }
        $types = array_values( array_diff( $types, array( 'marketing', 'survey' ) ) );
        $args['type'] = $types ? $types : array( 'ddw-twc-no-note-type' );
        return $args;
    }

    /**
     * Adjust only explicitly selected menu entries, labels and icons.
     * @return void
     */
    public static function menus() {
        global $menu, $submenu;
        if ( self::enabled( 'hide_marketing_menu' ) ) { remove_menu_page( 'woocommerce-marketing' ); }
        if ( self::enabled( 'hide_marketplace_menu' ) ) {
            remove_submenu_page( 'woocommerce', 'wc-addons' );
            remove_submenu_page( 'woocommerce', 'wc-admin&path=/extensions' );
        }
        foreach ( (array) $menu as $key => $item ) {
            if ( self::enabled( 'hide_payments_menu' ) && isset( $item[2] ) && self::is_payment_settings_link( $item[2] ) ) { remove_menu_page( $item[2] ); continue; }
            if ( isset( $item[2] ) && 'woocommerce' === $item[2] ) {
                if ( self::enabled( 'rename_menu' ) ) {
                    $badge = strpos( $item[0], '<span' );
                    $menu[$key][0] = esc_html( self::label() ) . ( false === $badge ? '' : ' ' . substr( $item[0], $badge ) );
                    $menu[$key][3] = self::label();
                }
                if ( '' !== self::options()['admin_icon'] ) { $menu[$key][6] = self::options()['admin_icon']; }
            }
        }
        if ( self::enabled( 'hide_payments_menu' ) ) {
            foreach ( (array) $submenu as $parent => $items ) {
                foreach ( (array) $items as $item ) {
                    if ( isset( $item[2] ) && self::is_payment_settings_link( $item[2] ) ) { remove_submenu_page( $parent, $item[2] ); }
                }
            }
        }
    }

    /**
     * Recognize native payment entry-point URLs without matching unrelated routes.
     * @param string $link Menu URL or native menu slug.
     * @return bool
     */
    public static function is_payment_settings_link( $link ) {
        if ( ! is_string( $link ) ) { return false; }
        $link = html_entity_decode( $link, ENT_QUOTES, 'UTF-8' );
        // Auch WordPress-Menüslugs wie „wc-admin&path=/payments/connect“.
        if ( 0 === strpos( $link, 'wc-admin&' ) ) { $link = 'admin.php?page=' . $link; }
        $query = wp_parse_url( $link, PHP_URL_QUERY );
        if ( ! is_string( $query ) ) { return false; }
        parse_str( $query, $args );
        if ( isset( $args['page'], $args['tab'] ) && 'wc-settings' === $args['page'] && 'checkout' === $args['tab'] ) {
            return true;
        }
        $path = $args['path'] ?? '';
        return isset( $args['page'] ) && 'wc-admin' === $args['page'] && is_string( $path ) &&
            ( '/payments' === $path || 0 === strpos( $path, '/payments/' ) );
    }

    /**
     * Replace the shop name only in translated WooCommerce administration labels.
     * @param string $translated Current translated display text.
     * @param string $original Untranslated native source text.
     * @param string $domain Translation textdomain.
     * @return string
     */
    public static function admin_translation( $translated, $original, $domain ) {
        if ( ! is_admin() || 'woocommerce' !== $domain || ! is_string( $translated ) || ! str_contains( $translated, 'WooCommerce' ) ) { return $translated; }
        return str_replace( 'WooCommerce', self::label(), $translated );
    }

    /**
     * Rename known dashboard widget titles while retaining their callbacks.
     * @return void
     */
    public static function dashboard_titles() {
        global $wp_meta_boxes;
        $titles = array(
            'woocommerce_dashboard_status' => self::label() . ' – ' . __( 'Overview', 'tools-for-woocommerce' ),
            'woocommerce_dashboard_recent_reviews' => self::label() . ' – ' . __( 'Recent reviews', 'tools-for-woocommerce' ),
            'woocommerce_network_orders' => self::label() . ' – ' . __( 'Network orders', 'tools-for-woocommerce' ),
        );
        foreach ( array( 'dashboard', 'dashboard-network' ) as $screen ) {
            foreach ( (array) ( $wp_meta_boxes[ $screen ] ?? array() ) as $context => $priorities ) {
                foreach ( (array) $priorities as $priority => $boxes ) {
                    foreach ( $titles as $id => $title ) {
                        if ( isset( $boxes[ $id ]['title'] ) ) {
                            $wp_meta_boxes[ $screen ][ $context ][ $priority ][ $id ]['title'] = $title;
                        }
                    }
                }
            }
        }
    }

    /**
     * Supply the optional free-order button label.
     * @param string $text Existing native display text.
     * @return string
     */
    public static function order_button( $text ) {
        return self::enabled( 'checkout_texts' ) && self::free() ? __( "Order for free", 'tools-for-woocommerce' ) : $text;
    }

    /**
     * Supply optional matching classic checkout captions.
     * @param string $translated Current translated display text.
     * @param string $original Untranslated native source text.
     * @param string $domain Translation textdomain.
     * @return string
     */
    public static function customer_translation( $translated, $original, $domain ) {
        if ( is_admin() || 'woocommerce' !== $domain || ! in_array( $original, array( 'Billing details', 'Billing &amp; Shipping', 'Billing address', 'Shipping address', 'Place order' ), true ) ) {
            return $translated;
        }
        $options = self::options();
        if ( ! $options['checkout_texts'] || 'standard' === $options['operation_mode'] ) { return $translated; }
        return match ( $original ) {
            'Billing details' => __( 'Your contact details and address', 'tools-for-woocommerce' ),
            'Billing &amp; Shipping' => 'free_physical' === $options['operation_mode']
                ? __( 'Your contact details and delivery address', 'tools-for-woocommerce' )
                : __( 'Your contact details', 'tools-for-woocommerce' ),
            'Billing address' => __( 'Contact address', 'tools-for-woocommerce' ),
            'Shipping address' => __( 'Delivery address', 'tools-for-woocommerce' ),
            'Place order' => __( 'Order for free', 'tools-for-woocommerce' ),
        };
    }

    /**
     * Adjust only the configured checkout page title in a free-offer mode.
     * @param string $title Current page or widget title.
     * @param int $id Current page or order ID.
     * @return string
     */
    public static function checkout_title( $title, $id ) {
        if ( ! is_admin() && function_exists( 'is_checkout' ) && is_checkout() &&
            (int) $id === (int) wc_get_page_id( 'checkout' ) ) {
            if ( ! self::free() || ! self::enabled( 'checkout_texts' ) ) { return $title; }
            return is_order_received_page() ? __( "Thank you for your order", 'tools-for-woocommerce' ) : ( self::physical() ? __( 'Order free products', 'tools-for-woocommerce' ) : __( 'Request free downloads', 'tools-for-woocommerce' ) );
        }
        return $title;
    }

    /**
     * Supply the selected free-offer privacy explanation with the native privacy link placeholder.
     * @param string $text Existing native display text.
     * @param string $type Native input type or privacy-text context.
     * @return string
     */
    public static function privacy_text( $text, $type ) {
        if ( ! self::free() || ! self::enabled( 'checkout_texts' ) || 'checkout' !== $type || ! wc_privacy_policy_page_id() ) { return $text; }
        // Core ersetzt den Platzhalter durch den tatsächlichen Datenschutzlink.
        return self::physical() ? __( 'Your personal data will be used to process your order and ship your products. Further information is available in our [privacy_policy].', 'tools-for-woocommerce' ) : __( 'Your personal data will be used to process your order and provide the downloads. Further information is available in our [privacy_policy].', 'tools-for-woocommerce' );
    }

    /**
     * Explain a matching free order without changing its fulfillment status.
     * @param string $text Existing native display text.
     * @param WC_Order $order Checkout or displayed order object.
     * @return string
     */
    public static function received_text( $text, $order ) {
        if ( ! $order instanceof WC_Order || 0.0 !== (float) $order->get_total() ||
            ! $order->has_status( array( 'processing', 'completed' ) ) ) { return $text; }
        if ( ! self::free() || ! self::enabled( 'checkout_texts' ) ) { return $text; }
        // Do not make a delivery promise for historical orders of another product type.
        $downloads = ! self::physical();
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $product = $item->get_product();
            if ( ! $product instanceof WC_Product || ( $downloads ? ( ! $product->is_virtual() || ! $product->is_downloadable() ) : $product->is_virtual() ) ) { return $text; }
        }
        return self::physical() ? __( 'Thank you. Your free order has been received. Your products will be sent to the delivery address shown in the order details.', 'tools-for-woocommerce' ) : __( 'Thank you. Your free download order has been received. Available download links are shown in your order details.', 'tools-for-woocommerce' );
    }

    /**
     * Remove only selected order metadata boxes, preserving download-mode access.
     * @param string $screen Current order screen ID.
     * @param mixed $object Native object supplied by the hook.
     * @return void
     */
    public static function order_boxes( $screen, $object ) {
        $hpos = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'woocommerce_page_wc-orders';
        if ( ! in_array( $screen, array( 'shop_order', $hpos ), true ) ) { return; }
        if ( self::enabled( 'hide_downloads_box' ) && 'free_downloads' !== self::mode() ) { remove_meta_box( 'woocommerce-order-downloads', $screen, 'normal' ); }
        if ( self::enabled( 'hide_attribution_box' ) ) { remove_meta_box( 'woocommerce-order-source-data', $screen, 'side' ); }
        if ( self::enabled( 'hide_customer_history_box' ) ) { remove_meta_box( 'woocommerce-customer-history', $screen, 'side' ); }
    }

    /**
     * Copy a complete billing address only to a new empty physical shipping address.
     * @param WC_Order $order Checkout or displayed order object.
     * @param array $data Submitted checkout data.
     * @return void
     */
    public static function copy_delivery_address( $order, $data = array() ) {
        if ( ! self::physical() || ! $order instanceof WC_Order || 0.0 !== (float) $order->get_total() ||
            ! $order->needs_shipping_address() ) { return; }
        // Vorhandene oder ausdrücklich abweichende Lieferadressen nie ersetzen.
        if ( ! empty( $data['ship_to_different_address'] ) ) { return; }
        $shipping = $order->get_address( 'shipping' );
        foreach ( array( 'first_name', 'last_name', 'company', 'phone', 'address_1', 'address_2', 'city', 'postcode', 'state', 'country' ) as $key ) {
            if ( ! empty( $shipping[ $key ] ) ) { return; }
        }
        $billing = $order->get_address( 'billing' );
        if ( empty( $billing['address_1'] ) || empty( $billing['city'] ) || empty( $billing['country'] ) ) { return; }
        $delivery = array_intersect_key( $billing, array_flip( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'state', 'country' ) ) );
        $order->set_address( $delivery, 'shipping' );
        // Core speichert im Checkout; keine historische Bestellung verändern.
    }

    /**
     * Explain pending physical fulfillment and missing shipping data.
     * @param WC_Order $order Checkout or displayed order object.
     * @return void
     */
    public static function shipping_notice( $order ) {
        if ( ! self::physical() || ! $order instanceof WC_Order || 0.0 !== (float) $order->get_total() ) { return; }
        $issues = array();
        if ( ! $order->get_items( 'shipping' ) ) { $issues[] = __( "No shipping line saved. Please check the product type and shipping configuration.", 'tools-for-woocommerce' ); }
        if ( ! $order->get_shipping_address_1() ) { $issues[] = __( "No delivery address saved. Check and complete the address in this order before shipping.", 'tools-for-woocommerce' ); }
        echo '<p><strong>' . esc_html( self::label() ) . ':</strong> ' . esc_html__( 'Free order. Processing means shipping is pending. Mark completed after dispatch.', 'tools-for-woocommerce' ) . '</p>';
        if ( $issues ) { echo '<p class="description"><strong>' . esc_html__( 'Check shipping:', 'tools-for-woocommerce' ) . '</strong> ' . esc_html( implode( ' ', $issues ) ) . '</p>'; }
    }

    /**
     * Remove the payment settings tab only when its interface switch is selected.
     * @param array $tabs Registered native settings tabs.
     * @return array
     */
    public static function settings_tabs( $tabs ) {
        unset( $tabs['checkout'] ); // Interner Schlüssel des Zahlungs-Tabs.
        return $tabs;
    }

    /**
     * Remove the saved payment-methods entry for free-offer mode.
     * @param array $items Existing native account menu entries.
     * @return array
     */
    public static function account_menu( $items ) {
        unset( $items['payment-methods'] );
        return $items;
    }

    /**
     * Recognize protected customer-management routes.
     * @param string $path Requested native administration route.
     * @return bool
     */
    public static function is_customer_route( $path ) {
        if ( ! is_string( $path ) ) { return false; }
        return '/customers' === $path || 0 === strpos( $path, '/customers/' ) ||
            '/analytics/customers' === $path || 0 === strpos( $path, '/analytics/customers/' );
    }

    /**
     * Redirect only explicitly hidden admin GET entry points.
     * @return void
     */
    public static function redirect_admin() {
        // Ausschließlich UI-GETs. Keine POSTs, AJAX, REST oder CLI umleiten.
        if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
            ( defined( 'WP_CLI' ) && WP_CLI ) ||
            ! current_user_can( 'manage_woocommerce' ) ||
            'get' !== sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            return;
        }
        $page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $path = isset( $_GET['path'] ) && is_string( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';
        $tab  = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        $hidden = ( self::enabled( 'hide_marketplace_menu' ) && 'wc-addons' === $page ) || ( self::enabled( 'hide_marketing_menu' ) && 'woocommerce-marketing' === $page );
        if ( 'wc-admin' === $page ) {
            // Leeren App-Einstieg nicht sperren: React-Seiten wie Kunden
            // brauchen die gemeinsam registrierte wc-admin-Seite.
            if ( self::is_customer_route( $path ) ) { return; }
            $roots = array();
            if ( self::enabled( 'hide_marketplace_menu' ) ) { $roots[] = '/extensions'; }
            if ( self::enabled( 'hide_marketing_menu' ) ) { $roots[] = '/marketing'; }
            if ( self::enabled( 'hide_payments_menu' ) ) { $roots[] = '/payments'; }
            foreach ( $roots as $root ) {
                if ( $path === $root || 0 === strpos( $path, $root . '/' ) ) { $hidden = true; }
            }
        }
        if ( self::enabled( 'hide_payments_menu' ) && 'wc-settings' === $page && 'checkout' === $tab ) { $hidden = true; }
        if ( $hidden ) {
            wp_safe_redirect( admin_url( 'edit.php?post_type=product' ) );
            exit;
        }
    }

    /**
     * Check a product price and type against the selected free-offer mode.
     * @param WC_Product $product Native product or variation.
     * @return string
     */
    public static function product_error( $product ) {
        if ( ! $product instanceof WC_Product || '' === $product->get_price() || 0.0 !== (float) $product->get_price() ) {
            return __( 'Only products with an explicit price of zero may be ordered in this mode.', 'tools-for-woocommerce' );
        }
        if ( self::physical() ) {
            if ( $product->is_virtual() || $product->is_downloadable() ) { return __( 'This mode accepts only free physical products without downloads.', 'tools-for-woocommerce' ); }
        } elseif ( ! $product->is_virtual() || ! $product->is_downloadable() || ! $product->get_downloads() ) {
            return __( 'This mode accepts only free virtual downloads with configured files.', 'tools-for-woocommerce' );
        }
        return '';
    }
    /**
     * Validate free products, fees, shipping and the cart total without changing costs.
     * @param WC_Cart $cart Native cart object.
     * @return string
     */
    private static function cart_error( $cart ) {
        if ( ! self::free() || ! $cart instanceof WC_Cart || $cart->is_empty() ) { return ''; }
        foreach ( $cart->get_cart() as $item ) {
            $message = self::product_error( $item['data'] ?? null );
            if ( $message ) { return $message; }
        }
        if ( self::physical() && ! $cart->needs_shipping() ) { return __( 'Shipping is not configured correctly. Please contact us.', 'tools-for-woocommerce' ); }
        foreach ( $cart->get_fees() as $fee ) {
            if ( 0.0 !== (float) $fee->amount ) { return __( 'Free orders must not contain nonzero fees.', 'tools-for-woocommerce' ); }
        }
        if ( 0.0 !== (float) $cart->get_shipping_total() || 0.0 !== (float) $cart->get_total( 'edit' ) ) {
            return __( 'This order contains unexpected costs. Please contact us; payment is not available here.', 'tools-for-woocommerce' );
        }
        return '';
    }

    /**
     * Append free-offer errors to classic checkout validation.
     * @param array $data Submitted checkout data.
     * @param WP_Error $errors Shared native validation errors.
     * @return void
     */
    public static function classic_validation( $data, $errors ) {
        $message = function_exists( 'WC' ) ? self::cart_error( WC()->cart ) : '';
        if ( $message ) { $errors->add( 'ddw_twc_free_order_cart', $message ); }
    }

    /**
     * Append free-offer errors to Store API cart validation.
     * @param WP_Error $errors Shared native validation errors.
     * @param WC_Cart $cart Native cart object.
     * @return void
     */
    public static function store_validation( $errors, $cart ) {
        $message = self::cart_error( $cart );
        if ( $message ) { $errors->add( 'ddw_twc_free_order_cart', $message ); }
    }

    /**
     * Check final free-order items and totals before payment processing.
     * @param WC_Order $order Checkout or displayed order object.
     * @return string
     */
    private static function order_error( $order ) {
        if ( ! self::free() || ! $order instanceof WC_Order ) { return ''; }
        if ( 0.0 !== (float) $order->get_total() ) { return __( 'The order contains unexpected costs. Please contact us.', 'tools-for-woocommerce' ); }
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $message = self::product_error( $item->get_product() );
            if ( $message ) { return $message; }
            if ( 0.0 !== (float) $item->get_total() || 0.0 !== (float) $item->get_total_tax() ) { return __( 'The order contains unexpected costs. Please contact us.', 'tools-for-woocommerce' ); }
        }
        foreach ( array( 'fee', 'shipping' ) as $type ) {
            foreach ( $order->get_items( $type ) as $item ) {
                if ( 0.0 !== (float) $item->get_total() || 0.0 !== (float) $item->get_total_tax() ) { return __( 'The order contains unexpected costs. Please contact us.', 'tools-for-woocommerce' ); }
            }
        }
        if ( self::physical() && ! $order->get_items( 'shipping' ) ) { return __( 'No shipping method was saved. Please contact us; free shipping must be configured.', 'tools-for-woocommerce' ); }
        if ( self::physical() && ! $order->get_shipping_address_1() ) { return __( 'The delivery address is missing. Please check the address or contact us.', 'tools-for-woocommerce' ); }
        return '';
    }

    /**
     * Reject invalid final classic checkout orders.
     * @param int $id Current page or order ID.
     * @param array $data Submitted checkout data.
     * @param WC_Order $order Checkout or displayed order object.
     * @return void
     */
    public static function classic_order( $id, $data, $order ) {
        $message = self::order_error( $order );
        if ( $message ) { throw new Exception( esc_html( $message ) ); }
    }

    /**
     * Reject invalid final Store API orders with a native route error.
     * @param WC_Order $order Checkout or displayed order object.
     * @return void
     */
    public static function store_order( $order ) {
        $message = self::order_error( $order );
        if ( $message ) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'ddw_twc_free_order_invalid', esc_html( $message ), 400 );
        }
    }

    /**
     * Remove only the selected classic checkout company field while retaining phone and addresses.
     * @param mixed $fields Native value supplied by the caller.
     * @return array
     */
    public static function checkout_fields( $fields ) {
        // OPTIONAL, nur klassischer Shortcode-Checkout. E-Mail, Namen und
        // sämtliche Lieferadressfelder bleiben. Land/PLZ für Versand erhalten.
        unset( $fields['billing']['billing_company'] );
        // Telefon für Rückfragen erhalten; vorhandene Feldkonfiguration beibehalten.
        return $fields;
    }
}



} // Ende der bedingten Klassendeklaration und Registrierung.
