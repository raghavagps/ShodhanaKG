# Current subdirectory release procedure

Build and verify locally. Do not deploy a default root-based `dist/` to the institutional subdirectory.

```powershell
D:\miniconda3\python.exe -B scripts/package_release.py --base /APP_BASE_PATH/ --output .audit-temp/new-release/package
```

Use a new output directory each time. The script runs the unchanged lint, production build/typecheck, and frontend unit commands, sets the matching Vite/API bases, creates Apache subdirectory/access-control files, and verifies every archived file against `manifest.json`. Temporary files and caches stay under the D:-located output directory.

Outputs: `release.tar.gz`, `release.sha256`, `manifest.json`, `deployment-contract.json`, logs, and a local verification `stage/`. The archive intentionally excludes:

- `api/config.php`: preserve the institution's configuration; do not overwrite credentials or storage settings.
- `api/core/reference_catalog_v1.json`: preserve and validate the deployment-specific RID bindings. Local RIDs are not interchangeable with production RIDs.

The stage contains local copies of these two files for local tests only. It must not be copied wholesale to production. The archive requires the preserved target files before activation. No KG migration or reimport is part of this release.

Before a separately authorized deployment, verify the package under its real subdirectory (routes, assets, graph selection, search and JSON/SSE). A PHP built-in-server test verifies application behavior but does not establish Apache module/configuration compatibility. Existing Apache access controls and preserved environment/settings must be reviewed on the target. The packager does not install PHP, edit server configuration, connect to the server, or deploy.

## Existing institutional PHP versions

The 11 October read-only check found Apache's enabled module configured for PHP 5.6, with CLI PHP 8.1.34 also installed. Local compatibility verification covers PHP 5.6.40 and PHP 8.1.34; see `docs/audits/deployment-remediation-20261011/`. The downloaded PHP 8.1 Windows runtime was verified against its official published SHA-256. Linux distro builds and Apache SAPI still have environmental differences.

Compatibility does not establish security maintenance. Both branches are upstream end-of-life. No runtime upgrade or institutional configuration change is authorized or performed by this work. Administrator security acceptance/vendor-maintenance evidence remains distinct from application compatibility.

## Rollback without KG changes

1. Before any later deployment, recheck the saved production application manifest against the live files. If anything changed, capture a new backup before activating the release.
2. Keep the backup private: it includes the server configuration/credentials and deployment-bound reference catalog. Do not publish it or include it in a release archive.
3. Verify its archive checksum and test extraction to a new isolated local directory. The current audit compared all 205 restored file contents to their remote checksums. Runtime caches/rate limits and previous backups were excluded; KG storage was neither copied nor changed.
4. If rollback is authorized, restore the matching frontend/index, API and server-bound config/catalog as one release. Use the existing deployment owner/group and recorded file modes; the Windows-generated archive does not attest Linux ownership restoration. Preserve private backups and runtime storage.
5. Recheck direct/deep routes, access-denied paths, health, graph RID selection and JSON/SSE against the restored release. Do not roll back or reimport KG data as part of code rollback.

No rollback into the live webroot has been performed. The local backup and restore test establish file recovery, not an executed production rollback.
