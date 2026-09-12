# WordPress CI/CD notes

The cross-project operating procedure is in `../zipquantum-app/docs/CI_CD_SECURE_RUNBOOK.md`.

WordPress CI now runs a redacting secret/path gate before quality and compatibility jobs. The package builder refuses a dirty tree and includes only Git-tracked production files. It emits:

- `dist/zipquantum-smart-links.zip`;
- `dist/zipquantum-smart-links.zip.sha256`;
- `dist/zipquantum-smart-links.SHA256SUMS` for the ZIP and manifest;
- `dist/zipquantum-smart-links.manifest.json` with the commit, ZIP hash and hashes of packaged files.

`ZQ_ALLOW_DIRTY_BUILD=1` is for a local non-release test only. The manifest records the override and release CI rejects it.

The installable ZIP and a live-runtime hotfix artefact are separate deliverables. For a reviewed runtime allowlist, create a checked-in plan under `scripts/release/plans/`, then run the shared tools from this repository so Git provenance resolves against WordPress:

```powershell
node ..\zipquantum-app\scripts\release\build-candidate.mjs --plan=scripts/release/plans/<plan>.json --output=output/release-candidate
node ..\zipquantum-app\scripts\release\verify-candidate.mjs --manifest=output/release-candidate/<release>.manifest.json --archive=output/release-candidate/<release>.tar.gz
```

The plan must use `component: "wordpress-plugin"`, target only the plugin runtime, carry a SHA-256 precondition (or explicit `absent`) for every file, and declare HTTPS HTTP/API/visual smokes. Promotion remains an explicit, separately reviewed manual action.

GitHub Release and WordPress.org are distribution channels, not automatic deployment to `/home/supportproxi/public_html/zq.tn`. A live promotion still requires runtime precondition hashes, a current database backup outside docroot, staging activation, HTTP/API checks, desktop/mobile visual review, cache verification and a tested rollback.
