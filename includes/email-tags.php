<?php
/** Resolves the Email Designer's text and link tags. */
defined( 'ABSPATH' ) || exit;

final class DDW_TWC_Email_Tags {
    const PAGES = array( 'shop' => 'shop', 'wagen' => 'cart', 'kasse' => 'checkout', 'konto' => 'myaccount', 'agb' => 'terms' );
    const ACCOUNT = array( 'bestellungen' => 'orders', 'downloads' => 'downloads', 'adressen' => 'edit-address' );
    const URL_PATTERN = '/^\{(?:seite(?:-impressum|-datenschutz)?:[1-9][0-9]*|wc-seite:[a-z]+|wc-konto:[a-z]+|bestellung:(?:ansehen|bezahlen))\}$/D';

    public static function public_page( int $id ): ?WP_Post {
        $page = $id > 0 ? get_post( $id ) : null;
        return $page && 'page' === $page->post_type && 'publish' === $page->post_status && '' === $page->post_password ? $page : null;
    }

    public static function imprint( WP_Post $page ): bool {
        return (bool) preg_match( '/impressum|imprint/iu', $page->post_title );
    }

    public static function clean_url( string $input ): string {
        return preg_match( self::URL_PATTERN, $input ) ? $input : esc_url_raw( $input, array( 'https', 'http' ) );
    }

    public static function url( string $value, $order = null ): string {
        if ( ! preg_match( self::URL_PATTERN, $value ) ) { return esc_url_raw( $value, array( 'https', 'http', 'mailto' ) ); }
        list( $group, $parameter ) = explode( ':', trim( $value, '{}' ), 2 );
        if ( str_starts_with( $group, 'seite' ) ) {
            $page = self::public_page( (int) $parameter );
            if ( ! $page || ( 'seite-impressum' === $group && ! self::imprint( $page ) ) || ( 'seite-datenschutz' === $group && $page->ID !== (int) get_option( 'wp_page_for_privacy_policy' ) ) ) { return ''; }
            return (string) get_permalink( $page );
        }
        if ( 'wc-seite' === $group ) {
            $key = self::PAGES[ $parameter ] ?? '';
            $page = $key ? self::public_page( wc_get_page_id( $key ) ) : null;
            return $page ? (string) get_permalink( $page ) : '';
        }
        if ( 'wc-konto' === $group ) {
            $key = self::ACCOUNT[ $parameter ] ?? '';
            $account = self::url( '{wc-seite:konto}' );
            return $account && $key && ! empty( WC()->query->get_query_vars()[ $key ] ) ? wc_get_endpoint_url( $key, '', $account ) : '';
        }
        if ( ! $order instanceof WC_Order || ! $order->get_id() ) { return ''; }
        if ( 'bezahlen' === $parameter ) {
            return $order->needs_payment() && ! empty( WC()->query->get_query_vars()['order-pay'] ) && self::url( '{wc-seite:kasse}' ) ? $order->get_checkout_payment_url() : '';
        }
        return $order->get_customer_id() && ! empty( WC()->query->get_query_vars()['view-order'] ) && self::url( '{wc-seite:konto}' ) ? $order->get_view_order_url() : '';
    }

    /** Keep valid URL tags intact across the HTML sanitizer. */
    public static function sanitize_markup( string $html, array $allowed ): string {
        $links = new WP_HTML_Tag_Processor( $html );
        $restore = array();
        $prefix = 'https://ddw-tag.invalid/' . wp_generate_uuid4() . '/';
        while ( $links->next_tag( 'A' ) ) {
            $href = $links->get_attribute( 'href' );
            if ( ! is_string( $href ) || ! preg_match( self::URL_PATTERN, $href ) ) { continue; }
            $url = $prefix . count( $restore );
            $links->set_attribute( 'href', $url );
            $restore['href="' . $url . '"'] = 'href="' . $href . '"';
        }
        return strtr( wp_kses( $links->get_updated_html(), $allowed, array( 'http', 'https', 'mailto' ) ), $restore );
    }

    /** Resolve links in allowlisted HTML without interpolating text into attributes. */
    public static function html( string $html, array $values, callable $replace, array &$warnings ): string {
        $links = new WP_HTML_Tag_Processor( $html );
        while ( $links->next_tag( 'A' ) ) {
            $href = $links->get_attribute( 'href' );
            if ( ! is_string( $href ) || ! preg_match( self::URL_PATTERN, $href ) ) { continue; }
            $url = self::url( $href, $values['_order'] ?? null );
            if ( $url ) {
                $links->set_attribute( 'href', $url );
                $warnings['resolved'][ $href ] = str_starts_with( $href, '{bestellung:' ) ? true : $url;
            }
            else { $warnings['missing'][] = $href; $links->remove_attribute( 'href' ); }
        }
        $html = $links->get_updated_html();
        // Interpolate text only between tags; allowed markup is sanitized separately.
        $parts = wp_html_split( $html );
        foreach ( $parts as &$part ) {
            if ( ! str_starts_with( $part, '<' ) ) { $part = $replace( $part, $values, true ); }
        }
        unset( $part );
        return implode( '', $parts );
    }

    /** Public pages only, bounded for typeahead. */
    public static function search( string $query, bool $imprints = false ): array {
        $pages = get_posts( array(
            'post_type' => 'page', 'post_status' => 'publish', 'has_password' => false,
            's' => $query, 'search_columns' => array( 'post_title' ), 'posts_per_page' => 20,
            'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true,
        ) );
        $out = array();
        foreach ( $pages as $page ) {
            if ( $imprints && ! self::imprint( $page ) ) { continue; }
            $out[ $page->ID ] = array( 'id' => $page->ID, 'title' => wp_strip_all_tags( get_the_title( $page ) ) );
        }
        return $out;
    }

    public static function catalog(): array {
        $out = array( 'pages' => array(), 'account' => array(), 'privacy' => 0, 'imprint' => array() );
        foreach ( self::PAGES as $key => $_ ) {
            if ( self::url( '{wc-seite:' . $key . '}' ) ) { $out['pages'][] = $key; }
        }
        foreach ( self::ACCOUNT as $key => $_ ) {
            if ( self::url( '{wc-konto:' . $key . '}' ) ) { $out['account'][] = $key; }
        }
        $privacy = (int) get_option( 'wp_page_for_privacy_policy' );
        if ( self::public_page( $privacy ) ) { $out['privacy'] = $privacy; }
        $out['imprint'] = array_values( self::search( 'Impressum', true ) + self::search( 'Imprint', true ) );
        return $out;
    }
}
