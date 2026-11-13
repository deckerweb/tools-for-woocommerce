<?php
/** Opt-in WooCommerce email presentation, REST editor and preview. */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/email-tags.php';
require_once __DIR__ . '/email-cxthemes.php';

final class DDW_TWC_Email_Designer {
    const OPTION = 'ddw_twc_email_design';
    const API = 'ddw-twc/v1';
    const PAGE = 'tfwc-email-designer';
    const LEGACY_PAGE = 'ddw-twc-email-designer';
    private const VERIFY_CLASS = \Automattic\WooCommerce\Internal\CustomerEmailVerification\Emails\CustomerVerifyEmail::class;
    private const VERIFY_ID = 'WC_Email_Customer_Verify_Email';

    public static function register(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ), 99 );
        add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
        add_action( 'admin_notices', array( self::class, 'woocommerce_notice' ) );
        add_filter( 'woocommerce_email_setting_columns', array( self::class, 'woocommerce_columns' ) );
        add_action( 'woocommerce_email_setting_column_tfwc_design', array( self::class, 'woocommerce_cell' ) );
        add_action( 'rest_api_init', array( self::class, 'routes' ) );
        add_filter( 'woocommerce_mail_callback_params', array( self::class, 'outgoing' ), 50, 2 );
    }

    public static function menu(): void {
        add_submenu_page( 'woocommerce', __( 'Email Designer', 'tools-for-woocommerce' ), __( 'Email Designer', 'tools-for-woocommerce' ), 'manage_options', self::PAGE, array( self::class, 'page' ) );
        $legacy = add_submenu_page( '', '', '', 'manage_options', self::LEGACY_PAGE, array( self::class, 'page' ) );
        if ( $legacy ) {
            add_action( 'load-' . $legacy, array( self::class, 'redirect_legacy' ) );
        }
    }

    public static function url(): string { return admin_url( 'admin.php?page=' . self::PAGE ); }

    public static function redirect_legacy(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        wp_safe_redirect( self::url() );
        exit;
    }

    private static function status_label( WC_Email $email, array $state ): string {
        if ( 'html' !== $email->get_email_type() ) { return __( 'WooCommerce format', 'tools-for-woocommerce' ); }
        $id = self::template_id( $email );
        if ( ! empty( $state['live']['templates'][ $id ]['enabled'] ) ) { return __( 'TfWC design active', 'tools-for-woocommerce' ); }
        if ( ! empty( $state['draft']['templates'][ $id ]['enabled'] ) ) { return __( 'Draft only', 'tools-for-woocommerce' ); }
        return __( 'WooCommerce default', 'tools-for-woocommerce' );
    }

    private static function template_link( WC_Email $email ): string {
        return add_query_arg( 'template', self::template_id( $email ), self::url() );
    }

    public static function settings_map_links(): array {
        if ( ! current_user_can( 'manage_options' ) || ! has_action( 'admin_menu', array( self::class, 'menu' ) ) ) { return array(); }
        $links = array();
        foreach ( self::emails() as $email ) {
            if ( 'html' === $email->get_email_type() ) { $links[ spl_object_id( $email ) ] = self::template_link( $email ); }
        }
        return $links;
    }

    public static function woocommerce_columns( array $columns ): array {
        if ( current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ) ) {
            $columns['tfwc_design'] = __( 'Email design', 'tools-for-woocommerce' );
        }
        return $columns;
    }

    public static function woocommerce_cell( $email ): void {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) { return; }
        if ( ! $email instanceof WC_Email || ! isset( self::emails()[ self::template_id( $email ) ] ) ) {
            echo '<td class="wc-email-settings-table-tfwc_design"></td>';
            return;
        }
        echo '<td class="wc-email-settings-table-tfwc_design"><span>' . esc_html( self::status_label( $email, self::state() ) ) . '</span>';
        if ( 'html' === $email->get_email_type() ) {
            echo '<br><a href="' . esc_url( self::template_link( $email ) ) . '">' . esc_html__( 'Edit design', 'tools-for-woocommerce' ) . '</a>';
        }
        echo '</td>';
    }

    /** Shared entry point for the classic settings and WooCommerce’s block email list. */
    public static function woocommerce_notice(): void {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $screen = get_current_screen();
        if ( ! $screen || ! str_ends_with( $screen->id, '_page_wc-settings' ) ) { return; }
        // Read-only routing context. No option writes are performed here.
        $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        if ( 'email' !== $tab ) { return; }
        $section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
        $state = self::state();
        $emails = self::emails();
        $count = 0;
        $selected = null;
        foreach ( $emails as $email ) {
            if ( 'html' === $email->get_email_type() && ! empty( $state['live']['templates'][ self::template_id( $email ) ]['enabled'] ) ) { ++$count; }
            if ( strtolower( self::template_id( $email ) ) === $section || $email->id === $section ) { $selected = $email; }
        }
        if ( '' !== $section && ! $selected ) { return; }
        $url = $selected ? self::template_link( $selected ) : self::url();
        echo '<div class="notice notice-info"><p><strong>Tools for WooCommerce · ' . esc_html__( 'Email Designer', 'tools-for-woocommerce' ) . '</strong> — ';
        if ( $selected ) { echo esc_html( self::status_label( $selected, $state ) ); }
        elseif ( $count ) {
            /* translators: %s: Number of published HTML email designs. */
            echo esc_html( sprintf( __( 'Standard HTML templates using the published design: %s.', 'tools-for-woocommerce' ), number_format_i18n( $count ) ) );
        } else { echo esc_html__( 'WooCommerce defaults are in use. Publish a design to take over selected standard HTML templates.', 'tools-for-woocommerce' ); }
        echo '</p><p>' . esc_html__( 'Manage sending, recipients and format here. WooCommerce design and text settings are kept for emails that use the native layout and are restored to use when Tools for WooCommerce is disabled.', 'tools-for-woocommerce' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open Email Designer', 'tools-for-woocommerce' ) . '</a></p>';
        if ( ! $selected ) {
            echo '<details><summary>' . esc_html__( 'Standard templates and design status', 'tools-for-woocommerce' ) . '</summary><ul>';
            foreach ( $emails as $email ) {
                echo '<li>';
                if ( 'html' === $email->get_email_type() ) { echo '<a href="' . esc_url( self::template_link( $email ) ) . '">' . esc_html( $email->get_title() ) . '</a>'; }
                else { echo esc_html( $email->get_title() ); }
                echo ' — ' . esc_html( self::status_label( $email, $state ) ) . '</li>';
            }
            echo '</ul></details>';
        }
        echo '</div>';
    }

    public static function page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        echo '<div class="wrap ddw-twc-root ddw-twc-designer-page">';
        DDW_TWC_Settings::header( __( 'Email Designer', 'tools-for-woocommerce' ) );
        echo '<div id="ddw-twc-email-editor"></div>';
        DDW_TWC_Settings::footer();
        echo '</div>';
    }

    public static function assets( string $hook ): void {
        if ( 'woocommerce_page_' . self::PAGE !== $hook ) { return; }
        DDW_TWC_Settings::chrome_assets();
        wp_enqueue_style( 'ddw-twc-email', plugins_url( 'assets/email-designer.css', DDW_TWC_FILE ), array( 'ddw-twc-settings' ), DDW_TWC_VERSION . '.' . filemtime( dirname( DDW_TWC_FILE ) . '/assets/email-designer.css' ) );
        wp_enqueue_style( 'wp-components' );
        wp_enqueue_media();
        wp_enqueue_script( 'ddw-twc-email', plugins_url( 'assets/email-designer.js', DDW_TWC_FILE ), array( 'wp-api-fetch', 'wp-element', 'wp-components' ), DDW_TWC_VERSION . '.' . filemtime( dirname( DDW_TWC_FILE ) . '/assets/email-designer.js' ), true );
        $palettes = array_map( static fn( $style ) => array( $style[3], $style[0], $style[1], $style[2] ), self::styles() );
        wp_localize_script( 'ddw-twc-email', 'ddwTwcEmail', array( 'palettes' => $palettes, 'path' => '/' . self::API . '/email', 'german' => str_starts_with( determine_locale(), 'de' ) ) );
    }

    private static function template_id( WC_Email $email ): string {
        $class = get_class( $email );
        if ( self::VERIFY_CLASS === $class ) { return self::VERIFY_ID; }
        if ( self::email_source( $email ) ) {
            $key = array_search( $email, WC()->mailer()->get_emails(), true );
            if ( ! $key ) {
                foreach ( WC()->mailer()->get_emails() as $registered_key => $registered ) {
                    if ( get_class( $registered ) === $class && $registered->id === $email->id ) { $key = $registered_key; break; }
                }
            }
            if ( is_string( $key ) && preg_match( '/^WC_Email_[A-Za-z0-9_]{1,100}$/D', $key ) ) { return $key; }
            return 'WC_Email_TfWC_GZD_' . substr( hash( 'sha256', $class . ':' . $email->id ), 0, 24 );
        }
        return $class;
    }

    private static function germanized_source( string $file ): string {
        foreach ( array( 'WC_GERMANIZED_PRO_ABSPATH' => 'Germanized Pro', 'WC_GERMANIZED_ABSPATH' => 'Germanized' ) as $constant => $label ) {
            $root = defined( $constant ) ? realpath( constant( $constant ) ) : false;
            if ( $root && str_starts_with( $file, $root . DIRECTORY_SEPARATOR ) ) { return $label; }
        }
        return '';
    }

    public static function email_source( WC_Email $email ): string {
        $file = ( new ReflectionClass( $email ) )->getFileName();
        return $file ? self::germanized_source( (string) realpath( $file ) ) : '';
    }

    /** Accept classic core mail classes and known first-party namespaced classes. */
    private static function emails(): array {
        $core = realpath( WC_ABSPATH . 'includes/emails' );
        $verify = realpath( WC_ABSPATH . 'src/Internal/CustomerEmailVerification/Emails/CustomerVerifyEmail.php' );
        $emails = array();
        foreach ( WC()->mailer()->get_emails() as $email ) {
            if ( ! $email instanceof WC_Email ) { continue; }
            $file = ( new ReflectionClass( $email ) )->getFileName();
            $path = $file ? realpath( $file ) : false;
            if ( $path && ( ( $core && dirname( $path ) === $core ) || ( $verify === $path && self::VERIFY_CLASS === get_class( $email ) ) || self::germanized_source( $path ) ) ) {
                $emails[ self::template_id( $email ) ] = $email;
            }
        }
        return $emails;
    }

    private static function styles(): array {
        // Canvas, paper, text, accent, brand surface, brand ink, font, rhythm.
        return array(
            'studio'   => array( '#e9e9e5', '#ffffff', '#20221d', '#33412a', '#232921', '#ffffff', 'Arial, sans-serif', 32 ),
            'journal'  => array( '#e9dfd0', '#fffaf1', '#2e2218', '#793a2b', '#fffaf1', '#38271c', 'Georgia, serif', 36 ),
            'atelier'  => array( '#e1eae4', '#ffffff', '#21392f', '#275841', '#e1efe6', '#183c2c', 'Arial, sans-serif', 28 ),
            'signal'   => array( '#e1e9fc', '#ffffff', '#14254b', '#1a409b', '#143379', '#ffffff', 'Arial, sans-serif', 26 ),
            'ledger'   => array( '#e6e9ee', '#ffffff', '#202938', '#344356', '#f0f2f5', '#202938', 'Arial, sans-serif', 24 ),
            'letter'   => array( '#e8e4dc', '#fffcf6', '#292520', '#774537', '#fffcf6', '#292520', 'Georgia, serif', 38 ),
            'parcel'   => array( '#e9e4da', '#ffffff', '#30271c', '#85501c', '#38291b', '#ffffff', 'Arial, sans-serif', 26 ),
            'gallery'  => array( '#eee7f3', '#ffffff', '#302339', '#603f76', '#f0e6f5', '#302339', 'Arial, sans-serif', 24 ),
            'postcard' => array( '#f5e4dc', '#fffaf6', '#49291e', '#963f27', '#f6d2c3', '#532b20', 'Georgia, serif', 30 ),
        );
    }

    private static function allowed_html(): array {
        return array_fill_keys( array( 'p', 'br', 'strong', 'em', 'b', 'i', 'ul', 'ol', 'li' ), array() )
            + array( 'a' => array( 'href' => true, 'title' => true ) );
    }

    private static function clean( $input, bool $branding_only = false ): array {
        $input = is_array( $input ) ? $input : array();
        $style = $input['style'] ?? '';
        $out = array( 'style' => is_string( $style ) && isset( self::styles()[ $style ] ) ? $style : 'studio', 'templates' => array() );
        $frame = $input['frame'] ?? '';
        $out['frame'] = in_array( $frame, array( 'outline', 'card' ), true ) ? $frame : '';
        foreach ( array( 'accent', 'canvas', 'paper', 'ink' ) as $color ) {
            $out[ $color ] = sanitize_hex_color( self::string( $input[ $color ] ?? '', 8 ) ) ?: '';
        }
        foreach ( array( 'header_title', 'header_slogan', 'footer_title', 'footer_copyright' ) as $key ) {
            $out[ $key ] = sanitize_text_field( self::string( $input[ $key ] ?? '', 400 ) );
        }
        foreach ( array( 'header', 'footer' ) as $part ) {
            $alignment = $input[ $part . '_align' ] ?? '';
            $out[ $part . '_align' ] = in_array( $alignment, array( 'start', 'center', 'end' ), true ) ? $alignment : '';
            $out[ $part . '_enabled' ] = ! array_key_exists( $part . '_enabled', $input ) || true === $input[ $part . '_enabled' ];
            foreach ( array( 'logo', 'link' ) as $key ) {
                $out[ $part . '_' . $key ] = ( 'link' === $key ? DDW_TWC_Email_Tags::clean_url( self::string( $input[ $part . '_' . $key ] ?? '', 2048 ) ) : esc_url_raw( self::string( $input[ $part . '_' . $key ] ?? '', 2048 ), array( 'https', 'http' ) ) );
            }
            $width = $input[ $part . '_logo_width' ] ?? 140;
            $out[ $part . '_logo_width' ] = is_numeric( $width ) ? max( 40, min( 400, (int) $width ) ) : 140;
            $out[ $part . '_alt' ] = sanitize_text_field( self::string( $input[ $part . '_alt' ] ?? '', 200 ) );
            $out[ $part . '_text' ] = DDW_TWC_Email_Tags::sanitize_markup( self::string( $input[ $part . '_text' ] ?? '', 4000 ), self::allowed_html() );
        }
        if ( $branding_only ) {
            unset( $out['templates'] );
            return $out;
        }
        $out['library'] = array();
        foreach ( array_slice( (array) ( $input['library'] ?? array() ), 0, 30, true ) as $id => $entry ) {
            if ( ! is_string( $id ) || ! preg_match( '/^design-[a-z0-9-]{1,60}$/D', $id ) || ! is_array( $entry ) ) { continue; }
            $name = sanitize_text_field( self::string( $entry['name'] ?? '', 100 ) );
            if ( '' !== $name ) {
                $out['library'][ $id ] = array( 'name' => $name, 'branding' => self::clean( $entry['branding'] ?? array(), true ), 'updated' => max( 0, min( time(), (int) ( $entry['updated'] ?? 0 ) ) ) );
            }
        }
        $selected = $input['selected'] ?? '';
        $out['selected'] = is_string( $selected ) && isset( $out['library'][ $selected ] ) ? $selected : '';
        foreach ( array_filter( (array) ( $input['templates'] ?? array() ), static fn( $id ) => is_string( $id ) && preg_match( '/^WC_Email_[A-Za-z0-9_]{1,100}$/D', $id ), ARRAY_FILTER_USE_KEY ) as $id => $template ) {
            if ( ! is_array( $template ) ) { continue; }
            $fields = array( 'enabled' => true === ( $template['enabled'] ?? false ) );
            foreach ( array( 'subject', 'heading' ) as $field ) {
                $fields[ $field ] = sanitize_text_field( self::string( $template[ $field ] ?? '', 400 ) );
            }
            $fields['extra'] = DDW_TWC_Email_Tags::sanitize_markup( self::string( $template['extra'] ?? '', 4000 ), self::allowed_html() );
            $fields['copy'] = array();
            foreach ( array_slice( (array) ( $template['copy'] ?? array() ), 0, 160, true ) as $source => $replacement ) {
                if ( ! is_string( $source ) || strlen( $source ) > 1200 || str_contains( $source, '<' ) ) { continue; }
                $replacement = sanitize_text_field( self::string( $replacement, 1200 ) );
                // Preserve native printf arguments, including their format and order.
                preg_match_all( '/%(?:[0-9]+\$)?[-+0-9.]*[a-z%]|%/i', $source, $before );
                preg_match_all( '/%(?:[0-9]+\$)?[-+0-9.]*[a-z%]|%/i', $replacement, $after );
                if ( $before[0] === $after[0] && '' !== $replacement ) { $fields['copy'][ $source ] = $replacement; }
            }
            $out['templates'][ $id ] = $fields;
        }
        return $out;
    }

    private static function string( $value, int $limit ): string {
        return is_string( $value ) ? wp_check_invalid_utf8( substr( $value, 0, $limit ), true ) : '';
    }

    private static function state(): array {
        $state = get_option( self::OPTION, array() );
        return is_array( $state ) ? $state : array();
    }

    private static function revision( array $state ): string {
        return hash( 'sha256', serialize( $state ) );
    }

    public static function export_state(): array {
        return array_intersect_key( self::state(), array( 'draft' => true, 'live' => true ) );
    }

    public static function valid_transfer( $data ): bool {
        if ( ! is_array( $data ) || array_diff_key( $data, array( 'draft' => true, 'live' => true ) ) ) { return false; }
        foreach ( $data as $design ) {
            if ( ! is_array( $design ) || ! is_array( $design['templates'] ?? null ) || ! is_string( $design['style'] ?? null ) ) { return false; }
        }
        return true;
    }

    public static function import_draft( array $data ): bool {
        if ( ! $data ) { return true; }
        $state = self::state();
        $state['draft'] = self::clean( $data['draft'] ?? $data['live'] );
        unset( $state['imported_live'] );
        if ( isset( $data['draft'], $data['live'] ) ) {
            $source_live = self::clean( $data['live'] );
            if ( $source_live !== $state['draft'] ) { $state['imported_live'] = $source_live; }
        }
        update_option( self::OPTION, $state, false );
        return self::state() === $state;
    }

    private static function values( $object ): array {
        $values = array( 'shop_name' => get_bloginfo( 'name' ), 'year' => wp_date( 'Y' ), 'customer_name' => '', 'kunde:vollname' => '', 'website_slogan' => get_bloginfo( 'description' ), 'copyright' => '©', 'registered' => '®' );
        if ( $object instanceof WC_Order ) {
            $values['_order'] = $object;
            $values['customer_name'] = $object->get_billing_first_name();
            $values['kunde:vollname'] = trim( $object->get_billing_first_name() . ' ' . $object->get_billing_last_name() );
            $date = $object->get_date_created();
            if ( $date ) {
                $values['bestelldatum:kurz'] = wc_format_datetime( $date, 'j.n.Y' );
                $values['bestelldatum:lang'] = wc_format_datetime( $date, __( 'F j, Y', 'tools-for-woocommerce' ) );
            }
            $country = $object->get_billing_country();
            $values['country'] = WC()->countries->get_countries()[ $country ] ?? $country;
            $values['order_number'] = $object->get_order_number();
            $values['order_total'] = html_entity_decode( wp_strip_all_tags( $object->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' );
        } elseif ( $object instanceof WP_User ) {
            $values['customer_name'] = $object->first_name ?: $object->display_name;
            $values['kunde:vollname'] = trim( $object->first_name . ' ' . $object->last_name ) ?: $object->display_name;
        }
        return $values;
    }

    private static function replace( string $text, array $values, bool $html = false ): string {
        $values['kunde:vorname'] = $values['customer_name'] ?? '';
        return preg_replace_callback( '/\{(shop_name|website_slogan|copyright|registered|year|customer_name|order_number|order_total|country|kunde:(?:vorname|vollname)|bestelldatum:(?:kurz|lang))(?:\|([^{}\r\n]{0,120}))?\}/u', static function ( $match ) use ( $values, $html ) {
            $value = (string) ( $values[ $match[1] ] ?? '' );
            if ( '' === $value ) { $value = $match[2] ?? ''; }
            return $html ? esc_html( $value ) : $value;
        }, $text ) ?? $text;
    }

    private static function rich( string $text, array $values, array &$warnings ): string {
        return wpautop( wp_kses( DDW_TWC_Email_Tags::html( $text, $values, self::replace(...), $warnings ), self::allowed_html(), array( 'https', 'http', 'mailto' ) ) );
    }

    private static function brand( array $design, string $part, array $values, array &$warnings ): string {
        if ( empty( $design[ $part . '_enabled' ] ) ) { return ''; }
        $logo = $design[ $part . '_logo' ];
        $target = $design[ $part . '_link' ];
        $link = DDW_TWC_Email_Tags::url( $target );
        if ( '' !== $target && '' === $link && preg_match( DDW_TWC_Email_Tags::URL_PATTERN, $target ) ) { $warnings['missing'][] = $target; }
        $alt = $design[ $part . '_alt' ] ?: $values['shop_name'];
        $width = (int) ( $design[ $part . '_logo_width' ] ?? 140 );
        $image = $logo ? '<img width="' . $width . '" style="width:' . $width . 'px;max-width:100%;height:auto;border:0" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $alt ) . '">' : '';
        if ( $link && preg_match( DDW_TWC_Email_Tags::URL_PATTERN, $target ) ) { $warnings['resolved'][ $target ] = $link; }
        if ( $image && $link ) { $image = '<a href="' . esc_url( $link ) . '">' . $image . '</a>'; }
        $title = self::replace( $design[ $part . '_title' ] ?? '', $values );
        if ( 'header' === $part && '' === $title && '' === $image && '' === $design['header_text'] ) { $title = $values['shop_name']; }
        $title = '' !== $title ? '<div style="font-size:21px;font-weight:bold;letter-spacing:.3px">' . esc_html( $title ) . '</div>' : '';
        $slogan = 'header' === $part ? self::replace( $design['header_slogan'] ?? '', $values ) : '';
        return $image . $title . ( '' !== $slogan ? '<div style="font-size:13px;margin-top:4px">' . esc_html( $slogan ) . '</div>' : '' ) . self::rich( $design[ $part . '_text' ], $values, $warnings );
    }

    /** Render classic core email contents inside the selected branded frame. */
    private static function render( WC_Email $email, array $design, array &$strings, bool $collect = true ): array {
        $fields = $design['templates'][ self::template_id( $email ) ] ?? array();
        $values = self::values( $email->object );
        if ( ! $email->is_customer_email() ) { unset( $values['_order'] ); }
        $subject = ! empty( $fields['subject'] ) ? self::replace( $fields['subject'], $values ) : $email->get_subject();
        $native_heading = $email->get_heading();
        $heading = ! empty( $fields['heading'] ) ? self::replace( $fields['heading'], $values ) : $native_heading;
        $copy = static function ( $translation, $source ) use ( &$strings, $fields, $values, $email, $collect ) {
            $repeated_intro = 'customer_completed_order' === $email->id && 'We have finished processing your order.' === $source;
            $override = isset( $fields['copy'][ $source ] );
            if ( ( ! $collect && ! $override && ! $repeated_intro ) || ! self::is_template_text() ) { return $translation; }
            if ( $collect && count( $strings ) < 160 && strlen( $source ) <= 1200 ) { $strings[ $source ] = $translation; }
            if ( ! $override ) {
                if ( $repeated_intro ) { return ''; }
                return $translation;
            }
            $safe_values = str_contains( $source, '%' ) ? array_map( static fn( $value ) => is_scalar( $value ) ? str_replace( '%', '%%', (string) $value ) : $value, $values ) : $values;
            return self::replace( $fields['copy'][ $source ], $safe_values );
        };
        $mailer = WC()->mailer();
        $header = array( $mailer, 'email_header' );
        $footer = array( $mailer, 'email_footer' );
        $hp = has_action( 'woocommerce_email_header', $header );
        $fp = has_action( 'woocommerce_email_footer', $footer );
        if ( false !== $hp ) { remove_action( 'woocommerce_email_header', $header, $hp ); }
        if ( false !== $fp ) { remove_action( 'woocommerce_email_footer', $footer, $fp ); }
        $domains = array( 'woocommerce' );
        if ( self::email_source( $email ) ) {
            $domains = array_merge( $domains, array( 'woocommerce-germanized', 'woocommerce-germanized-pro', 'storeabill', 'shiptastic', 'eu-order-withdrawal-button-for-woocommerce' ) );
        }
        foreach ( $domains as $domain ) { add_filter( 'gettext_' . $domain, $copy, 20, 2 ); }
        $buffer_level = ob_get_level();
        try {
            $body = $email->get_content_html();
        } finally {
            while ( ob_get_level() > $buffer_level ) { ob_end_clean(); }
            foreach ( $domains as $domain ) { remove_filter( 'gettext_' . $domain, $copy, 20 ); }
            if ( false !== $hp ) { add_action( 'woocommerce_email_header', $header, $hp ); }
            if ( false !== $fp ) { add_action( 'woocommerce_email_footer', $footer, $fp ); }
        }
        if ( ! is_string( $body ) || '' === trim( $body ) ) { throw new RuntimeException( 'Empty email body.' ); }
        $body = self::deduplicate_heading( $body, $heading, $native_heading );
        $warnings = array( 'missing' => array(), 'resolved' => array() );
        $extra = self::rich( $fields['extra'] ?? '', $values, $warnings );
        $html = self::layout( $body . $extra, $heading, $design, $values, $warnings );
        return array( 'subject' => sanitize_text_field( $subject ), 'heading' => $heading, 'html' => $html, 'unresolved' => array_values( array_unique( $warnings['missing'] ) ), 'resolved' => $warnings['resolved'] );
    }

    /** Remove a repeated title from an embedded template, preserving unrelated section headings. */
    private static function deduplicate_heading( string $html, string ...$headings ): string {
        $normalize = static fn( $text ) => trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ?? '' );
        $names = array_filter( array_map( $normalize, $headings ) );
        $html = preg_replace_callback( '~<(h[12])\b[^>]*>(.*?)</\1\s*>~is', static function ( $match ) use ( $names, $normalize ) {
            return in_array( $normalize( $match[2] ), $names, true ) ? '' : $match[0];
        }, $html ) ?? $html;
        return preg_replace( '~<p(?:\s[^>]*)?>\s*</p>~i', '', $html ) ?? $html;
    }

    /** Exclude translations from country lists, formatters and other nested data providers. */
    private static function is_template_text(): bool {
        foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 14 ) as $frame ) {
            if ( ! in_array( $frame['function'], array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' ), true ) ) { continue; }
            $file = $frame['file'] ?? '';
            if ( str_starts_with( $file, ABSPATH . WPINC . '/' ) ) { continue; }
            return str_starts_with( $file, WC_ABSPATH . 'templates/emails/' )
                || ( str_contains( $file, '/templates/emails/' ) && self::germanized_source( (string) realpath( $file ) ) );
        }
        return false;
    }

    /** Build a fluid email frame with a fixed-width fallback for classic Outlook. */
    private static function layout( string $body, string $heading, array $design, array $values, array &$warnings ): string {
        $style = $design['style'];
        list( $bg, $paper, $ink, $accent, $brand_bg, $brand_ink, $font, $pad ) = self::styles()[ $style ];
        $bg = ( $design['canvas'] ?? '' ) ?: $bg;
        $accent = ( $design['accent'] ?? '' ) ?: $accent;
        $paper = ( $design['paper'] ?? '' ) ?: $paper;
        $ink = ( $design['ink'] ?? '' ) ?: $ink;
        $link = self::readable_accent( $accent, $paper, $ink );
        $center = in_array( $style, array( 'journal', 'postcard' ), true );
        $align = $center ? 'center' : ( is_rtl() ? 'right' : 'left' );
        $start = is_rtl() ? 'right' : 'left';
        $header_align = match ( $design['header_align'] ?? '' ) { 'center' => 'center', 'start' => $start, 'end' => is_rtl() ? 'left' : 'right', default => $align };
        $footer_align = match ( $design['footer_align'] ?? '' ) { 'center' => 'center', 'start' => $start, 'end' => is_rtl() ? 'left' : 'right', default => $align };
        $brand = self::brand( $design, 'header', $values, $warnings );
        $footer = self::brand( $design, 'footer', $values, $warnings );
        $copyright = self::replace( $design['footer_copyright'] ?? '', $values );
        $ref = isset( $values['order_number'] ) ? '#' . esc_html( $values['order_number'] ) : '';
        $label = esc_html__( 'Order', 'tools-for-woocommerce' );
        $ref = $ref ? '<span style="font-size:12px;font-weight:bold;letter-spacing:1px">' . $label . ' ' . $ref . '</span>' : '';
        $heading = esc_html( $heading );
        $hero = '<h1 style="font-size:' . ( 'ledger' === $style ? 25 : 34 ) . 'px;line-height:1.15;margin:8px 0 0;font-weight:' . ( $center ? 'normal' : 'bold' ) . '">' . $heading . '</h1>';
        $brand_css = 'background:' . $brand_bg . ';color:' . $brand_ink;
        $brand_pad = 'padding:24px ' . $pad . 'px';
        $headline = match ( $style ) {
            'studio' => self::row( $ref . $hero, 'padding:32px ' . $pad . 'px 26px;border-bottom:1px solid #dedfd8;color:' . $ink, $align ),
            'journal' => self::row( '<div style="border-top:3px double ' . $accent . ';border-bottom:1px solid ' . $accent . ';padding:25px 8px">' . $ref . $hero . '</div>', 'padding:0 ' . $pad . 'px 22px;color:' . $ink, $align ),
            'atelier' => self::row( '<div style="background:#edf5ef;border-left:5px solid ' . $accent . ';padding:20px">' . $hero . '<div style="margin-top:12px">' . $ref . '</div></div>', 'padding:20px ' . $pad . 'px 5px;color:' . $ink, $align ),
            'signal' => self::row( $hero . ( $ref ? '<div style="margin-top:16px;padding-top:12px;border-top:1px solid #8195c6">' . $ref . '</div>' : '' ), $brand_css . ';padding:12px ' . $pad . 'px 28px;border-bottom:6px solid ' . $accent, $align ),
            'ledger' => self::row( '<div style="border:1px solid #c7cdd4;padding:14px">' . $ref . $hero . '</div>', 'padding:20px ' . $pad . 'px 8px;color:' . $ink, $align ),
            'letter' => self::row( $hero . '<div style="margin-top:14px;color:' . $link . '">' . $ref . '</div>', 'padding:12px ' . $pad . 'px 20px;border-top:1px solid #ccbca6;color:' . $ink, $start ),
            'parcel' => self::row( '<div style="background:#fff1d9;border:1px solid #e5c896;padding:20px">' . ( $brand ? '' : $ref ) . $hero . '</div>', 'padding:20px ' . $pad . 'px 0;color:' . $ink, $start ),
            'gallery' => self::row( $hero . '<div style="margin-top:12px">' . $ref . '</div>', 'padding:26px ' . $pad . 'px;background:#f7effa;color:' . $ink, 'center' ),
            'postcard' => self::row( '<div style="padding:12px 0 22px">' . $hero . '</div>' . ( $ref ? '<div style="display:inline-block;background:#ffffff;color:#532b20;padding:6px 12px">' . $ref . '</div>' : '' ), $brand_css . ';padding:0 ' . $pad . 'px 24px;border-bottom:4px solid ' . $accent, $align ),
        };
        if ( 'parcel' === $style && $brand && $ref ) {
            $brand .= '<div style="margin-top:10px;color:#fff1d9">' . $ref . '</div>';
        }
        if ( 'letter' === $style && $footer ) {
            $footer = '<div style="border-top:1px solid #ccbca6;padding-top:14px;font-style:italic">' . $footer . '</div>';
        }
        $brand_row = '' === $brand ? '' : self::row( self::style_content( $brand, $style, $brand_ink, $brand_ink ), $brand_css . ';' . $brand_pad, $header_align );
        $body_row = self::row( self::style_content( $body, $style, $ink, $link ), 'padding:25px ' . $pad . 'px 30px;color:' . $ink, is_rtl() ? 'right' : 'left' );
        $footer_row = '';
        if ( ! empty( $design['footer_enabled'] ) ) {
            $footer_row = '' === $footer ? '' : self::row( self::style_content( $footer, $style, $brand_ink, $brand_ink ), $brand_css . ';' . $brand_pad . ';font-size:14px;border-top:3px solid ' . $accent, $footer_align );
            if ( '' !== $copyright ) {
                $footer_row .= self::row( esc_html( $copyright ), 'background:#f5f5f5;color:#1b1b1b;padding:13px ' . $pad . 'px;font-size:12px', $footer_align );
            }
        }
        $frame_css = match ( $design['frame'] ?? '' ) {
            'outline' => 'border:1px solid #a9abb0;',
            'card' => 'border:1px solid #b8bbc2;border-radius:12px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.10);',
            default => '',
        };
        return '<!doctype html><html dir="' . ( is_rtl() ? 'rtl' : 'ltr' ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>@media(max-width:480px){.ddw-twc-cell{padding-left:14px!important;padding-right:14px!important}.ddw-twc-cell h1{font-size:26px!important}.ddw-twc-cell img{max-width:100%!important;height:auto!important}.ddw-twc-cell .address-container td.address-cell,.ddw-twc-cell #addresses>tbody>tr>td{display:block!important;width:auto!important}.ddw-twc-cell .ddw-twc-data-cell{padding:7px 8px!important}.ddw-twc-cell a{overflow-wrap:anywhere}.ddw-twc-cell .email-order-item-thumbnail{width:38px!important}.ddw-twc-cell .email-order-item-thumbnail img{width:32px!important}}.ddw-twc-gallery-items .order_item td.td{border-bottom:8px solid #fff}</style></head>'
            . '<body style="margin:0;background:' . $bg . '"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="' . $bg . '"><tr><td align="center" style="padding:18px 8px">'
            . '<!--[if mso]><table role="presentation" width="620"><tr><td><![endif]-->'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="' . $paper . '" style="max-width:620px;' . $frame_css . 'color:' . $ink . ';font-family:' . $font . ';font-size:16px;line-height:1.55">'
            . $brand_row . $headline . $body_row . $footer_row
            . '</table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
    }

    private static function row( string $html, string $css, string $align ): string {
        return '<tr><td class="ddw-twc-cell" align="' . esc_attr( $align ) . '" style="' . esc_attr( $css ) . '">' . $html . '</td></tr>';
    }

    /** Keep user-selected accents from producing unreadable links. */
    private static function readable_accent( string $color, string $paper, string $fallback ): string {
        $luminance = static function ( string $hex ): float {
            $hex = ltrim( $hex, '#' );
            if ( 3 === strlen( $hex ) ) { $hex = preg_replace( '/(.)/', '$1$1', $hex ); }
            $channels = array_map( static function ( $pair ) {
                $value = hexdec( $pair ) / 255;
                return $value <= 0.04045 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
            }, str_split( $hex, 2 ) );
            return $channels[0] * .2126 + $channels[1] * .7152 + $channels[2] * .0722;
        };
        $a = $luminance( $color );
        $b = $luminance( $paper );
        return ( max( $a, $b ) + .05 ) / ( min( $a, $b ) + .05 ) >= 4.5 ? $color : $fallback;
    }

    /** Restyle native tables in place to keep product, download and extension data intact. */
    private static function style_content( string $body, string $style, string $ink, string $accent ): string {
        $html = new WP_HTML_Tag_Processor( $body );
        $cells = match ( $style ) {
            'ledger' => array( 'background:#f0f2f5;border:1px solid #c7cdd4', 'border:1px solid #c7cdd4' ),
            'journal' => array( 'border-bottom:3px double #b99e84', 'border:0;border-bottom:1px dotted #c6b5a3' ),
            'atelier' => array( 'background:#e1efe6;border:0', 'background:#f1f7f2;border:0;border-bottom:4px solid #fff' ),
            'signal' => array( 'background:#e1e9fc;border:0', 'background:#f3f6ff;border:0;border-bottom:2px solid #fff' ),
            'letter' => array( 'border:0;border-bottom:1px solid #bfae99', 'border:0' ),
            'parcel' => array( 'background:#efe2ce;border:0', 'background:#fff8ec;border:0;border-bottom:3px solid #fff' ),
            'gallery' => array( 'background:#f4e9f8;border:0', 'background:#faf5fc;border:0;border-top:6px solid #fff;border-bottom:6px solid #fff' ),
            'postcard' => array( 'background:#f6d2c3;border:0', 'background:#fff1e9;border:0' ),
            default => array( 'background:#eff0eb;border:0', 'border:0' ),
        };
        $styles = array(
            'A' => 'color:' . $accent . ';text-decoration:underline',
            'P' => 'margin:0 0 14px',
            'TABLE' => 'border-collapse:collapse;max-width:100%;border:0',
            'TH' => 'padding:10px 12px;vertical-align:top;color:' . $ink . ';' . $cells[0],
            'TD' => 'padding:10px 12px;vertical-align:top;overflow-wrap:anywhere;' . $cells[1],
        );
        while ( $html->next_tag() ) {
            $tag = $html->get_tag();
            if ( ! isset( $styles[ $tag ] ) ) { continue; }
            if ( in_array( $tag, array( 'TD', 'TH' ), true ) && ! $html->has_class( 'td' ) ) { continue; }
            if ( in_array( $tag, array( 'TD', 'TH' ), true ) ) { $html->add_class( 'ddw-twc-data-cell' ); }
            $existing = $html->get_attribute( 'style' );
            if ( 'TABLE' === $tag ) { $html->remove_attribute( 'border' ); }
            if ( 'gallery' === $style && 'TD' === $tag ) { $styles['TD'] = 'padding:14px;overflow-wrap:anywhere;' . $cells[1]; }
            if ( 'gallery' === $style && 'TABLE' === $tag && $html->has_class( 'email-order-details' ) ) {
                $html->add_class( 'ddw-twc-gallery-items' );
            }
            $html->set_attribute( 'style', ( is_string( $existing ) ? $existing . ';' : '' ) . $styles[ $tag ] );
        }
        return $html->get_updated_html();
    }

    public static function outgoing( array $params, WC_Email $email ): array {
        $design = self::state()['live'] ?? array();
        if ( empty( $design['templates'][ self::template_id( $email ) ]['enabled'] ) || 'html' !== $email->get_email_type() ) { return $params; }
        if ( ! isset( self::emails()[ self::template_id( $email ) ] ) ) { return $params; }
        try {
            $strings = array();
            $rendered = self::render( $email, $design, $strings, false );
            $params[1] = $rendered['subject'];
            $params[2] = $rendered['html'];
        } catch ( Throwable $error ) {
            wc_get_logger()->warning( 'Email Designer could not render ' . self::template_id( $email ) . '; native email used.', array( 'source' => 'tools-for-woocommerce' ) );
        }
        return $params;
    }

    public static function routes(): void {
        foreach ( array( '' => array( 'GET', 'get' ), '/draft' => array( 'POST', 'save' ), '/publish' => array( 'POST', 'publish' ), '/preview' => array( 'POST', 'preview' ), '/preview-option' => array( 'POST', 'enable_preview_option' ), '/style-preview' => array( 'POST', 'style_preview' ), '/test' => array( 'POST', 'test' ), '/pages' => array( 'GET', 'search_pages' ), '/migration' => array( 'GET', 'migration' ) ) as $route => $handler ) {
            register_rest_route( self::API, '/email' . $route, array(
                'methods' => $handler[0],
                'callback' => array( self::class, $handler[1] ),
                'permission_callback' => static fn() => current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ),
            ) );
        }
    }

    public static function get(): array {
        $emails = array();
        $state = self::state();
        foreach ( self::emails() as $id => $email ) {
            $emails[] = array( 'id' => $id, 'title' => wp_strip_all_tags( $email->get_title() ), 'type' => $email->get_email_type(), 'order' => self::uses_order( $id ), 'source' => self::email_source( $email ),
                'enabled' => $email->is_enabled(),
                'admin' => ! $email->is_customer_email(),
                'recipient' => $email->is_customer_email() ? '' : sanitize_text_field( (string) $email->get_option( 'recipient', get_option( 'admin_email' ) ) ),
                'live' => ! empty( $state['live']['templates'][ $id ]['enabled'] ),
                'settingsUrl' => add_query_arg( array( 'page' => 'wc-settings', 'tab' => 'email', 'section' => strtolower( (string) ( array_search( $email, WC()->mailer()->get_emails(), true ) ?: $id ) ) ), admin_url( 'admin.php' ) ),
            );
        }
        return array( 'previewOptions' => array( 'realOrder' => DDW_TWC_Commerce::enabled( 'preview_real_orders' ), 'externalImages' => DDW_TWC_Commerce::enabled( 'preview_external_images' ) ), 'revision' => self::revision( $state ), 'woocommerceBranding' => self::woocommerce_branding(), 'senderSettingsUrl' => admin_url( 'admin.php?page=wc-settings&tab=email' ), 'emails' => $emails, 'design' => $state['draft'] ?? $state['live'] ?? self::clean( array() ), 'styles' => array_keys( self::styles() ), 'migration' => DDW_TWC_Email_CxThemes::sources(), 'importedLive' => $state['imported_live'] ?? null, 'published' => isset( $state['live'] ), 'saved' => isset( $state['draft'] ), 'conflicts' => self::conflicts(), 'tags' => DDW_TWC_Email_Tags::catalog(), 'address' => wp_get_current_user()->user_email );
    }

    /** Read the saved WooCommerce branding without modifying either design. */
    private static function woocommerce_branding(): array {
        $candidate = array();
        $logo = esc_url_raw( (string) get_option( 'woocommerce_email_header_image', '' ), array( 'https', 'http' ) );
        if ( $logo ) {
            $candidate['header_logo'] = $logo;
            $candidate['header_logo_width'] = get_option( 'woocommerce_email_header_image_width', 120 );
        }
        $align = get_option( 'woocommerce_email_header_alignment', '' );
        if ( in_array( $align, array( 'left', 'right', 'center' ), true ) ) {
            $candidate['header_align'] = 'center' === $align ? 'center' : ( ( 'right' === $align ) === is_rtl() ? 'start' : 'end' );
        }
        $footer = (string) get_option( 'woocommerce_email_footer_text', '' );
        if ( '' !== trim( $footer ) ) {
            $candidate['footer_text'] = strtr( $footer, array(
                '{site_title}' => '{shop_name}',
                '{site_url}' => esc_url( home_url() ),
                '{store_email}' => sanitize_email( (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ) ),
                '{store_address}' => esc_html( WC()->countries->get_base_address() ),
            ) );
        }
        foreach ( array( 'accent' => 'base_color', 'canvas' => 'background_color', 'paper' => 'body_background_color', 'ink' => 'text_color' ) as $field => $option ) {
            $color = sanitize_hex_color( (string) get_option( 'woocommerce_email_' . $option, '' ) );
            if ( $color ) { $candidate[ $field ] = $color; }
        }
        $clean = self::clean( $candidate, true );
        return array_filter( array_intersect_key( $clean, $candidate ), static fn( $value ) => '' !== $value );
    }

    private static function sender( WC_Email $email ): array {
        $from_name = $email->get_from_name();
        $from_address = $email->get_from_address();
        $reply_name = $reply_address = '';
        if ( $email->is_customer_email() && is_callable( array( $email, 'get_reply_to_enabled' ) ) && $email->get_reply_to_enabled() ) {
            $address = $email->get_reply_to_address();
            if ( is_email( $address ) && 0 !== strcasecmp( $address, $from_address ) ) {
                $reply_address = $address;
                $reply_name = $email->get_reply_to_name() ?: $from_name;
            }
        }
        return array( 'name' => $from_name, 'address' => $from_address, 'replyName' => $reply_name, 'replyAddress' => $reply_address );
    }

    public static function migration( WP_REST_Request $request ): array {
        $source = sanitize_key( self::string( $request->get_param( 'source' ), 32 ) );
        $available = array_map( static fn( WC_Email $email ) => wp_strip_all_tags( $email->get_title() ), self::emails() );
        return DDW_TWC_Email_CxThemes::inspect( $source, $available );
    }

    public static function search_pages( WP_REST_Request $request ): array {
        $query = sanitize_text_field( self::string( $request->get_param( 'q' ), 100 ) );
        return strlen( $query ) >= 2 ? array_values( DDW_TWC_Email_Tags::search( $query ) ) : array();
    }

    /** Match active plugin basenames on the current site and network. */
    private static function conflicts(): array {
        $known = array(
            'kadence-woocommerce-email-designer' => 'Kadence WooCommerce Email Designer',
            'woocommerce-email-control' => 'WooCommerce Email Customizer (cxThemes)',
            'yaymail' => 'YayMail',
            'yaymail-pro' => 'YayMail Pro',
            'email-customizer-for-woocommerce' => 'Email Customizer for WooCommerce',
            'email-customizer-woocommerce' => 'Email Template Builder for WooCommerce',
            'woocommerce-email-customizer' => 'WooCommerce Email Customizer',
            'decorator-woocommerce-email-customizer' => 'Decorator',
            'yeemail' => 'YeeMail',
        );
        $active = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
        $found = array();
        foreach ( $active as $file ) {
            $slug = dirname( $file );
            if ( isset( $known[ $slug ] ) ) { $found[] = $known[ $slug ]; }
        }
        if ( \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'block_email_editor' ) ) {
            $found[] = __( 'WooCommerce block email editor', 'tools-for-woocommerce' );
        }
        return array_values( array_unique( $found ) );
    }

    private static function write( WP_REST_Request $request, bool $publish ): array|WP_Error {
        if ( strlen( (string) $request->get_body() ) > 524288 || ! is_array( $request->get_param( 'design' ) ) ) {
            return new WP_Error( 'ddw_twc_design', __( 'Invalid or oversized email design.', 'tools-for-woocommerce' ), array( 'status' => 400 ) );
        }
        $design = self::clean( $request->get_param( 'design' ) );
        return DDW_TWC_Write_Lock::run( static function () use ( $design, $request, $publish ) {
            $state = self::state();
            $revision = $request->get_param( 'revision' );
            if ( null !== $revision && self::revision( $state ) !== $revision ) {
                return new WP_Error( 'ddw_twc_stale', __( 'The email design changed in another session. Reload the editor before saving.', 'tools-for-woocommerce' ), array( 'status' => 409 ) );
            }
            $previous = $state['draft']['library'] ?? $state['live']['library'] ?? array();
            foreach ( $design['library'] as $id => &$entry ) {
                $old = $previous[ $id ] ?? array();
                $entry['updated'] = ( $old['name'] ?? null ) === $entry['name'] && ( $old['branding'] ?? null ) === $entry['branding'] ? (int) ( $old['updated'] ?? 0 ) : time();
            }
            unset( $entry );
            $state['draft'] = $design;
            if ( $publish ) { $state['live'] = $design; }
            update_option( self::OPTION, $state, false );
            if ( self::state() !== $state ) { return new WP_Error( 'ddw_twc_write', __( 'Could not save the email design.', 'tools-for-woocommerce' ), array( 'status' => 500 ) ); }
            return array( 'design' => $design, 'revision' => self::revision( $state ) );
        } );
    }

    public static function save( WP_REST_Request $request ): array|WP_Error { return self::write( $request, false ); }
    public static function publish( WP_REST_Request $request ): array|WP_Error { return self::write( $request, true ); }

    public static function enable_preview_option( WP_REST_Request $request ): array|WP_Error {
        $key = $request->get_param( 'option' );
        if ( 'preview_real_orders' === $key && ! current_user_can( 'edit_shop_orders' ) ) {
            return new WP_Error( 'ddw_twc_preview_permission', __( 'You cannot access order data.', 'tools-for-woocommerce' ), array( 'status' => 403 ) );
        }
        $result = DDW_TWC_Settings::enable_preview_option( is_string( $key ) ? $key : '' );
        if ( is_wp_error( $result ) ) { return $result; }
        return $result ? array( 'enabled' => true ) : new WP_Error( 'ddw_twc_write', __( 'Could not save the shop settings.', 'tools-for-woocommerce' ), array( 'status' => 500 ) );
    }

    private static function uses_order( string $id ): bool {
        return ! str_starts_with( $id, 'WC_Email_TfWC_GZD_' ) && ! in_array( $id, array( 'WC_Email_Customer_New_Account', 'WC_Email_Customer_Reset_Password', 'WC_Email_Admin_Payment_Gateway_Enabled', self::VERIFY_ID ), true );
    }

    private static function sample( WP_REST_Request $request, bool $demo ): array|WP_Error {
        $id = $request->get_param( 'email' );
        $emails = self::emails();
        if ( ! is_string( $id ) || ! isset( $emails[ $id ] ) || strlen( (string) $request->get_body() ) > 524288 ) {
            return new WP_Error( 'ddw_twc_email', __( 'Invalid email preview request.', 'tools-for-woocommerce' ), array( 'status' => 400 ) );
        }
        $position = min( 99, max( 0, (int) $request->get_param( 'position' ) ) );
        $count = 0;
        $order = null;
        if ( ! $demo && true === $request->get_param( 'realOrder' ) && self::uses_order( $id ) && current_user_can( 'edit_shop_orders' ) ) {
            $query = wc_get_orders( array( 'status' => 'completed', 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ), 'order' => 'DESC', 'limit' => 1, 'offset' => $position, 'paginate' => true ) );
            $count = min( 100, (int) $query->total );
            $candidate = $query->orders[0] ?? null;
            if ( $candidate && current_user_can( 'edit_shop_order', $candidate->get_id() ) ) { $order = $candidate; }
        }
        $preview = \Automattic\WooCommerce\Internal\Admin\EmailPreview\EmailPreview::instance();
        $preview_mode = static fn() => true;
        $mask_item_meta = static fn( $meta, $item ) => $item instanceof WC_Order_Item_Product ? array() : $meta;
        try {
            $preview->set_email_type( get_class( $emails[ $id ] ) );
            $email = clone $preview->get_email();
            if ( $order ) {
                add_filter( 'woocommerce_is_email_preview', $preview_mode );
                add_filter( 'woocommerce_order_item_get_formatted_meta_data', $mask_item_meta, PHP_INT_MAX, 2 );
                $masked = clone $order;
                $masked->set_customer_note( '' );
                foreach ( array( 'billing', 'shipping' ) as $address_type ) {
                    $address = $masked->get_address( $address_type );
                    foreach ( array( 'last_name', 'email', 'phone', 'company', 'address_1', 'address_2', 'postcode', 'city' ) as $field ) {
                        if ( array_key_exists( $field, $address ) ) { $address[ $field ] = ''; }
                    }
                    $masked->set_address( $address, $address_type );
                }
                $email->set_object( $masked );
                $email->placeholders['{order_number}'] = $order->get_order_number();
                $email->placeholders['{order_date}'] = $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '';
            } else {
                $preview->set_up_filters();
            }
            $strings = array();
            $out = self::render( $email, self::clean( $request->get_param( 'design' ) ), $strings );
        } catch ( Throwable $error ) {
            return new WP_Error( 'ddw_twc_preview', __( 'WooCommerce could not render this email preview.', 'tools-for-woocommerce' ), array( 'status' => 422 ) );
        } finally {
            if ( $order ) {
                remove_filter( 'woocommerce_is_email_preview', $preview_mode );
                remove_filter( 'woocommerce_order_item_get_formatted_meta_data', $mask_item_meta, PHP_INT_MAX );
            } else { $preview->clean_up_filters(); }
        }
        // Previews and test emails have inert links, including download and reset URLs.
        $html = new WP_HTML_Tag_Processor( $out['html'] );
        while ( $html->next_tag( 'A' ) ) { $html->remove_attribute( 'href' ); }
        $out['html'] = $html->get_updated_html();
        $out['copy'] = array_filter( $strings, static fn( $source ) => ! str_contains( (string) $source, '<' ), ARRAY_FILTER_USE_KEY );
        $out['sender'] = self::sender( $email );
        $out['context'] = $order ? array( 'name' => $order->get_billing_first_name(), 'number' => $order->get_order_number(), 'position' => $position + 1, 'count' => $count ) : null;
        return $out;
    }

    public static function preview( WP_REST_Request $request ): array|WP_Error { return self::sample( $request, false ); }

    public static function style_preview( WP_REST_Request $request ): array|WP_Error {
        $request->set_param( 'email', 'WC_Email_Customer_Completed_Order' );
        return self::sample( $request, true );
    }

    public static function test( WP_REST_Request $request ): array|WP_Error {
        $address = wp_get_current_user()->user_email;
        $key = 'ddw_twc_mail_test_' . get_current_user_id();
        if ( get_transient( $key ) ) { return new WP_Error( 'ddw_twc_rate', __( 'Wait a minute before sending another test email.', 'tools-for-woocommerce' ), array( 'status' => 429 ) ); }
        $result = self::sample( $request, true );
        if ( is_wp_error( $result ) ) { return $result; }
        set_transient( $key, 1, MINUTE_IN_SECONDS );
        if ( ! wp_mail( $address, '[TEST] ' . $result['subject'], $result['html'], array( 'Content-Type: text/html; charset=UTF-8' ) ) ) {
            return new WP_Error( 'ddw_twc_test_failed', __( 'WordPress could not hand off the test email.', 'tools-for-woocommerce' ), array( 'status' => 502 ) );
        }
        return array( 'accepted' => true );
    }
}
