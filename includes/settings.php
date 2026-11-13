<?php
/** Site-local reversible configuration. SPDX-License-Identifier: GPL-2.0-or-later */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'DDW_TWC_Settings', false ) ) {
/** Owns website settings, presets, data transfer and the administration interface. */
final class DDW_TWC_Settings {
    const OPTION = 'ddw_twc_settings';
    /**
     * Provide conservative defaults for a fresh website.
     * @return array
     */
    public static function defaults() {
        return array(
            'hide_marketplace_ads' => true, 'hide_payment_ads' => true,
            'hide_shipping_ads' => true, 'hide_promo_notes' => true,
            'hide_setup_tasks' => true, 'hide_setup_redirect' => false,
            'hide_footer_prompt' => true,
            'hide_marketplace_menu' => false, 'hide_marketing_menu' => false,
            'hide_payments_menu' => false,
            'split_admin_menu' => false, 'settings_map_menu' => false, 'settings_map_menu_label' => '', 'rename_menu' => false, 'rename_dashboard' => false, 'rename_admin_texts' => false,
            'admin_label' => 'WooCommerce', 'admin_icon' => '',
            'hide_downloads_box' => false, 'hide_attribution_box' => false,
            'hide_customer_history_box' => false,
            'operation_mode' => 'standard', 'checkout_texts' => false,
            'trim_checkout' => false, 'shipping_notice' => false,
            'catalog_enabled' => false, 'catalog_hide_prices' => false,
            'catalog_product_context' => false, 'catalog_message' => '', 'catalog_contact_url' => '', 'catalog_contact_label' => '',
            'rules_enabled' => false, 'rules_single' => false,
            'rules_min_quantity' => 0, 'rules_max_quantity' => 0,
            'rules_min_line' => 0, 'rules_max_line' => 0, 'rules_step' => 1,
            'rules_min_amount' => '0', 'rules_max_amount' => '0',
            'donation_enabled' => false, 'donation_text' => '', 'donation_url' => '', 'donation_label' => '',
            'donation_shop' => false, 'donation_product' => false, 'donation_cart' => false,
            'donation_email' => false, 'donation_email_physical' => '', 'donation_email_downloads' => '',
            'donation_checkout' => true, 'donation_thankyou' => false, 'delete_data' => false, 'preview_real_orders' => false, 'preview_external_images' => false,
        );
    }
    /**
     * Read current settings and translate earlier configuration without a database write.
     * @return array
     */
    public static function options() {
        $stored = get_option( self::OPTION, null );
        if ( null === $stored ) { $stored = get_option( 'ddw_purify_woocommerce', array() ); }
        $stored = is_array( $stored ) ? $stored : array();
        if ( ! isset( $stored['operation_mode'] ) && ( isset( $stored['paymentless'] ) || isset( $stored['cleanup'] ) ) ) {
            $legacy = array_merge( array( 'cleanup' => true, 'rename_admin' => true, 'admin_label' => 'Free offers', 'paymentless' => true, 'checkout_texts' => true, 'trim_checkout' => true, 'order_view' => true ), $stored );
            $out = self::defaults();
            foreach ( array( 'hide_marketplace_ads', 'hide_payment_ads', 'hide_shipping_ads', 'hide_promo_notes', 'hide_setup_tasks', 'hide_setup_redirect', 'hide_footer_prompt', 'hide_marketplace_menu', 'hide_marketing_menu' ) as $key ) { $out[$key] = (bool) $legacy['cleanup']; }
            foreach ( array( 'rename_menu', 'rename_dashboard', 'rename_admin_texts' ) as $key ) { $out[$key] = (bool) $legacy['rename_admin']; }
            $out['admin_label'] = $legacy['admin_label'];
            $out['admin_icon'] = $legacy['rename_admin'] ? 'dashicons-album' : '';
            $out['operation_mode'] = $legacy['paymentless'] ? 'free_physical' : 'standard';
            $out['hide_payments_menu'] = (bool) $legacy['paymentless'];
            $out['checkout_texts'] = (bool) $legacy['checkout_texts'] && (bool) $legacy['paymentless'];
            $out['trim_checkout'] = (bool) $legacy['trim_checkout'] && (bool) $legacy['paymentless'];
            foreach ( array( 'hide_downloads_box', 'hide_attribution_box', 'hide_customer_history_box', 'shipping_notice' ) as $key ) { $out[$key] = (bool) $legacy['order_view'] && (bool) $legacy['paymentless']; }
            return self::normalize( $out );
        }
        return self::normalize( array_merge( self::defaults(), $stored ) );
    }
    /**
     * Return the selected starting configuration without applying it.
     * @param string $name Selected preset name.
     * @param string $variant Free-offer delivery variant.
     * @return array
     */
    public static function preset( $name, $variant = 'physical' ) {
        if ( 'individual' === $name ) { return self::options(); }
        $out = self::defaults();
        if ( 'free' !== $name ) { return $out; }
        $downloads = 'downloads' === $variant;
        $out['operation_mode'] = $downloads ? 'free_downloads' : 'free_physical';
        $out['hide_payments_menu'] = true;
        $out['rename_menu'] = true;
        $out['rename_dashboard'] = true;
        $out['rename_admin_texts'] = ! $downloads;
        $out['admin_label'] = $downloads ? 'Downloads' : 'Free offers';
        $out['admin_icon'] = $downloads ? 'dashicons-download' : 'dashicons-album';
        $out['hide_downloads_box'] = ! $downloads;
        $out['hide_attribution_box'] = true;
        $out['hide_customer_history_box'] = true;
        $out['checkout_texts'] = true;
        $out['trim_checkout'] = true;
        $out['shipping_notice'] = ! $downloads;
        return $out;
    }
    /**
     * List allowed native menu icons.
     * @return array
     */
    public static function icons() { return array( '', 'dashicons-cart', 'dashicons-album', 'dashicons-download', 'dashicons-store' ); }
    /**
     * Normalize known scalar settings and preserve the settings schema.
     * @param array|mixed $input Submitted settings candidate.
     * @return array
     */
    private static function normalize( $input ) {
        $out = self::defaults();
        foreach ( $out as $key => $default ) {
            $value = $input[$key] ?? $default;
            if ( in_array( $key, array( 'rules_min_quantity', 'rules_max_quantity', 'rules_min_line', 'rules_max_line', 'rules_step' ), true ) ) {
                $out[$key] = is_scalar( $value ) && is_numeric( $value ) && (float) $value >= 0 && floor( (float) $value ) === (float) $value ? min( 1000000, (int) $value ) : $default;
                if ( 'rules_step' === $key ) { $out[$key] = max( 1, $out[$key] ); }
            } elseif ( in_array( $key, array( 'rules_min_amount', 'rules_max_amount' ), true ) ) {
                $number = is_scalar( $value ) ? str_replace( ',', '.', (string) $value ) : '0';
                $out[$key] = is_numeric( $number ) && is_finite( (float) $number ) ? (string) round( max( 0, min( 1000000000, (float) $number ) ), function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 ) : '0';
            } elseif ( in_array( $key, array( 'catalog_contact_url', 'donation_url' ), true ) ) {
                $out[$key] = is_string( $value ) ? esc_url_raw( $value, array( 'https', 'http', 'mailto' ) ) : '';
            } elseif ( in_array( $key, array( 'catalog_message', 'donation_text', 'donation_email_physical', 'donation_email_downloads' ), true ) ) {
                $out[$key] = is_string( $value ) ? wp_html_excerpt( sanitize_textarea_field( $value ), 4000, '' ) : '';
            } elseif ( in_array( $key, array( 'catalog_contact_label', 'donation_label' ), true ) ) {
                $out[$key] = is_string( $value ) ? wp_html_excerpt( sanitize_text_field( $value ), 120, '' ) : '';
            } elseif ( 'operation_mode' === $key ) {
                $out[$key] = is_string( $value ) && in_array( $value, array( 'standard', 'free_physical', 'free_downloads' ), true ) ? $value : 'standard';
            } elseif ( 'admin_icon' === $key ) {
                $out[$key] = is_string( $value ) && in_array( $value, self::icons(), true ) ? $value : '';
            } elseif ( in_array( $key, array( 'admin_label', 'settings_map_menu_label' ), true ) ) {
                $label = is_string( $value ) ? sanitize_text_field( $value ) : '';
                $out[$key] = '' !== $label ? wp_html_excerpt( $label, 60, '' ) : $default;
            } else { $out[$key] = in_array( $value, array( true, 1, '1' ), true ); }
        }
        return $out;
    }
    /**
     * Register the native hooks owned by this module.
     * @return void
     */
    public static function register() {
        add_action( 'admin_post_ddw_twc_save_tab', array( __CLASS__, 'save_tab' ) );
        add_action( 'admin_post_ddw_twc_export', array( __CLASS__, 'export_settings' ) );
        add_action( 'admin_post_ddw_twc_import', array( __CLASS__, 'import_settings' ) );
        add_action( 'admin_post_ddw_twc_preset', array( __CLASS__, 'apply_preset' ) );
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'settings' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( DDW_TWC_FILE ), array( __CLASS__, 'links' ) );
        add_filter( 'plugin_row_meta', array( __CLASS__, 'meta' ), 10, 2 );
    }
    /**
     * Register the site-local Settings API schema.
     * @return void
     */
    public static function settings() {
        register_setting( 'ddw_twc', self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize' ), 'default' => self::defaults() ) );
    }
    /**
     * Validate submitted settings and retain the previous configuration on conflict.
     * @param array|mixed $input Submitted settings candidate.
     * @return array
     */
    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        // Missing checkboxes mean off on a submitted settings form.
        foreach ( self::defaults() as $key => $default ) {
            if ( is_bool( $default ) && ! isset( $input[$key] ) ) { $input[$key] = false; }
        }
        foreach ( array( 'rules_min_quantity', 'rules_max_quantity', 'rules_min_line', 'rules_max_line', 'rules_step', 'rules_min_amount', 'rules_max_amount' ) as $key ) {
            if ( ! isset( $input[$key] ) || '' === $input[$key] ) { continue; }
            $value = is_scalar( $input[$key] ) ? str_replace( ',', '.', (string) $input[$key] ) : '';
            if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 || ( ! str_contains( $key, 'amount' ) && floor( (float) $value ) !== (float) $value ) || ( 'rules_step' === $key && (float) $value < 1 ) ) {
                add_settings_error( self::OPTION, 'ddw_twc_number', __( 'Use nonnegative limits and positive whole-number quantity increments. Your previous settings were kept.', 'tools-for-woocommerce' ) ); return self::options();
            }
        }
        $out = self::normalize( $input );
        $error = self::conflict( $out );
        if ( $error ) { add_settings_error( self::OPTION, 'ddw_twc_conflict', $error ); return self::options(); }
        return $out;
    }
    /**
     * Explain contradictory quantity/value bounds without changing saved limits.
     * @param array $s Normalized candidate settings.
     * @return string Localized conflict or empty string.
     */
    public static function conflict( array $s ): string {
        if ( ! $s['rules_enabled'] || $s['rules_single'] ) { return ''; }
        foreach ( array( array( 'rules_min_quantity', 'rules_max_quantity' ), array( 'rules_min_line', 'rules_max_line' ), array( 'rules_min_line', 'rules_max_quantity' ), array( 'rules_min_amount', 'rules_max_amount' ) ) as $pair ) {
            if ( (float) $s[$pair[1]] > 0 && (float) $s[$pair[0]] > (float) $s[$pair[1]] ) { return __( 'A minimum must not exceed its maximum. Your previous settings were kept.', 'tools-for-woocommerce' ); }
        }
        $smallest = ceil( max( 1, $s['rules_min_line'] ) / $s['rules_step'] ) * $s['rules_step'];
        foreach ( array( 'rules_max_line', 'rules_max_quantity' ) as $key ) {
            if ( $s[$key] > 0 && $smallest > $s[$key] ) { return __( 'The quantity increment leaves no valid quantity within these limits. Your previous settings were kept.', 'tools-for-woocommerce' ); }
        }
        if ( $s['rules_max_quantity'] > 0 && ceil( max( 1, $s['rules_min_quantity'] ) / $s['rules_step'] ) * $s['rules_step'] > $s['rules_max_quantity'] ) { return __( 'The quantity increment leaves no valid quantity within these limits. Your previous settings were kept.', 'tools-for-woocommerce' ); }
        return '';
    }
    /**
     * Apply a deliberately selected preset after capability and nonce checks.
     * @return void
     */
    public static function apply_preset() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You are not allowed to change these settings.', 'tools-for-woocommerce' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'ddw_twc_preset' );
        $name = isset( $_POST['preset'] ) && is_string( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : '';
        $variant = isset( $_POST['variant'] ) && is_string( $_POST['variant'] ) ? sanitize_key( wp_unslash( $_POST['variant'] ) ) : '';
        if ( ! in_array( $name, array( 'tidy', 'free', 'individual' ), true ) || ( 'free' === $name && ! in_array( $variant, array( 'physical', 'downloads' ), true ) ) ) {
            wp_die( esc_html__( 'Please select a valid preset and delivery variant.', 'tools-for-woocommerce' ), '', array( 'response' => 400 ) );
        }
        $tab = 'free' === $name ? 'offers' : 'tidy';
        if ( 'individual' !== $name ) {
            $preset = self::preset( $name, $variant );
            $preset = array_filter( $preset, static fn( $key ) => ! str_starts_with( $key, 'donation_' ), ARRAY_FILTER_USE_KEY );
            if ( true !== DDW_TWC_Write_Lock::run( static fn() => self::persist( self::merge_tab( $tab, $preset ) ) ) ) {
                wp_die( esc_html__( 'Could not save the shop settings.', 'tools-for-woocommerce' ), '', array( 'response' => 500 ) );
            }
        }
        add_settings_error( 'general', 'settings_updated', __( 'Settings saved.', 'tools-for-woocommerce' ), 'success' );
        set_transient( 'settings_errors', get_settings_errors(), 30 );
        wp_safe_redirect( add_query_arg( 'settings-updated', 'true', self::tab_url( $tab ) ) );
        exit;
    }
    /**
     * Add the settings screen under WooCommerce or the WordPress settings fallback.
     * @return void
     */
    public static function menu() {
        if ( defined( 'WC_VERSION' ) && current_user_can( 'manage_options' ) ) {
            add_submenu_page( 'woocommerce', 'Tools for WooCommerce', 'Tools for WooCommerce', 'manage_options', 'tools-for-woocommerce', array( __CLASS__, 'page' ) );
        } else { add_options_page( 'Tools for WooCommerce', 'Tools for WooCommerce', 'manage_options', 'tools-for-woocommerce', array( __CLASS__, 'page' ) ); }
    }
    /** Return the current menu location, including the paused-dependency fallback. @return string */
    public static function url(): string { return admin_url( ( defined( 'WC_VERSION' ) ? 'admin.php' : 'options-general.php' ) . '?page=tools-for-woocommerce' ); }
    /**
     * Prepend the settings action link for authorized administrators.
     * @param array $links Existing native plugin-row links.
     * @return array
     */
    public static function links( $links ) {
        if ( current_user_can( 'manage_options' ) ) {
            array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'tools-for-woocommerce' ) . '</a>' );
        }
        return $links;
    }
    /**
     * Append optional project support links to this plugin row.
     * @param array $links Existing native plugin-row links.
     * @param string $file Native plugin basename.
     * @return array
     */
    public static function meta( $links, $file ) {
        if ( plugin_basename( DDW_TWC_FILE ) !== $file ) { return $links; }
        $links[] = '<a href="https://ko-fi.com/deckerweb" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Donate', 'tools-for-woocommerce' ) . '</a>';
        $links[] = '<a href="https://deckerweb.de/newsletter/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Newsletter', 'tools-for-woocommerce' ) . '</a>';
        return $links;
    }
    /**
     * Enqueue local assets only on this plugin settings screen.
     * @param string $hook Current administration screen hook.
     * @return void
     */
    public static function assets( $hook ) {
        if ( ! in_array( $hook, array( 'settings_page_tools-for-woocommerce', 'woocommerce_page_tools-for-woocommerce' ), true ) ) { return; }
        self::chrome_assets();
        if ( 'offers' === self::requested_tab() && class_exists( 'DDW_TWC_Practice_Tools', false ) ) {
            wp_enqueue_script( 'wc-enhanced-select' );
            wp_enqueue_style( 'woocommerce_admin_styles' );
        }
    }

    /** Enqueue the shared admin header, footer and changelog styles. */
    public static function chrome_assets(): void {
        wp_enqueue_style( 'ddw-twc-settings', plugins_url( 'assets/settings.css', DDW_TWC_FILE ), array(), DDW_TWC_VERSION . '.' . filemtime( dirname( DDW_TWC_FILE ) . '/assets/settings.css' ) );
        wp_enqueue_script( 'ddw-twc-settings', plugins_url( 'assets/settings.js', DDW_TWC_FILE ), array(), DDW_TWC_VERSION, true );
    }
    /**
     * Explain a missing or conflicting dependency to authorized administrators.
     * @return void
     */
    public static function notices() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network', 'settings_page_tools-for-woocommerce', 'woocommerce_page_tools-for-woocommerce' ), true ) ) { return; }
        $message = '';
        if ( class_exists( 'Deckerweb_Purify_WooCommerce_V1', false ) || class_exists( 'DDW_Purify_WooCommerce', false ) ) {
            $message = __( 'Deactivate the previous cleanup snippet or plugin before using Tools for WooCommerce. Store functions are paused while the previous implementation is active.', 'tools-for-woocommerce' );
        } elseif ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '11.1.2', '<' ) ) {
            $message = __( 'Tools for WooCommerce requires active WooCommerce 11.1.2 or newer. Its shop rules are currently inactive.', 'tools-for-woocommerce' );

        }
        if ( $message ) { echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>'; }
    }
    /**
     * Resolve the active administration language, using English as fallback.
     * @return string
     */
    public static function language() { return 0 === strpos( determine_locale(), 'de' ) ? 'de' : 'en'; }
    /**
     * Render one labeled boolean setting and its unchecked fallback.
     * @param string $key Settings or native option key.
     * @param string $label Translated field label.
     * @param array $options Effective site settings.
     * @return void
     */
    public static function checkbox( $key, $label, $options ) {
        echo '<p><input type="hidden" name="' . esc_attr( self::OPTION . '[' . $key . ']' ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( self::OPTION . '[' . $key . ']' ) . '" value="1" ' . checked( $options[$key], true, false ) . '> ' . esc_html( $label ) . '</label></p>';
    }
    /**
     * Render the configuration forms, explanations and shared footer.
     * @return void
     */
    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $options = self::options();
        echo '<div class="wrap ddw-twc-root">';
        self::header();
        settings_errors();
        echo '<div class="ddw-twc-shortcuts">';
        if ( has_action( 'admin_menu', array( DDW_TWC_Email_Designer::class, 'menu' ) ) ) {
            echo '<section class="ddw-twc-designer-card" aria-labelledby="ddw-twc-designer-title"><span class="ddw-twc-designer-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span><div><h2 id="ddw-twc-designer-title">' . esc_html__( 'Email Designer', 'tools-for-woocommerce' ) . '</h2><p>' . esc_html__( 'Design standard WooCommerce emails, preview them live and send a test email.', 'tools-for-woocommerce' ) . '</p></div><a class="button button-primary" href="' . esc_url( DDW_TWC_Email_Designer::url() ) . '">' . esc_html__( 'Open Designer', 'tools-for-woocommerce' ) . '</a></section>';
        }
        if ( class_exists( 'DDW_TWC_Settings_Map', false ) && current_user_can( 'manage_woocommerce' ) ) {
            echo '<section class="ddw-twc-designer-card ddw-twc-map-shortcut" aria-labelledby="ddw-twc-map-title"><span class="ddw-twc-designer-icon dashicons dashicons-admin-generic" aria-hidden="true"></span><div><h2 id="ddw-twc-map-title">' . esc_html__( 'Shop settings', 'tools-for-woocommerce' ) . '</h2><p>' . esc_html__( 'Go straight to WooCommerce settings and shop checks.', 'tools-for-woocommerce' ) . '</p></div><a class="button button-secondary" href="' . esc_url( DDW_TWC_Settings_Map::url() ) . '">' . esc_html__( 'Open overview', 'tools-for-woocommerce' ) . '</a></section>';
        }
        echo '</div>';
        $tab = self::requested_tab();
        $tabs = array(
            'tidy' => __( 'Tidy shop', 'tools-for-woocommerce' ),
            'offers' => __( 'Free offers', 'tools-for-woocommerce' ),
            'catalog' => __( 'Catalog', 'tools-for-woocommerce' ),
            'rules' => __( 'Cart Rules', 'tools-for-woocommerce' ),
            'management' => __( 'Management', 'tools-for-woocommerce' ),
        );
        echo '<nav class="nav-tab-wrapper ddw-twc-tabs" aria-label="' . esc_attr__( 'Settings sections', 'tools-for-woocommerce' ) . '">';
        foreach ( $tabs as $id => $label ) {
            echo '<a href="' . esc_url( self::tab_url( $id ) ) . '" class="nav-tab' . ( $id === $tab ? ' nav-tab-active" aria-current="page"' : '"' ) . '>' . esc_html( $label ) . '</a>';
        }
        echo '</nav><h2 class="ddw-twc-tab-title">' . esc_html( $tabs[$tab] ) . '</h2>';
        if ( 'management' === $tab ) {
            self::transfer_form();
        }
        if ( in_array( $tab, array( 'tidy', 'offers' ), true ) ) { self::preset_form( $tab ); }
        echo '<form class="ddw-twc-settings-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="ddw_twc_save_tab"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
        wp_nonce_field( 'ddw_twc_save_' . $tab );
        match ( $tab ) {
            'tidy' => self::tidy_fields( $options ),
            'offers' => self::offer_fields( $options ),
            'catalog' => self::catalog_fields( $options ),
            'rules' => self::rule_fields( $options ),
            'management' => self::management_fields( $options ),
        };
        submit_button();
        echo '</form>';
        if ( 'offers' === $tab && class_exists( 'DDW_TWC_Practice_Tools', false ) ) { DDW_TWC_Practice_Tools::offer_check( $options ); }
        if ( 'management' === $tab ) {
            echo '<p class="description">' . esc_html__( 'Tested with WooCommerce 11.1.2 through 11.3.x.', 'tools-for-woocommerce' ) . '</p>';
        }
        self::footer();
        echo '</div>';
    }

    /** Resolve a settings page from its allowlisted URL parameter. */
    private static function requested_tab(): string {
        $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        return in_array( $tab, array( 'offers', 'catalog', 'rules', 'management' ), true ) ? $tab : 'tidy';
    }

    /** Return the direct URL of a settings section. */
    private static function tab_url( string $tab ): string {
        return add_query_arg( 'tab', $tab, self::url() );
    }

    /** Map each stored field to the section allowed to edit it. */
    private static function tab_keys( string $tab ): array {
        return array_filter( self::defaults(), static function ( $key ) use ( $tab ) {
            $owner = match ( true ) {
                str_starts_with( $key, 'donation_' ), in_array( $key, array( 'operation_mode', 'checkout_texts', 'trim_checkout', 'shipping_notice' ), true ) => 'offers',
                str_starts_with( $key, 'catalog_' ) => 'catalog',
                str_starts_with( $key, 'rules_' ) => 'rules',
                in_array( $key, array( 'delete_data', 'preview_real_orders', 'preview_external_images' ), true ) => 'management',
                default => 'tidy',
            };
            return $tab === $owner;
        }, ARRAY_FILTER_USE_KEY );
    }

    /** Merge submitted fields into the current site configuration. */
    private static function merge_tab( string $tab, array $input ): array {
        return array_replace( self::options(), array_intersect_key( $input, self::tab_keys( $tab ) ) );
    }

    /** Save one section and return to it with native WordPress feedback. */
    public static function save_tab(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You are not allowed to change these settings.', 'tools-for-woocommerce' ), '', array( 'response' => 403 ) ); }
        $tab = isset( $_POST['tab'] ) && is_string( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
        if ( ! self::tab_keys( $tab ) ) { wp_die( esc_html__( 'Invalid settings section.', 'tools-for-woocommerce' ), '', array( 'response' => 400 ) ); }
        check_admin_referer( 'ddw_twc_save_' . $tab );
        $input = isset( $_POST[self::OPTION] ) && is_array( $_POST[self::OPTION] ) ? wp_unslash( $_POST[self::OPTION] ) : array();
        $result = DDW_TWC_Write_Lock::run( static function () use ( $tab, $input ): bool {
            $candidate = self::sanitize( self::merge_tab( $tab, $input ) );
            if ( get_settings_errors( self::OPTION ) ) { return false; }
            return self::persist( $candidate );
        } );
        if ( true === $result ) {
            add_settings_error( 'general', 'settings_updated', __( 'Settings saved.', 'tools-for-woocommerce' ), 'success' );
        } elseif ( ! get_settings_errors( self::OPTION ) ) {
            wp_die( esc_html( is_wp_error( $result ) ? $result->get_error_message() : __( 'Could not save the shop settings.', 'tools-for-woocommerce' ) ), '', array( 'response' => is_wp_error( $result ) ? 409 : 500 ) );
        }
        set_transient( 'settings_errors', get_settings_errors(), 30 );
        wp_safe_redirect( add_query_arg( 'settings-updated', 'true', self::tab_url( $tab ) ) );
        exit;
    }

    /** Offer a scoped preset with its effects visible before applying it. */
    private static function preset_form( string $tab ): void {
        $tidy = 'tidy' === $tab;
        echo '<details class="ddw-twc-preset"><summary>' . esc_html__( 'Presets', 'tools-for-woocommerce' ) . '</summary><p>' . esc_html( $tidy
            ? __( 'Restores the default cleanup switches, menu name and icon, and order screen settings.', 'tools-for-woocommerce' )
            : __( 'Sets the order mode and classic checkout options for free shipping or downloads. Your support message and its display settings are kept.', 'tools-for-woocommerce' )
        ) . '</p><form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post"><input type="hidden" name="action" value="ddw_twc_preset"><input type="hidden" name="preset" value="' . ( $tidy ? 'tidy' : 'free' ) . '">';
        wp_nonce_field( 'ddw_twc_preset' );
        if ( ! $tidy ) {
            echo '<label for="ddw-twc-variant">' . esc_html__( 'Free-offer variant', 'tools-for-woocommerce' ) . '</label> <select name="variant" id="ddw-twc-variant"><option value="physical">' . esc_html__( 'Physical shipping', 'tools-for-woocommerce' ) . '</option><option value="downloads">' . esc_html__( 'Downloads', 'tools-for-woocommerce' ) . '</option></select> ';
        }
        submit_button( __( 'Apply preset and save', 'tools-for-woocommerce' ), 'secondary', 'submit', false );
        echo '</form></details>';
    }

    /** Render the cleanup controls. */
    private static function tidy_fields( array $options ): void {
        $groups = array(
            __( 'Advertising and distractions', 'tools-for-woocommerce' ) => array(
                'hide_marketplace_ads' => __( 'Suppress marketplace advertising and recommendations', 'tools-for-woocommerce' ),
                'hide_payment_ads' => __( 'Suppress payment-provider recommendations', 'tools-for-woocommerce' ),
                'hide_shipping_ads' => __( 'Suppress shipping-partner recommendations', 'tools-for-woocommerce' ),
                'hide_promo_notes' => __( 'Filter marketing and survey notes from the inbox', 'tools-for-woocommerce' ),
                'hide_setup_tasks' => __( 'Hide onboarding task lists', 'tools-for-woocommerce' ),
                'hide_setup_redirect' => __( 'Prevent automatic setup-wizard redirects', 'tools-for-woocommerce' ),
                'hide_footer_prompt' => __( 'Hide the WooCommerce footer review prompt', 'tools-for-woocommerce' ),
            ),
            __( 'Navigation', 'tools-for-woocommerce' ) => array(
                'settings_map_menu' => __( 'Show the shop settings overview in the admin menu', 'tools-for-woocommerce' ),
                'split_admin_menu' => __( 'Split the WooCommerce menu into shop and management', 'tools-for-woocommerce' ),
                'hide_marketplace_menu' => __( 'Hide the marketplace entry points', 'tools-for-woocommerce' ),
                'hide_marketing_menu' => __( 'Hide the core marketing entry points', 'tools-for-woocommerce' ),
                'hide_payments_menu' => __( 'Hide payment menus and the payment settings tab', 'tools-for-woocommerce' ),
            ),
            __( 'Order screen', 'tools-for-woocommerce' ) => array(
                'hide_downloads_box' => __( 'Hide download permissions in the order screen (except in download mode)', 'tools-for-woocommerce' ),
                'hide_attribution_box' => __( 'Hide order attribution in the order screen', 'tools-for-woocommerce' ),
                'hide_customer_history_box' => __( 'Hide customer revenue history in the order screen', 'tools-for-woocommerce' ),
            ),
            __( 'Names and appearance', 'tools-for-woocommerce' ) => array(
                'rename_menu' => __( 'Rename the main WooCommerce menu', 'tools-for-woocommerce' ),
                'rename_dashboard' => __( 'Rename core WooCommerce dashboard widgets', 'tools-for-woocommerce' ),
                'rename_admin_texts' => __( 'Replace WooCommerce in PHP-translated admin labels', 'tools-for-woocommerce' ),
            ),
        );
        foreach ( $groups as $heading => $labels ) {
            echo '<fieldset><legend><h2>' . esc_html( $heading ) . '</h2></legend>';
            if ( isset( $labels['rename_menu'] ) ) {
                echo '<p><label for="ddw-twc-label">' . esc_html__( 'Admin name', 'tools-for-woocommerce' ) . '</label> <input type="text" id="ddw-twc-label" name="' . esc_attr( self::OPTION ) . '[admin_label]" value="' . esc_attr( $options['admin_label'] ) . '" maxlength="60" class="regular-text"></p>';
                echo '<p class="description">' . esc_html__( 'The menu, dashboard widgets and admin text use this name when their respective options are enabled.', 'tools-for-woocommerce' ) . '</p>';
            }
            foreach ( $labels as $key => $label ) {
                self::checkbox( $key, $label, $options );
                if ( 'settings_map_menu' === $key ) {
                    echo '<p><label for="ddw-twc-map-menu-label">' . esc_html__( 'Overview menu label', 'tools-for-woocommerce' ) . '</label> <input type="text" id="ddw-twc-map-menu-label" name="' . esc_attr( self::OPTION ) . '[settings_map_menu_label]" value="' . esc_attr( $options['settings_map_menu_label'] ) . '" maxlength="60" class="regular-text" placeholder="' . esc_attr__( 'Shop settings overview', 'tools-for-woocommerce' ) . '"></p>';
                }
                if ( 'split_admin_menu' === $key ) {
                    echo '<p class="description">' . esc_html__( 'Find orders, customers, products and the analytics overview in the main menu. Settings, tools and other add-ons move to the management menu.', 'tools-for-woocommerce' ) . '</p>';
                }
                if ( 'hide_payments_menu' === $key ) {
                    echo '<p class="description">' . esc_html__( 'Hiding payment screens does not disable gateways.', 'tools-for-woocommerce' ) . '</p>';
                }
                if ( 'rename_menu' === $key ) { self::menu_icon_field( $options ); }
            }
            echo '</fieldset>';
        }
    }

    /** Render the menu icon control. */
    private static function menu_icon_field( array $options ): void {
        echo '<p><label for="ddw-twc-icon">' . esc_html__( 'Menu icon', 'tools-for-woocommerce' ) . '</label> <select id="ddw-twc-icon" name="' . esc_attr( self::OPTION ) . '[admin_icon]">';
        $icons = array( '' => __( 'Keep original', 'tools-for-woocommerce' ), 'dashicons-cart' => __( 'Cart', 'tools-for-woocommerce' ), 'dashicons-album' => __( 'Disc', 'tools-for-woocommerce' ), 'dashicons-download' => __( 'Download', 'tools-for-woocommerce' ), 'dashicons-store' => __( 'Store', 'tools-for-woocommerce' ) );
        foreach ( $icons as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $options['admin_icon'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></p>';
    }

    /** Render free order and voluntary support settings. */
    private static function offer_fields( array $options ): void {
        echo '<fieldset><legend><h2>' . esc_html__( 'Orders and checkout', 'tools-for-woocommerce' ) . '</h2></legend><p><label for="ddw-twc-mode">' . esc_html__( 'Order mode', 'tools-for-woocommerce' ) . '</label> <select id="ddw-twc-mode" name="' . esc_attr( self::OPTION ) . '[operation_mode]">';
        $modes = array( 'standard' => __( 'Regular WooCommerce sales', 'tools-for-woocommerce' ), 'free_physical' => __( 'Free physical products', 'tools-for-woocommerce' ), 'free_downloads' => __( 'Free downloads', 'tools-for-woocommerce' ) );
        foreach ( $modes as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( $options['operation_mode'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></p><p class="description">' . esc_html__( 'Free modes reject nonzero product prices and order totals and offer no payment gateways. Physical mode requires shipping and an address. Download mode requires virtual, downloadable products and keeps download permissions accessible.', 'tools-for-woocommerce' ) . '</p>';
        foreach ( array( 'checkout_texts' => __( 'Use matching free-order texts in the classic checkout', 'tools-for-woocommerce' ), 'trim_checkout' => __( 'Remove company from the classic checkout; keep phone', 'tools-for-woocommerce' ), 'shipping_notice' => __( 'Show shipping checks for free physical orders in admin', 'tools-for-woocommerce' ) ) as $key => $label ) { self::checkbox( $key, $label, $options ); }
        echo '</fieldset><details><summary>' . esc_html__( 'Before the first order', 'tools-for-woocommerce' ) . '</summary><p>' . esc_html__( 'Set product prices to 0. For physical offers, configure a free shipping method. For downloads, mark products as virtual and downloadable and attach the files.', 'tools-for-woocommerce' ) . '</p><p>' . esc_html__( 'WooCommerce controls the fields and labels in block checkout.', 'tools-for-woocommerce' ) . '</p></details>';
        self::support_fields( $options );
    }

    /** Render catalog settings. */
    private static function catalog_fields( array $options ): void {
        echo '<fieldset><legend><h2>' . esc_html__( 'Catalog mode', 'tools-for-woocommerce' ) . '</h2></legend><p>' . esc_html__( 'Show products without accepting new storefront orders. Existing orders and customer accounts stay accessible. Clear page caches after changing this mode.', 'tools-for-woocommerce' ) . '</p>';
        self::checkbox( 'catalog_enabled', __( 'Use the shop as a presentation catalog', 'tools-for-woocommerce' ), $options );
        self::checkbox( 'catalog_hide_prices', __( 'Hide displayed product prices', 'tools-for-woocommerce' ), $options );
        self::text_field( 'catalog_message', __( 'Catalog explanation (optional)', 'tools-for-woocommerce' ), $options, 'textarea' );
        self::text_field( 'catalog_contact_url', __( 'Inquiry or contact URL (optional)', 'tools-for-woocommerce' ), $options, 'url' );
        self::checkbox( 'catalog_product_context', __( 'Include product details in the inquiry link', 'tools-for-woocommerce' ), $options );
        echo '<p class="description">' . esc_html__( 'Product links pass tfwc_product, tfwc_sku and tfwc_product_url to your contact form. Email links include the details in the message.', 'tools-for-woocommerce' ) . '</p>';
        self::text_field( 'catalog_contact_label', __( 'Contact link label (optional)', 'tools-for-woocommerce' ), $options );
        echo '<p><code>[twc_catalog_notice]</code> ' . esc_html__( 'places this explanation and contact link in a custom layout.', 'tools-for-woocommerce' ) . '</p></fieldset>';
    }

    /** Render cart and order rules. */
    private static function rule_fields( array $options ): void {
        echo '<fieldset><legend><h2>' . esc_html__( 'Rule activation', 'tools-for-woocommerce' ) . '</h2></legend>';
        self::checkbox( 'rules_enabled', __( 'Enable cart and order limits', 'tools-for-woocommerce' ), $options );
        self::checkbox( 'rules_single', __( 'Exactly one item: one product with quantity one in the entire cart and order', 'tools-for-woocommerce' ), $options );
        echo '<p class="description">' . esc_html__( 'Exactly one item overrides the other quantity and value rules. Otherwise, zero means no limit. Amounts use the shop currency and product value after discounts, excluding tax, shipping and fees.', 'tools-for-woocommerce' ) . '</p>';
        echo '</fieldset><fieldset><legend><h2>' . esc_html__( 'Quantity and order value', 'tools-for-woocommerce' ) . '</h2></legend><div class="ddw-twc-field-grid">';
        foreach ( array( 'rules_min_quantity' => __( 'Minimum total quantity', 'tools-for-woocommerce' ), 'rules_max_quantity' => __( 'Maximum total quantity', 'tools-for-woocommerce' ), 'rules_min_line' => __( 'Minimum quantity per product or variation', 'tools-for-woocommerce' ), 'rules_max_line' => __( 'Maximum quantity per product or variation', 'tools-for-woocommerce' ), 'rules_step' => __( 'Quantity increment per product or variation', 'tools-for-woocommerce' ), 'rules_min_amount' => __( 'Minimum product value', 'tools-for-woocommerce' ), 'rules_max_amount' => __( 'Maximum product value', 'tools-for-woocommerce' ) ) as $key => $label ) { self::text_field( $key, $label, $options, 'number' ); }
        echo '</div></fieldset>';
    }

    /** Render voluntary support settings. */
    private static function support_fields( array $options ): void {
        echo '<fieldset><legend><h2>' . esc_html__( 'Voluntary support', 'tools-for-woocommerce' ) . '</h2></legend><p>' . esc_html__( 'Optional information for free offers, such as support for shipping costs or a tip for downloads. No payment is collected and no fee is added to the order.', 'tools-for-woocommerce' ) . '</p>';
        self::checkbox( 'donation_enabled', __( 'Show voluntary support information for free offers', 'tools-for-woocommerce' ), $options );
        self::text_field( 'donation_text', __( 'Support message (blank uses a matching default)', 'tools-for-woocommerce' ), $options, 'textarea' );
        self::text_field( 'donation_url', __( 'External support URL (optional)', 'tools-for-woocommerce' ), $options, 'url' );
        self::text_field( 'donation_label', __( 'Support link label (optional)', 'tools-for-woocommerce' ), $options );
        echo '<fieldset class="ddw-twc-placements"><legend>' . esc_html__( 'Display locations', 'tools-for-woocommerce' ) . '</legend>';
        foreach ( array( 'donation_shop' => __( 'Shop listing', 'tools-for-woocommerce' ), 'donation_product' => __( 'Product page', 'tools-for-woocommerce' ), 'donation_cart' => __( 'Cart', 'tools-for-woocommerce' ), 'donation_checkout' => __( 'Checkout', 'tools-for-woocommerce' ), 'donation_thankyou' => __( 'Order confirmation', 'tools-for-woocommerce' ) ) as $key => $label ) { self::checkbox( $key, $label, $options ); }
        echo '</fieldset><details><summary>' . esc_html__( 'Completed order email', 'tools-for-woocommerce' ) . '</summary>';
        self::checkbox( 'donation_email', __( 'Include a voluntary support message in eligible completion emails', 'tools-for-woocommerce' ), $options );
        self::text_field( 'donation_email_physical', __( 'Email message for physical orders', 'tools-for-woocommerce' ), $options, 'textarea' );
        self::text_field( 'donation_email_downloads', __( 'Email message for downloads', 'tools-for-woocommerce' ), $options, 'textarea' );
        echo '<p class="description">' . esc_html__( 'Leave the email message blank to use the general support message or the default for the order type.', 'tools-for-woocommerce' ) . '</p></details>';
        echo '<p><code>[twc_support_notice]</code> ' . esc_html__( 'places the support message in a custom layout or a Shortcode block.', 'tools-for-woocommerce' ) . '</p></fieldset>';
    }

    /** Render uninstall settings. */
    private static function management_fields( array $options ): void {
        echo '<fieldset><legend><h2>' . esc_html__( 'Email preview', 'tools-for-woocommerce' ) . '</h2></legend>';
        self::checkbox( 'preview_real_orders', __( 'Use real order data in the email preview', 'tools-for-woocommerce' ), $options );
        self::checkbox( 'preview_external_images', __( 'Allow external images in the email preview', 'tools-for-woocommerce' ), $options );
        echo '</fieldset>';
        echo '<fieldset><legend><h2>' . esc_html__( 'Data retention', 'tools-for-woocommerce' ) . '</h2></legend>';
        self::checkbox( 'delete_data', __( 'Delete this plugin’s website settings on uninstall (products and orders are always kept)', 'tools-for-woocommerce' ), $options );
        echo '</fieldset>';
    }
    /**
     * Render one labeled scalar settings input.
     * @param string $key Schema key.
     * @param string $label Translated label.
     * @param array $options Effective values.
     * @param string $type Text, URL, number or textarea.
     * @return void
     */
    public static function text_field( string $key, string $label, array $options, string $type = 'text' ): void {
        $name = self::OPTION . '[' . $key . ']'; $id = 'ddw-twc-' . $key;
        echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><br>';
        if ( 'textarea' === $type ) { echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" maxlength="4000" class="large-text">' . esc_textarea( $options[$key] ) . '</textarea>'; }
        else { echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $options[$key] ) . '" class="regular-text"' . ( 'number' === $type ? ' min="' . ( 'rules_step' === $key ? '1' : '0' ) . '" step="' . ( str_contains( $key, 'amount' ) ? 'any' : '1' ) . '"' : '' ) . '>'; }
        echo '</p>';
    }
    /** Render manual JSON transfer forms outside the primary settings form. @return void */
    public static function transfer_form(): void {
        echo '<section class="ddw-twc-transfer" aria-labelledby="ddw-twc-transfer-title"><h3 id="ddw-twc-transfer-title">' . esc_html__( 'Transfer settings', 'tools-for-woocommerce' ) . '</h3><p>' . esc_html__( 'Choose which TfWC settings to export or paste a configuration to review its import. The transfer includes plugin settings only.', 'tools-for-woocommerce' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ddw_twc_export">';
        wp_nonce_field( 'ddw_twc_export' );
        echo '<label for="ddw-twc-export-scope">' . esc_html__( 'Export scope', 'tools-for-woocommerce' ) . '</label> <select id="ddw-twc-export-scope" name="scope">';
        foreach ( self::transfer_scopes() as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        submit_button( __( 'Export settings', 'tools-for-woocommerce' ), 'secondary' );
        echo '</form><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ddw_twc_import">';
        wp_nonce_field( 'ddw_twc_import' );
        echo '<p><label for="ddw-twc-import">' . esc_html__( 'Exported settings JSON', 'tools-for-woocommerce' ) . '</label><textarea id="ddw-twc-import" name="configuration" class="large-text code" rows="5" required></textarea></p>';
        echo '<label for="ddw-twc-import-scope">' . esc_html__( 'Import scope', 'tools-for-woocommerce' ) . '</label> <select id="ddw-twc-import-scope" name="scope">';
        echo '<option value="file">' . esc_html__( 'Use the scope in the file', 'tools-for-woocommerce' ) . '</option>';
        foreach ( self::transfer_scopes() as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        submit_button( __( 'Review import', 'tools-for-woocommerce' ), 'secondary' );
        echo '</form></section>';
    }

    private static function transfer_scopes(): array {
        return array(
            'all' => __( 'All TfWC settings including Email Designer', 'tools-for-woocommerce' ),
            'settings' => __( 'TfWC settings without Email Designer', 'tools-for-woocommerce' ),
            'email' => __( 'Email Designer only', 'tools-for-woocommerce' ),
        );
    }

    public static function export_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( '', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'ddw_twc_export' );
        $scope = isset( $_POST['scope'] ) && is_string( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'settings';
        if ( ! isset( self::transfer_scopes()[ $scope ] ) ) { wp_die( '', '', array( 'response' => 400 ) ); }
        $data = array( 'plugin' => 'tools-for-woocommerce', 'schema' => 2, 'scope' => $scope );
        if ( 'email' !== $scope ) { $data['settings'] = self::options(); }
        if ( 'settings' !== $scope ) {
            $data['email'] = DDW_TWC_Email_Designer::export_state();
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="tools-for-woocommerce-' . $scope . '.json"' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    public static function import_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( '', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'ddw_twc_import' );
        $raw = isset( $_POST['configuration'] ) && is_string( $_POST['configuration'] ) ? wp_unslash( $_POST['configuration'] ) : '';
        $data = strlen( $raw ) <= 4194304 ? json_decode( $raw, true, 20 ) : null;
        $schema = $data['schema'] ?? null;
        $scope = 1 === $schema ? 'settings' : ( $data['scope'] ?? null );
        $scopes = self::transfer_scopes();
        if ( ! is_array( $data ) || 'tools-for-woocommerce' !== ( $data['plugin'] ?? null ) || ! in_array( $schema, array( 1, 2 ), true ) || ! is_string( $scope ) || ! isset( $scopes[ $scope ] )
            || ( 'email' !== $scope && ( ! is_array( $data['settings'] ?? null ) || array_diff_key( $data['settings'], self::defaults() ) ) )
            || ( 'settings' !== $scope && ! DDW_TWC_Email_Designer::valid_transfer( $data['email'] ?? null ) ) ) {
            wp_die( esc_html__( 'This is not a supported Tools for WooCommerce settings export.', 'tools-for-woocommerce' ), '', array( 'response' => 400 ) );
        }
        $requested = isset( $_POST['scope'] ) && is_string( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'file';
        if ( 'file' !== $requested && ( ! isset( $scopes[ $requested ] ) || ( 'all' !== $scope && $scope !== $requested ) ) ) {
            wp_die( esc_html__( 'The file does not contain the selected settings.', 'tools-for-woocommerce' ), '', array( 'response' => 400 ) );
        }
        $scope = 'file' === $requested ? $scope : $requested;
        $settings = 'email' === $scope ? null : self::normalize( $data['settings'] );
        if ( null !== $settings && ( $conflict = self::conflict( $settings ) ) ) { wp_die( esc_html( $conflict ), '', array( 'response' => 400 ) ); }
        if ( ! isset( $_POST['confirm_transfer'] ) ) {
            ob_start();
            echo '<h1>' . esc_html__( 'Review import', 'tools-for-woocommerce' ) . '</h1><p>' . esc_html( $scopes[ $scope ] ) . '</p>';
            if ( null !== $settings ) { echo '<p>' . esc_html__( 'The included plugin settings replace the current settings.', 'tools-for-woocommerce' ) . '</p>'; }
            if ( 'settings' !== $scope ) {
                $email = $data['email'];
                if ( $email ) {
                    echo '<p>' . esc_html__( 'The imported design replaces the current draft. The active email design stays in use.', 'tools-for-woocommerce' ) . '</p>';
                    echo '<ul>';
                    if ( isset( $email['draft'] ) ) { echo '<li>' . esc_html__( 'Includes the source draft.', 'tools-for-woocommerce' ) . '</li>'; }
                    if ( isset( $email['live'] ) ) { echo '<li>' . esc_html__( 'Includes the source’s published version for preview.', 'tools-for-woocommerce' ) . '</li>'; }
                    $encoded = wp_json_encode( $email, JSON_UNESCAPED_SLASHES );
                    if ( is_string( $encoded ) && preg_match( '~https?://~i', $encoded ) ) {
                        echo '<li>' . esc_html__( 'Contains fixed URLs. Check links and reselect any source-site logos in the target Media Library.', 'tools-for-woocommerce' ) . '</li>';
                    }
                    if ( is_string( $encoded ) && preg_match( '/\{seite-[^{}:]+:[0-9]+/', $encoded ) ) {
                        echo '<li>' . esc_html__( 'Contains tags with page IDs. Check the target pages before publishing.', 'tools-for-woocommerce' ) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>' . esc_html__( 'This file contains no saved email design.', 'tools-for-woocommerce' ) . '</p>';
                }
            }
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ddw_twc_import"><input type="hidden" name="confirm_transfer" value="1"><input type="hidden" name="scope" value="' . esc_attr( $scope ) . '"><textarea hidden name="configuration">' . esc_textarea( $raw ) . '</textarea>';
            wp_nonce_field( 'ddw_twc_import' );
            submit_button( __( 'Apply import', 'tools-for-woocommerce' ) );
            echo '</form><p><a href="' . esc_url( self::tab_url( 'management' ) ) . '">' . esc_html__( 'Cancel', 'tools-for-woocommerce' ) . '</a></p>';
            wp_die( ob_get_clean(), esc_html__( 'Review import', 'tools-for-woocommerce' ), array( 'response' => 200 ) );
        }
        $result = DDW_TWC_Write_Lock::run( static fn() => self::apply_transfer( $settings, 'settings' === $scope ? array() : $data['email'] ) );
        if ( is_wp_error( $result ) ) {
            wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 500 ) );
        }
        add_settings_error( 'general', 'settings_updated', __( 'Settings saved.', 'tools-for-woocommerce' ), 'success' );
        set_transient( 'settings_errors', get_settings_errors(), 30 );
        wp_safe_redirect( 'settings' !== $scope && $data['email'] && has_action( 'admin_menu', array( DDW_TWC_Email_Designer::class, 'menu' ) ) ? DDW_TWC_Email_Designer::url() : add_query_arg( 'settings-updated', 'true', self::tab_url( 'management' ) ) );
        exit;
    }
    public static function enable_preview_option( string $key ): bool|WP_Error {
        if ( ! in_array( $key, array( 'preview_real_orders', 'preview_external_images' ), true ) ) {
            return new WP_Error( 'ddw_twc_preview_option', __( 'Invalid preview option.', 'tools-for-woocommerce' ), array( 'status' => 400 ) );
        }
        return DDW_TWC_Write_Lock::run( static fn() => self::persist( array_replace( self::options(), array( $key => true ) ) ) );
    }

    private static function persist( $settings ): bool {
        update_option( self::OPTION, $settings );
        return get_option( self::OPTION, null ) === $settings;
    }

    private static function apply_transfer( ?array $settings, array $email ): true|WP_Error {
        $previous = get_option( self::OPTION, null );
        if ( null !== $settings && ! self::persist( $settings ) ) {
            return new WP_Error( 'ddw_twc_settings_write', __( 'Could not save the shop settings.', 'tools-for-woocommerce' ) );
        }
        if ( DDW_TWC_Email_Designer::import_draft( $email ) ) { return true; }

        if ( null !== $settings ) {
            // Avoid restoring over a different value saved since the import began.
            if ( get_option( self::OPTION, null ) !== $settings ) {
                return new WP_Error( 'ddw_twc_import_rollback', __( 'The email design could not be imported. Check the current shop settings before retrying.', 'tools-for-woocommerce' ) );
            }
            if ( $previous === $settings ) {
                $restored = true;
            } elseif ( null === $previous ) {
                delete_option( self::OPTION );
                $restored = null === get_option( self::OPTION, null );
            } else {
                $restored = self::persist( $previous );
            }
            if ( ! $restored ) {
                return new WP_Error( 'ddw_twc_import_rollback', __( 'The email design could not be imported. Check the current shop settings before retrying.', 'tools-for-woocommerce' ) );
            }
        }
        return new WP_Error( 'ddw_twc_email_write', __( 'Could not save the email design.', 'tools-for-woocommerce' ) );
    }

    /** Render the shared page heading before WordPress admin notices. */
    public static function header( string $section = '' ): void {
        $title = 'Tools for WooCommerce';
        if ( '' !== $section ) { $title .= ' · ' . $section; }
        echo '<header class="ddw-twc-header"><img src="' . esc_url( plugins_url( 'assets/artwork/icon.svg', DDW_TWC_FILE ) ) . '" width="64" height="64" alt=""><div><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1><p>' . esc_html__( 'More clarity in your shop.', 'tools-for-woocommerce' ) . '</p></div></header><hr class="wp-header-end">';
    }

    /**
     * Render the localized project footer and accessible local changelog dialog.
     * @return void
     */
    public static function footer() {
        require_once __DIR__ . '/deckerweb-changelog-v1.php';
        $lang = self::language();
        $file = 'docs/changelog' . ( 'de' === $lang ? '-de' : '' ) . '.txt';
        $url = plugins_url( $file, DDW_TWC_FILE );
        $wiki = 'https://github.com/deckerweb/tools-for-woocommerce/wiki/' . ( 'de' === $lang ? 'Home-de' : 'Home' );
        echo '<footer class="ddw-twc-footer" aria-label="' . esc_attr__( 'Plugin information', 'tools-for-woocommerce' ) . '"><div><strong>Tools for WooCommerce</strong> <span>' . esc_html__( 'Version', 'tools-for-woocommerce' ) . ' ' . esc_html( DDW_TWC_VERSION ) . '</span> · <a href="' . esc_url( $url ) . '" id="ddw-twc-history" aria-haspopup="dialog" aria-controls="ddw-twc-dialog">' . esc_html__( 'Changelog', 'tools-for-woocommerce' ) . '</a> · <a href="' . esc_url( $wiki ) . '">' . esc_html__( 'Documentation', 'tools-for-woocommerce' ) . '</a><p>' . esc_html__( 'More clarity in your shop.', 'tools-for-woocommerce' ) . '</p></div><div><span>© 2026 <a href="https://deckerwebplugins.com/' . ( 'de' === $lang ? 'de/' : 'en/' ) . '" target="_blank" rel="noopener noreferrer">David Decker – DECKERWEB</a></span><a href="https://deckerwebplugins.com/' . ( 'de' === $lang ? 'de/plugins/tools-for-woocommerce/' : 'en/plugins/tools-for-woocommerce/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Plugin website', 'tools-for-woocommerce' ) . '</a></div></footer>';
        echo '<dialog hidden id="ddw-twc-dialog" aria-labelledby="ddw-twc-dialog-title"><header><h2 id="ddw-twc-dialog-title">' . esc_html__( 'Changelog', 'tools-for-woocommerce' ) . '</h2><form method="dialog"><button type="submit" class="button" id="ddw-twc-dialog-close" autofocus>' . esc_html__( 'Close', 'tools-for-woocommerce' ) . '</button></form></header>';
        $text = file_get_contents( dirname( __DIR__ ) . '/' . $file );
        echo wp_kses_post( Deckerweb_Changelog_Renderer_V1::render( $text ) );
        echo '</dialog>';
    }
}
}
