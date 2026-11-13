=== Tools for WooCommerce ===
Contributors: deckerweb
Tags: woocommerce, catalog, cart, checkout, cleanup
Requires at least: 7.0
Tested up to: 7.1.4
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce tools for a calmer store, free offers, catalog mode and cart rules.

== Description ==

Plugin website
https://deckerwebplugins.com/en/plugins/tools-for-woocommerce/

Tools for WooCommerce adds a calmer workspace and clearly configurable store features to WooCommerce. Regular sales, free offers, a presentation catalog and order limits can be configured for each store.

* Reduce advertising and recommendations while retaining important notices.
* Guard free physical orders or virtual downloads.
* Optional voluntary donation/tip information with an external link.
* Present products as a catalog with a contact link.
* Enforce exactly one item or flexible minimum/maximum rules.
* Website-local settings, previous configuration support and JSON transfer.

== Installation ==

1. Use PHP 8.3+, WordPress 7.0+ and WooCommerce 11.1.2+.
2. Deactivate a previous cleanup snippet or the earlier Purify WooCommerce test build.
3. Upload the ZIP through Plugins → Add New → Upload Plugin and activate it.
4. Open WooCommerce → Tools for WooCommerce. Without active WooCommerce, find it under Settings; store features pause while configuration remains available.
5. Apply a suitable starting point, then save individual adjustments.

When manually switching to the new plugin folder, previous website settings are read until a new configuration is saved. Products and orders are not migrated. Do not run the old and new implementations together.

== Frequently Asked Questions ==

= Can the store still sell normally? =
Yes. Tidy shop retains regular sales and gateways.

= How do I allow exactly one book or item? =
Enable Cart Rules and select Exactly one item. One unit in total is allowed.

= Are donations charged at checkout? =
No. The notice is voluntary information; an optional link leads to an external support service.

= Can free downloads and shipped products be mixed? =
Free variants are separate. Regular order mode retains WooCommerce functionality.

= Does the catalog provide an inquiry form? =
It provides a configurable link to an existing contact page or form.

= What happens to earlier settings? =
The new build reads the old configuration until new settings are saved. Deactivate the previous plugin and snippet first.

= What happens on uninstall? =
Settings remain by default. Only the opt-in deletion setting removes this plugin’s website configuration; products and orders remain.

== Changelog ==

= 1.0.0 — 2026-11-13 =

* New: Shop settings overview with direct links and checks for shop pages and shipping zones.
* New: Email Designer with nine base styles, dynamic tags and support for Germanized templates.
* Improved: Purchase, coupon and order status checks, plus split admin navigation.
* Improved: Import WooCommerce branding into a draft and save preview preferences persistently.
* Fixed: Handle failed and overlapping writes when saving or importing settings.
* Fixed: Add missing German labels to the settings overview.

= 0.3.0 — 2026-11-10 =
* New: Catalog mode with optional hidden prices, an explanatory message and a contact link.
* New: Cart Rules for exactly one item, minimum/maximum quantities, product values and quantity increments.
* New: Optional voluntary support messages for free physical products and downloads.
* New: Settings export/import and opt-in deletion of website settings.
* Improved: Renamed to Tools for WooCommerce with a new plugin folder, textdomain and code prefixes; earlier settings remain readable.
* Improved: Updated shared update and plugin-library components; requires PHP 8.3.
* Fixed: Preserved regular sales by default and displayed one settings-save confirmation.

= 0.2.0 — 2026-10-06 =
* New: Tidy shop, Free offers and Individual presets, with separate physical-shipping and download variants.
* Improved: Separate advertising, admin screens, names, icons and order boxes; preserve earlier configuration.
* Improved: Validate free-product types, configured downloads, shipping and nonzero fees.

= 0.1.0 — 2026-10-06 =
* New: Reversible WooCommerce cleanup and guarded free physical orders.
* New: Site settings, optional classic checkout text and shared deckerweb integrations.
