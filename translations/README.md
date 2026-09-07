# Translation review drafts

The files under `review/` are complete machine-assisted drafts for:

- French (`fr_FR`)
- Spanish (`es_ES`)
- Simplified Chinese (`zh_CN`)
- Arabic (`ar`)

They are not release files and must not be imported into Translate WordPress before a fluent human translator has reviewed every string. This is required by the WordPress Polyglots translation policy.

Run `node scripts/generate-translation-drafts.mjs` after editing `drafts.json`. The script validates the catalogue against `languages/zipquantum-smart-links.pot`, writes reviewable PO files here, and writes temporary MO files under `output/translation-drafts/` for local WordPress testing.

After human approval, import the reviewed PO file into both the **Stable (latest release)** and **Development (trunk)** projects on Translate WordPress. Translate the **Stable Readme** project separately so the public directory page is localized too.
