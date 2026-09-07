=== ZipQuantum – Smart Links & QR Codes ===
Contributors: xaere
Tags: smart links, deep links, qr code, woocommerce, marketing
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress and WooCommerce content to ZipQuantum Smart Links and downloadable QR codes.

== Description ==

ZipQuantum turns published WordPress content into server-managed Smart Links. Connect a ZipQuantum account with OAuth, choose a verified or managed routing domain, then create or synchronize links from the editor or bulk tools.

The plugin works with WordPress alone. When WooCommerce is active, it also supports products, product categories, coupons, and a Marketing menu entry.

The settings screen keeps account, routing, and automation choices together, while each supported editor exposes its Smart Link and QR controls.

Features include:

* OAuth public-client connection with PKCE and a central ZipQuantum callback.
* Smart Links for posts, pages, public custom post types, products, categories, and coupons.
* Managed synchronization or read-only attachment to an existing Smart Link.
* Downloadable QR codes returned by ZipQuantum.
* Simple click totals.
* A durable local queue with retries, WP-Cron, admin processing, Retry, and Resume.
* Clone and migration protection.
* No visitor fingerprinting, advertising identifiers, or visitor analytics added by this plugin.

A ZipQuantum account and the external ZipQuantum service are required. A Free plan is available; paid plans increase service limits. The plugin itself is not trialware.

== Documentation ==

Setup instructions and integration guides are available at https://zq.tn/docs/cms-integrations/. They cover account connection, routing, synchronization, and WordPress/WooCommerce setup.

== External Service ==

ZipQuantum – Smart Links & QR Codes connects to the ZipQuantum service at `a.zq.tn` and `zq.tn`.

The service is required to:

* authenticate your ZipQuantum account;
* create and synchronize Smart Links;
* generate QR codes;
* retrieve Smart Link analytics and account capabilities.

Depending on the features you use, the plugin may send:

* the WordPress site origin and path from `home_url()`;
* WordPress content URLs;
* content titles and descriptions;
* featured image URLs;
* WordPress and WooCommerce object identifiers;
* routing configuration;
* an installation UUID and security handoff values.

The plugin does not send WordPress visitor analytics, visitor fingerprints, advertising identifiers, or mobile SDK data to ZipQuantum.

Service: https://zq.tn/

Terms of Service: https://zq.tn/terms-of-service/

Privacy Policy: https://zq.tn/privacy-policy/

== Installation ==

1. Install and activate the plugin for one WordPress site.
2. Open **Settings → ZipQuantum**.
3. Select **Connect ZipQuantum** and approve access in the ZipQuantum window.
4. Choose a managed subdomain or verified custom domain.
5. Select content types and explicitly enable automatic creation if desired.

Network activation is not supported in version 1.0. On Multisite, activate and connect the plugin separately for each site.

== Frequently Asked Questions ==

= Does this plugin install tracking? =

No. It does not add a visitor SDK, fingerprinting, advertising identifiers, or WordPress visitor analytics.

= What happens when WordPress content is deleted? =

Only the local association metadata is removed. The remote ZipQuantum Smart Link is never deleted automatically.

= Does uninstalling delete my Smart Links? =

No. Remote Smart Links are never deleted. Local association metadata is retained by default and can be removed through the opt-in advanced setting before uninstalling.

= Does it work without WooCommerce? =

Yes. WooCommerce support is loaded only when WooCommerce is active.

= What happens after cloning or moving a site? =

Synchronization stops with an identity mismatch. An administrator can move the existing installation, create a new installation with quarantined local associations, or reconnect unchanged credentials.

== Screenshots ==

1. Review the connected account, routing domains, and automatic content synchronization settings.
2. Monitor the durable synchronization queue, resume blocked work, and retry explicit failures.
3. Copy, download, or synchronize a post's Smart Link and QR code directly from the editor.

== Privacy ==

The plugin sends only the site and content information needed for the selected ZipQuantum features. See the External Service section for the complete disclosure.

== Changelog ==

= 1.0.0 =

* Initial WordPress.org release.
