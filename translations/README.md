# Translation review drafts

The files under `review/` are complete machine-assisted drafts for:

- French (`fr_FR`)
- Spanish (`es_ES`)
- Simplified Chinese (`zh_CN`)
- Arabic (`ar`)

They are not release files and must not be imported into Translate WordPress before a fluent human translator has reviewed every string. This is required by the WordPress Polyglots translation policy.

Run `node scripts/generate-translation-drafts.mjs` after editing `drafts.json`. The script validates the interface catalogue against `languages/zipquantum-smart-links.pot`, writes reviewable PO files here, and writes temporary MO files under `output/translation-drafts/` for local WordPress testing.

Run `node scripts/generate-readme-translation-drafts.mjs` after editing `readme-drafts.json`. It writes the four public-directory-page PO files under `review/readme/`. The catalogue contains the exact 58 strings currently exposed by the WordPress.org **Stable Readme** project and preserves its HTML and URLs.

After human approval:

1. Import the interface PO file into both **Stable (latest release)** and **Development (trunk)**.
2. Import the matching file from `review/readme/` into both **Stable Readme (latest release)** and **Development Readme (trunk)**. Their current 58-string catalogues are identical; Stable Readme localizes the public directory page.
3. Request approval from the locale's WordPress translation editors.
