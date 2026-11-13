<?php
/** Read-only adapter for the WooCommerce Email Customizer by cxThemes. */
defined( 'ABSPATH' ) || exit;

final class DDW_TWC_Email_CxThemes {
    private const SOURCES = array( 'woocommerce' => 'WooCommerce', 'deluxe' => 'Deluxe' );
    private const FIELDS = array( 'subject', 'heading', 'main_text', 'heading_pending', 'main_text_pending', 'main_text_generate_pass' );
    private const TEMPLATES = array(
        'new_order' => 'WC_Email_New_Order',
        'cancelled_order' => 'WC_Email_Cancelled_Order',
        'failed_order' => 'WC_Email_Failed_Order',
        'customer_cancelled_order' => 'WC_Email_Customer_Cancelled_Order',
        'customer_failed_order' => 'WC_Email_Customer_Failed_Order',
        'customer_on_hold_order' => 'WC_Email_Customer_On_Hold_Order',
        'customer_processing_order' => 'WC_Email_Customer_Processing_Order',
        'customer_completed_order' => 'WC_Email_Customer_Completed_Order',
        'customer_refunded_order' => 'WC_Email_Customer_Refunded_Order',
        'customer_invoice' => 'WC_Email_Customer_Invoice',
        'customer_note' => 'WC_Email_Customer_Note',
        'customer_new_account' => 'WC_Email_Customer_New_Account',
        'customer_reset_password' => 'WC_Email_Customer_Reset_Password',
        'customer_pos_completed_order' => 'WC_Email_Customer_POS_Completed_Order',
        'customer_pos_refunded_order' => 'WC_Email_Customer_POS_Refunded_Order',
        'admin_payment_gateway_enabled' => 'WC_Email_Admin_Payment_Gateway_Enabled',
    );

    private static function read( string $key ): string {
        $value = get_option( 'ec_' . $key, '' );
        return is_string( $value ) ? trim( wp_check_invalid_utf8( substr( $value, 0, 4000 ), true ) ) : '';
    }

    public static function sources(): array {
        $sources = array();
        $keys = self::keys();
        wp_prime_option_caches( array_merge( array( 'ec_template' ), ...array_map(
            static fn( $source ) => array_map( static fn( $key ) => 'ec_' . $source . '_' . $key, $keys ),
            array_keys( self::SOURCES )
        ) ) );
        foreach ( self::SOURCES as $key => $name ) {
            foreach ( $keys as $field ) {
                if ( '' !== self::read( $key . '_' . $field ) ) {
                    $sources[ $key ] = $name;
                    break;
                }
            }
        }
        return array( 'options' => $sources, 'preferred' => array_key_exists( self::read( 'template' ), $sources ) ? self::read( 'template' ) : '' );
    }

    private static function keys(): array {
        $keys = array( 'all_footer_text', 'all_header_logo' );
        foreach ( self::TEMPLATES as $key => $id ) {
            foreach ( self::FIELDS as $field ) {
                $keys[] = $key . '_' . $field;
            }
        }
        return $keys;
    }

    public static function inspect( string $source, array $available ): array {
        if ( ! isset( self::SOURCES[ $source ] ) ) { return array(); }
        wp_prime_option_caches( array_map( static fn( $key ) => 'ec_' . $source . '_' . $key, self::keys() ) );
        $rows = array();
        foreach ( self::TEMPLATES as $key => $id ) {
            $fields = array();
            $manual = array();
            $review = array();
            foreach ( self::FIELDS as $field ) {
                $value = self::read( $source . '_' . $key . '_' . $field );
                if ( '' === $value ) { continue; }
                if ( ! isset( $available[ $id ] ) ) { $manual[ $field ] = $value; continue; }
                if ( in_array( $field, array( 'subject', 'heading' ), true ) ) {
                    $converted = self::tags( $value );
                    if ( ! self::has_old_tags( $converted ) ) {
                        $fields[ $field ] = $converted;
                        continue;
                    }
                }
                if ( 'main_text' === $field && ( $copy = self::copy( $key, $value ) ) ) {
                    $fields['copy'] = $copy;
                    $review[ $field ] = $value;
                    continue;
                }
                $manual[ $field ] = $value;
            }
            if ( $fields || $manual ) {
                $rows[ $id ] = array( 'title' => $available[ $id ] ?? $key, 'fields' => $fields, 'manual' => $manual, 'review' => $review );
            }
        }

        $branding = array();
        $manual_branding = array();
        $logo = self::read( $source . '_all_header_logo' );
        if ( $logo && ! in_array( $logo, array( 'http://', 'https://' ), true ) ) {
            $url = esc_url_raw( $logo, array( 'http', 'https' ) );
            if ( wp_parse_url( $url, PHP_URL_HOST ) ) { $branding['header_logo'] = $url; } else { $manual_branding['header_logo'] = $logo; }
        }
        $footer = self::read( $source . '_all_footer_text' );
        if ( $footer ) {
            $converted = self::tags( $footer );
            if ( self::has_old_tags( $converted ) ) { $manual_branding['footer_text'] = $footer; } else { $branding['footer_text'] = $converted; }
        }
        return array( 'rows' => $rows, 'branding' => $branding, 'manualBranding' => $manual_branding );
    }

    private static function tags( string $text ): string {
        return strtr( $text, array(
            '[ec_order]' => '#{order_number} ({bestelldatum:lang})',
            '[ec_firstname]' => '{kunde:vorname}',
            '[ec_site_name]' => '{shop_name}',
        ) );
    }

    private static function has_old_tags( string $text ): bool {
        return (bool) preg_match( '/\[ec_[^\]]+\]/i', $text );
    }

    private static function copy( string $key, string $value ): array {
        $plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
        $native = array(
            'new_order' => array( 'You’ve received the following order from %s:', 'You’ve received a new order from %s:' ),
            'cancelled_order' => array( 'Notification to let you know &mdash; order #%1$s belonging to %2$s has been cancelled:', 'We’re getting in touch to let you know that order #%1$s from %2$s has been cancelled.' ),
            'customer_processing_order' => array( "Just to let you know &mdash; we've received your order #%s, and it is now being processed:", 'Just to let you know — we’ve received your order #%s, and it is now being processed:' ),
            'customer_completed_order' => array( 'We have finished processing your order.' ),
        );
        if ( 'customer_note' === $key && str_contains( $plain, '[ec_customer_note]' ) ) {
            list( $before, $after ) = array_map( 'trim', explode( '[ec_customer_note]', $plain, 2 ) );
            if ( $before && $after && ! self::has_old_tags( $before . $after ) && ! str_contains( $before . $after, '%' ) ) {
                return array( 'The following note has been added to your order:' => self::tags( $before ), 'As a reminder, here are your order details:' => self::tags( $after ) );
            }
        }
        if ( 'customer_new_account' === $key && str_contains( $plain, '[ec_account_link]' ) ) {
            $text = strtr( $plain, array( '[ec_site_name]' => '%1$s', '[ec_user_login]' => '%2$s', '[ec_account_link]' => '%3$s' ) );
            if ( ! self::has_old_tags( $text ) && preg_match_all( '/%[^\s]+?s/', $text, $matches ) && array( '%1$s', '%2$s', '%3$s' ) === $matches[0] ) {
                return array(
                    'Thanks for creating an account on %1$s. Your username is %2$s. You can access your account area to view orders, change your password, and more at: %3$s' => $text,
                    'Thanks for creating an account on %s. Here’s a copy of your user details.' => 'Thanks for creating an account on %s.',
                );
            }
        }
        if ( 'customer_reset_password' === $key && preg_match( '/^(.*?)Username:\s*\[ec_user_login\]\s*(.*?)\s*\[ec_reset_password_link\]$/is', $plain, $match ) && ! self::has_old_tags( $match[1] . $match[2] ) && ! str_contains( $match[1] . $match[2], '%' ) ) {
            $intro = rtrim( $match[1], ': ' ) . ' %s:';
            return array(
                'Someone has requested a new password for the following account on %s:' => $intro,
                'If you didn’t make this request, just ignore this email. If you’d like to proceed, reset your password via the link below:' => $match[2],
                "If you didn't make this request, just ignore this email. If you'd like to proceed:" => $match[2],
            );
        }
        if ( ! isset( $native[ $key ] ) || str_contains( $plain, '%' ) ) { return array(); }
        $text = strtr( $plain, match ( $key ) {
            'new_order' => array( '[ec_firstname] [ec_lastname]' => '%s' ),
            'cancelled_order' => array( '[ec_order]' => '#%1$s ({bestelldatum:lang})', '[ec_firstname] [ec_lastname]' => '%2$s' ),
            'customer_processing_order' => array( '[ec_order]' => '#%s ({bestelldatum:lang})' ),
            default => array(),
        } );
        $text = self::tags( $text );
        if ( self::has_old_tags( $text ) ) { return array(); }
        $copy = array();
        preg_match_all( '/%(?:[0-9]+\$)?s/', $text, $markers );
        foreach ( $native[ $key ] as $original ) {
            preg_match_all( '/%(?:[0-9]+\$)?s/', $original, $expected );
            if ( $markers[0] === $expected[0] ) { $copy[ $original ] = $text; }
        }
        return $copy;
    }
}
