# ZipQuantum – Smart Links & QR Codes

Official GPLv2-or-later WordPress plugin owned by Xaere. Git is the source of truth; WordPress.org SVN is a release channel only.

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
