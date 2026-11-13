<?php
/** Read-only navigation for registered WooCommerce settings. */
defined( 'ABSPATH' ) || exit;

final class DDW_TWC_Settings_Map {
    private const PAGE = 'tfwc-settings-map';

    public static function register(): void {
        add_action( 'admin_menu', static function (): void {
            $label = __( 'Shop settings overview', 'tools-for-woocommerce' );
            $parent = DDW_TWC_Commerce::enabled( 'settings_map_menu' ) ? 'woocommerce' : null;
            $menu_label = DDW_TWC_Settings::options()['settings_map_menu_label'];
            $hook = add_submenu_page( $parent, $label, $menu_label ? esc_html( $menu_label ) : $label, 'manage_woocommerce', self::PAGE, array( self::class, 'page' ) );
            if ( $hook ) {
                add_action( 'load-' . $hook, static function (): void {
                    add_action( 'admin_enqueue_scripts', array( DDW_TWC_Settings::class, 'chrome_assets' ) );
                } );
            }
        } );
    }

    public static function url(): string {
        return admin_url( 'admin.php?page=' . self::PAGE );
    }

    public static function groups(): array {
        $pages = WC_Admin_Settings::get_settings_pages();
        $tabs = apply_filters( 'woocommerce_settings_tabs_array', array() );
        $sections = array();
        foreach ( $pages as $page ) {
            if ( is_callable( array( $page, 'get_id' ) ) && is_callable( array( $page, 'get_sections' ) ) ) {
                $sections[ $page->get_id() ] = $page->get_sections();
                if ( is_a( $page, 'WC_GZD_Settings_Germanized' ) ) {
                    foreach ( $page->get_tabs() as $module ) {
                        if ( $module->hide_from_main_panel() || $module->needs_install() ) { continue; }
                        $sections[ $page->get_id() ][ $module->get_name() ] = array( wp_strip_all_tags( $module->get_label() ), $module->get_link() );
                    }
                }
            }
        }
        $groups = array();
        foreach ( $tabs as $tab => $label ) {
            if ( ! is_string( $tab ) || '' === $tab || ! is_string( $label ) ) { continue; }
            $links = $sections[ $tab ] ?? apply_filters( 'woocommerce_get_sections_' . $tab, array() );
            if ( 'customizer' === $tab ) {
                $translations = array(
                    'Shop Loop' => __( 'Shop Loop', 'tools-for-woocommerce' ),
                    'Product Page' => __( 'Product Page', 'tools-for-woocommerce' ),
                    'Checkout' => __( 'Checkout', 'tools-for-woocommerce' ),
                    'Misc' => __( 'Misc', 'tools-for-woocommerce' ),
                );
                $links = array_map( static fn( $name ) => is_string( $name ) ? ( $translations[ $name ] ?? $name ) : $name, (array) $links );
            }
            $groups[ $tab ] = array( 'label' => $label, 'sections' => $links );
        }
        return $groups;
    }

    private static function links( string $tab, string $url, array $sections ): void {
        $entries = array();
        if ( 'checkout' === $tab ) {
            foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
                $target = add_query_arg( 'section', $gateway->id, $url );
                if ( is_callable( array( $gateway, 'get_settings_url' ) ) ) {
                    try {
                        $custom = trim( (string) $gateway->get_settings_url() );
                        if ( preg_match( '~^(?:/wp-admin/|/)?admin\.php\?~', $custom ) ) {
                            $custom = admin_url( substr( $custom, strpos( $custom, 'admin.php?' ) ) );
                        }
                        if ( preg_match( '~^https?://~i', $custom ) ) { $target = $custom; }
                    } catch ( Throwable $error ) {
                        // Keep the native gateway section when a provider cannot supply its URL.
                    }
                }
                $entries[] = array( $gateway->get_method_title() ?: $gateway->get_title(), $target, '' );
                unset( $sections[ $gateway->id ], $sections[ sanitize_title( get_class( $gateway ) ) ] );
            }
        }
        if ( 'email' === $tab ) {
            $entries[] = array( __( 'Email settings', 'tools-for-woocommerce' ), $url, '' );
            unset( $sections[''] );
        }
        if ( 'email' === $tab ) {
            $templates = array();
            $designs = DDW_TWC_Email_Designer::settings_map_links();
            foreach ( WC()->mailer()->get_emails() as $key => $email ) {
                if ( ! $email instanceof WC_Email ) { continue; }
                $source = DDW_TWC_Email_Designer::email_source( $email );
                $templates[] = array( $email->get_title() . ( $source ? ' · ' . $source : '' ), add_query_arg( 'section', strtolower( $key ), $url ), $designs[ spl_object_id( $email ) ] ?? '' );
                unset( $sections[ strtolower( $key ) ] );
            }
        }
        foreach ( $sections as $section => $label ) {
            if ( is_array( $label ) ) {
                $entries[] = array( $label[0], $label[1], '' );
            } elseif ( is_string( $label ) && '' !== trim( $label ) ) {
                $entries[] = array( $label, '' === $section ? $url : add_query_arg( 'section', $section, $url ), '' );
            }
        }
        self::render_links( $entries );
        if ( ! empty( $templates ) ) {
            /* translators: %s: Number of available email templates. */
            $summary = sprintf( __( 'Email templates (%s)', 'tools-for-woocommerce' ), number_format_i18n( count( $templates ) ) );
            echo '<details class="ddw-twc-map-email-templates"><summary>' . esc_html( $summary ) . '</summary>';
            self::render_links( $templates );
            echo '</details>';
        }
    }

    private static function render_links( array $entries ): void {
        if ( ! $entries ) { return; }
        echo '<ul>';
        foreach ( $entries as list( $label, $url, $design ) ) {
            echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( wp_strip_all_tags( $label ) ) . '</a>';
            if ( $design ) {
                echo ' · <a href="' . esc_url( $design ) . '" aria-label="' . esc_attr( wp_strip_all_tags( $label ) . ' · ' . __( 'Edit design', 'tools-for-woocommerce' ) ) . '">' . esc_html__( 'Edit design', 'tools-for-woocommerce' ) . '</a>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    private static function product_links(): void {
        $entries = array();
        foreach ( get_object_taxonomies( 'product', 'objects' ) as $taxonomy ) {
            if ( ! $taxonomy->show_ui || str_starts_with( $taxonomy->name, 'pa_' ) || ! current_user_can( $taxonomy->cap->manage_terms ) ) { continue; }
            $url = add_query_arg( array( 'taxonomy' => $taxonomy->name, 'post_type' => 'product' ), admin_url( 'edit-tags.php' ) );
            $entries[] = array( $taxonomy->labels->menu_name, $url, '' );
        }
        if ( current_user_can( 'manage_product_terms' ) ) {
            $entries[] = array( __( 'Attributes', 'woocommerce' ), admin_url( 'edit.php?post_type=product&page=product_attributes' ), '' );
        }
        if ( ! $entries ) { return; }
        echo '<details class="ddw-twc-map-email-templates"><summary>' . esc_html__( 'Product taxonomies and attributes', 'tools-for-woocommerce' ) . '</summary>';
        self::render_links( $entries );
        echo '</details>';
    }

    private static function analytics(): void {
        global $submenu;
        $rows = $submenu['wc-admin&path=/analytics/overview'] ?? array();
        $entries = array();
        foreach ( $rows as $row ) {
            if ( ! current_user_can( $row[1] ) || ! preg_match( '~^(?:admin\.php\?)?wc-admin(?:&|$)|^admin\.php\?page=wc-admin&~', $row[2] ) ) { continue; }
            $target = str_starts_with( $row[2], 'admin.php?' ) ? $row[2] : 'admin.php?page=' . $row[2];
            $entries[ $target ] = array( $row[0], admin_url( $target ), '' );
        }
        if ( ! $entries ) { return; }
        echo '<section><h2>' . esc_html__( 'Analytics', 'woocommerce' ) . '</h2>';
        self::render_links( $entries );
        echo '</section>';
    }

    public static function page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        echo '<div class="wrap ddw-twc-root">';
        DDW_TWC_Settings::header( __( 'Shop settings overview', 'tools-for-woocommerce' ) );
        if ( current_user_can( 'manage_options' ) ) {
            echo '<p class="ddw-twc-map-back"><a href="' . esc_url( DDW_TWC_Settings::url() ) . '"><span aria-hidden="true">← </span>' . esc_html__( 'Back to TfWC settings', 'tools-for-woocommerce' ) . '</a></p>';
        }
        echo '<div class="ddw-twc-settings-map">';
        $descriptions = array(
            'general' => __( 'Store address, selling locations and currency.', 'tools-for-woocommerce' ),
            'products' => __( 'Product display, inventory and downloads.', 'tools-for-woocommerce' ),
            'shipping' => __( 'Shipping zones, methods and options.', 'tools-for-woocommerce' ),
            'checkout' => __( 'Payment methods and providers.', 'tools-for-woocommerce' ),
            'account' => __( 'Customer accounts, guest checkout and privacy.', 'tools-for-woocommerce' ),
            'email' => __( 'Shop notifications, recipients and sender settings.', 'tools-for-woocommerce' ),
            'advanced' => __( 'Shop pages, endpoints and technical integrations.', 'tools-for-woocommerce' ),
        );
        foreach ( self::groups() as $tab => $group ) {
            $url = add_query_arg( array( 'page' => 'wc-settings', 'tab' => $tab ), admin_url( 'admin.php' ) );
            echo '<section><h2><a href="' . esc_url( $url ) . '">' . esc_html( $group['label'] ) . '</a></h2>';
            if ( isset( $descriptions[ $tab ] ) ) { echo '<p>' . esc_html( $descriptions[ $tab ] ) . '</p>'; }
            self::links( $tab, $url, (array) $group['sections'] );
            if ( 'products' === $tab ) { self::product_links(); }
            if ( 'advanced' === $tab && class_exists( 'DDW_TWC_Shop_Checks', false ) ) { DDW_TWC_Shop_Checks::pages(); }
            if ( 'shipping' === $tab && class_exists( 'DDW_TWC_Shop_Checks', false ) ) { DDW_TWC_Shop_Checks::shipping(); }
            echo '</section>';
        }
        self::analytics();
        echo '</div>';
        DDW_TWC_Settings::footer();
        echo '</div>';
    }
}
