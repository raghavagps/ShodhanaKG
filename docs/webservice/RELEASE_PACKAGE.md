# Subdirectory Release Procedure

Build and verify locally. Do not deploy a default root-based `dist/` to the institutional subdirectory.

```powershell
python -B scripts/package_release.py --base /APP_BASE_PATH/ --output .audit-temp/release-package
```

Use a separate output directory each time. The script runs lint, production build/typecheck, and frontend unit commands, sets matching Vite and API bases, generates Apache configuration and access-control files, and verifies archived files against `manifest.json`.

Outputs: `release.tar.gz`, `release.sha256`, `manifest.json`, `deployment-contract.json`, logs, and a local verification `stage/`. The archive intentionally excludes:

- `webservice/api/config.php`: preserve institutional configuration; do not overwrite credentials or storage settings.
- `webservice/api/core/reference_catalog_v1.json`: preserve and validate deployment-specific RID bindings.

The stage contains local copies of these two files for local tests only. It must not be copied wholesale to production. The archive requires preserved target files before activation. No database migration or re-import is part of this release.

Before deployment, verify the package under its target subdirectory (routes, assets, graph selection, search, and JSON/SSE). A PHP built-in-server test verifies application behavior but does not establish Apache module compatibility. Review Apache access controls and environment settings on the target. The packager does not install PHP, edit server configuration, connect to the server, or deploy.

## Institutional PHP Environment

The institutional Apache environment uses PHP 5.6.40+. Application compatibility verification targets PHP 5.6.40+.

Compatibility does not establish security maintenance. Administrator security acceptance and vendor maintenance evidence remain distinct from application compatibility.

## Rollback Procedure

1. Before deployment, recheck the saved production application manifest against live files. If files changed, capture a backup before activating the release.
2. Keep the backup private: it includes server configuration, credentials, and deployment-bound reference catalogs.
3. Verify archive checksums and test extraction to an isolated directory. Restored file contents are validated against their archive checksums. Runtime caches and rate limits are excluded; database storage is not modified during code rollback.
4. If rollback is authorized, restore the frontend bundle, API files, and server configuration together. Use existing deployment owner and group permissions.
5. Verify direct and deep routes, health endpoints, graph RID selection, and JSON/SSE responses against the restored release. Do not re-import database data as part of code rollback.
