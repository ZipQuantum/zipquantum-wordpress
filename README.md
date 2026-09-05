<p align="center">
  <img src="https://cdn.simpleicons.org/wordpress/21759B" alt="WordPress" width="72" height="72">
  &nbsp;&nbsp;&nbsp;
  <img src="https://cdn.simpleicons.org/woocommerce/96588A" alt="WooCommerce" width="72" height="72">
</p>

<h1 align="center">ZipQuantum – Smart Links & QR Codes</h1>

<p align="center">
  Official integration for WordPress and WooCommerce.
</p>

> **Developer distribution:** install this package directly from the WordPress admin while the WordPress.org review is pending.

<p align="center">
  <a href="https://github.com/ZipQuantum/zipquantum-wordpress/releases/latest/download/zipquantum-smart-links.zip"><strong>Download the latest installable ZIP</strong></a>
  ·
  <a href="https://zq.tn/developers/integrations/">Installation guide</a>
</p>

Official GPLv2-or-later WordPress plugin owned by Xaere. Git is the source of truth; WordPress.org SVN is a release channel only.

## Installation

1. Download `zipquantum-smart-links.zip` from the latest GitHub Release.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP, activate the plugin, and connect your ZipQuantum account.

## Development

```bash
composer install
composer lint
composer test
```

Build the production ZIP with:

```bash
php bin/build.php
```

The build script creates `dist/zipquantum-smart-links.zip` with the required top-level `zipquantum-smart-links/` directory.

Multisite network management and all features listed as post-1.0 backlog are intentionally out of scope.
