=== RVN Compare Products for WooCommerce ===
Contributors: revolen
Tags: woocommerce, compare, product comparison, comparison table, compare products
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let shoppers compare WooCommerce products side by side in a clean, responsive comparison table.

== Description ==

RVN Compare Products for WooCommerce adds a product comparison feature to your store: compare buttons on product cards and product pages, a live counter for your header and a clean comparison table with differences highlighted.

Version 0.4.1 includes fixes from real-world testing on the Storefront theme: robust product ID parsing in static shortcodes (including Russian and typographic quotes), thumbnail layout isolation, clean circular slider arrows that stay visible when reaching the end of the list, and seamless browser tab switching without layout jumps. The build passed the full automated check cycle (WordPress Coding Standards, PHPStan, PHP 8.1-8.4 syntax, Plugin Check with no errors or warnings, browser tests and the WP Super Cache scenario).

= Privacy =

The plugin does not send any data to external servers, does not load remote assets and does not track visitors.

== Installation ==

1. Make sure WooCommerce 8.2 or newer is installed and active.
2. Upload the plugin through Plugins → Add New → Upload Plugin, or install it from the plugin directory.
3. Activate the plugin.
4. Open RVN → Compare in the WordPress admin.

== Frequently Asked Questions ==

= Does the plugin work without WooCommerce? =

No. The plugin stays inactive and shows a notice with a link to install or activate WooCommerce.

= Is the plugin compatible with High-Performance Order Storage (HPOS)? =

Yes. The plugin does not read or modify orders.

= What happens to my data when I delete the plugin? =

By default, the plugin settings and saved comparison lists are removed. The comparison page is never deleted because it is your content.

== Changelog ==

= 0.4.1 =
* Fixed: static shortcode [rvn-compare-table products="..."] now reliably parses IDs regardless of typographic, Russian or curly quotes, spaces and delimiters.
* Fixed: product card image thumbnail is strictly contained above the product title without overflowing the card or overlapping text in classic themes.
* Fixed: comparison table slider arrows are styled as clean, circular buttons with SVG chevrons and remain visible (with a disabled state) when reaching the beginning or end of the list.
* Fixed: switching between browser tabs no longer displays the loading banner or causes the table layout to jump when product IDs have not changed.
* Fixed: desktop table column calculation now respects the 5-column default on wide screens regardless of theme container width.

= 0.4.0 =
* New: comparison page created automatically with the [rvn-compare-table] shortcode, REST endpoint /table, field registry with groups, difference highlighting and category tabs.
* New: static shortcode [rvn-compare-table products="1,2,3"] shows the requested products side by side.
* Fixed: static shortcode no longer splits explicitly requested products into category tabs.
* Fixed: WPCS formatting, Yoda condition and the reserved word `static` used as a parameter name.

= 0.3.1 =
* Fixed: compare button now appears on single product pages with classic templates (Storefront, Astra and others).
* Fixed: "Compare" button no longer shows two icons at once; icon-only mode no longer gains extra text in the active state.
* Fixed: the "Undo" action in the "added" notification now removes the product as expected.
* Fixed: overlay positions are placed inside the product image container, so all four corners are accurate.
* Improvement: notification countdown pauses on hover.
* Improvement: page cache is flushed when the plugin is activated.

= 0.3.0 =
* Compare buttons on product cards and product pages: nine positions, classic and block templates, exclusions respected.
* Two button states ("Compare" / "In comparison"); pressing again removes the product.
* Live counter button, plain counter, "Clear all" button and per-category progress, updated without page reloads.
* Notifications with countdown bar, close button and an undo action; safe-area aware positioning.
* Shortcodes: [rvn-compare-button], [rvn-compare-counter], [rvn-compare-counter-button], [rvn-compare-clear], [rvn-compare-progress].
* Cross-tab synchronisation and correct behaviour on page-cached sites.

= 0.2.0 =
* Comparison core: guest lists in the browser, registered lists in the user profile, merging on sign in.
* Limits: 50 products in total and 12 per category or group, with clear reasons in the response.
* Categories: direct membership, comparison rules, ignored categories, custom category groups and the "Other" group.
* Product and category exclusions for automatic buttons.
* REST API rvn-compare/v1 with nonce protection and throttled merging.
* Tools → RVN Diagnostics screen with a copyable environment report.

= 0.1.0 =
* Plugin foundation: requirement checks for WordPress, WooCommerce and PHP.
* RVN admin menu placed right after WooCommerce Marketing, with Compare and Support pages.
* HPOS compatibility declaration.
* Russian translation.
* Clean uninstallation, including multisite networks.
