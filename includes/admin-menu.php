<?php
/** Rearranges display entries after WordPress has resolved admin pages and permissions. */
defined( 'ABSPATH' ) || exit;

final class DDW_TWC_Admin_Menu {
    private const CORE = 'woocommerce';
    private const MANAGEMENT = 'tfwc-woocommerce-management';
    private const PRODUCTS = 'edit.php?post_type=product';
    private const ANALYTICS = 'wc-admin&path=/analytics/overview';
    private const REPORTS = array( 'overview', 'products', 'revenue', 'orders', 'variations', 'categories', 'coupons', 'taxes', 'downloads', 'stock', 'settings' );
    private static array $display = array();
    private static array $reports = array();

    public static function register(): void {
        add_filter( 'parent_file', array( self::class, 'parent' ), PHP_INT_MAX );
        add_action( 'in_admin_header', array( self::class, 'report_navigation' ), 20 );
        add_filter( 'submenu_file', array( self::class, 'child' ), PHP_INT_MAX, 2 );
    }

    /** Work with actual menu entries and keep their registered destinations intact. */
    public static function split( array $menu, array $submenu ): array {
        $parents = array_column( $menu, null, 2 );
        $root = $parents[ self::CORE ] ?? null;
        if ( ! $root || ! current_user_can( $root[1] ) ) { return array( $menu, $submenu, array(), array() ); }

        $managed_parents = array( self::CORE, self::PRODUCTS, self::ANALYTICS, 'woocommerce-marketing', 'wc-reports' );
        foreach ( $parents as $slug => $row ) {
            if ( self::payment( $slug ) || self::subscription( $slug ) ) { $managed_parents[] = $slug; }
        }
        $items = array();
        $remove = array();
        foreach ( array_unique( $managed_parents ) as $parent ) {
            if ( ! isset( $parents[ $parent ] ) || ! current_user_can( $parents[ $parent ][1] ) ) { continue; }
            $children = $submenu[ $parent ] ?? array();
            if ( self::CORE !== $parent && ( ! $children || ! in_array( $parent, array_column( $children, 2 ), true ) ) ) {
                array_unshift( $children, $parents[ $parent ] );
            }
            if ( self::CORE !== $parent ) { $remove[] = $parent; }
            foreach ( $children as $row ) {
                if ( ! current_user_can( $row[1] ) ) { continue; }
                $url = self::url( $row[2], $parent );
                if ( DDW_TWC_Commerce::enabled( 'hide_payments_menu' ) && self::payment( $url ) ) { continue; }
                $key = self::key( $url );
                $row[2] = $url;
                $items[ $key ] ??= $row;
            }
        }
        $hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        $active_orders = self::key( $hpos ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' );
        if ( isset( $items[ $active_orders ] ) ) {
            unset( $items[ self::key( $hpos ? 'edit.php?post_type=shop_order' : 'admin.php?page=wc-orders' ) ] );
        }

        if ( isset( $items[ self::key( 'admin.php?page=wc-admin' ) ] ) || isset( $items[ self::key( 'admin.php?page=wc-admin&path=/' ) ] ) ) {
            unset( $items[ self::key( 'admin.php?page=woocommerce' ) ] );
        }

        $core = $management = $reports = array();
        $overview = self::key( self::url( self::ANALYTICS ) );
        $has_overview = isset( $items[ $overview ] );
        foreach ( $items as $key => $row ) {
            $rank = self::rank( $row[2] );
            if ( $has_overview && self::report_path( $row[2] ) && 2 === count( self::route( $row[2] )[1] ) ) {
                if ( $key === $overview ) {
                    $row[0] = $parents[ self::ANALYTICS ][0] ?? esc_html__( 'Analytics', 'woocommerce' );
                }
                $reports[] = $row;
                if ( $key !== $overview ) { continue; }
            }
            if ( $rank < 100 ) {
                $core[] = array( $rank, $row );
                continue;
            }
            list( , $args ) = self::route( $row[2] );
            $page = $args['page'] ?? '';
            if ( 'wc-settings' === $page && 100 === $rank ) {
                $row[0] = esc_html__( 'Shop settings', 'tools-for-woocommerce' );
            } elseif ( 'wc-reports' === $page ) {
                $row[0] = esc_html__( 'Classic reports', 'tools-for-woocommerce' );
            } elseif ( 'wc-admin' === $page && '/analytics/settings' === ( $args['path'] ?? '' ) ) {
                $row[0] = esc_html__( 'Analytics settings', 'tools-for-woocommerce' );
            }
            $management[] = array( $rank, $row );
        }
        if ( ! $core ) { return array( $menu, $submenu, array(), array() ); }
        $sort = static function ( array $rows ): array {
            usort( $rows, static fn( $a, $b ) => $a[0] <=> $b[0] );
            return array_column( $rows, 1 );
        };
        $submenu[ self::CORE ] = $sort( $core );
        $display = array( self::CORE => $submenu[ self::CORE ] );
        $new_menu = array();
        foreach ( $menu as $row ) {
            if ( in_array( $row[2], $remove, true ) ) { unset( $submenu[ $row[2] ] ); continue; }
            $new_menu[] = $row;
            if ( self::CORE === $row[2] && $management ) {
                $name = DDW_TWC_Commerce::enabled( 'rename_menu' ) ? DDW_TWC_Commerce::label() : 'WooCommerce';
                /* translators: %s: WooCommerce menu name. */
                $label = sprintf( __( '%s Management', 'tools-for-woocommerce' ), $name );
                $new_menu[] = array( esc_html( $label ), $root[1], self::MANAGEMENT, $label, 'menu-top', 'toplevel_page_' . self::MANAGEMENT, 'dashicons-admin-generic' );
                $submenu[ self::MANAGEMENT ] = $sort( $management );
                $display[ self::MANAGEMENT ] = $submenu[ self::MANAGEMENT ];
            }
        }
        return array( $new_menu, $submenu, $display, $reports );
    }

    private static function url( string $slug, string $parent = self::CORE ): string {
        $slug = html_entity_decode( $slug, ENT_QUOTES, 'UTF-8' );
        if ( preg_match( '~^(?:[a-z][a-z0-9+.-]*:|/)|^[^?]+\.php(?:\?|$)~i', $slug ) ) { return $slug; }
        $base = str_starts_with( $parent, 'edit.php?' ) ? $parent . '&' : 'admin.php?';
        return $base . 'page=' . $slug;
    }

    private static function route( string $url ): array {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        $query = wp_parse_url( $url, PHP_URL_QUERY );
        parse_str( is_string( $query ) ? $query : '', $args );
        ksort( $args );
        return array( basename( (string) $path ), $args );
    }

    private static function key( string $url ): string {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        return wp_json_encode( array( $host, self::route( $url ) ) );
    }

    private static function payment( string $slug ): bool {
        $url = self::url( $slug );
        list( , $args ) = self::route( $url );
        return in_array( $args['page'] ?? '', array( 'woocommerce-payments', 'wc-payments', 'wcpay' ), true )
            || DDW_TWC_Commerce::is_payment_settings_link( $url );
    }

    private static function subscription( string $slug ): bool {
        list( $file, $args ) = self::route( self::url( $slug ) );
        return 'shop_subscription' === ( $args['post_type'] ?? '' ) || in_array( $args['page'] ?? '', array( 'wc-orders--shop_subscription', 'woocommerce-subscriptions', 'wc-subscriptions' ), true );
    }

    private static function rank( string $url ): int {
        list( $file, $args ) = self::route( $url );
        $page = $args['page'] ?? '';
        $path = $args['path'] ?? '';
        $type = $args['post_type'] ?? '';
        if ( 'wc-orders' === $page || 'shop_order' === $type ) { return 10; }
        if ( 'wc-admin' === $page && '/customers' === $path ) { return 20; }
        if ( 'product' === $type && ! $page && 'edit.php' === $file ) { return 30; }
        if ( 'product' === $type && 'post-new.php' === $file ) { return 31; }
        if ( 'product' === $type && ( 'edit-tags.php' === $file || 'product_attributes' === $page ) ) { return 32; }
        if ( 'wc-admin' === $page && '/analytics/overview' === $path ) { return 25; }
        if ( self::payment( $url ) ) { return 21; }
        if ( self::subscription( $url ) ) { return 22; }
        if ( 'wc-settings' === $page && count( $args ) === 1 ) { return 100; }
        if ( in_array( $page, array( 'tools-for-woocommerce', 'tfwc-email-designer', 'wc-status' ), true ) ) { return 110; }
        return 120;
    }

    /** Resolve the selected item, preferring the most specific registered route. */
    public static function selected( array $display, string $request, string $type = '', string $taxonomy = '' ): ?array {
        list( $file, $args ) = self::route( $request );
        $route_keys = array_flip( array( 'page', 'path', 'post_type', 'taxonomy' ) );
        $best = null;
        $specificity = -1;
        foreach ( $display as $parent => $rows ) {
            foreach ( $rows as $row ) {
                if ( wp_parse_url( $row[2], PHP_URL_HOST ) ) { continue; }
                list( $target_file, $target ) = self::route( $row[2] );
                if ( $file !== $target_file || ! $target || array_intersect_key( $target, $route_keys ) !== array_intersect_key( $args, $route_keys )
                    || array_intersect_key( $args, $target ) !== $target ) { continue; }
                if ( count( $target ) > $specificity ) { $best = array( $parent, $row[2] ); $specificity = count( $target ); }
            }
        }
        if ( $best ) { return $best; }
        if ( 'admin.php' === $file && self::report_path( $request ) ) {
            foreach ( $display[ self::CORE ] ?? array() as $row ) {
                if ( self::key( $row[2] ) === self::key( self::url( self::ANALYTICS ) ) ) { return array( self::CORE, $row[2] ); }
            }
        }

        $fallback = match ( true ) {
            'term.php' === $file && $taxonomy && is_object_in_taxonomy( 'product', $taxonomy ) => 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=product',
            'product' === $type && 'post.php' === $file => self::PRODUCTS,
            'shop_order' === $type && 'post.php' === $file => 'edit.php?post_type=shop_order',
            'shop_subscription' === $type && 'post.php' === $file => 'edit.php?post_type=shop_subscription',
            default => '',
        };
        if ( $fallback ) {
            foreach ( $display[ self::CORE ] ?? array() as $row ) {
                if ( self::key( $row[2] ) === self::key( $fallback ) ) { return array( self::CORE, $row[2] ); }
            }
        }
        return null;
    }

    private static function report_path( string $url ): string {
        list( $file, $args ) = self::route( $url );
        $path = $args['path'] ?? '';
        if ( 'admin.php' !== $file || 'wc-admin' !== ( $args['page'] ?? '' ) || ! is_string( $path ) || ! str_starts_with( $path, '/analytics/' ) ) { return ''; }
        return in_array( substr( $path, strlen( '/analytics/' ) ), self::REPORTS, true ) ? $path : '';
    }

    public static function report_navigation(): void {
        $request = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path = self::report_path( $request );
        if ( ! $path || count( self::$reports ) < 2 ) { return; }
        echo '<form action="' . esc_url( admin_url( 'admin.php' ) ) . '" method="get" class="wrap tfwc-analytics-navigation"><input type="hidden" name="page" value="wc-admin"><label for="tfwc-analytics-report">' . esc_html__( 'Analytics report', 'tools-for-woocommerce' ) . '</label> <select id="tfwc-analytics-report" name="path">';
        foreach ( self::$reports as $row ) {
            $target = self::report_path( $row[2] );
            echo '<option value="' . esc_attr( $target ) . '"' . selected( $target, $path, false ) . '>' . esc_html( wp_strip_all_tags( $row[0] ) ) . '</option>';
        }
        echo '</select> <button type="submit" class="button">' . esc_html__( 'Open report', 'tools-for-woocommerce' ) . '</button></form>';
    }

    private static function current(): ?array {
        $screen = get_current_screen();
        $request = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        return self::selected( self::$display, $request, $screen->post_type ?? '', $screen->taxonomy ?? '' );
    }

    public static function parent( $parent ) {
        global $menu, $submenu;
        if ( ! self::$display && ! is_network_admin() && ! is_user_admin() ) {
            list( $menu, $submenu, self::$display, self::$reports ) = self::split( (array) $menu, (array) $submenu );
        }
        return self::current()[0] ?? $parent;
    }

    public static function child( $child, $parent ) {
        $active = self::current();
        return $active && $active[0] === $parent ? $active[1] : $child;
    }
}
